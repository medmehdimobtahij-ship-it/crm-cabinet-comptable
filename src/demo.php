<?php
/**
 * Mode démo (réglage « demo », variable CRM_DEMO=1) : le CRM tourne hors ligne avec des données FICTIVES.
 *
 *   - data-demo/ est rempli au premier lancement : compte « demo » / « demo », clients, prospects à toutes les étapes,
 *     dossiers SharePoint simulés et quelques lettres de mission générées par le vrai moteur de lettres (src/lettres.php) ;
 *   - Microsoft 365 (connexion, Outlook, SharePoint, conversion PDF) et Pennylane ne sont jamais appelés : les routes
 *     concernées répondent par un message clair, et la fonction http() refuse toute sortie réseau par sécurité ;
 *   - le répertoire des entreprises (recherche par nom ou SIREN) est simulé avec quelques sociétés inventées.
 * Toutes les personnes, sociétés, adresses et numéros ci-dessous sont inventés.
 */
declare(strict_types=1);

const DEMO_IDENTIFIANT = 'demo';
const DEMO_NOM = 'Camille Lefèvre';
const DEMO_MESSAGE = 'Mode démo : cette fonction appelle Microsoft 365 ou Pennylane, elle est désactivée ici (aucun compte externe). '
    . 'Le code d\'intégration complet est dans src/outlook.php, src/sharepoint.php et src/pennylane.php.';

/** SIREN fictif mais valide (clé de Luhn) : préfixe 000, jamais attribué par l'INSEE. */
function demo_siren(int $n): string
{
    $base = sprintf('000%05d', $n);
    for ($cle = 0; $cle < 10; $cle++) {
        if (siren_valide($base . $cle)) {
            return $base . $cle;
        }
    }
    return '';
}

/** Sociétés inventées du répertoire simulé (même forme que l'API publique recherche-entreprises). */
function demo_registre(): array
{
    $e = fn(int $n, string $nom, string $nature, string $voie, string $cp, string $ville, string $prenom, string $nomd, string $qualite) => [
        'siren' => demo_siren($n), 'nom_complet' => $nom, 'nature_juridique' => $nature, 'etat_administratif' => 'A',
        'date_creation' => '2019-03-01', 'activite_principale' => '62.02A', 'tranche_effectif_salarie' => '03',
        'siege' => ['numero_voie' => '', 'type_voie' => '', 'libelle_voie' => $voie, 'code_postal' => $cp, 'libelle_commune' => $ville,
            'siret' => demo_siren($n) . '00012', 'activite_principale' => '62.02A'],
        'dirigeants' => [['type_dirigeant' => 'personne physique', 'prenoms' => $prenom, 'nom' => $nomd, 'qualite' => $qualite,
            'date_de_naissance' => '1984-05']],
        'finances' => [], 'complements' => []];
    return [
        $e(101, 'ATELIER DUPAIN', '5710', '12 RUE DES EXEMPLES', '75011', 'PARIS', 'Paul', 'DUPAIN', 'Président'),
        $e(102, 'VERGER DES TROIS POMMES', '5499', '4 CHEMIN DU VERGER', '69003', 'LYON', 'Lucie', 'POMMIER', 'Gérant'),
        $e(103, 'STUDIO PIXEL FICTIF', '5720', '8 AVENUE IMAGINAIRE', '33000', 'BORDEAUX', 'Hugo', 'PIXEL', 'Président'),
        $e(104, 'TRANSPORTS FILANTS', '5499', '27 ROUTE DE LA DEMO', '44000', 'NANTES', 'Nadia', 'ROULIER', 'Gérant'),
        $e(105, 'BOULANGERIE DU MOULIN BLEU', '5498', '1 PLACE DU MOULIN', '67000', 'STRASBOURG', 'Marc', 'FARINE', 'Gérant'),
    ];
}

/** Réponse simulée du répertoire pour une adresse de l'API (paramètre q : nom ou SIREN). */
function demo_registre_reponse(string $url): array
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
    $cherche = mb_strtoupper(sans_accents(trim((string)($q['q'] ?? ''))));
    $res = array_values(array_filter(demo_registre(), fn($e) => $cherche !== ''
        && ($e['siren'] === $cherche || str_contains(sans_accents($e['nom_complet']), $cherche))));
    return ['results' => $res, 'total_results' => count($res)];
}

/** Supprime le contenu d'un dossier de données de démo (jamais un autre dossier). */
function demo_vider(string $dossier): void
{
    if (!is_dir($dossier) || !str_contains(basename($dossier), 'demo')) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}

