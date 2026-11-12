<?php
/**
 * Prospects du CRM : étapes du pipeline, alertes de relance, doublons de
 * contacts, honoraires, et toutes les actions (créer, déplacer, perdu, signée, relance, contacts…). Fichier : data/prospects.json.
 */
declare(strict_types=1);

const ORDRE_ETAPES = ['A_QUALIFIER', 'RDV_DECOUVERTE', 'PROPOSITION_ENVOYEE', 'LETTRE_ENVOYEE'];
const LIBELLES_ETAPE = ['A_QUALIFIER' => 'À qualifier', 'RDV_DECOUVERTE' => 'RDV découverte',
    'PROPOSITION_ENVOYEE' => 'Proposition envoyée', 'LETTRE_ENVOYEE' => 'Lettre de mission envoyée'];
const LIBELLES_NATURE = ['PROFESSIONNEL' => 'Professionnel', 'PARTICULIER' => 'Particulier'];
const LIBELLES_SOURCE = ['SITE_INTERNET' => 'Site internet', 'FORMULAIRE' => 'Formulaire en ligne',
    'RECOMMANDATION' => "Recommandation d'un client", 'RESEAU' => 'Réseau ou partenaire', 'CONFRERE' => 'Confrère',
    'EVENEMENT' => 'Événement', 'AUTRE' => 'Autre'];
const LIBELLES_ORIGINE = ['CREATION' => 'Création de société', 'REPRISE' => "Reprise d'un confrère",
    'EXISTANTE' => 'Société existante sans expert-comptable'];
const LIBELLES_ORIGINE_EI = ['CREATION' => "Création d'entreprise", 'REPRISE' => "Reprise d'un confrère",
    'EXISTANTE' => 'Entreprise existante sans expert-comptable'];
// Ancien expert-comptable d'un dossier repris (lettre confraternelle) : « cabinet » est aussi le « cabinet précédent »
const CHAMPS_ANCIEN = ['cabinet', 'civilite', 'prenom', 'nom', 'adresse', 'cp', 'ville', 'email', 'telephone'];
const LIBELLES_ROLE = ['CONTACT_PRINCIPAL' => 'Contact principal', 'REPRESENTANT_LEGAL' => 'Représentant légal', 'DECLARANT' => 'Déclarant',
    'CONJOINT' => 'Conjoint', 'ASSOCIE' => 'Associé', 'AUTRE' => 'Autre'];
const LIBELLES_CIVILITE = ['' => '–', 'M' => 'M.', 'MME' => 'Mme'];
const LIBELLES_MOTIF_PERTE = ['PRIX' => 'Prix', 'CONCURRENT' => 'Concurrent retenu', 'SANS_REPONSE' => 'Pas de réponse',
    'HORS_CIBLE' => 'Hors cible', 'PROJET_ABANDONNE' => 'Projet abandonné', 'AUTRE' => 'Autre'];
const LIBELLES_TYPE = ['SOCIETE' => 'Société', 'EI' => 'Entreprise individuelle', 'PARTICULIER' => 'Particulier'];
const MISSIONS_PAR_TYPE = [
    'SOCIETE' => ['Présentation des comptes', 'Établissement des comptes', 'Assistance aux déclarations fiscales (TVA)'],
    'EI' => ['BNC (déclaration 2035)', 'BIC (déclaration 2031)', 'LMNP (location meublée)', 'Assistance aux déclarations fiscales (TVA)'],
    'PARTICULIER' => ['Déclaration de revenus (IR)', 'Revenus fonciers (2044)', 'IFI'],
];
const ANCIENNES_MISSIONS = ['Comptabilité / Fiscalité', 'Juridique annuel', 'Création de société', 'Paie', 'Prévisionnel', 'Attestation',
    'Situation intermédiaire', 'Évaluation / apport de titres', 'Autre', 'Accompagnement micro-entreprise', 'BNC (2035)',
    'LMNP', 'Création / immatriculation', 'Juridique', 'Déclaration de revenus', 'Revenus fonciers', 'BNC', 'BIC au réel'];
