<?php
/**
 * L'API JSON du CRM, consommée par l'interface (public/app.js). Toute l'API exige une session, sauf la connexion.
 * Plusieurs personnes peuvent travailler en même temps : chaque
 * écriture relit le fichier sous verrou, et Ctrl+Z n'annule que sa propre dernière action, si personne n'a modifié
 * les prospects entre-temps (piles gardées sur disque : data/annulations/<identifiant>.json).
 * Le PDF des lettres et leur rangement dans SharePoint passent par le compte Microsoft de la personne (sharepoint.php).
 */
declare(strict_types=1);

const CHAMPS_MODIFIABLES = ['denomination', 'siren', 'responsable', 'source', 'source_autre', 'origine', 'cabinet_precedent', 'cabinet_plus_tard', 'missions',
    'forme_envisagee', 'creation_prevue', 'nb_biens', 'nb_personnes'];
const NOMS_CHAMPS = ['denomination' => 'dénomination', 'siren' => 'SIREN', 'responsable' => 'responsable', 'source' => 'source',
    'origine' => 'origine', 'cabinet_precedent' => 'cabinet précédent', 'missions' => 'missions envisagées', 'source_autre' => 'source',
    'cabinet_plus_tard' => 'cabinet précédent à renseigner plus tard',
    'forme_envisagee' => 'forme envisagée', 'creation_prevue' => 'création prévue', 'nb_biens' => 'nombre de biens',
    'nb_personnes' => 'personnes du foyer'];
const DOMAINES_PUBLICS = ['gmail.com', 'outlook.com', 'outlook.fr', 'hotmail.com', 'hotmail.fr', 'live.fr', 'live.com', 'yahoo.fr', 'yahoo.com',
    'icloud.com', 'orange.fr', 'free.fr', 'sfr.fr', 'laposte.net', 'wanadoo.fr'];
const CACHE_MAILS = 60;      // secondes : la fiche s'ouvre vite quand on revient dessus
const CACHE_NOUVEAUX = 40;   // secondes : mails non lus des prospects (pipeline), relus au plus toutes les 40 s
const DOSSIERS_FRAIS = 1800;  // secondes : liste des dossiers clients relue dans SharePoint au plus toutes les 30 minutes
const PILE_MAX = 30;

final class Api
{
    private ?Registre $reg = null;
    private array $moi = [];

    // ---------- Outils ----------
    private function reg(): Registre
    {
        return $this->reg ??= new Registre();
    }

    private function params(): array
    {
        $p = lire_json(chemin_data('parametres.json'), []);
        $out = [];
        foreach (DEFAUTS as $k => $v) {
            $out[$k] = (string)($p[$k] ?? $v);
        }
        return $out;
    }

    private function jours(string $cle, int $defaut): int
    {
        $v = $this->params()[$cle];
        return ctype_digit($v) ? (int)$v : $defaut;
    }

    private function version(): string
    {
        $f = $this->reg()->chemin;
        return is_file($f) ? md5_file($f) : '0';
    }

    private function prospect_vu(array $p, string $aujourdhui, int $delai): array
    {
        [$r, $pt] = totaux_honoraires($p);
        return array_merge($p, ['alertes' => $p['statut'] === 'PROSPECT' ? alertes($p, $aujourdhui, $delai) : [], 'recurrent' => $r, 'ponctuel' => $pt]);
    }

    private function prospect_ou_404(?string $pid): array
    {
        $this->reg()->recharger();
        $p = $this->reg()->trouver($pid);
        if ($p === null) {
            arret(404, 'Prospect introuvable.');
        }
        return $p;
    }

    private function relances(): array
    {
        return array_map('array_values', relances_modeles());
    }

    // --- Ctrl+Z / Ctrl+Y de chacun ---
    private function piles(): array
    {
        return lire_json(chemin_data('annulations', $this->moi['identifiant'] . '.json'), []) + ['annuler' => [], 'retablir' => []];
    }

    private function ecrire_piles(array $piles): void
    {
        $piles['annuler'] = array_slice($piles['annuler'], -PILE_MAX);
        $piles['retablir'] = array_slice($piles['retablir'], -PILE_MAX);
        ecrire_json(chemin_data('annulations', $this->moi['identifiant'] . '.json'), $piles, false);
    }

    private function etat_annulation(): array
    {
        $p = $this->piles();
        return ['annuler' => $p['annuler'] ? end($p['annuler'])[2] : null, 'retablir' => $p['retablir'] ? end($p['retablir'])[2] : null];
    }

    /** Modification des prospects sous verrou ; l'état d'avant est gardé pour le Ctrl+Z de la personne connectée. */
    private function action(callable $fonction): array
    {
        return sous_verrou(function () use ($fonction) {
            $reg = $this->reg();
            $avant = $reg->lire_fichier();
            $reg->derniere_action = '';  // libellé du Ctrl+Z : celui de cette action
            try {
                $resultat = $fonction();
            } catch (ErreurSaisie $e) {
                $reg->recharger();
                throw $e;
            }
            $apres = $reg->lire_fichier();
            if ($apres !== $avant) {
                $piles = $this->piles();
                $piles['annuler'][] = [$avant, $apres, $reg->derniere_action ?: 'dernière modification'];
                $piles['retablir'] = [];
                $this->ecrire_piles($piles);
            }
            return ['ok' => true, 'resultat' => $resultat, 'version' => $this->version(), 'annulation' => $this->etat_annulation()];
        });
    }

    /** Ctrl+Z / Ctrl+Y : seulement si personne n'a modifié les prospects depuis (sinon on écraserait son travail). */
    private function defaire(string $source, string $cible, string $verbe): array
    {
        return sous_verrou(function () use ($source, $cible, $verbe) {
            $piles = $this->piles();
            if (!$piles[$source]) {
                arret(409, 'Rien à ' . ($verbe === 'annulée' ? 'annuler.' : 'rétablir.'));
            }
            [$avant, $apres, $libelle] = end($piles[$source]);
            if ($this->reg()->lire_fichier() !== $apres) {
                $piles[$source] = [];
                $this->ecrire_piles($piles);
                arret(409, 'Impossible : les prospects ont été modifiés depuis (par vous ou un collègue).');
            }
            array_pop($piles[$source]);
            $this->reg()->ecrire_fichier($avant);
            $this->reg()->recharger();
            $piles[$cible][] = [$apres, $avant, $libelle];
            $this->ecrire_piles($piles);
            return ['ok' => true, 'libelle' => "Action $verbe : $libelle", 'version' => $this->version(), 'annulation' => $this->etat_annulation()];
        });
    }

    // ---------- Aiguillage ----------
    public function traiter(string $methode, string $chemin): void
    {
        if ($chemin === '/api/connexion' && $methode === 'POST') {
            json_reponse(...$this->connexion());
        }
        if ($chemin === '/api/connexion/options' && $methode === 'GET') {
            json_reponse(200, ['microsoft' => connexion_microsoft_disponible(), 'mot_de_passe' => mode_connexion() === '', 'demo' => MODE_DEMO]);
        }
        if ($chemin === '/api/microsoft/connexion' && $methode === 'GET') {
            connexion_microsoft_disponible() ? redirection(microsoft_flux_demarrer('connexion')) : page_redirection('/?microsoft=indisponible');
        }
        if ($chemin === '/api/deconnexion' && $methode === 'POST') {
            session_demarrer();
            if (!empty($_SESSION['identifiant'])) {
                journal_securite('deconnexion', $_SESSION['identifiant']);
            }
            $_SESSION = [];
            session_destroy();
            json_reponse(200, ['ok' => true]);
        }
        $this->moi = exiger_connexion(!in_array($chemin, ['/api/version', '/api/outlook/nouveaux'], true));
        if (MODE_DEMO && $this->hors_demo($methode, $chemin)) {  // démo hors ligne : Microsoft 365 et Pennylane jamais appelés
            json_reponse(409, ['erreur' => DEMO_MESSAGE, 'demo' => true]);
        }
        $routes = [
            ['GET', '/api/moi', 'moi'], ['POST', '/api/mot-de-passe', 'mot_de_passe'],
            ['GET', '/api/microsoft/lier', 'microsoft_lier'], ['GET', '/api/securite', 'securite'],
            ['GET', '/api/signature', 'signature'], ['POST', '/api/signature', 'signature_enregistrer'], ['POST', '/api/signature/outlook', 'signature_outlook'], ['GET', '/api/signature/outlook-locales', 'signatures_locales'],
            ['POST', '/api/signature/apercu', 'signature_apercu'], ['POST', '/api/outlook/archiver', 'mail_archiver'],
            ['GET', '/api/pennylane', 'pennylane'], ['POST', '/api/pennylane/jeton', 'pennylane_jeton_enregistrer'], ['POST', '/api/pennylane/synchro', 'pennylane_synchro'],
            ['POST', '/api/pennylane/ignores', 'pennylane_ignorer'], ['POST', '/api/pennylane/claude', 'pennylane_claude'],
            ['GET', '/api/pennylane/claude', 'pennylane_claude_etat'], ['POST', '/api/pennylane/claude/resultat', 'pennylane_claude_resultat'],
            ['POST', '/api/pennylane/claude/appliquer', 'pennylane_claude_appliquer'], ['POST', '/api/outlook/envoyer', 'mail_libre'],
            ['POST', '/api/securite/mode', 'securite_mode'], ['POST', '/api/securite/delier', 'securite_delier'],
            ['POST', '/api/securite/fermer-sessions', 'securite_fermer_sessions'],
            ['GET', '/api/version', 'api_version'], ['GET', '/api/etat', 'etat'],
            ['POST', '/api/prospects', 'creer'],
            ['POST', '#^/api/prospects/([0-9a-f]{32})/(deplacer|perdu|signee|onboarding-termine|relance|relance-faite|honoraires|contacts|modifier|registre|supprimer|ancien-cabinet|confraternelle|confraternelle-envoyee)$#', 'ecriture'],
            ['POST', '/api/entreprises/coordonnees', 'entreprise_coordonnees'],
            ['POST', '/api/annuler', 'annuler'], ['POST', '/api/retablir', 'retablir'],
            ['GET', '/api/entreprises', 'entreprises'], ['GET', '/api/entreprises/fiche', 'entreprise_fiche'], ['GET', '/api/doublons', 'api_doublons'],
            ['GET', '#^/api/prospects/([0-9a-f]{32})/mail-relance$#', 'mail_relance'],
            ['GET', '/api/clients', 'clients'], ['GET', '/api/clients.csv', 'clients_csv'], ['POST', '/api/clients/dossiers', 'clients_dossiers'], ['POST', '/api/clients/ouvrir', 'clients_ouvrir'], ['POST', '/api/clients/statut', 'clients_statut'], ['POST', '/api/clients/dossier', 'clients_creer_dossier'],
            ['GET', '/api/clients/modele.csv', 'clients_modele'], ['POST', '/api/clients/import', 'clients_import'],
            ['POST', '/api/parametres', 'parametres'], ['GET', '/api/relances-standard', 'relances_standard'],
            ['GET', '/api/lettres', 'lettres_liste'], ['POST', '/api/lettres', 'lettres_generer'],
            ['POST', '/api/lettres/annexe2', 'annexe2_defaut'], ['GET', '/api/lettres/dossiers', 'lettres_dossiers'],
            ['GET', '/api/lettres/prefill', 'lettres_prefill'],
            ['GET', '#^/api/lettres/([0-9a-f]{32})$#', 'lettre_detail'], ['GET', '#^/api/lettres/([0-9a-f]{32})/fichier$#', 'lettre_fichier'],
            ['GET', '#^/api/lettres/([0-9a-f]{32})/pdf$#', 'lettre_pdf'], ['POST', '#^/api/lettres/([0-9a-f]{32})/pdf$#', 'lettre_faire_pdf'],
            ['POST', '#^/api/lettres/([0-9a-f]{32})/supprimer$#', 'lettre_supprimer'],
            ['GET', '/api/outlook', 'outlook_etat'], ['POST', '/api/outlook/verifier', 'outlook_verifier'],
            ['POST', '/api/outlook/connexion', 'outlook_connexion'], ['POST', '/api/outlook/connexion/retour', 'outlook_retour'],
            ['POST', '/api/outlook/deconnexion', 'outlook_deconnexion'],
            ['GET', '/api/outlook/nouveaux', 'mails_nouveaux'],
            ['GET', '#^/api/prospects/([0-9a-f]{32})/mails$#', 'mails_prospect'], ['POST', '#^/api/prospects/([0-9a-f]{32})/mails$#', 'mail_envoyer'],
            ['GET', '#^/api/outlook/mails/([^/]+)$#', 'mail_detail'], ['GET', '#^/api/outlook/mails/([^/]+)/pj/([^/]+)$#', 'mail_pj'],
        ];
        $connue = false;
        foreach ($routes as [$verbe, $motif, $nom]) {
            $ok = $motif[0] === '#' ? preg_match($motif, $chemin, $m) : $motif === $chemin;
            if ($ok) {
                $connue = true;
                if ($verbe === $methode) {
                    $r = $this->$nom(...array_map('rawurldecode', array_slice($m ?? [], 1)));
                    json_reponse(...(is_array($r) && isset($r[0], $r[1]) && is_int($r[0]) && count($r) === 2 ? $r : [200, $r]));
                }
            }
            $m = [];
        }
        arret($connue ? 405 : 404, $connue ? 'Méthode non permise.' : 'Introuvable.');
    }