/** Remplit data-demo/ au premier lancement (ou à nouveau avec $forcer). Ne fait rien hors du mode démo. */
function demo_initialiser(bool $forcer = false): bool
{
    if (!MODE_DEMO || (!$forcer && is_file(chemin_data('comptes.json')))) {
        return false;
    }
    if ($forcer) {
        demo_vider(DATA);
    }
    sous_verrou(function () {
        $auj = aujourdhui();
        $an = (int)date('Y');
        ecrire_json(chemin_data('comptes.json'), [DEMO_IDENTIFIANT => ['nom' => DEMO_NOM, 'hash' => password_hash('demo', PASSWORD_DEFAULT)]]);
        cle_secrete();
        ecrire_json(chemin_data('parametres.json'), ['Responsables' => DEMO_NOM . "\nThomas Garnier", 'DelaiSansActiviteJours' => '15',
            'RelanceSuivanteJours' => '7']);
        ecrire_json(chemin_data('lettres-cabinet.json'), ['fonction' => 'expert-comptable', 'couverture' => 'la France métropolitaine',
            'mediateur' => 'Médiateur fictif de la consommation (exemple)']);

        // Clients du cabinet (comme s'ils venaient de Pennylane) : tous inventés.
        $c = fn(string $id, string $nom, string $type, string $forme, string $statut, string $contact, string $email, string $tel, string $ville,
            string $cp, string $activite, int $n) => ['id' => $id, 'nom' => $nom, 'type' => $type, 'forme' => $forme, 'statut' => $statut,
            'contact' => $contact, 'email' => $email, 'telephone' => $tel, 'ville' => $ville, 'cp' => $cp, 'adresse' => "$n rue de l'Exemple",
            'activite' => $activite, 'siren' => $type === 'PARTICULIER' ? '' : demo_siren(200 + $n), 'pennylane' => '', 'collaborateur' => DEMO_NOM,
            'source' => 'Pennylane'];
        $clients = [
            $c('PL-9001', 'ATELIER DUPAIN', 'SOCIETE', 'SAS', 'ACTIF', 'Paul Dupain', 'paul.dupain@exemple.test', '06 00 00 00 01', 'Paris', '75011', 'Boulangerie artisanale', 1),
            $c('PL-9002', 'VERGER DES TROIS POMMES', 'SOCIETE', 'SARL', 'ACTIF', 'Lucie Pommier', 'lucie@verger-exemple.test', '06 00 00 00 02', 'Lyon', '69003', 'Production de fruits', 2),
            $c('PL-9003', 'STUDIO PIXEL FICTIF', 'SOCIETE', 'SASU', 'ACTIF', 'Hugo Pixel', 'hugo@studio-pixel.test', '06 00 00 00 03', 'Bordeaux', '33000', 'Conception graphique', 3),
            $c('PL-9004', 'TRANSPORTS FILANTS', 'SOCIETE', 'SARL', 'ONBOARDING', 'Nadia Roulier', 'nadia@transports-filants.test', '06 00 00 00 04', 'Nantes', '44000', 'Transport routier', 4),
            $c('PL-9005', 'DR ÉLODIE EXEMPLE', 'EI', 'Entrepreneur individuel', 'ACTIF', 'Élodie Exemple', 'elodie@cabinet-medical.test', '06 00 00 00 05', 'Lille', '59000', 'Médecin généraliste', 5),
            $c('PL-9006', 'LMNP DURAND', 'LMNP', 'Entrepreneur individuel', 'ACTIF', 'Jean Durand', 'jean.durand@exemple.test', '06 00 00 00 06', 'Rennes', '35000', 'Location meublée', 6),
            $c('PL-9007', 'LES SAVEURS IMAGINAIRES', 'SOCIETE', 'SAS', 'ACTIF', 'Inès Gourmet', 'ines@saveurs-imaginaires.test', '06 00 00 00 07', 'Marseille', '13001', 'Restauration', 7),
            $c('PL-9008', 'ASSOCIATION LES JARDINS DE DEMO', 'ASSOCIATION', 'Association', 'ACTIF', 'Rémi Bêche', 'contact@jardins-demo.test', '', 'Tours', '37000', 'Jardins partagés', 8),
            $c('PL-9009', 'NOVA CONSEIL EXEMPLE', 'SOCIETE', 'SAS', 'ONBOARDING', 'Sarah Nova', 'sarah@nova-exemple.test', '06 00 00 00 09', 'Toulouse', '31000', 'Conseil en organisation', 9),
            $c('PL-9010', 'BOULANGERIE DU MOULIN BLEU', 'SOCIETE', 'EURL', 'ACTIF', 'Marc Farine', 'marc@moulin-bleu.test', '06 00 00 00 10', 'Strasbourg', '67000', 'Boulangerie', 10),
        ];
        ecrire_json(chemin_data('clients.json'), ['maj' => $auj, 'clients' => $clients]);

        // Dossiers SharePoint simulés (liens d'exemple, jamais ouverts en démo)
        $noms = ['ATELIER DUPAIN', 'BOULANGERIE DU MOULIN BLEU', 'DR ELODIE EXEMPLE', 'LES SAVEURS IMAGINAIRES', 'LMNP DURAND', 'STUDIO PIXEL FICTIF',
            'VERGER DES TROIS POMMES'];
        $annees = [];
        foreach ($noms as $i => $n) {
            $annees[$n] = array_map('strval', $i % 3 === 0 ? [$an - 2, $an - 1, $an] : [$an - 1, $an]);
        }
        dossiers_reseau_enregistrer($noms, [($an - 1) . '/ATELIER DUPAIN', "$an/ATELIER DUPAIN", "$an/STUDIO PIXEL FICTIF"],
            ['ATELIER DUPAIN', 'STUDIO PIXEL FICTIF', 'VERGER DES TROIS POMMES'], $annees);

        // Prospects, créés par le vrai registre (mêmes contrôles que l'interface), puis répartis dans le pipeline
        $reg = new Registre();
        $p = function (array $d, string $etape = 'A_QUALIFIER', ?int $relance = null, array $honoraires = []) use ($reg, $auj) {
            $pid = $reg->creer($d)['id'];
            if ($etape !== 'A_QUALIFIER') {
                $reg->deplacer($pid, $etape);
            }
            if ($relance !== null) {
                $reg->planifier_relance($pid, plus_jours($auj, $relance), 'Point d\'avancement');
            }
            if ($honoraires) {
                $reg->enregistrer_honoraires($pid, $honoraires, 'HT', 'saisi dans le CRM');
            }
            return $pid;
        };
        $mois = fn(float $m, string $lib = 'Tenue comptable') => ['libelle' => $lib, 'montant' => $m, 'unite' => 'mois'];
        $an_ = fn(float $m, string $lib) => ['libelle' => $lib, 'montant' => $m, 'unite' => 'an'];
        $contact = fn(string $civ, string $prenom, string $nom, string $email, string $role = 'REPRESENTANT_LEGAL', string $fonction = '') =>
            ['civilite' => $civ, 'prenom' => $prenom, 'nom' => $nom, 'email' => $email, 'telephone' => '06 00 00 01 ' . sprintf('%02d', strlen($nom)),
                'role' => $role, 'fonction' => $fonction];
        $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'CAFÉ DES EXEMPLES', 'forme' => 'SAS', 'origine' => 'CREATION',
            'en_creation' => true, 'forme_envisagee' => 'SAS', 'source' => 'SITE_INTERNET', 'responsable' => DEMO_NOM,
            'missions' => ['Présentation des comptes'], 'contacts' => [$contact('M', 'Bruno', 'Arabica', 'bruno@cafe-exemples.test', 'REPRESENTANT_LEGAL', 'Président')]],
            'A_QUALIFIER', 3);
        $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'MENUISERIE BOIS FICTIF', 'forme' => 'SARL', 'origine' => 'EXISTANTE',
            'source' => 'RECOMMANDATION', 'responsable' => 'Thomas Garnier', 'missions' => ['Établissement des comptes', 'Assistance aux déclarations fiscales (TVA)'],
            'contacts' => [$contact('M', 'Yves', 'Copeau', 'yves@menuiserie-fictif.test', 'REPRESENTANT_LEGAL', 'Gérant')]], 'A_QUALIFIER', -4);
        $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'EI', 'regime' => 'BNC', 'denomination' => 'KINÉ EXEMPLE', 'origine' => 'EXISTANTE',
            'source' => 'RESEAU', 'responsable' => DEMO_NOM, 'missions' => ['BNC (déclaration 2035)'],
            'contacts' => [$contact('MME', 'Aline', 'Massage', 'aline@kine-exemple.test', 'REPRESENTANT_LEGAL', 'Kinésithérapeute')]],
            'RDV_DECOUVERTE', 6, [$mois(120, 'Tenue et déclaration 2035')]);
        $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'GREEN LOGISTIQUE DEMO', 'forme' => 'SAS', 'origine' => 'REPRISE',
            'cabinet_precedent' => 'CABINET FICTIF & ASSOCIÉS', 'source' => 'CONFRERE', 'responsable' => 'Thomas Garnier',
            'missions' => ['Présentation des comptes'], 'contacts' => [$contact('MME', 'Zoé', 'Vert', 'zoe@green-logistique.test', 'REPRESENTANT_LEGAL', 'Présidente')]],
            'PROPOSITION_ENVOYEE', -2, [$mois(390), $an_(1200, 'Bilan et liasse fiscale')]);
        $p(['nature' => 'PARTICULIER', 'denomination' => 'Foyer Lemoine', 'source' => 'RECOMMANDATION', 'responsable' => DEMO_NOM, 'nb_personnes' => '3',
            'missions' => ['Déclaration de revenus (IR)', 'Revenus fonciers (2044)'],
            'contacts' => [$contact('MME', 'Anne', 'Lemoine', 'anne.lemoine@exemple.test', 'DECLARANT')]], 'PROPOSITION_ENVOYEE', 10,
            [$an_(240, 'Déclaration de revenus')]);
        $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'HORIZON SOFTWARE FICTIF', 'forme' => 'SASU', 'origine' => 'CREATION',
            'en_creation' => true, 'forme_envisagee' => 'SASU', 'source' => 'EVENEMENT', 'responsable' => DEMO_NOM,
            'missions' => ['Présentation des comptes', 'Assistance aux déclarations fiscales (TVA)'],
            'contacts' => [$contact('M', 'Karim', 'Code', 'karim@horizon-fictif.test', 'REPRESENTANT_LEGAL', 'Président')]], 'LETTRE_ENVOYEE', 2,
            [$mois(290), $an_(950, 'Bilan et liasse fiscale')]);
        $perdu = $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'IMMO PATRIMOINE DEMO', 'forme' => 'SCI', 'origine' => 'EXISTANTE',
            'source' => 'SITE_INTERNET', 'responsable' => 'Thomas Garnier', 'missions' => ['Établissement des comptes'],
            'contacts' => [$contact('M', 'Louis', 'Brique', 'louis@immo-demo.test')]], 'RDV_DECOUVERTE');
        $reg->marquer_perdu($perdu, 'PRIX', 'Budget inférieur au forfait proposé');
        $signe = $p(['nature' => 'PROFESSIONNEL', 'type_pro' => 'SOCIETE', 'denomination' => 'NOVA CONSEIL EXEMPLE', 'forme' => 'SAS', 'origine' => 'EXISTANTE',
            'source' => 'RECOMMANDATION', 'responsable' => DEMO_NOM, 'missions' => ['Présentation des comptes'],
            'contacts' => [$contact('MME', 'Sarah', 'Nova', 'sarah@nova-exemple.test', 'REPRESENTANT_LEGAL', 'Présidente')]], 'LETTRE_ENVOYEE', null, [$mois(350)]);
        $reg->lettre_signee($signe, plus_jours($auj, -5));

        // Dates étalées pour un pipeline réaliste (création il y a quelques jours ou semaines)
        $reg->recharger();
        foreach ($reg->prospects as $i => &$x) {
            $recul = 4 + 6 * $i;
            $x['cree_le'] = plus_jours($auj, -$recul);
            foreach ($x['historique'] as $k => &$h) {
                $h['le'] = plus_jours($auj, -$recul + min($k * 2, $recul - 1)) . substr($h['le'], 10);
            }
            unset($h);
            $x['derniere_activite'] = substr(end($x['historique'])['le'], 0, 10);
        }
        unset($x);
        $reg->enregistrer();

        // Quelques lettres de mission, générées par le vrai moteur à partir des modèles fictifs
        $base = ['interlocuteur' => DEMO_NOM, 'fonction' => 'expert-comptable', 'couverture' => 'la France métropolitaine',
            'mediateur' => 'Médiateur fictif de la consommation (exemple)', 'date_lettre' => $auj];
        lettres_generer($base + ['modele' => 'PRESENTATION', 'type' => 'SOCIETE', 'source' => 'client', 'ref' => 'PL-9001', 'denomination' => 'Atelier Dupain',
            'forme' => 'SAS', 'civilite' => 'M', 'prenom' => 'Paul', 'nom' => 'Dupain', 'adresse' => '12 rue des Exemples', 'cp' => '75011', 'ville' => 'Paris',
            'activite' => 'boulangerie artisanale', 'salaries' => '3', 'exercice_ouvert' => "$an-01-01", 'forfait_mensuel' => '320',
            'forfait_comptes' => '1400', 'forfait_depot' => '90', 'minimum' => '2500', 'qualite' => 'Président'], DEMO_NOM);
        lettres_generer($base + ['modele' => 'IR', 'type' => 'PARTICULIER', 'civilite' => 'MME', 'prenom' => 'Anne', 'nom' => 'Lemoine',
            'adresse' => '5 allée des Tilleuls', 'cp' => '35000', 'ville' => 'Rennes', 'annee' => (string)($an - 1), 'composition' => 'un couple et un enfant',
            'revenus' => ['salaires', 'revenus fonciers'], 'forfait_ir' => '240'], DEMO_NOM);
        lettres_generer($base + ['modele' => 'BNC', 'type' => 'EI', 'civilite' => 'MME', 'prenom' => 'Aline', 'nom' => 'Massage',
            'adresse' => '3 place de la Santé', 'cp' => '59000', 'ville' => 'Lille', 'profession' => 'masseur-kinésithérapeute', 'annee' => (string)$an,
            'forfait_mensuel' => '120', 'forfait_liasse' => '600'], DEMO_NOM);
    });
    return true;
}