// Anciens intitulés -> intitulés actuels (les prospects déjà enregistrés sont convertis à la lecture)
const RENOMMAGES = ['BIC au réel' => 'BIC (déclaration 2031)', 'Présentation des comptes (avec assurance)' => 'Présentation des comptes',
    'Établissement des comptes (sans assurance)' => 'Établissement des comptes'];
const FORMES_ENVISAGEES = ['' => 'Pas encore décidée', 'SAS' => 'SAS', 'SASU' => 'SASU', 'SARL' => 'SARL', 'EURL' => 'EURL', 'SCI' => 'SCI',
    'EI' => 'Entreprise individuelle', 'AUTRE' => 'Autre'];
const LIBELLES_ALERTE = ['RELANCE_EN_RETARD' => 'Relance en retard', 'SANS_RELANCE' => 'Sans relance prévue'];
const DEFAUTS = ['Responsables' => "Claire Moreau\nPaul Bernard", 'DelaiSansActiviteJours' => '15', 'RelanceSuivanteJours' => '7'];

function missions_connues(): array
{
    static $m = null;
    $m ??= array_values(array_unique(array_merge(array_merge(...array_values(MISSIONS_PAR_TYPE)), ANCIENNES_MISSIONS)));
    return $m;
}

/** Régime d'une entreprise individuelle, déduit de la lettre choisie. */
function regime_des_missions(array $missions): string
{
    $texte = implode(' ', $missions);
    foreach (['LMNP' => 'LMNP', 'BNC' => 'BNC', 'BIC' => 'BIC'] as $mot => $regime) {
        if (str_contains($texte, $mot)) {
            return $regime;
        }
    }
    return $texte !== '' ? 'MICRO' : '';
}

/** SOCIETE, EI ou PARTICULIER (prospects créés avant ce choix : professionnel = société, sauf LMNP / BNC). */
function type_prospect(array $p): string
{
    if (($p['nature'] ?? '') === 'PARTICULIER') {
        return 'PARTICULIER';
    }
    if (in_array($p['type_pro'] ?? '', ['SOCIETE', 'EI'], true)) {
        return $p['type_pro'];
    }
    foreach ($p['missions'] ?? [] as $m) {
        if (in_array($m, array_slice(MISSIONS_PAR_TYPE['EI'], 0, 3), true) || in_array($m, ['LMNP', 'BNC', 'BNC (2035)'], true)) {
            return 'EI';
        }
    }
    return 'SOCIETE';
}

/** Pourquoi la lettre de mission ne peut pas encore partir (reprise d'un confrère), ou null. */
function reprise_bloque_lettre(array $p): ?string
{
    if (($p['nature'] ?? '') !== 'PROFESSIONNEL' || ($p['origine'] ?? '') !== 'REPRISE') {
        return null;
    }
    if (trim((string)($p['cabinet_precedent'] ?? '')) === '') {
        return 'Reprise d\'un confrère : renseignez d\'abord l\'ancien cabinet (fiche du prospect › Ancien cabinet).';
    }
    if (empty($p['confraternelle']['envoyee_le'])) {
        return 'Reprise d\'un confrère : envoyez d\'abord la lettre confraternelle à l\'ancien cabinet (fiche du prospect › Ancien cabinet).';
    }
    return null;
}

function siren_valide(?string $siren): bool
{
    $s = str_replace(' ', '', (string)$siren);
    if (strlen($s) !== 9 || !ctype_digit($s)) {
        return false;
    }
    $total = 0;
    foreach (array_reverse(str_split($s)) as $i => $c) {
        $n = (int)$c * ($i % 2 ? 2 : 1);
        $total += $n > 9 ? $n - 9 : $n;
    }
    return $total % 10 === 0;
}