    /** Routes qui appellent Microsoft 365 ou Pennylane : refusées en mode démo, avec un message clair. */
    private function hors_demo(string $methode, string $chemin): bool
    {
        $exactes = ['GET /api/microsoft/lier', 'POST /api/outlook/connexion', 'POST /api/outlook/connexion/retour', 'POST /api/outlook/verifier',
            'POST /api/outlook/envoyer', 'POST /api/pennylane/jeton', 'POST /api/pennylane/synchro', 'POST /api/pennylane/claude',
            'POST /api/pennylane/claude/resultat', 'POST /api/pennylane/claude/appliquer', 'POST /api/clients/dossiers', 'POST /api/clients/dossier',
            'POST /api/signature/outlook', 'POST /api/securite/mode', 'POST /api/entreprises/coordonnees'];
        return in_array("$methode $chemin", $exactes, true)
            || ($methode === 'POST' && preg_match('#^/api/prospects/[0-9a-f]{32}/mails$#', $chemin) === 1);
    }

    // ---------- Connexion ----------
    private function connexion(): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
        $d = corps();
        $id = strtolower(trim((string)($d['identifiant'] ?? '')));
        if (mode_connexion() !== '') {
            return [403, ['erreur' => MESSAGES_MICROSOFT['mot_de_passe'], 'microsoft' => true]];
        }
        if (essais_trop_nombreux($ip, $id)) {
            journal_securite('connexion_bloquee', isset(comptes_lire()[$id]) ? $id : null);
            return [429, ['erreur' => 'Trop d\'essais : réessayez dans 15 minutes.']];
        }
        $compte = comptes_verifier($id, $d['mot_de_passe'] ?? null);
        if (!$compte) {
            noter_essai_rate($ip, $id);
            journal_securite('connexion_refusee', isset(comptes_lire()[$id]) ? $id : null, ['methode' => 'mot_de_passe']);
            return [401, ['erreur' => 'Identifiant ou mot de passe incorrect.']];
        }
        ouvrir_session($compte, 'mot_de_passe');
        journal_securite('connexion', $compte['identifiant'], ['methode' => 'mot_de_passe', 'mfa' => false]);
        return [200, $compte];
    }

    /** Retour de la page Microsoft (connexion au CRM, ou liaison du compte) : jamais d'erreur brute, toujours un retour au CRM. */
    public function retour_microsoft(): never
    {
        try {
            [$flux, $r, $infos, $mfa] = microsoft_flux_terminer($_GET);
            $ms = ['oid' => (string)($infos['oid'] ?? ''), 'tid' => (string)$infos['tid'],
                'adresse' => strtolower((string)($infos['preferred_username'] ?? $infos['email'] ?? ''))];
            if ($flux['but'] === 'lier') {
                if ($refus = comptes_lier_microsoft((string)$flux['identifiant'], $ms)) {
                    journal_securite('liaison_refusee', $flux['identifiant'], ['adresse' => $ms['adresse'], 'motif' => $refus]);
                    page_redirection("/?microsoft=$refus");
                }
                (new Boite((string)$flux['identifiant'], outlook_client_id()))->adopter($r, $infos);
                journal_securite('microsoft_lie', $flux['identifiant'], ['adresse' => $ms['adresse'], 'mfa' => $mfa]);
                page_redirection('/?microsoft=lie');
            }
            $compte = compte_par_microsoft($ms['oid'], $ms['tid']);
            if (!$compte && ($compte = compte_a_lier($infos))) {  // première connexion : lié tout seul
                if ($refus = comptes_lier_microsoft($compte['identifiant'], $ms)) {
                    page_redirection("/?microsoft=$refus");
                }
                journal_securite('microsoft_lie', $compte['identifiant'], ['adresse' => $ms['adresse'], 'motif' => 'première connexion']);
            }
            if (!$compte) {
                journal_securite('connexion_refusee', null, ['methode' => 'microsoft', 'adresse' => $ms['adresse'], 'motif' => 'compte non lié']);
                page_redirection('/?microsoft=inconnu');
            }
            if (mode_connexion() === 'microsoft_mfa' && $mfa !== true) {
                journal_securite('connexion_refusee', $compte['identifiant'], ['methode' => 'microsoft', 'motif' => 'sans double authentification']);
                page_redirection('/?microsoft=mfa');
            }
            (new Boite($compte['identifiant'], outlook_client_id()))->adopter($r, $infos);  // la boîte Outlook est connectée du même coup
            ouvrir_session($compte, 'microsoft', $mfa);
            journal_securite('connexion', $compte['identifiant'], ['methode' => 'microsoft', 'mfa' => $mfa, 'adresse' => $ms['adresse']]);
            page_redirection('/');
        } catch (ErreurOutlook $e) {
            $cle = array_key_exists($e->getMessage(), MESSAGES_MICROSOFT) ? $e->getMessage() : 'erreur';
            journal_securite('connexion_refusee', null, ['methode' => 'microsoft', 'motif' => $cle]);
            page_redirection("/?microsoft=$cle");
        } catch (Throwable $e) {
            error_log('CRM Cabinet : ' . $e);
            page_redirection('/?microsoft=erreur');
        }
    }

    private function moi(): array
    {
        return $this->moi;
    }

    /** Nouvelle génération de sessions (mot de passe changé, sessions fermées) : celle-ci reste ouverte. */
    private function garder_cette_session(int $generation): void
    {
        session_demarrer();
        $_SESSION['generation'] = $generation;
        session_write_close();
    }

    private function mot_de_passe(): array
    {
        $d = corps();
        if (!comptes_verifier($this->moi['identifiant'], $d['ancien'] ?? null)) {
            throw new ErreurSaisie(['ancien' => 'Mot de passe actuel incorrect.']);
        }
        try {
            sous_verrou(fn() => comptes_changer($this->moi['identifiant'], $d['nouveau'] ?? null));
        } catch (InvalidArgumentException $e) {
            throw new ErreurSaisie(['nouveau' => $e->getMessage()]);
        }
        $this->garder_cette_session(comptes_lire()[$this->moi['identifiant']]['generation']);
        journal_securite('mot_de_passe_change', $this->moi['identifiant']);
        return ['ok' => true];
    }

    // ---------- Sécurité : compte Microsoft, mode de connexion, journal ----------
    private function microsoft_lier(): never
    {
        connexion_microsoft_disponible() ? redirection(microsoft_flux_demarrer('lier', $this->moi['identifiant']))
            : page_redirection('/?microsoft=indisponible');
    }

    private function securite(): array
    {
        $comptes = comptes_lire();
        $ms = $comptes[$this->moi['identifiant']]['microsoft'] ?? null;
        return [
            'session' => ['methode' => $this->moi['methode'], 'mfa' => $this->moi['mfa']],
            'microsoft' => $ms ? ['adresse' => $ms['adresse'], 'lie_le' => $ms['lie_le']] : null,
            'comptes' => array_map(fn($id, $c) => ['identifiant' => $id, 'nom' => $c['nom'], 'lie' => !empty($c['microsoft'])], array_keys($comptes), $comptes),
            'mode' => mode_connexion(), 'modes' => MODES_CONNEXION, 'disponible' => connexion_microsoft_disponible(),
            'journal' => journal_du_compte($this->moi['identifiant']),
            'duree' => ['inactivite' => SESSION_INACTIVITE, 'max' => SESSION_DUREE_MAX],
        ];
    }

    /** Mode de connexion du cabinet. Jamais de blocage : Microsoft seulement si tous les comptes sont liés ; double
     *  authentification exigée seulement si on vient soi-même de se connecter ainsi. */
    private function securite_mode(): array
    {
        $mode = (string)(corps()['mode'] ?? '');
        if (!array_key_exists($mode, MODES_CONNEXION)) {
            throw new ErreurSaisie(['mode' => 'Mode inconnu.']);
        }
        if ($mode !== '') {  // les comptes pas encore liés le seront à leur première connexion Microsoft (compte_a_lier)
            if (empty(comptes_lire()[$this->moi['identifiant']]['microsoft'])) {
                throw new ErreurSaisie(['mode' => 'Liez d\'abord votre compte Microsoft (bouton ci-dessus) : sinon vous ne pourriez plus vous connecter.']);
            }
            if (!connexion_microsoft_disponible()) {
                throw new ErreurSaisie(['mode' => MESSAGES_MICROSOFT['indisponible']]);
            }
        }
        if ($mode === 'microsoft_mfa' && $this->moi['mfa'] !== true) {
            throw new ErreurSaisie(['mode' => 'Connectez-vous d\'abord avec Microsoft et Microsoft Authenticator : le CRM vérifie ainsi que la double authentification marche.']);
        }
        sous_verrou(function () use ($mode) {
            $p = lire_json(chemin_data('parametres.json'), []);
            $p['ModeConnexion'] = $mode;
            ecrire_json(chemin_data('parametres.json'), $p);
        });
        journal_securite('mode_connexion', $this->moi['identifiant'], ['mode' => $mode]);
        return $this->securite();
    }

    private function securite_delier(): array
    {
        if (mode_connexion() !== '') {
            throw new ErreurSaisie(['microsoft' => 'Repassez d\'abord en « Microsoft ou mot de passe » : sinon vous ne pourriez plus vous connecter.']);
        }
        comptes_delier_microsoft($this->moi['identifiant']);
        journal_securite('microsoft_delie', $this->moi['identifiant']);
        return $this->securite();
    }

    private function securite_fermer_sessions(): array
    {
        $this->garder_cette_session(comptes_fermer_sessions($this->moi['identifiant']));
        journal_securite('sessions_fermees', $this->moi['identifiant']);
        return ['ok' => true];
    }

    // ---------- Lecture ----------
    private function api_version(): array
    {
        return ['version' => $this->version()];
    }

    private function etat(): array
    {
        $reg = $this->reg();
        $reg->recharger();
        $auj = aujourdhui();
        $delai = $this->jours('DelaiSansActiviteJours', 15);
        $r = [];
        foreach (ORDRE_ETAPES as $e) {
            $r[] = [$e, LIBELLES_ETAPE[$e]];
        }
        return ['version' => $this->version(), 'aujourdhui' => $auj,
            'prospects' => array_map(fn($p) => $this->prospect_vu($p, $auj, $delai), $reg->prospects),
            'parametres' => $this->params(), 'annulation' => $this->etat_annulation(), 'relances' => $this->relances(),
            'referentiels' => ['etapes' => $r, 'natures' => LIBELLES_NATURE, 'sources' => LIBELLES_SOURCE, 'origines' => LIBELLES_ORIGINE, 'origines_ei' => LIBELLES_ORIGINE_EI,
                'roles' => LIBELLES_ROLE, 'civilites' => LIBELLES_CIVILITE, 'motifs' => LIBELLES_MOTIF_PERTE, 'types' => LIBELLES_TYPE,
                'missions' => MISSIONS_PAR_TYPE, 'formes' => FORMES_ENVISAGEES, 'alertes' => LIBELLES_ALERTE]];
    }

    // ---------- Écriture ----------
    private function creer(): array
    {
        $d = corps();
        if (($d['nature'] ?? '') === 'PROFESSIONNEL' && ($d['type_pro'] ?? '') === 'EI') {
            $d['regime'] = regime_des_missions(is_array($d['missions'] ?? null) ? $d['missions'] : []);
        }
        return $this->action(fn() => $this->reg()->creer($d)['id']);
    }

    private function ecriture(string $pid, string $quoi): array
    {
        $this->prospect_ou_404($pid);
        $d = corps();
        $reg = $this->reg();
        switch ($quoi) {
            case 'deplacer':
                return $this->action(fn() => $reg->deplacer($pid, is_string($d['etape'] ?? null) ? $d['etape'] : null));
            case 'perdu':
                return $this->action(fn() => $reg->marquer_perdu($pid, (string)($d['motif'] ?? ''), (string)($d['precision'] ?? '')));
            case 'signee':
                return $this->action(fn() => $reg->lettre_signee($pid, lire_date($d['le'] ?? null) ?? aujourdhui()));
            case 'onboarding-termine':
                return $this->action(fn() => $reg->terminer_onboarding($pid));
            case 'relance':
                $quand = lire_date($d['le'] ?? null);
                if (!empty($d['le']) && !$quand) {
                    throw new ErreurSaisie(['le' => 'Date invalide.']);
                }
                return $this->action(fn() => $reg->planifier_relance($pid, $quand, (string)($d['note'] ?? '')));
            case 'relance-faite':
                $delai = $this->jours('RelanceSuivanteJours', 7);
                return $this->action(fn() => (string)($reg->relance_faite($pid, null, (string)($d['commentaire'] ?? ''), $delai) ?? ''));
            case 'honoraires':
                $lignes = [];
                foreach (is_array($d['lignes'] ?? null) ? $d['lignes'] : [] as $l) {
                    $t = str_replace([',', ' ', "\u{a0}", "\u{202f}"], ['.', '', '', ''], (string)($l['montant'] ?? ''));
                    if (!is_array($l) || !is_numeric($t)) {
                        continue;
                    }
                    $lignes[] = ['montant' => (float)$t] + $l;
                }
                return $this->action(fn() => $reg->enregistrer_honoraires($pid, $lignes, 'HT', 'saisi dans le CRM'));  // toujours HT
            case 'contacts':
                return $this->action(fn() => $reg->enregistrer_contact($pid, $d));
            case 'modifier':
                return $this->modifier($pid, array_intersect_key($d, array_flip(CHAMPS_MODIFIABLES)));
            case 'registre':
                return $this->registre_actualiser($pid);
            case 'supprimer':
                $this->vider_cache($pid);
                return $this->action(fn() => $reg->supprimer($pid));
            case 'ancien-cabinet':
                $email = trim((string)($d['email'] ?? ''));
                if ($email !== '' && !email_valide($email)) {
                    throw new ErreurSaisie(['email' => 'Adresse e-mail invalide.']);
                }
                return $this->action(fn() => $reg->enregistrer_ancien($pid, $d));
            case 'confraternelle':
                return $this->confraternelle($pid);
            case 'confraternelle-envoyee':  // envoyée par courrier (recommandé) : notée à la main
                $le = lire_date($d['le'] ?? null) ?? aujourdhui();
                return $this->action(fn() => $reg->noter_confraternelle($pid, null, $le, (string)($d['comment'] ?? 'par courrier')));
        }
        arret(404, 'Introuvable.');
    }

    private function modifier(string $pid, array $d): array
    {
        return $this->action(function () use ($pid, $d) {
            $reg = $this->reg();
            $reg->recharger();
            $p = &$reg->trouver($pid);
            if ($p === null) {
                arret(404, 'Prospect introuvable.');
            }
            if (array_key_exists('denomination', $d) && trim((string)$d['denomination']) === '') {
                throw new ErreurSaisie(['denomination' => 'La dénomination est obligatoire.']);
            }
            if ($p['nature'] === 'PROFESSIONNEL' && array_key_exists('origine', $d) && !$d['origine']) {
                throw new ErreurSaisie(['origine' => 'Choisir l\'origine du dossier.']);
            }
            $origine = array_key_exists('origine', $d) ? $d['origine'] : ($p['origine'] ?? null);
            $cabinet = array_key_exists('cabinet_precedent', $d) ? $d['cabinet_precedent'] : ($p['cabinet_precedent'] ?? null);
            $plus_tard = array_key_exists('cabinet_plus_tard', $d) ? !empty($d['cabinet_plus_tard']) : !empty($p['cabinet_plus_tard']);
            if ($origine === 'REPRISE' && trim((string)$cabinet) === '' && !$plus_tard) {
                throw new ErreurSaisie(['cabinet_precedent' => 'Indiquer le cabinet précédent, ou cocher « À renseigner plus tard ».']);
            }
            if (array_key_exists('cabinet_plus_tard', $d)) {
                $d['cabinet_plus_tard'] = trim((string)$cabinet) === '' && $plus_tard;
            }
            if (array_key_exists('cabinet_precedent', $d) && is_array($p['ancien'] ?? null)) {
                $p['ancien']['cabinet'] = trim((string)$d['cabinet_precedent']);
            }
            if (array_key_exists('source_autre', $d)) {
                $d['source_autre'] = mb_substr(trim((string)$d['source_autre']), 0, 80);
            }
            $change = [];
            foreach ($d as $k => $v) {
                if (($p[$k] ?? null) !== $v) {
                    $change[] = $k;
                }
            }
            if (!$change) {
                return false;
            }
            foreach ($d as $k => $v) {
                $p[$k] = $v;
            }
            $reg->noter($p, 'Fiche modifiée : ' . implode(', ', array_map(fn($k) => NOMS_CHAMPS[$k], $change)) . '.');
            $reg->enregistrer();
            return true;
        });
    }

    private function annuler(): array
    {
        return $this->defaire('annuler', 'retablir', 'annulée');
    }

    private function retablir(): array
    {
        return $this->defaire('retablir', 'annuler', 'rétablie');
    }

    // ---------- Assistant « Nouveau prospect » ----------
    private function entreprises(): array
    {
        return ['resultats' => entreprises_chercher((string)($_GET['q'] ?? ''))];
    }

    /** Siège, forme et dirigeants d'une entreprise (registre national) : pré-remplissage du nouveau prospect. */
    private function entreprise_fiche(): array
    {
        $r = entreprises_resume((string)($_GET['siren'] ?? ''));
        if ($r === null) {
            arret(404, 'Entreprise introuvable dans le registre (ou registre injoignable).');
        }
        return ['entreprise' => $r];
    }

    /** Relit la fiche du registre (SIRET, TVA, activité, effectif, finances…) ; complète le siège et la forme s'ils manquent. */
    private function registre_actualiser(string $pid): array
    {
        $siren = (string)($this->prospect_ou_404($pid)['siren'] ?? '');
        $e = entreprises_resume($siren);
        if ($e === null) {
            throw new ErreurSaisie(['siren' => $siren === '' ? 'Pas de SIREN dans la fiche.' : 'Entreprise introuvable dans le registre (ou registre injoignable).']);
        }
        return $this->action(function () use ($pid, $e) {
            $reg = $this->reg();
            $reg->recharger();
            $p = &$reg->trouver($pid);
            $p['registre'] = registre_a_garder($e['registre']);
            foreach (['adresse', 'cp', 'ville', 'forme'] as $k) {
                if (trim((string)($p[$k] ?? '')) === '' && $e[$k] !== '') {
                    $p[$k] = $e[$k];
                }
            }
            $reg->noter($p, 'Informations du registre des entreprises mises à jour.');
            $reg->enregistrer();
            return true;
        });
    }

    /**
     * E-mails et téléphones de l'entreprise : aucun registre public ne les donne ; on les cherche dans la boîte Outlook de la
     * personne connectée (mails qui citent la société ou ses dirigeants, signatures). Rien n'est enregistré ici.
     */
    private function entreprise_coordonnees(): array
    {
        $d = corps();
        $b = $this->boite();
        if ($b->adresse() === '') {
            return ['coordonnees' => [], 'boite' => false];
        }
        $personnes = array_slice(array_values(array_filter(is_array($d['personnes'] ?? null) ? $d['personnes'] : [], 'is_array')), 0, 4);
        try {
            return ['coordonnees' => $b->coordonnees(mb_substr(trim((string)($d['societe'] ?? '')), 0, 120), $personnes), 'boite' => true];
        } catch (ErreurOutlook $e) {
            return ['coordonnees' => [], 'boite' => true, 'erreur' => $e->getMessage()];
        }
    }

    private function api_doublons(): array
    {
        $this->reg()->recharger();
        return ['doublons' => doublons($this->reg()->prospects, $_GET['prenom'] ?? null, $_GET['nom'] ?? null, $_GET['email'] ?? null)];
    }

    private function mail_relance(string $pid): array
    {
        $p = $this->prospect_ou_404($pid);
        $contact = null;
        foreach ($p['contacts'] ?? [] as $c) {
            if (!empty($c['email'])) {
                $contact = $c;
                break;
            }
        }
        $contact ??= $p['contacts'][0] ?? [];
        [$objet, $texte] = mail_relance($p, $contact);
        return ['objet' => $objet, 'texte' => $texte, 'a' => $contact['email'] ?? ''];
    }

    // ---------- Clients ----------
    private function clients(): array
    {
        $b = $this->boite();
        if ($b->adresse() !== '' && !$b->personnel() && (time() - dossiers_reseau_instant() > DOSSIERS_FRAIS || dossiers_reseau_sans_annees())) {
            try {
                $this->lire_dossiers($b);
            } catch (ErreurOutlook $e) {  // SharePoint injoignable : on garde la dernière liste connue
            }
        }
        $this->reg()->recharger();
        [, $maj] = charger_pennylane();
        return ['clients' => tous_les_clients($this->reg()->prospects), 'maj' => $maj, 'dossiers_maj' => dossiers_reseau_maj()];
    }

    private function lire_dossiers(Boite $b): int
    {
        [$noms, $annees] = sp_dossiers_clients($b);
        $juridiques = sp_dossiers_juridiques($b);
        $societe = sp_dossiers_societe($b);
        return sous_verrou(fn() => dossiers_reseau_enregistrer($noms, $juridiques, $societe, $annees));
    }

    /** « Actualiser les dossiers » : relit tout de suite la liste des dossiers clients dans SharePoint. */
    private function clients_dossiers(): array
    {
        $b = $this->boite();
        try {
            return ['ok' => true, 'dossiers' => $this->lire_dossiers($b)];
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
    }

    /**
     * « Ouvrir le dossier » : quand le CRM tourne sur le PC même de la personne (CRM local, ouvert sur localhost), le serveur
     * demande à Windows d'ouvrir le dossier du client dans l'Explorateur (rien à installer). Sinon {ouvert: false} : l'interface
     * passe par la recherche Windows du poste, ou SharePoint. Seuls les noms de la liste des dossiers clients sont acceptés.
     */
    private function clients_ouvrir(): array
    {
        $nom = (string)(corps()['dossier'] ?? '');
        $type = (string)(corps()['type'] ?? '');
        $juridique = $type === 'juridique';
        $liste = $juridique ? dossiers_juridiques() : ($type === 'societe' ? dossiers_societe() : array_merge(dossiers_reseau(), dossiers_compta_annees()));
        if (!in_array($nom, $liste, true)) {
            arret(404, 'Dossier client inconnu.');
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $base = chemin_compta_local() ?: reglage('reseau');
        if (MODE_DEMO || $base === '') {  // démo, ou dossier synchronisé inconnu sur ce poste : l'interface propose SharePoint
            return ['ouvert' => false];
        }
        if ($juridique) {  // à côté du dossier comptable : Dossiers › Juridique annuel › année › CLIENT
            $base = getenv('CRM_JURIDIQUE') ?: dirname($base) . DIRECTORY_SEPARATOR . RACINE_JURIDIQUE;
        }
        if ($type === 'societe') {  // à côté du dossier comptable : Dossiers › Permanent › CLIENT
            $base = getenv('CRM_SOCIETE') ?: dirname($base) . DIRECTORY_SEPARATOR . RACINE_SOCIETE;
        }
        $chemin = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $nom);
        if (PHP_OS_FAMILY !== 'Windows' || !in_array($ip, ['127.0.0.1', '::1'], true) || !is_dir($chemin)) {
            return ['ouvert' => false];
        }
        if (getenv('CRM_SANS_EXPLORATEUR') === '1') {  // tests : pas de fenêtre ouverte
            ecrire_fichier(chemin_data('explorateur.txt'), $chemin);
            return ['ouvert' => true];
        }
        $p = proc_open(['explorer.exe', $chemin], [], $tubes);  // sans passer par une invite de commande
        $ok = is_resource($p);
        if ($ok) {
            proc_close($p);
        }
        return ['ouvert' => $ok];
    }

    /**
     * Crée le dossier manquant d'un client dans SharePoint (synchronisé dans OneDrive) : dossier comptable (avec le modèle de
     * l'année), dossier permanent ou juridique annuel d'une année. Création seulement : un dossier du même nom est repris.
     */
    private function clients_creer_dossier(): array
    {
        $d = corps();
        $type = (string)($d['type'] ?? '');
        $nom = trim(preg_replace('/\s+/u', ' ', (string)($d['nom'] ?? '')));
        $annee = (string)($d['annee'] ?? date('Y'));
        if (!in_array($type, ['compta', 'societe', 'juridique'], true)) {
            throw new ErreurSaisie(['type' => 'Type de dossier inconnu.']);
        }
        if (!nom_de_dossier_sur($nom) || str_starts_with($nom, '0.')) {
            throw new ErreurSaisie(['nom' => 'Nom de dossier invalide (pas de \\ / : * ? " < > |).']);
        }
        $creables = $type === 'juridique' ? annees_creables(annees_juridique()) : ($type === 'compta' ? annees_creables(fenetre_compta()) : [$annee]);
        if (!in_array($annee, $creables, true)) {
            throw new ErreurSaisie(['annee' => 'Année non proposée.']);
        }
        $this->reg()->recharger();
        $client = null;
        foreach (tous_les_clients($this->reg()->prospects) as $x) {
            if ((string)$x['id'] === (string)($d['id'] ?? '')) {
                $client = $x;
            }
        }
        if ($client === null) {
            arret(404, 'Client introuvable.');
        }
        if ($type === 'compta' && $client['dossier'] !== '') {  // nouvelle année : dans le dossier comptable existant du client
            $nom = $client['dossier'];
        }
        $b = $this->boite();
        try {
            [$chemin, $cree] = sp_creer_dossier_client($b, $type, $nom, $annee);
            $this->lire_dossiers($b);  // les liens « Ouvrir » suivent aussitôt
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        if (!empty($client['pid'])) {
            sous_verrou(fn() => $this->reg()->journal($client['pid'], ($cree ? 'Dossier créé' : 'Dossier déjà existant') . " dans SharePoint : $chemin."));
        }
        return ['ok' => true, 'chemin' => $chemin, 'cree' => $cree];
    }

    /**
     * Statut d'un client : Actif, Onboarding, ou Archivé (masqué des listes, toutes ses informations gardées).
     * Client signé dans le CRM : l'onboarding de sa fiche prospect suit, et l'historique le note.
     */
    private function clients_statut(): array
    {
        $d = corps();
        $id = (string)($d['id'] ?? '');
        $statut = (string)($d['statut'] ?? '');
        if (!in_array($statut, ['ACTIF', 'ONBOARDING', 'ARCHIVE'], true)) {
            throw new ErreurSaisie(['statut' => 'Statut inconnu.']);
        }
        return sous_verrou(function () use ($id, $statut) {
            $reg = $this->reg();
            $reg->recharger();
            $c = null;
            foreach (tous_les_clients($reg->prospects, false) as $x) {
                if ((string)$x['id'] === $id) {
                    $c = $x;
                    break;
                }
            }
            if ($c === null) {
                arret(404, 'Client introuvable.');
            }
            $archives = clients_archives();
            if ($statut === 'ARCHIVE') {
                $archives[$id] = ['le' => maintenant_minutes(), 'par' => $this->moi['nom']];
                clients_archives_ecrire($archives);
                if (!empty($c['pid'])) {
                    $reg->journal($c['pid'], 'Client archivé.');
                }
                return ['ok' => true];
            }
            $cles = array_filter([$id, (string)($c['pid'] ?? '')]);
            if (array_intersect_key($archives, array_flip($cles))) {
                $archives = array_diff_key($archives, array_flip($cles));
                clients_archives_ecrire($archives);
                if (!empty($c['pid'])) {
                    $reg->journal($c['pid'], 'Client désarchivé.');
                }
            }
            if ($c['statut'] !== $statut) {
                if (!empty($c['pid'])) {
                    $statut === 'ACTIF' ? $reg->terminer_onboarding($c['pid']) : $reg->reprendre_onboarding($c['pid']);
                } else {
                    $f = lire_json(chemin_data('clients.json'), []);
                    foreach ($f['clients'] ?? [] as $i => $x) {
                        if ((string)$x['id'] === $id) {
                            $f['clients'][$i]['statut'] = $statut;
                        }
                    }
                    ecrire_json(chemin_data('clients.json'), $f);
                }
            }
            return ['ok' => true];
        });
    }

    /** Import CSV ou presse-papiers : aperçu ({appliquer: false}), puis import ({appliquer: true}). */
    private function clients_import(): array
    {
        $d = corps();
        if (!is_string($d['texte'] ?? null)) {
            throw new ErreurSaisie(['texte' => 'Rien à importer : choisissez un fichier CSV ou collez le tableau.']);
        }
        return clients_importer($d['texte'], !empty($d['appliquer']));
    }

    private function clients_modele(): never
    {
        envoyer_fichier(clients_modele_csv(), 'modele-clients.csv', 'text/csv; charset=utf-8', true, true);
    }

    private function clients_csv(): never
    {
        $this->reg()->recharger();
        $champ = function ($v): string {  // comme le module csv de Python : guillemets seulement si nécessaire
            $v = is_bool($v) ? ($v ? 'True' : 'False') : (string)($v ?? '');
            return preg_match('/[;"\r\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        };
        $lignes = [implode(';', ['Société', 'Contact', 'E-mail', 'Téléphone', 'Statut', 'SIREN', 'Forme', 'Ville', 'Pennylane', 'Dossier comptable', 'Dossier permanent', 'Juridique annuel'])];
        foreach (tous_les_clients($this->reg()->prospects) as $c) {
            $c['statut'] = $c['archive'] ? 'ARCHIVE' : $c['statut'];
            $lignes[] = implode(';', array_map(fn($k) => $champ($c[$k] ?? ''),
                ['nom', 'contact', 'email', 'telephone', 'statut', 'siren', 'forme', 'ville', 'pennylane', 'dossier_url', 'dossier_societe_url', 'dossier_juridique_url']));
        }
        $texte = "\u{feff}" . implode("\r\n", $lignes) . "\r\n";
        envoyer_fichier($texte, 'clients-' . aujourdhui() . '.csv', 'text/csv; charset=utf-8', true, true);
    }

    // ---------- Paramètres ----------
    private function parametres(): array
    {
        $d = corps();
        $champs = [];
        foreach (['DelaiSansActiviteJours', 'RelanceSuivanteJours'] as $cle) {
            if (array_key_exists($cle, $d) && !ctype_digit(trim((string)$d[$cle]))) {
                $champs[$cle] = 'Nombre de jours (entier).';
            }
        }
        if ($champs) {
            throw new ErreurSaisie($champs);
        }
        sous_verrou(function () use ($d) {
            $p = lire_json(chemin_data('parametres.json'), []);
            foreach (['Responsables', 'DelaiSansActiviteJours', 'RelanceSuivanteJours', 'MessagerieClientId', 'MessagerieRetourCRM'] as $cle) {
                if (array_key_exists($cle, $d)) {
                    $p[$cle] = trim((string)$d[$cle]);
                }
            }
            ecrire_json(chemin_data('parametres.json'), $p);
            if (is_array($d['relances'] ?? null)) {
                $m = [];
                foreach ($d['relances'] as $e => $v) {
                    if (is_array($v) && array_is_list($v) && count($v) === 2) {
                        $m[$e] = [(string)$v[0], (string)$v[1]];
                    }
                }
                relances_enregistrer($m);
            }
        });
        return ['ok' => true, 'parametres' => $this->params(), 'relances' => $this->relances()];
    }

    private function relances_standard(): array
    {
        return array_map('array_values', MODELES_RELANCE);
    }

    // ---------- Livrables : lettres de mission ----------
    private function cabinet(): array
    {
        return lire_json(chemin_data('lettres-cabinet.json'), []);
    }

    private function vue_lettre(array $e): array
    {
        return ['pdf' => (bool)lettre_chemin($e['id'], 'pdf')] + $e;
    }

    private function lettres_liste(): array
    {
        $index = lettres_index();
        usort($index, fn($a, $b) => strcmp($b['le'], $a['le']));
        $modeles = [];
        foreach (MODELES_LETTRE as $c => $m) {
            $modeles[$c] = ['libelle' => $m[0], 'public' => $m[1], 'types' => $m[2], 'partenaire' => (bool)$m[4], 'annexe' => in_array($c, AVEC_ANNEXE2, true)];
        }
        $champs = ['id', 'modele', 'libelle', 'partenaire', 'fichier', 'dossier', 'restantes', 'le', 'par', 'classement', 'pdf_erreur'];
        return ['lettres' => array_map(fn($e) => array_intersect_key($e, array_flip($champs)) + ['classement' => null, 'pdf_erreur' => '']
            + ['pdf' => (bool)lettre_chemin($e['id'], 'pdf')], $index),
            'modeles' => $modeles, 'annexe2' => ['qui' => QUI, 'tarifs' => TARIFS, 'couleurs' => COULEURS]];
    }

    private function annexe2_defaut(): array
    {
        return ['lignes' => repartition_par_defaut(corps())];
    }

    private function lettres_dossiers(): array
    {
        $reg = $this->reg();
        $reg->recharger();
        $out = [];
        foreach ($reg->prospects as $p) {
            if ($p['statut'] !== 'PERDU') {
                $out[] = ['source' => 'prospect', 'ref' => $p['id'], 'nom' => $p['denomination'], 'type' => type_prospect($p),
                    'sous' => $p['code'] . ' · ' . ($p['statut'] === 'CLIENT' ? 'Client' : 'Prospect')];
            }
        }
        $noms = array_flip(array_map(fn($d) => mb_strtoupper($d['nom']), $out));
        foreach (tous_les_clients($reg->prospects, false) as $c) {
            if (($c['source'] ?? '') === 'CRM' || isset($noms[mb_strtoupper($c['nom'])])) {
                continue;
            }
            $out[] = ['source' => 'client', 'ref' => $c['id'], 'nom' => $c['nom'],
                'type' => ['LMNP' => 'EI'][$c['type'] ?? ''] ?? (($c['type'] ?? '') ?: 'SOCIETE'),
                'sous' => implode(' · ', array_filter(['Client Pennylane', $c['forme'] ?? '', $c['ville'] ?? '', $c['archive'] ? 'Archivé' : '']))];
        }
        usort($out, fn($a, $b) => strcmp(mb_strtoupper($a['nom']), mb_strtoupper($b['nom'])));
        return ['dossiers' => $out];
    }

    /** Tout ce que le CRM sait déjà du dossier : fiche prospect ou Pennylane, puis le répertoire officiel (adresse,
     *  dirigeant, qualité) si le SIREN est connu ; les mentions du cabinet retenues la dernière fois. */
    private function lettres_prefill(): array
    {
        $source = $_GET['source'] ?? '';
        $ref = $_GET['ref'] ?? '';
        $this->reg()->recharger();
        if ($source === 'prospect') {
            $v = prefill_prospect($this->prospect_ou_404($ref));
        } elseif ($source === 'client') {
            $c = null;
            foreach (tous_les_clients($this->reg()->prospects, false) as $x) {
                if ((string)$x['id'] === $ref) {
                    $c = $x;
                    break;
                }
            }
            if ($c === null) {
                arret(404, 'Client introuvable.');
            }
            $v = prefill_client($c);
        } else {
            $v = ['source' => '', 'ref' => '', 'type' => ($_GET['type'] ?? '') ?: 'SOCIETE', 'modeles_suggeres' => []];
        }
        if (!empty($v['siren']) && empty($v['adresse'])) {
            $r = prefill_repertoire((string)$v['siren']);
            foreach (['adresse', 'cp', 'ville', 'forme', 'qualite'] as $k) {
                $v[$k] = ($v[$k] ?? '') ?: ($r[$k] ?? '');
            }
            if (empty($v['prenom']) && empty($v['nom']) && !empty($r['dirigeant_nom'])) {
                $v['prenom'] = $r['dirigeant_prenom'];
                $v['nom'] = $r['dirigeant_nom'];
            }
            $v['repertoire'] = (bool)$r;
        }
        $cab = $this->cabinet();
        $v = array_merge($v, ['interlocuteur' => ($v['responsable'] ?? '') ?: $this->moi['nom'], 'fonction' => $cab['fonction'] ?? '',
            'couverture' => $cab['couverture'] ?? '', 'mediateur' => $cab['mediateur'] ?? '', 'date_lettre' => aujourdhui()]);
        return ['valeurs' => $v];
    }

    private function lettres_generer(): array
    {
        $d = corps();
        $v = is_array($d['valeurs'] ?? null) ? $d['valeurs'] : [];
        $ident = is_string($d['id'] ?? null) && $d['id'] !== '' ? $d['id'] : null;
        if (!isset(MODELES_LETTRE[$v['modele'] ?? ''])) {
            throw new ErreurSaisie(['modele' => 'Choisir une lettre de mission.']);
        }
        $entree = sous_verrou(function () use ($v, $ident) {
            $cab = $this->cabinet();
            foreach (['couverture', 'mediateur', 'fonction'] as $k) {
                $t = trim((string)($v[$k] ?? ''));
                if ($t !== '') {
                    $cab[$k] = $t;
                }
            }
            ecrire_json(chemin_data('lettres-cabinet.json'), $cab);
            $entree = lettres_generer($v, $this->moi['nom'], $ident);
            $reg = $this->reg();
            if (($v['source'] ?? '') === 'prospect') {
                $reg->recharger();
                $p = &$reg->trouver((string)($v['ref'] ?? ''));
                if ($p !== null) {
                    $reg->noter($p, 'Lettre de mission ' . ($ident ? 'modifiée' : 'préparée') . " : {$entree['libelle']}.");
                    $reg->enregistrer();
                }
            }
            return $entree;
        });
        return ['lettre' => array_intersect_key($entree, array_flip(['id', 'fichier', 'restantes', 'libelle', 'pdf', 'classement']))];
    }

    private function lettre_detail(string $id): array
    {
        $e = lettre_trouver($id) ?? arret(404, 'Lettre introuvable.');
        return ['lettre' => $this->vue_lettre($e)];
    }

    /** PDF de la lettre : rangée dans SharePoint (Lettres de mission › client) puis convertie par Microsoft, avec le compte
     *  Microsoft de la personne connectée. Le PDF est aussi gardé dans le CRM. En cas d'échec, la raison reste sur la lettre. */
    private function lettre_faire_pdf(string $id): array
    {
        $e = lettre_trouver($id);
        $docx = lettre_chemin($id);
        if (!$e || !$docx) {
            arret(404, 'Lettre introuvable.');
        }
        $b = $this->boite();
        $action = null;
        try {
            [$pdf, $classement] = sp_lettre_en_pdf($b, $e, (string)file_get_contents($docx));
            $maj = ['pdf' => true, 'pdf_erreur' => '', 'classement' => $classement];
        } catch (ErreurOutlook $x) {
            $pdf = null;
            $action = $x->action;
            $maj = ['pdf_erreur' => $x->getMessage()];
        }
        sous_verrou(function () use ($id, $pdf, $maj) {
            if ($pdf !== null) {
                ecrire_fichier(lettres_dossier() . "/$id.pdf", $pdf);
            }
            $index = lettres_index();
            foreach ($index as &$x) {
                if ($x['id'] === $id) {
                    $x = array_merge($x, $maj);
                }
            }
            unset($x);
            lettres_ecrire_index($index);
        });
        return ['lettre' => $this->vue_lettre(lettre_trouver($id)), 'action' => $action];
    }

    private function lettre_fichier(string $id): never
    {
        $e = lettre_trouver($id);
        $chemin = lettre_chemin($id);
        if (!$e || !$chemin) {
            arret(404, 'Lettre introuvable.');
        }
        envoyer_fichier($chemin, $e['fichier'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', true);
    }

    /** La lettre en PDF : la version à faire signer (envoi à la signature électronique, puis au client). */
    private function lettre_pdf(string $id): never
    {
        $e = lettre_trouver($id);
        $chemin = lettre_chemin($id, 'pdf');
        if (!$e || !$chemin) {
            arret(404, 'PDF pas encore prêt.');
        }
        envoyer_fichier($chemin, substr($e['fichier'], 0, -5) . '.pdf', 'application/pdf', ($_GET['voir'] ?? '') !== '1');
    }

    private function lettre_supprimer(string $id): array
    {
        sous_verrou(fn() => lettres_supprimer($id));
        return ['ok' => true];
    }

    // ---------- Messagerie Outlook (chacun sa boîte) ----------
    private function boite(): Boite
    {
        return new Boite($this->moi['identifiant'], outlook_client_id());
    }

    private function erreur_outlook(ErreurOutlook $e, int $statut = 409): array
    {
        return [$statut, ['erreur' => $e->getMessage(), 'action' => $e->action]];
    }

    private function dossier_cache(): string
    {
        return chemin_data('cache-mails', $this->moi['identifiant']);
    }

    private function vider_cache(?string $pid = null): void
    {
        foreach (glob($this->dossier_cache() . '/' . ($pid ?? '') . '*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function outlook_etat(): array
    {
        $b = $this->boite();
        $f = $b->flux();
        $cx = $f && ($f['expire'] ?? 0) > time() - 3600
            ? ['etat' => $f['statut'] ?? 'attente', 'code' => '', 'url' => '', 'message' => $f['message'] ?? null, 'action' => $f['action'] ?? null]
            : null;
        $p = lire_json(chemin_data('parametres.json'), []);
        return ['adresse' => $b->adresse(), 'client_id' => $b->client_id, 'connexion' => $cx, 'retour_crm' => ($p['MessagerieRetourCRM'] ?? '') === '1',
            'adresse_crm' => origine()];
    }

    /** Mode de connexion : toujours « navigateur » (page Microsoft) ; seule vérification utile, l'identifiant d'application. */
    private function outlook_verifier(): array
    {
        try {
            $this->boite()->verifier_application();
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e, 200);
        }
        return ['ok' => true, 'mode' => 'navigateur'];
    }

    private function outlook_connexion(): array
    {
        $retour = adresse_retour_microsoft();
        $cx = $this->boite()->demarrer($retour);
        return ['etat' => 'attente', 'mode' => 'navigateur', 'code' => '', 'url' => $cx['url'], 'automatique' => $retour !== RETOUR_MICROSOFT];
    }

    /** Connexion terminée : retour Microsoft sur le CRM, ou adresse collée par la personne (page de retour Microsoft). */
    private function outlook_retour(): array
    {
        $b = $this->boite();
        try {
            $a = $b->terminer((string)(corps()['adresse'] ?? ''));
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        if (empty(comptes_lire()[$this->moi['identifiant']]['microsoft']) && !$b->personnel()  // compte Microsoft lié du même coup
            && comptes_lier_microsoft($this->moi['identifiant'], $b->identite()) === null) {
            journal_securite('microsoft_lie', $this->moi['identifiant'], ['adresse' => $a]);
        }
        return ['ok' => true, 'adresse' => $a];
    }

    private function outlook_deconnexion(): array
    {
        $this->boite()->deconnecter();
        $this->vider_cache();
        return ['ok' => true];
    }


    // ---------- Synchro Pennylane (lecture seule) ----------
    private function pennylane(): array
    {
        $ign = pennylane_ignores();
        return pennylane_etat() + ['ignores' => array_map(fn($id, $nom) => ['id' => (string)$id, 'nom' => $nom], array_keys($ign), $ign),
            'claude_local' => $this->sur_ce_pc()];
    }

    /** Navigateur sur le PC où tourne le CRM (Claude Desktop, Outlook de ce PC). */
    private function sur_ce_pc(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && PHP_OS_FAMILY === 'Windows';
    }

    /** Ne plus ignorer (Paramètres), ou ignorer, un dossier Pennylane. */
    private function pennylane_ignorer(): array
    {
        $d = corps();
        $id = (string)($d['id'] ?? '');
        sous_verrou(function () use ($id, $d) {
            $ign = pennylane_ignores();
            if (!empty($d['ignorer'])) {
                $ign[$id] = mb_substr((string)($d['nom'] ?? ''), 0, 150);
            } else {
                unset($ign[$id]);
            }
            pennylane_enregistrer(['ignores' => $ign]);
        });
        return $this->pennylane();
    }

    /** Fiches sans contact, e-mail, téléphone ou forme : Claude Desktop les cherche dans Pennylane. Sur ce PC, Claude est ouvert et
     *  le prompt envoyé ; ailleurs (en ligne), le prompt est rendu pour être collé à la main, et la réponse recollée dans le CRM. */
    private function pennylane_claude(): array
    {
        $un = (string)(corps()['id'] ?? '');  // fiche client : ce dossier seulement, même complet (infos changées dans Pennylane)
        $dossiers = [];
        foreach (tous_les_clients($this->reg()->prospects, false) as $c) {
            $lie = preg_match('#/companies/(\d+)#', (string)($c['pennylane'] ?? ''), $m) === 1;
            if ($un !== '') {  // fiche client : ce dossier, relié ou non (sinon Claude le cherche par son nom)
                if ((string)$c['id'] === $un) {
                    $dossiers[] = ['client' => (string)$c['id'], 'pennylane_id' => $lie ? $m[1] : '', 'nom' => $c['nom']];
                }
                continue;
            }
            if (!empty($c['archive']) || !$lie) {
                continue;
            }
            foreach (array_keys(CHAMPS_CLAUDE) as $k) {
                if (trim((string)($c[$k] ?? '')) === '') {
                    $dossiers[] = ['client' => (string)$c['id'], 'pennylane_id' => $m[1], 'nom' => $c['nom']];
                    break;
                }
            }
        }
        if (!$dossiers) {
            if ($un !== '') {
                arret(404, 'Client introuvable.');
            }
            return ['dossiers' => 0, 'lance' => false, 'prompt' => ''];
        }
        @unlink(fichier_claude());
        @unlink(fichier_reponse_claude());
        $local = $this->sur_ce_pc();
        $sans_fichier = fn(string $p) => str_replace('3. Avec l\'extension Filesystem, écris en écrasant le fichier', '3. Donne dans ta réponse, à la place du fichier', $p);
        $mode = 'manuel';
        $prompt = $sans_fichier(prompt_claude($dossiers, '(pas de fichier : réponds uniquement avec le tableau JSON, sans autre texte)'));
        if ($local && lancer_claude_arriere_plan($prompt)) {
            $mode = 'arriere-plan';  // Claude Code sans fenêtre : rien ne s'ouvre, la réponse arrive dans le CRM
        } elseif ($local) {
            $prompt = prompt_claude($dossiers, str_replace('/', '\\', fichier_claude()));
            $mode = lancer_claude($prompt) ? 'claude-desktop' : 'manuel';
            if ($mode === 'manuel') {
                $prompt = $sans_fichier(prompt_claude($dossiers, '(pas de fichier : réponds uniquement avec le tableau JSON, sans autre texte)'));
            }
        }
        $lance = $mode !== 'manuel';
        sous_verrou(fn() => pennylane_enregistrer(['claude' => ['le' => time(), 'dossiers' => count($dossiers), 'lance' => $lance, 'mode' => $mode]]));
        return ['dossiers' => count($dossiers), 'lance' => $lance, 'mode' => $mode, 'prompt' => $prompt, 'fichier' => fichier_claude()];
    }

    /** Résultat de Claude : prêt quand le fichier est écrit (et ne bouge plus) ; propositions = informations manquantes trouvées. */
    private function pennylane_claude_etat(): array
    {
        $demande = lire_json(chemin_data('pennylane.json'), [])['claude'] ?? null;
        $f = fichier_claude();
        if (!$demande) {
            return ['etat' => 'aucune'];
        }
        if (is_file(fichier_reponse_claude()) && !is_file($f)) {  // arrière-plan : la réponse de Claude Code est arrivée
            $r = json_de_reponse((string)file_get_contents(fichier_reponse_claude()));
            if ($r === null) {
                return ['etat' => 'illisible'];
            }
            ecrire_fichier($f, json_encode($r, JSON_UNESCAPED_UNICODE));
            touch($f, time() - 10);
        }
        if (!is_file($f) || time() - filemtime($f) < 3) {
            return ['etat' => time() - $demande['le'] > 1800 ? 'expire' : 'attente', 'depuis' => $demande['le']];
        }
        $r = json_decode((string)file_get_contents($f), true);
        if (!is_array($r)) {
            return ['etat' => 'illisible'];
        }
        $tous = tous_les_clients($this->reg()->prospects, false);
        return ['etat' => 'pret', 'propositions' => propositions_claude($r, $tous), 'introuvables' => introuvables_claude($r, $tous)];
    }

    /** CRM en ligne : la réponse de Claude (tableau JSON) collée dans le CRM. */
    private function pennylane_claude_resultat(): array
    {
        $r = json_de_reponse((string)(corps()['texte'] ?? ''));
        if ($r === null) {
            throw new ErreurSaisie(['texte' => 'Collez le tableau JSON donné par Claude (il commence par [ et finit par ]).']);
        }
        ecrire_fichier(fichier_claude(), json_encode($r, JSON_UNESCAPED_UNICODE));
        touch(fichier_claude(), time() - 10);
        $tous = tous_les_clients($this->reg()->prospects, false);
        return ['etat' => 'pret', 'propositions' => propositions_claude($r, $tous), 'introuvables' => introuvables_claude($r, $tous)];
    }

    /** Informations cochées par la personne : écrites dans les fiches, seulement là où c'est vide. */
    private function pennylane_claude_appliquer(): array
    {
        $lignes = is_array(corps()['lignes'] ?? null) ? corps()['lignes'] : [];
        $tous = array_column(tous_les_clients($this->reg()->prospects, false), null, 'id');
        $n = sous_verrou(function () use ($lignes, $tous) {
            $d = lire_json(chemin_data('clients.json'), []);
            $clients = $d['clients'] ?? [];
            $n = 0;
            foreach ($lignes as $l) {
                $id = (string)($l['id'] ?? '');
                if (!in_array($id, array_map(fn($c) => (string)$c['id'], $clients), true) && str_starts_with($id, 'SP-') && isset($tous[$id])) {
                    $clients[] = ['id' => $id, 'nom' => $tous[$id]['nom'], 'type' => 'SOCIETE', 'statut' => 'ACTIF', 'source' => 'SharePoint',
                        'siren' => '', 'forme' => '', 'contact' => '', 'email' => '', 'telephone' => '', 'pennylane' => ''];  // dossier SharePoint : sa fiche
                }
                foreach ($clients as &$c) {
                    if ((string)$c['id'] !== $id) {
                        continue;
                    }
                    foreach ([...array_keys(CHAMPS_CLAUDE), 'pennylane'] as $k) {  // cochée par la personne, qui a vu l'ancienne valeur : écrite
                        $v = mb_substr(trim((string)($l['champs'][$k] ?? '')), 0, 150);
                        if ($v !== '' && $v !== trim((string)($c[$k] ?? '')) && ($k !== 'email' || email_valide($v))
                            && ($k !== 'pennylane' || preg_match('#^https://app\.pennylane\.com/companies/\d+$#', $v)) && ($k !== 'siren' || siren_valide($v))) {
                            $c[$k] = $v;
                            $n++;
                        }
                    }
                }
                unset($c);
            }
            if ($n) {
                clients_ecrire_avec_sauvegarde($d, $clients);
            }
            return $n;
        });
        return ['ok' => true, 'champs' => $n];
    }

    /** Jeton « Firm » de Pennylane (portée companies:readonly) : essayé, puis gardé chiffré. Vide = retiré. Jamais renvoyé. */
    private function pennylane_jeton_enregistrer(): array
    {
        $jeton = trim((string)(corps()['jeton'] ?? ''));
        if ($jeton === '') {
            sous_verrou(fn() => @unlink(chemin_data('pennylane.json')));
            return pennylane_etat();
        }
        try {
            pennylane_dossiers($jeton);
        } catch (RuntimeException $e) {
            throw new ErreurSaisie(['jeton' => $e->getMessage()]);
        }
        sous_verrou(fn() => pennylane_enregistrer(['chiffre' => chiffrer($jeton, 'pennylane'), 'par' => $this->moi['nom'], 'le' => maintenant_minutes()]));
        return pennylane_etat();
    }

    /** Lit les dossiers de Pennylane et dit quels dossiers manquent dans SharePoint (et l'inverse). En deux temps : aperçu de ce qui
     *  serait ajouté ou complété dans la liste des clients (rien n'est écrit), puis, si la personne valide (appliquer), l'écriture. */
    private function pennylane_synchro(): array
    {
        $appliquer = !empty(corps()['appliquer']);
        $choisis = is_array(corps()['ajouter'] ?? null) ? array_map('strval', corps()['ajouter']) : null;
        $a_ignorer = is_array(corps()['ignorer'] ?? null) ? corps()['ignorer'] : [];
        $jeton = pennylane_jeton();
        if ($jeton === '') {
            return [409, ['erreur' => 'Pennylane pas encore relié : collez le jeton du cabinet.', 'jeton' => true]];
        }
        try {
            $societes = pennylane_dossiers($jeton);
        } catch (RuntimeException $e) {
            return [502, ['erreur' => $e->getMessage()]];
        }
        $b = $this->boite();
        if ($b->adresse() !== '' && !$b->personnel()) {  // dossiers SharePoint relus, pour un compte rendu à jour
            try {
                $this->lire_dossiers($b);
            } catch (Throwable) {
                // liste gardée en mémoire : le compte rendu reste utile
            }
        }
        if ($appliquer && $a_ignorer) {  // pas cochés : ignorés pour toujours (Paramètres pour revenir en arrière)
            $noms = array_column(array_map(fn($x) => ['id' => (string)$x['id'], 'nom' => (string)$x['name']], $societes), 'nom', 'id');
            sous_verrou(function () use ($a_ignorer, $noms) {
                $ign = pennylane_ignores();
                foreach ($a_ignorer as $id) {
                    if (isset($noms[(string)$id])) {
                        $ign[(string)$id] = $noms[(string)$id];
                    }
                }
                pennylane_enregistrer(['ignores' => $ign]);
            });
        }
        [$ajoutes, $completes] = sous_verrou(fn() => pennylane_fusionner($societes, $appliquer, $appliquer ? $choisis : null));
        $r = ['le' => maintenant_minutes(), 'par' => $this->moi['nom'], 'pennylane' => count($societes), 'ajoutes' => $ajoutes, 'completes' => $completes,
            'applique' => $appliquer];
        if (!$appliquer && ($ajoutes || $completes)) {
            return $r + ['apercu' => true];  // la personne voit d'abord ce qui changerait, et valide
        }
        $ign = pennylane_ignores();
        $ids = array_map(fn($x) => lien_pennylane($x['id']), array_filter($societes, fn($x) => !isset($ign[(string)$x['id']])));
        $manquants = $absents = [];
        foreach (tous_les_clients($this->reg()->prospects) as $c) {
            if (!empty($c['archive'])) {
                continue;
            }
            $vue = ['id' => $c['id'], 'nom' => $c['nom']];
            if (in_array($c['pennylane'] ?? '', $ids, true) && ($c['dossier'] ?? '') === '') {
                $manquants[] = $vue;  // dans Pennylane, pas de dossier comptable
            } elseif (($c['source'] ?? '') === 'SharePoint') {
                $absents[] = $vue;  // dossier comptable sans dossier Pennylane
            }
        }
        $incomplets = 0;
        foreach (tous_les_clients($this->reg()->prospects, false) as $c) {
            if (empty($c['archive']) && str_contains((string)($c['pennylane'] ?? ''), '/companies/')
                && array_filter(array_keys(CHAMPS_CLAUDE), fn($k) => trim((string)($c[$k] ?? '')) === '')) {
                $incomplets++;
            }
        }
        $r += ['manquants' => $manquants, 'absents' => $absents, 'incomplets' => $incomplets, 'apercu' => false];
        sous_verrou(fn() => pennylane_enregistrer(['synchro' => ['le' => $r['le'], 'par' => $r['par'], 'pennylane' => $r['pennylane'],
            'manquants' => count($manquants), 'absents' => count($absents)]]));
        return $r;
    }

    /** Fils de discussion archivés par la personne (indésirables) : cachés dans le CRM, rien ne change dans Outlook. */
    private function archives(): array
    {
        return lire_json(chemin_data('mails-archives', $this->moi['identifiant'] . '.json'), []);
    }

    private function mail_archiver(): array
    {
        $d = corps();
        $fil = trim((string)($d['fil'] ?? ''));
        if ($fil === '' || strlen($fil) > 400) {
            throw new ErreurSaisie(['fil' => 'Fil inconnu.']);
        }
        $liste = array_values(array_diff($this->archives(), [$fil]));
        if (!empty($d['archive'])) {
            $liste[] = $fil;
        }
        ecrire_json(chemin_data('mails-archives', $this->moi['identifiant'] . '.json'), $liste);
        $this->vider_cache();
        return ['ok' => true, 'archive' => !empty($d['archive'])];
    }

    /** Adresses des contacts du prospect, sauf la sienne et celles de son domaine pro (collègues). */
    private function adresses_du_prospect(array $p, string $moi): array
    {
        $dom = str_contains($moi, '@') ? substr($moi, strrpos($moi, '@') + 1) : '';
        $ancien = strtolower(trim((string)($p['ancien']['email'] ?? '')));
        $out = [];
        foreach ($p['contacts'] ?? [] as $c) {
            $a = strtolower(trim((string)($c['email'] ?? '')));
            if ($a !== '' && $a !== $moi && $a !== $ancien && (in_array($dom, DOMAINES_PUBLICS, true) || !str_ends_with($a, "@$dom")) && !in_array($a, $out, true)) {
                $out[] = $a;
            }
        }
        return $out;
    }

    private function mails_prospect(string $pid): array
    {
        $p = $this->prospect_ou_404($pid);
        $b = $this->boite();
        $moi = $b->adresse();
        if ($moi === '') {
            return ['connecte' => false];
        }
        $ancien = ($_GET['avec'] ?? '') === 'ancien';  // fenêtre « Échanges » de l'ancien cabinet : seulement les mails avec le confrère
        $adresses = $ancien ? array_values(array_filter([strtolower(trim((string)($p['ancien']['email'] ?? '')))]))
            : $this->adresses_du_prospect($p, $moi);
        $fichier = $this->dossier_cache() . "/$pid" . ($ancien ? '-ancien' : '') . '.json';
        $cache = lire_json($fichier, null);
        if (!empty($_GET['frais']) || !is_array($cache) || time() - ($cache['t'] ?? 0) > CACHE_MAILS || ($cache['adresses'] ?? null) !== $adresses) {
            try {
                $mails = $adresses ? array_map(fn($e) => resume_mail($e, $moi), $b->mails_avec($adresses)) : [];
            } catch (ErreurOutlook $e) {
                return $this->erreur_outlook($e);
            }
            $cache = ['t' => time(), 'mails' => $mails, 'adresses' => $adresses];
            ecrire_json($fichier, $cache, false);
        }
        $archives = $this->archives();
        $fils = array_map(fn($f) => $f + ['archive' => in_array($f['id'], $archives, true)], conversations($cache['mails']));
        return ['connecte' => true, 'adresse' => $moi, 'adresses' => $adresses, 'fils' => $fils];
    }

    /** Mails non lus des contacts de chaque prospect (pastille du pipeline). Lus dans Outlook ou dans le CRM : la pastille s'en va. */
    private function mails_nouveaux(): array
    {
        $b = $this->boite();
        $moi = $b->adresse();
        if ($moi === '') {
            return ['connecte' => false];
        }
        $fichier = $this->dossier_cache() . '/_nouveaux.json';
        $cache = lire_json($fichier, null);
        if (empty($_GET['frais']) && is_array($cache) && time() - ($cache['t'] ?? 0) < CACHE_NOUVEAUX) {
            $mails = $cache['mails'];
        } else {
            try {
                $mails = array_map(fn($e) => ['id' => $e['id'], 'fil' => ($e['conversationId'] ?? '') ?: $e['id'], 'de' => adresse_graph($e['from'] ?? []), 'nom' => nom_graph($e['from'] ?? []),
                    'objet' => trim((string)($e['subject'] ?? '')) ?: '(sans objet)', 'le' => $e['receivedDateTime'] ?? ''], $b->non_lus());
            } catch (ErreurOutlook $e) {
                return $this->erreur_outlook($e);
            }
            ecrire_json($fichier, ['t' => time(), 'mails' => $mails], false);
        }
        $this->reg()->recharger();
        $par_adresse = [];
        foreach ($this->reg()->prospects as $p) {
            if (($p['statut'] ?? '') === 'PERDU') {
                continue;
            }
            foreach ($this->adresses_du_prospect($p, $moi) as $a) {
                $par_adresse[$a][] = $p['id'];
            }
        }
        $out = [];
        $archives = $this->archives();
        foreach ($mails as $m) {
            if (in_array($m['fil'] ?? '', $archives, true)) {  // fil archivé : pas de pastille
                continue;
            }
            foreach ($par_adresse[$m['de']] ?? [] as $pid) {
                $o = &$out[$pid];
                $o ??= ['non_lus' => 0, 'dernier' => '', 'objet' => '', 'de' => '', 'ids' => []];
                $o['non_lus']++;
                $o['ids'][] = $m['id'];
                if ($m['le'] > $o['dernier']) {
                    $o = array_merge($o, ['dernier' => $m['le'], 'objet' => $m['objet'], 'de' => $m['nom'] ?: $m['de']]);
                }
                unset($o);
            }
        }
        return ['connecte' => true, 'prospects' => (object)$out, 'total' => array_sum(array_column($out, 'non_lus'))];
    }

    private function mail_detail(string $mid): array
    {
        $b = $this->boite();
        try {
            [$nouveau, $complet, $lu] = $b->contenu($mid);
            $pj = $b->pieces_jointes($mid);
            if (!$lu) {
                $b->marquer_lu($mid);
                @unlink($this->dossier_cache() . '/_nouveaux.json');  // la pastille du pipeline suit tout de suite
                foreach (glob($this->dossier_cache() . '/*.json') ?: [] as $f) {  // lu aussi dans les fiches déjà en cache
                    $c = lire_json($f, null);
                    $change = false;
                    foreach ($c['mails'] ?? [] as $i => $m) {
                        if ($m['id'] === $mid && !$m['lu']) {
                            $c['mails'][$i]['lu'] = true;
                            $change = true;
                        }
                    }
                    if ($change) {
                        ecrire_json($f, $c, false);
                    }
                }
            }
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        return ['texte' => trim($nouveau) ?: trim($complet), 'complet' => $complet, 'pj' => $pj];
    }

    private function mail_pj(string $mid, string $aid): array
    {
        try {
            $r = $this->boite()->telecharger($mid, $aid);
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        if ($r === null) {
            arret(404, 'Pièce jointe introuvable.');
        }
        [$contenu, $nom, $type] = $r;
        // « Ouvrir » dans le navigateur : seulement les types sans danger (PDF, images, texte) ; le reste est téléchargé
        $sur = in_array(strtolower(trim(explode(';', $type)[0])), ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'], true);
        envoyer_fichier($contenu, $nom, $type, !(($_GET['voir'] ?? '') === '1' && $sur), true,
            "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; object-src 'self'");
    }

    /** Envoi depuis la boîte de la personne connectée ; lettres de mission du prospect jointes en PDF si demandé.
     *  Ensuite, comme les grands CRM : la relance due est faite et la suivante prévue (Paramètres). */
    private function mail_envoyer(string $pid): array
    {
        $p = $this->prospect_ou_404($pid);
        $d = corps();
        $liste = fn($x) => array_values(array_map('trim', array_filter(is_array($x) ? $x : [], fn($a) => is_string($a) && str_contains($a, '@'))));
        $a = $liste($d['a'] ?? []);
        $cc = $liste($d['cc'] ?? []);
        $objet = trim((string)($d['objet'] ?? ''));
        $repondre = is_string($d['repondre_a'] ?? null) && $d['repondre_a'] !== '' ? $d['repondre_a'] : null;
        $champs = [];
        if (!$a) {
            $champs['a'] = 'Au moins un destinataire.';
        }
        if ($objet === '' && !$repondre) {
            $champs['objet'] = 'L\'objet est obligatoire.';
        }
        if (trim(strip_tags((string)($d['html'] ?? ''))) === '' && trim((string)($d['texte'] ?? '')) === '') {
            $champs['texte'] = 'Le message est vide.';
        }
        if ($champs) {
            throw new ErreurSaisie($champs);
        }
        [$pieces, $lettres] = $this->pieces_jointes($d);
        $mission = array_filter($lettres, fn($e) => ($e['modele'] ?? '') !== 'CONFRATERNELLE');
        $confraternelle = array_filter($lettres, fn($e) => ($e['modele'] ?? '') === 'CONFRATERNELLE');
        if ($mission && ($refus = reprise_bloque_lettre($p))) {
            throw new ErreurSaisie(['lettres' => $refus]);
        }
        try {
            $this->boite()->envoyer($a, $objet, corps_mail($d), $cc, $pieces, $repondre);
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        $this->vider_cache($pid);
        $delai = $this->jours('RelanceSuivanteJours', 7);
        $vers_ancien = ($d['avec'] ?? '') === 'ancien';  // mail au confrère : ni relance du prospect, ni changement d'étape
        [$cas, , $nouvelle] = sous_verrou(function () use ($pid, $p, $a, $objet, $pieces, $delai, $mission, $confraternelle, $vers_ancien) {
            $reg = $this->reg();
            $avant = $reg->lire_fichier();
            $reg->journal($pid, ($vers_ancien ? 'Mail envoyé à l\'ancien cabinet (' : 'Mail envoyé à ') . implode(', ', $a) . ($vers_ancien ? ')' : '')
                . ' : « ' . ($objet !== '' ? $objet : 'réponse') . ' »' . ($pieces ? ' (' . count($pieces) . ' lettre(s) jointe(s))' : '') . '.');
            if ($confraternelle) {
                $reg->noter_confraternelle($pid, null, aujourdhui(), 'par mail à ' . implode(', ', $a));
            }
            if ($mission && in_array($p['etape'], ['A_QUALIFIER', 'RDV_DECOUVERTE', 'PROPOSITION_ENVOYEE'], true)) {
                $reg->deplacer($pid, 'LETTRE_ENVOYEE');  // la lettre de mission est partie : le pipeline suit
            }
            $r = $vers_ancien ? ['', null, null] : $reg->apres_mail_envoye($pid, aujourdhui(), $delai);
            if ($reg->lire_fichier() !== $avant) {
                $piles = $this->piles();
                $piles['annuler'][] = [$avant, $reg->lire_fichier(), "{$p['denomination']} : mail envoyé"];
                $this->ecrire_piles($piles);
            }
            return $r;
        });
        return ['ok' => true, 'relance' => $cas, 'prochaine' => $nouvelle ?? ''];
    }

    /** PDF des lettres choisies (Livrables) : [[nom, contenu]] et leurs fiches. */
    private function pieces_jointes(array $d): array
    {
        $pieces = $lettres = [];
        foreach (is_array($d['lettres'] ?? null) ? $d['lettres'] : [] as $lid) {
            $chemin = lettre_chemin((string)$lid, 'pdf');
            $e = lettre_trouver((string)$lid);
            if ($chemin && $e) {
                $pieces[] = [substr($e['fichier'], 0, -5) . '.pdf', (string)file_get_contents($chemin)];
                $lettres[] = $e;
            }
        }
        return [$pieces, $lettres];
    }

    /** Mail hors fiche prospect (lettre de mission d'un client du cabinet, depuis Livrables). */
    private function mail_libre(): array
    {
        $d = corps();
        $a = array_values(array_filter(array_map('trim', is_array($d['a'] ?? null) ? $d['a'] : []), fn($x) => is_string($x) && str_contains($x, '@')));
        $objet = trim((string)($d['objet'] ?? ''));
        $champs = [];
        if (!$a) {
            $champs['a'] = 'Au moins un destinataire.';
        }
        if ($objet === '') {
            $champs['objet'] = 'L\'objet est obligatoire.';
        }
        if (trim(strip_tags((string)($d['html'] ?? ''))) === '' && trim((string)($d['texte'] ?? '')) === '') {
            $champs['texte'] = 'Le message est vide.';
        }
        if ($champs) {
            throw new ErreurSaisie($champs);
        }
        [$pieces] = $this->pieces_jointes($d);
        try {
            $this->boite()->envoyer($a, $objet, corps_mail($d), [], $pieces);
        } catch (ErreurOutlook $e) {
            return $this->erreur_outlook($e);
        }
        return ['ok' => true];
    }

    /** Lettre confraternelle du prospect (reprise d'un confrère) : créée sur le papier du cabinet, rangée dans Livrables. */
    private function confraternelle(string $pid): array
    {
        $p = $this->prospect_ou_404($pid);
        if (($p['origine'] ?? '') !== 'REPRISE') {
            throw new ErreurSaisie(['ancien' => 'Seulement pour la reprise du dossier d\'un confrère.']);
        }
        if (trim((string)($p['cabinet_precedent'] ?? '')) === '') {
            throw new ErreurSaisie(['cabinet' => 'Indiquez d\'abord le cabinet de l\'ancien expert-comptable.']);
        }
        $entree = sous_verrou(function () use ($p, $pid) {
            $e = confraternelle_generer($p, $this->moi['nom']);
            $this->reg()->noter_confraternelle($pid, $e['id']);
            return $e;
        });
        return ['lettre' => $this->vue_lettre($entree)];
    }

    // ---------- Signature des mails (chacun la sienne) ----------
    private function chemin_signature(): string
    {
        return chemin_data('signatures', $this->moi['identifiant'] . '.html');
    }

    /** Signature enregistrée de la personne connectée ; sinon une signature simple (nom, cabinet, adresse mail). L'interface propose
     *  alors celle d'Outlook (signatures_locales) et demande avant de la garder. */
    private function signature(): array
    {
        $html = is_file($this->chemin_signature()) ? (string)file_get_contents($this->chemin_signature()) : null;
        if ($html === null) {
            $adresse = $this->boite()->adresse();
            $html = '<p><b>' . htmlspecialchars($this->moi['nom']) . '</b><br>' . htmlspecialchars(CABINET_NOM) . ' · Expertise comptable'
                . ($adresse ? '<br><a href="mailto:' . htmlspecialchars($adresse) . '">' . htmlspecialchars($adresse) . '</a>' : '') . '</p>';
        }
        return ['html' => $html, 'personnalisee' => is_file($this->chemin_signature())];
    }

    private function lire_signature_outlook(): ?string
    {
        $b = $this->boite();
        if ($b->adresse() === '') {
            return null;
        }
        try {
            return $b->signature_outlook();
        } catch (ErreurOutlook) {
            return null;
        }
    }

    /** Signature relue dans le dernier mail envoyé (CRM en ligne, sans accès à l'Outlook du PC). Pas gardée : l'interface demande. */
    private function signature_outlook(): array
    {
        $sig = $this->lire_signature_outlook();
        if ($sig === null) {
            return [409, ['erreur' => 'Aucune signature trouvée dans vos derniers mails envoyés : importez le fichier de votre signature Outlook.']];
        }
        return ['html' => $sig, 'source' => 'outlook'];
    }

    /** Signatures enregistrées dans Outlook, sur ce PC : seulement quand le navigateur est sur le PC où tourne le CRM (sinon ce
     *  seraient celles d'un autre poste). La signature par défaut d'Outlook en premier. */
    private function signatures_locales(): array
    {
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        $adresse = $this->boite()->adresse();
        return ['signatures' => $local && $adresse !== '' ? signatures_outlook_locales($adresse) : []];
    }

    /** Fichier de signature importé (.htm d'Outlook) : nettoyé pour l'aperçu, pas encore gardé. */
    private function signature_apercu(): array
    {
        $brut = mb_substr((string)(corps()['html'] ?? ''), 0, 3000000);
        if (preg_match('#<body[^>]*>(.*)</body>#is', $brut, $m)) {
            $brut = $m[1];
        }
        return ['html' => nettoyer_html($brut)];
    }

    private function signature_enregistrer(): array
    {
        $html = nettoyer_html(mb_substr((string)(corps()['html'] ?? ''), 0, 1500000));  // avec le logo intégré
        ecrire_fichier($this->chemin_signature(), $html);
        return ['html' => $html, 'personnalisee' => true];
    }
}