function nom_complet(array $c): string
{
    $civ = !empty($c['civilite']) ? (LIBELLES_CIVILITE[$c['civilite']] ?? '') : '';
    return trim(implode(' ', array_filter([$civ, $c['prenom'] ?? '', mb_strtoupper($c['nom'] ?? '')], fn($x) => $x !== '')));
}

function email_valide(string $email): bool
{
    return str_contains($email, '@') && str_contains(substr($email, strrpos($email, '@') + 1), '.');
}

/** Contacts saisis à la création : « contacts » (plusieurs), ou l'ancien « contact » (un seul). */
function contacts_saisis(array $d): array
{
    if (is_array($d['contacts'] ?? null)) {
        return array_values(array_filter($d['contacts'], 'is_array'));
    }
    return is_array($d['contact'] ?? null) ? [$d['contact']] : [];
}

/** Contrôles de la création (schéma des fiches prospects). Lève ErreurSaisie. */
function valider_creation(array $d): void
{
    $champs = [];
    $pro = ($d['nature'] ?? '') === 'PROFESSIONNEL';
    if (!isset(LIBELLES_NATURE[$d['nature'] ?? ''])) {
        $champs['nature'] = 'Choisir la nature du dossier.';
    }
    if (trim((string)($d['denomination'] ?? '')) === '') {
        $champs['denomination'] = $pro ? 'La dénomination est obligatoire.' : 'Le nom du foyer est obligatoire.';
    }
    if ($pro && !isset(LIBELLES_ORIGINE[$d['origine'] ?? ''])) {
        $champs['origine'] = "Choisir l'origine du dossier.";
    }
    if ($pro && ($d['origine'] ?? '') === 'REPRISE' && trim((string)($d['cabinet_precedent'] ?? '')) === '' && empty($d['cabinet_plus_tard'])) {
        $champs['cabinet_precedent'] = 'Indiquer le cabinet précédent, ou cocher « À renseigner plus tard ».';
    }
    if (($d['source'] ?? '') === 'AUTRE' && mb_strlen(trim((string)($d['source_autre'] ?? ''))) > 80) {
        $champs['source_autre'] = '80 caractères au plus.';
    }
    $siren = str_replace(' ', '', (string)($d['siren'] ?? ''));
    if ($pro && $siren !== '' && !siren_valide($siren)) {
        $champs['siren'] = 'SIREN invalide : 9 chiffres avec une clé correcte.';
    }
    foreach (['nb_biens' => 999, 'nb_personnes' => 99] as $cle => $maxi) {
        $v = trim((string)($d[$cle] ?? ''));
        if ($v !== '' && (!ctype_digit($v) || (int)$v > $maxi)) {
            $champs[$cle] = "Nombre entier entre 0 et $maxi.";
        }
    }
    foreach (contacts_saisis($d) as $i => $c) {
        if (!empty($c['id_lie'])) {
            continue;
        }
        $n = $i ? ' (contact ' . ($i + 1) . ')' : '';
        if (trim((string)($c['nom'] ?? '')) === '') {
            $champs['contact.nom'] ??= "Le nom du contact est obligatoire$n.";
        }
        $email = trim((string)($c['email'] ?? ''));
        if ($email !== '' && !email_valide($email)) {
            $champs['contact.email'] ??= "Adresse e-mail invalide$n.";
        }
    }
    $relance = trim((string)($d['prochaine_relance'] ?? ''));
    if ($relance !== '' && !lire_date($relance)) {
        $champs['prochaine_relance'] = 'Date invalide (JJ/MM/AAAA).';
    }
    if ($champs) {
        throw new ErreurSaisie($champs);
    }
}

/** RELANCE_EN_RETARD : date de relance passée ; SANS_RELANCE : pas de relance et aucune activité depuis N jours. */
function alertes(array $p, string $aujourdhui, int $sans_activite_jours = 15): array
{
    $a = [];
    $relance = lire_date($p['prochaine_relance'] ?? null);
    if ($relance && $relance < $aujourdhui) {
        $a[] = 'RELANCE_EN_RETARD';
    }
    if (!$relance) {
        $dates = array_filter([lire_date($p['derniere_activite'] ?? null), lire_date($p['cree_le'] ?? null)]);
        $reference = $dates ? max($dates) : $aujourdhui;
        if (jours_entre($reference, $aujourdhui) >= $sans_activite_jours) {
            $a[] = 'SANS_RELANCE';
        }
    }
    return $a;
}

/** Contacts déjà connus : même e-mail, ou mêmes prénom et nom. [[contact, [dénominations]]]. */
function doublons(array $prospects, ?string $prenom, ?string $nom, ?string $email): array
{
    $email = mb_strtolower(trim((string)$email));
    $prenom = mb_strtolower(trim((string)$prenom));
    $nom = mb_strtolower(trim((string)$nom));
    $trouves = [];
    foreach ($prospects as $p) {
        foreach ($p['contacts'] ?? [] as $c) {
            $meme_mail = $email !== '' && mb_strtolower(trim((string)($c['email'] ?? ''))) === $email;
            $meme_nom = $nom !== '' && mb_strtolower(trim((string)($c['nom'] ?? ''))) === $nom
                && mb_strtolower(trim((string)($c['prenom'] ?? ''))) === $prenom;
            if ($meme_mail || $meme_nom) {
                $trouves[$c['id']] ??= ['contact' => $c, 'dossiers' => []];
                $trouves[$c['id']]['dossiers'][] = $p['denomination'];
            }
        }
    }
    return array_values($trouves);
}

/** [récurrent sur un an, ponctuel] à partir des honoraires saisis sur la fiche. Offerts exclus. */
function totaux_honoraires(array $p): array
{
    $recurrent = $ponctuel = 0.0;
    foreach ($p['honoraires']['lignes'] ?? [] as $h) {
        $m = (float)($h['montant'] ?? 0);
        if (!empty($h['offert'])) {
            continue;
        }
        $u = $h['unite'] ?? '';
        if (in_array($u, ['mois', 'an', 'trimestre'], true)) {
            $recurrent += $m * ['mois' => 12, 'trimestre' => 4, 'an' => 1][$u];
        } elseif (in_array($u, ['forfait', 'declaration'], true)) {
            $ponctuel += $m;
        }
    }
    return [$recurrent, $ponctuel];
}

function euros(float $x): string
{
    return number_format($x, 0, ',', ' ') . ' €';
}

/**
 * Les prospects (data/prospects.json). Chaque méthode relit le fichier avant d'écrire : ce qu'un collègue vient
 * d'enregistrer n'est jamais écrasé. À appeler sous verrou (sous_verrou) pour les écritures.
 */
final class Registre
{
    public array $prospects = [];
    public string $derniere_action = '';

    public function __construct(public string $chemin = '')
    {
        $this->chemin = $chemin ?: chemin_data('prospects.json');
        $this->recharger();
    }

    public function recharger(): void
    {
        $this->prospects = lire_json($this->chemin, []);
        foreach ($this->prospects as &$p) {
            if (array_intersect(array_keys(RENOMMAGES), $p['missions'] ?? [])) {
                $p['missions'] = array_values(array_unique(array_map(fn($m) => RENOMMAGES[$m] ?? $m, $p['missions'])));
            }
        }
        unset($p);
    }

    public function lire_fichier(): string
    {
        return is_file($this->chemin) ? (string)file_get_contents($this->chemin) : '[]';
    }

    public function ecrire_fichier(string $texte): void
    {
        ecrire_fichier($this->chemin, $texte);
    }

    public function enregistrer(): void
    {
        ecrire_json($this->chemin, $this->prospects);
    }

    public function &trouver(?string $pid): ?array
    {
        foreach ($this->prospects as &$p) {
            if ($p['id'] === $pid) {
                return $p;
            }
        }
        unset($p);
        $rien = null;
        return $rien;
    }

    public function existe(?string $pid): bool
    {
        foreach ($this->prospects as $p) {
            if ($p['id'] === $pid) {
                return true;
            }
        }
        return false;
    }

    public function prochain_code(): string
    {
        $nums = [];
        foreach ($this->prospects as $p) {
            if (str_starts_with($p['code'] ?? '', 'P-')) {
                $nums[] = (int)substr($p['code'], 2);
            }
        }
        return sprintf('P-%04d', $nums ? max($nums) + 1 : 1);
    }

    public function noter(array &$p, string $texte): void
    {
        $this->derniere_action = ($p['denomination'] ?? '') . " : $texte";  // libellé de Ctrl+Z
        $p['historique'][] = ['le' => maintenant_minutes(), 'texte' => $texte];
        $p['derniere_activite'] = aujourdhui();
    }

    /** Crée le prospect à l'étape « À qualifier ». Retourne le prospect. */
    public function creer(array $d): array
    {
        valider_creation($d);
        $this->recharger();
        $pro = $d['nature'] === 'PROFESSIONNEL';
        $contacts = [];
        foreach (contacts_saisis($d) as $c) {
            if (!empty($c['id_lie'])) {  // contact déjà connu : même personne, rôle propre à ce dossier
                foreach ($this->prospects as $q) {
                    foreach ($q['contacts'] ?? [] as $x) {
                        if ($x['id'] === $c['id_lie'] && !in_array($x['id'], array_column($contacts, 'id'), true)) {
                            $contacts[] = array_merge($x, ['role' => ($c['role'] ?? '') ?: 'AUTRE']);
                            break 2;
                        }
                    }
                }
            } else {
                $contacts[] = ['id' => uuid_hex(), 'role' => ($c['role'] ?? '') ?: 'AUTRE', 'civilite' => $c['civilite'] ?? '',
                    'prenom' => trim((string)($c['prenom'] ?? '')), 'nom' => trim((string)($c['nom'] ?? '')),
                    'email' => trim((string)($c['email'] ?? '')), 'telephone' => trim((string)($c['telephone'] ?? '')),
                    'fonction' => mb_substr(trim((string)($c['fonction'] ?? '')), 0, 80)];
            }
        }
        $relance = lire_date($d['prochaine_relance'] ?? null);
        $type_pro = $pro ? (in_array($d['type_pro'] ?? '', ['SOCIETE', 'EI'], true) ? $d['type_pro'] : 'SOCIETE') : '';
        $creation = $pro && !empty($d['en_creation']);
        $p = [
            'id' => uuid_hex(), 'code' => $this->prochain_code(), 'nature' => $d['nature'],
            'denomination' => trim((string)$d['denomination']), 'siren' => $pro ? str_replace(' ', '', (string)($d['siren'] ?? '')) : '',
            'responsable' => $d['responsable'] ?? '', 'source' => $d['source'] ?? '', 'origine' => $pro ? ($d['origine'] ?? '') : '',
            'en_creation' => $creation, 'type_pro' => $type_pro,
            'regime' => ($pro && ($d['type_pro'] ?? '') === 'EI') ? ($d['regime'] ?? '') : '',
            'partenaire' => !empty($d['partenaire']) && !$pro, 'forme' => $pro ? ($d['forme'] ?? '') : '',
            'forme_envisagee' => $creation ? ($d['forme_envisagee'] ?? '') : '',
            'creation_prevue' => $creation ? mb_substr(trim((string)($d['creation_prevue'] ?? '')), 0, 40) : '',
            'cabinet_precedent' => ($pro && ($d['origine'] ?? '') === 'REPRISE') ? trim((string)($d['cabinet_precedent'] ?? '')) : '',
            'cabinet_plus_tard' => $pro && ($d['origine'] ?? '') === 'REPRISE' && trim((string)($d['cabinet_precedent'] ?? '')) === '',
            'source_autre' => ($d['source'] ?? '') === 'AUTRE' ? mb_substr(trim((string)($d['source_autre'] ?? '')), 0, 80) : '',
            'nb_biens' => $pro ? '' : trim((string)($d['nb_biens'] ?? '')),
            'nb_personnes' => $pro ? '' : trim((string)($d['nb_personnes'] ?? '')),
            'missions' => array_values(array_filter($d['missions'] ?? [], fn($m) => in_array($m, missions_connues(), true))),
            'contacts' => $contacts, 'etape' => 'A_QUALIFIER', 'statut' => 'PROSPECT',
            'prochaine_relance' => $relance ?? '', 'note_relance' => mb_substr(trim((string)($d['note_relance'] ?? '')), 0, 200),
            'adresse' => $pro ? mb_substr(trim((string)($d['adresse'] ?? '')), 0, 200) : '',  // siège (registre des entreprises)
            'cp' => $pro ? mb_substr(trim((string)($d['cp'] ?? '')), 0, 10) : '', 'ville' => $pro ? mb_substr(trim((string)($d['ville'] ?? '')), 0, 80) : '',
            'registre' => $pro ? registre_a_garder($d['registre'] ?? null) : [],  // SIRET, TVA, activité, effectif, chiffre d'affaires…
            'cree_le' => aujourdhui(), 'derniere_activite' => aujourdhui(),
            'historique' => [['le' => maintenant_minutes(), 'texte' => 'Prospect créé (À qualifier).']],
        ];
        $this->prospects[] = $p;
        $this->derniere_action = "{$p['denomination']} : prospect créé";
        ecrire_json($this->chemin, $this->prospects);
        return $p;
    }

    public function deplacer(string $pid, ?string $etape): bool
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        if ($p === null || !in_array($etape, ORDRE_ETAPES, true) || $p['etape'] === $etape) {
            return false;
        }
        $p['etape'] = $etape;
        $p['etape_le'] = aujourdhui();  // « en attente de signature depuis le… »
        $this->noter($p, 'Étape : ' . LIBELLES_ETAPE[$etape] . '.');
        $this->enregistrer();
        return true;
    }

    public function marquer_perdu(string $pid, ?string $motif, ?string $precision = ''): void
    {
        if (!isset(LIBELLES_MOTIF_PERTE[(string)$motif])) {
            throw new ErreurSaisie(['motif' => 'Choisir un motif.']);
        }
        $this->recharger();
        $p = &$this->trouver($pid);
        $precision = trim((string)$precision);
        $p['statut'] = 'PERDU';
        $p['perte'] = ['motif' => $motif, 'precision' => mb_substr($precision, 0, 300), 'le' => aujourdhui()];
        $this->noter($p, 'Perdu : ' . LIBELLES_MOTIF_PERTE[$motif] . ($precision !== '' ? " ($precision)" : '') . '.');
        $this->enregistrer();
    }

    /** Lettre signée : client du cabinet, en onboarding (pièces à recevoir), et quitte le pipeline. */
    public function lettre_signee(string $pid, ?string $le = null): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $p['statut'] = 'CLIENT';
        $p['signee_le'] = $le ?: aujourdhui();
        $p['onboarding'] = 'EN_COURS';
        $this->noter($p, 'Lettre de mission signée : client du cabinet (onboarding).');
        $this->enregistrer();
    }

    /** Client remis en onboarding (pièces à recevoir), depuis la fiche client. */
    public function reprendre_onboarding(string $pid): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $p['onboarding'] = 'EN_COURS';
        $this->noter($p, 'Client remis en onboarding.');
        $this->enregistrer();
    }

    public function terminer_onboarding(string $pid): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $p['onboarding'] = 'TERMINE';
        $this->noter($p, 'Onboarding terminé : pièces reçues, client actif.');
        $this->enregistrer();
    }

    public function planifier_relance(string $pid, ?string $quand, ?string $note = ''): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $p['prochaine_relance'] = $quand ?: '';
        $p['note_relance'] = mb_substr(trim((string)$note), 0, 200);
        $this->noter($p, $quand ? 'Relance prévue le ' . jjmmaaaa($quand) . '.' : 'Relance supprimée.');
        $this->enregistrer();
    }

    /** Supprime le prospect pour de bon (pas « perdu ») : il quitte le fichier, son historique avec lui. */
    public function supprimer(string $pid): void
    {
        $this->recharger();
        foreach ($this->prospects as $i => $p) {
            if ($p['id'] === $pid) {
                $this->derniere_action = "{$p['denomination']} : supprimé";
                array_splice($this->prospects, $i, 1);
                $this->enregistrer();
                return;
            }
        }
    }

    /** Ancien expert-comptable d'un dossier repris : cabinet, expert, adresse, e-mail. Le cabinet devient le « cabinet précédent ». */
    public function enregistrer_ancien(string $pid, array $d): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $a = [];
        foreach (CHAMPS_ANCIEN as $k) {
            $a[$k] = mb_substr(trim((string)($d[$k] ?? '')), 0, $k === 'adresse' ? 200 : 80);
        }
        $p['ancien'] = $a;
        $p['cabinet_precedent'] = $a['cabinet'];
        $p['cabinet_plus_tard'] = $a['cabinet'] === '';
        $this->noter($p, 'Ancien cabinet renseigné' . ($a['cabinet'] !== '' ? " : {$a['cabinet']}" : '') . '.');
        $this->enregistrer();
    }

    /** Lettre confraternelle préparée ($lettre) ou envoyée ($envoyee_le, $comment : par mail, par courrier…). */
    public function noter_confraternelle(string $pid, ?string $lettre = null, ?string $envoyee_le = null, string $comment = ''): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        if ($p === null) {
            return;
        }
        $c = $p['confraternelle'] ?? [];
        if ($lettre !== null) {
            $c['lettre'] = $lettre;
            $this->noter($p, 'Lettre confraternelle préparée pour ' . (($p['cabinet_precedent'] ?? '') ?: 'l\'ancien cabinet') . '.');
        }
        if ($envoyee_le !== null) {
            $c['envoyee_le'] = $envoyee_le;
            $this->noter($p, 'Lettre confraternelle envoyée' . ($comment !== '' ? " $comment" : '') . ' le ' . jjmmaaaa($envoyee_le) . '.');
        }
        $p['confraternelle'] = $c;
        $this->enregistrer();
    }

    /** Ligne d'historique (compte comme activité). */
    public function journal(string $pid, string $texte): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        if ($p !== null) {
            $this->noter($p, $texte);
            $this->enregistrer();
        }
    }

    /** Ajoute (sans id) ou modifie un contact ; un contact lié à plusieurs dossiers est la même personne. */
    public function enregistrer_contact(string $pid, array $c): void
    {
        $champs = [];
        if (trim((string)($c['nom'] ?? '')) === '') {
            $champs['nom'] = 'Le nom du contact est obligatoire.';
        }
        $email = trim((string)($c['email'] ?? ''));
        if ($email !== '' && !email_valide($email)) {
            $champs['email'] = 'Adresse e-mail invalide.';
        }
        if ($champs) {
            throw new ErreurSaisie($champs);
        }
        $this->recharger();
        $coord = ['civilite' => $c['civilite'] ?? '', 'prenom' => trim((string)($c['prenom'] ?? '')), 'nom' => trim((string)$c['nom']),
            'email' => $email, 'telephone' => trim((string)($c['telephone'] ?? ''))];
        if (array_key_exists('fonction', $c)) {
            $coord['fonction'] = mb_substr(trim((string)$c['fonction']), 0, 80);
        }
        if (!empty($c['id'])) {
            foreach ($this->prospects as &$q) {
                foreach ($q['contacts'] as &$x) {
                    if ($x['id'] === $c['id']) {
                        $x = array_merge($x, $coord);
                        if ($q['id'] === $pid) {
                            $x['role'] = ($c['role'] ?? '') ?: (($x['role'] ?? '') ?: 'AUTRE');
                        }
                    }
                }
                unset($x);
            }
            unset($q);
            $p = &$this->trouver($pid);
            $this->noter($p, 'Contact modifié : ' . nom_complet($coord) . '.');
        } else {
            $p = &$this->trouver($pid);
            $p['contacts'][] = array_merge(['id' => uuid_hex(), 'role' => ($c['role'] ?? '') ?: 'AUTRE'], $coord);
            $this->noter($p, 'Contact ajouté : ' . nom_complet($coord) . '.');
        }
        $this->enregistrer();
    }

    /** Relance faite : notée, la suivante est prévue $delai jours après (0 = aucune). Retourne la nouvelle date ou null. */
    public function relance_faite(string $pid, ?string $le = null, string $comment = '', int $delai = 7): ?string
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        $le = $le ?: aujourdhui();
        $ancienne = lire_date($p['prochaine_relance'] ?? null);
        $suivante = $delai ? plus_jours($le, $delai) : null;
        $p['prochaine_relance'] = $suivante ?? '';
        $p['note_relance'] = '';
        $texte = ($ancienne ? 'Relance du ' . jjmmaaaa($ancienne) . ' faite' : 'Relance faite') . ($comment !== '' ? " ($comment)" : '') . '.';
        $this->noter($p, $texte . ($suivante ? ' Prochaine relance le ' . jjmmaaaa($suivante) . '.' : ' Pas de prochaine relance.'));
        $this->enregistrer();
        return $suivante;
    }

    /** Comme les grands CRM : un mail envoyé fait la relance due et prévoit la suivante. [cas, ancienne, nouvelle]. */
    public function apres_mail_envoye(string $pid, string $le, int $delai = 7): array
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        if ($p === null || $p['statut'] !== 'PROSPECT') {
            return ['aucune', null, null];
        }
        $r = lire_date($p['prochaine_relance'] ?? null);
        if ($r && $r > $le) {
            return ['maintenue', $r, $r];
        }
        if ($r) {
            unset($p);
            return ['faite', $r, $this->relance_faite($pid, $le, 'mail envoyé', $delai)];
        }
        if (!$delai) {
            return ['aucune', null, null];
        }
        $s = plus_jours($le, $delai);
        $p['prochaine_relance'] = $s;
        $this->noter($p, 'Relance prévue le ' . jjmmaaaa($s) . ' (après un mail envoyé).');
        $this->enregistrer();
        return ['prevue', null, $s];
    }

    public function enregistrer_honoraires(string $pid, array $lignes, string $taxe = 'HT', string $origine = ''): void
    {
        $this->recharger();
        $p = &$this->trouver($pid);
        if ($p === null) {
            return;
        }
        $p['honoraires'] = ['lignes' => array_values(array_map(fn($l) => ['libelle' => $l['libelle'] ?? '', 'montant' => (float)$l['montant'],
            'unite' => ($l['unite'] ?? '') ?: 'forfait', 'offert' => !empty($l['offert'])], $lignes)), 'taxe' => $taxe, 'origine' => $origine];
        [$r, $pt] = totaux_honoraires($p);
        $this->noter($p, 'Honoraires : ' . euros($r) . " $taxe par an" . ($pt ? ', ' . euros($pt) . " $taxe ponctuel" : '')
            . ($origine !== '' ? " ($origine)" : '') . '.');
        $this->enregistrer();
    }
}
