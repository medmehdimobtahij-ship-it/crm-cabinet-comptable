<?php
/**
 * Mails de relance (modèles par étape, modifiables dans Paramètres), répertoire officiel des entreprises (API publique
 * recherche-entreprises.api.gouv.fr, sans clé) et liste des clients (Pennylane importé + clients signés dans le CRM).
 * (Première version du CRM écrite en Python, portée ensuite en PHP pour l'hébergement mutualisé.)
 */
declare(strict_types=1);

// ---------- Relances ----------
const MODELES_RELANCE = [
    'A_QUALIFIER' => ['Suite à notre échange',
        "Je me permets de revenir vers vous suite à notre premier échange.\n\nSeriez-vous disponible pour un rendez-vous de découverte ? Ce serait l’occasion de faire le point sur votre situation et vos besoins, et de vous présenter notre accompagnement."],
    'RDV_DECOUVERTE' => ['Suite à notre rendez-vous',
        "Je reviens vers vous suite à notre rendez-vous.\n\nAvez-vous pu avancer dans votre réflexion ? Je peux vous adresser une proposition d’accompagnement dès que vous le souhaitez."],
    'PROPOSITION_ENVOYEE' => ['Notre proposition d’accompagnement',
        "Je me permets de revenir vers vous concernant la proposition que je vous ai adressée.\n\nAvez-vous pu en prendre connaissance ? Je reste disponible pour en discuter ou l’ajuster à vos besoins."],
    'LETTRE_ENVOYEE' => ['Lettre de mission',
        "Je reviens vers vous concernant la lettre de mission que je vous ai transmise.\n\nDès réception de la version signée, nous pourrons démarrer notre collaboration. N’hésitez pas si vous avez la moindre question."],
];

/** [étape => [objet, texte]] : texte modifié dans Paramètres, sinon texte standard. */
function relances_modeles(): array
{
    $perso = lire_json(chemin_data('relances.json'), []);
    $out = [];
    foreach (MODELES_RELANCE as $e => $m) {
        $out[$e] = (isset($perso[$e]) && is_array($perso[$e]) && count($perso[$e]) === 2) ? array_values($perso[$e]) : $m;
    }
    return $out;
}

/** Ne garde que les textes différents du standard. */
function relances_enregistrer(array $m): void
{
    $perso = [];
    foreach ($m as $e => $v) {
        if (isset(MODELES_RELANCE[$e]) && array_values($v) !== MODELES_RELANCE[$e]) {
            $perso[$e] = array_values($v);
        }
    }
    ecrire_json(chemin_data('relances.json'), $perso);
}

/** [objet, texte] du mail de relance : formule d'appel avec le contact, texte de l'étape, formule de fin. */
function mail_relance(array $p, array $contact = []): array
{
    $textes = relances_modeles();
    [$objet, $corps] = $textes[$p['etape'] ?? ''] ?? $textes['A_QUALIFIER'];
    $civilite = ['M' => 'Monsieur', 'MME' => 'Madame'][$contact['civilite'] ?? ''] ?? '';
    $nom = mb_strtoupper($contact['nom'] ?? '');
    $salut = ($civilite && $nom) ? trim("Bonjour $civilite $nom") : 'Bonjour';
    $den = $p['denomination'] ?? '';
    return [$den !== '' ? "$objet – $den" : $objet, "$salut,\n\n$corps\n\nBien à vous,"];
}

// ---------- Répertoire officiel des entreprises ----------
const FORMES_JURIDIQUES = ['5710' => 'SAS', '5720' => 'SASU', '5499' => 'SARL', '5498' => 'EURL', '1000' => 'Entrepreneur individuel',
    '6540' => 'SCI', '5599' => 'SA', '5202' => 'SNC', '9220' => 'Association', '6599' => 'Société civile', '5306' => 'SELARL', '5785' => 'SELAS'];

/** GET JSON (8 s max) ; null si le service ne répond pas. */
function http_json(string $url, int $delai = 8): ?array
{
    if (MODE_DEMO) {  // démo hors ligne : répertoire simulé (sociétés inventées, src/demo.php)
        return demo_registre_reponse($url);
    }
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $delai, CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'CRMCabinet/1.0']);
    $r = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    if ($r === false || $code >= 400) {
        return null;
    }
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : null;
}

/** [{nom, siren, forme, ville, ferme}] ; [] si moins de 3 lettres ou service injoignable. */
function entreprises_chercher(?string $texte, int $nb = 6): array
{
    $texte = trim((string)$texte);
    if (mb_strlen($texte) < 3) {
        return [];
    }
    $d = http_json('https://recherche-entreprises.api.gouv.fr/search?per_page=8&q=' . rawurlencode($texte)) ?? [];
    $out = [];
    foreach (array_slice($d['results'] ?? [], 0, $nb) as $e) {
        $out[] = ['nom' => $e['nom_complet'] ?? $e['nom_raison_sociale'] ?? $e['siren'] ?? '', 'siren' => $e['siren'] ?? '',
            'forme' => FORMES_JURIDIQUES[(string)($e['nature_juridique'] ?? '')] ?? '',
            'ville' => $e['siege']['libelle_commune'] ?? '', 'ferme' => ($e['etat_administratif'] ?? '') === 'C'];
    }
    return $out;
}

/** Fiche complète du répertoire pour un SIREN (siège, dirigeants), ou null. */
function entreprises_fiche(?string $siren): ?array
{
    $s = str_replace(' ', '', (string)$siren);
    if (strlen($s) !== 9 || !ctype_digit($s)) {
        return null;
    }
    $d = http_json('https://recherche-entreprises.api.gouv.fr/search?per_page=1&q=' . $s) ?? [];
    foreach ($d['results'] ?? [] as $e) {
        if (($e['siren'] ?? '') === $s) {
            return $e;
        }
    }
    return null;
}

/**
 * Fiche d'une entreprise pour pré-remplir le CRM : siège, forme, date de création, dirigeants (personnes physiques).
 * Source : recherche-entreprises.api.gouv.fr, qui reprend l'INSEE et le registre national des entreprises (INPI).
 */
function entreprises_resume(?string $siren): ?array
{
    $e = entreprises_fiche($siren);
    if (!$e) {
        return null;
    }
    $s = $e['siege'] ?? [];
    $voie = implode(' ', array_filter([$s['numero_voie'] ?? '', $s['indice_repetition'] ?? '', $s['type_voie'] ?? '', $s['libelle_voie'] ?? '']));
    $titre = fn($t) => mb_convert_case(mb_strtolower(trim((string)$t)), MB_CASE_TITLE);
    $dirigeants = [];
    foreach ($e['dirigeants'] ?? [] as $x) {
        if (($x['type_dirigeant'] ?? '') !== 'personne physique' || trim((string)($x['nom'] ?? '')) === '') {
            continue;  // sociétés dirigeantes, commissaires aux comptes : pas un contact
        }
        $q = (string)($x['qualite'] ?? '');
        $cle = mb_strtolower(sans_accents($q));
        $role = preg_match('/president|gerant|directeur general|dirigeant|exploitant|entrepreneur/', $cle) && !str_contains($cle, 'vice')
            ? 'REPRESENTANT_LEGAL' : (str_contains($cle, 'associe') ? 'ASSOCIE' : 'AUTRE');
        $ne = (string)($x['date_de_naissance'] ?? '');
        $dirigeants[] = ['prenom' => $titre(explode(' ', trim((string)($x['prenoms'] ?? '')))[0] ?? ''), 'nom' => mb_strtoupper(trim((string)$x['nom'])),
            'fonction' => $q, 'role' => $role, 'ne_le' => preg_match('/^(\d{4})-(\d{2})$/', $ne, $m) ? "$m[2]/$m[1]" : (string)($x['annee_de_naissance'] ?? '')];
    }
    $finances = is_array($e['finances'] ?? null) ? $e['finances'] : [];
    krsort($finances, SORT_STRING);
    $derniere = array_key_first($finances);
    $naf = (string)($e['activite_principale'] ?? $s['activite_principale'] ?? '');
    $complement = trim((string)($s['complement_adresse'] ?? ''));
    return ['siren' => $e['siren'], 'nom' => $e['nom_complet'] ?? $e['nom_raison_sociale'] ?? '', 'forme' => FORMES_JURIDIQUES[(string)($e['nature_juridique'] ?? '')] ?? '',
        'adresse' => $titre($voie) . ($complement !== '' ? ', ' . $titre($complement) : ''), 'cp' => $s['code_postal'] ?? '', 'ville' => $s['libelle_commune'] ?? '',
        'creee_le' => $e['date_creation'] ?? '', 'ferme' => ($e['etat_administratif'] ?? '') === 'C', 'dirigeants' => $dirigeants,
        'registre' => [
            'siret' => (string)($s['siret'] ?? ''), 'tva' => (string)(($e['tva'][0] ?? '') ?: tva_intracom((string)$e['siren'])),
            'naf' => $naf, 'activite' => libelle_naf($naf), 'creee_le' => (string)($e['date_creation'] ?? ''),
            'debut_activite' => (string)($s['date_debut_activite'] ?? ''),
            'effectif' => TRANCHES_EFFECTIF[(string)($e['tranche_effectif_salarie'] ?? '')] ?? '',
            'nom_commercial' => implode(', ', array_filter(array_merge([(string)($s['nom_commercial'] ?? '')], (array)($s['liste_enseignes'] ?? []), [(string)($e['sigle'] ?? '')]))),
            'convention' => implode(', ', array_map(fn($c) => "IDCC $c", (array)($e['complements']['liste_idcc'] ?? []))),
            'etablissements' => (string)($e['nombre_etablissements_ouverts'] ?? ''),
            'annee_finances' => (string)($derniere ?? ''), 'ca' => (string)($finances[$derniere]['ca'] ?? ''),
            'resultat' => (string)($finances[$derniere]['resultat_net'] ?? ''),
            'maj' => aujourdhui()]];
}

/** Numéro de TVA intracommunautaire français, calculé depuis le SIREN (clé = (12 + 3 × (SIREN mod 97)) mod 97). */
function tva_intracom(string $siren): string
{
    return preg_match('/^\d{9}$/', $siren) ? sprintf('FR%02d%s', (12 + 3 * ((int)$siren % 97)) % 97, $siren) : '';
}

/** Ce qu'on garde du registre dans la fiche : chaînes courtes, clés connues seulement. */
function registre_a_garder($r): array
{
    $out = [];
    foreach (['siret', 'tva', 'naf', 'activite', 'creee_le', 'debut_activite', 'effectif', 'nom_commercial', 'convention', 'etablissements',
                 'annee_finances', 'ca', 'resultat', 'maj'] as $k) {
        if (is_array($r) && is_scalar($r[$k] ?? null) && trim((string)$r[$k]) !== '') {
            $out[$k] = mb_substr(trim((string)$r[$k]), 0, 200);
        }
    }
    return $out;
}

const TRANCHES_EFFECTIF = ['NN' => 'Pas de salarié déclaré', '00' => '0 salarié', '01' => '1 ou 2 salariés', '02' => '3 à 5 salariés', '03' => '6 à 9 salariés',
    '11' => '10 à 19 salariés', '12' => '20 à 49 salariés', '21' => '50 à 99 salariés', '22' => '100 à 199 salariés', '31' => '200 à 249 salariés',
    '32' => '250 à 499 salariés', '41' => '500 à 999 salariés', '42' => '1 000 à 1 999 salariés', '51' => '2 000 à 4 999 salariés',
    '52' => '5 000 à 9 999 salariés', '53' => '10 000 salariés et plus'];

/** Libellé de l'activité (code NAF) : précis pour les activités courantes du cabinet, sinon celui de la division. */
function libelle_naf(string $code): string
{
    if ($code === '') {
        return '';
    }
    $precis = ['70.22Z' => 'Conseil pour les affaires et autres conseils de gestion', '70.10Z' => 'Activités des sièges sociaux',
        '64.20Z' => 'Activités des sociétés holding', '68.20A' => 'Location de logements', '68.20B' => 'Location de terrains et d\'autres biens immobiliers',
        '68.10Z' => 'Activités des marchands de biens immobiliers', '68.31Z' => 'Agences immobilières', '69.20Z' => 'Activités comptables',
        '69.10Z' => 'Activités juridiques', '62.01Z' => 'Programmation informatique', '62.02A' => 'Conseil en systèmes et logiciels informatiques',
        '86.21Z' => 'Activité des médecins généralistes', '86.22C' => 'Autres activités des médecins spécialistes', '86.23Z' => 'Pratique dentaire',
        '86.90E' => 'Activités des professionnels de la rééducation, de l\'appareillage et des pédicures-podologues',
        '47.11B' => 'Commerce d\'alimentation générale', '47.91B' => 'Vente à distance sur catalogue spécialisé', '56.10A' => 'Restauration traditionnelle',
        '56.10C' => 'Restauration de type rapide', '55.20Z' => 'Hébergement touristique et autre hébergement de courte durée',
        '71.11Z' => 'Activités d\'architecture', '71.12B' => 'Ingénierie, études techniques', '73.11Z' => 'Activités des agences de publicité',
        '74.90B' => 'Activités spécialisées, scientifiques et techniques diverses', '85.59A' => 'Formation continue d\'adultes',
        '90.01Z' => 'Arts du spectacle vivant', '49.32Z' => 'Transports de voyageurs par taxis', '49.41A' => 'Transports routiers de fret interurbains',
        '41.20A' => 'Construction de maisons individuelles', '96.02A' => 'Coiffure', '93.13Z' => 'Activités des centres de culture physique',
        '66.19B' => 'Autres activités auxiliaires de services financiers', '66.22Z' => 'Activités des agents et courtiers d\'assurances'];
    $divisions = ['01' => 'Culture et production animale', '02' => 'Sylviculture et exploitation forestière', '03' => 'Pêche et aquaculture',
        '05' => 'Extraction de houille et de lignite', '06' => 'Extraction d\'hydrocarbures', '07' => 'Extraction de minerais métalliques',
        '08' => 'Autres industries extractives', '09' => 'Services de soutien aux industries extractives', '10' => 'Industries alimentaires',
        '11' => 'Fabrication de boissons', '12' => 'Fabrication de produits à base de tabac', '13' => 'Fabrication de textiles', '14' => 'Industrie de l\'habillement',
        '15' => 'Industrie du cuir et de la chaussure', '16' => 'Travail du bois', '17' => 'Industrie du papier et du carton', '18' => 'Imprimerie',
        '19' => 'Cokéfaction et raffinage', '20' => 'Industrie chimique', '21' => 'Industrie pharmaceutique', '22' => 'Produits en caoutchouc et en plastique',
        '23' => 'Autres produits minéraux non métalliques', '24' => 'Métallurgie', '25' => 'Fabrication de produits métalliques',
        '26' => 'Produits informatiques, électroniques et optiques', '27' => 'Fabrication d\'équipements électriques', '28' => 'Fabrication de machines et équipements',
        '29' => 'Industrie automobile', '30' => 'Autres matériels de transport', '31' => 'Fabrication de meubles', '32' => 'Autres industries manufacturières',
        '33' => 'Réparation et installation de machines', '35' => 'Électricité, gaz, vapeur et air conditionné', '36' => 'Captage, traitement et distribution d\'eau',
        '37' => 'Collecte et traitement des eaux usées', '38' => 'Collecte et traitement des déchets', '39' => 'Dépollution',
        '41' => 'Construction de bâtiments', '42' => 'Génie civil', '43' => 'Travaux de construction spécialisés',
        '45' => 'Commerce et réparation d\'automobiles et de motocycles', '46' => 'Commerce de gros', '47' => 'Commerce de détail',
        '49' => 'Transports terrestres', '50' => 'Transports par eau', '51' => 'Transports aériens', '52' => 'Entreposage et services auxiliaires des transports',
        '53' => 'Activités de poste et de courrier', '55' => 'Hébergement', '56' => 'Restauration', '58' => 'Édition',
        '59' => 'Production de films, vidéo, télévision ; édition musicale', '60' => 'Programmation et diffusion', '61' => 'Télécommunications',
        '62' => 'Programmation, conseil et autres activités informatiques', '63' => 'Services d\'information', '64' => 'Activités des services financiers',
        '65' => 'Assurance', '66' => 'Activités auxiliaires de services financiers et d\'assurance', '68' => 'Activités immobilières',
        '69' => 'Activités juridiques et comptables', '70' => 'Sièges sociaux ; conseil de gestion', '71' => 'Architecture et ingénierie',
        '72' => 'Recherche-développement scientifique', '73' => 'Publicité et études de marché', '74' => 'Autres activités spécialisées, scientifiques et techniques',
        '75' => 'Activités vétérinaires', '77' => 'Location et location-bail', '78' => 'Activités liées à l\'emploi', '79' => 'Agences de voyage, voyagistes',
        '80' => 'Enquêtes et sécurité', '81' => 'Services relatifs aux bâtiments et aménagement paysager', '82' => 'Services administratifs et de soutien aux entreprises',
        '84' => 'Administration publique', '85' => 'Enseignement', '86' => 'Activités pour la santé humaine', '87' => 'Hébergement médico-social et social',
        '88' => 'Action sociale sans hébergement', '90' => 'Activités créatives, artistiques et de spectacle', '91' => 'Bibliothèques, archives, musées',
        '92' => 'Jeux de hasard et d\'argent', '93' => 'Activités sportives, récréatives et de loisirs', '94' => 'Organisations associatives',
        '95' => 'Réparation d\'ordinateurs et de biens personnels', '96' => 'Autres services personnels', '97' => 'Ménages employeurs de personnel domestique',
        '98' => 'Activités des ménages pour usage propre', '99' => 'Organisations extraterritoriales'];
    return $precis[$code] ?? $divisions[substr($code, 0, 2)] ?? '';
}

// ---------- Clients ----------
const TYPES_CLIENT = ['SOCIETE' => 'Société', 'EI' => 'Entreprise individuelle', 'LMNP' => 'LMNP', 'ASSOCIATION' => 'Association',
    'PARTICULIER' => 'Particulier'];
const PIECES = [
    'SOCIETE' => ['Pièce d’identité du dirigeant', 'Statuts à jour', 'Extrait Kbis de moins de 3 mois', 'RIB de la société',
        'Bénéficiaires effectifs', 'Accès bancaires / relevés'],
    'EI' => ['Pièce d’identité', 'Extrait RNE / avis de situation INSEE', 'RIB professionnel', 'Accès bancaires / relevés'],
    'LMNP' => ['Pièce d’identité', 'Acte d’acquisition du bien', 'Numéro SIRET de l’activité', 'RIB', 'Tableau d’amortissement du prêt'],
    'PARTICULIER' => ['Pièce d’identité', 'Dernier avis d’impôt', 'RIB'],
    'ASSOCIATION' => ['Pièce d’identité du président', 'Statuts', 'Récépissé de déclaration en préfecture', 'RIB'],
];
// Arborescence des dossiers clients dans SharePoint (synchronisée par OneDrive sur les postes) : réglable (config.json), valeurs d'exemple.
define('SP_BIBLIOTHEQUE', reglage('sharepoint', 'https://exemple.sharepoint.com/sites/cabinet/Documents partages'));
define('SP_RACINE', reglage('sharepoint_dossiers', 'Dossiers'));              // dossier qui regroupe tout ce qui suit
define('SHAREPOINT_DOSSIERS', SP_BIBLIOTHEQUE . '/' . SP_RACINE);
define('RACINE_LETTRES', reglage('dossier_lettres', 'Lettres de mission'));    // Lettres de mission › CLIENT
define('RACINE_COMPTA', reglage('dossier_compta', 'Comptabilite'));            // Comptabilite › CLIENT › année
define('RACINE_JURIDIQUE', reglage('dossier_juridique', 'Juridique annuel'));  // Juridique annuel › année › CLIENT
define('RACINE_SOCIETE', reglage('dossier_permanent', 'Permanent'));           // dossier permanent du client : Kbis, statuts, mandats
define('RACINE_MODELE', reglage('dossier_modele', '0. Modele'));               // Comptabilite › 0. Modele › année : copié pour chaque client
const MOTS_NEUTRES = ['SAS', 'SASU', 'SARL', 'SARLU', 'SELARLU', 'EURL', 'SCI', 'SA', 'LMNP'];
const MOTS_GENERIQUES = ['HOLDING', 'CONSULTING', 'ADVISORY', 'CONSEIL', 'GROUP', 'DISTRIB', 'DISTRIBUTION', 'CAPITAL', 'FRANCE',
    'SERVICES', 'INVEST', 'IMMOBILIER', 'PATRIMOINE', 'SOLUTIONS', 'PARTNERS', 'CONCEPT', 'FINANCE'];

/**
 * Noms des dossiers clients (bibliothèque › Dossiers › Comptabilite), lus dans SharePoint avec le compte Microsoft
 * de la personne connectée (sharepoint.php) et gardés dans data/dossiers-reseau.json.
 */
function dossiers_reseau(): array
{
    $d = lire_json(chemin_data('dossiers-reseau.json'), []);
    $noms = array_values(array_filter($d['dossiers'] ?? [], fn($n) => is_string($n) && $n !== '' && !str_starts_with($n, '0.') && $n !== RACINE_MODELE));
    sort($noms, SORT_STRING);
    return $noms;
}

/** Dossiers juridiques, « année/NOM » (Juridique annuel › 2025 › ATELIER DUPAIN). */
function dossiers_juridiques(): array
{
    $d = lire_json(chemin_data('dossiers-reseau.json'), []);
    return array_values(array_filter($d['juridique'] ?? [], fn($n) => is_string($n) && preg_match('#^\d{4}/[^/]+$#', $n)));
}

/** Dossiers juridiques du client, du plus récent au plus ancien : [[année, « année/NOM »]]. */
function juridiques_du_client(string $nom, array $juridiques): array
{
    $par_annee = [];
    foreach ($juridiques as $j) {
        [$annee, $n] = explode('/', $j, 2);
        $par_annee[$annee][] = $n;
    }
    krsort($par_annee, SORT_STRING);
    $out = [];
    foreach ($par_annee as $annee => $noms) {
        sort($noms, SORT_STRING);
        $d = dossier_du_client($nom, $noms);
        if ($d !== '') {
            $out[] = ['annee' => (string)$annee, 'dossier' => "$annee/$d", 'url' => lien_sharepoint("$annee/$d", RACINE_JURIDIQUE)];
        }
    }
    return $out;
}

/** Dossiers permanents (Dossiers › Permanent). */
function dossiers_societe(): array
{
    $d = lire_json(chemin_data('dossiers-reseau.json'), []);
    return array_values(array_filter($d['societe'] ?? [], fn($n) => is_string($n) && nom_de_dossier_sur($n)));
}

/**
 * Années montrées pour chaque client (mêmes colonnes pour tous) : de la plus ancienne trouvée, au plus 4 ans en arrière, jusqu'à
 * l'année en cours ou la plus récente créée (une année ajoutée en avance, 2027, apparaît pour tous, et la suivante est proposée).
 */
function fenetre_annees(array $trouvees): array
{
    $a = (int)date('Y');
    $premiere = $a - 1;
    $derniere = $a;
    foreach ($trouvees as $x) {
        $premiere = min($premiere, max($a - 4, (int)$x));
        $derniere = max($derniere, min($a + 5, (int)$x));
    }
    return array_map('strval', range($premiere, $derniere));
}

/** Années du juridique annuel (Juridique annuel › année). */
function annees_juridique(): array
{
    return fenetre_annees(array_map(fn($j) => substr($j, 0, 4), dossiers_juridiques()));
}

/** Dossier Comptabilite synchronisé par OneDrive sur le PC où tourne le CRM (réglage « reseau ») ; '' s'il n'y est pas (hébergement). */
function chemin_compta_local(): string
{
    $base = reglage('reseau');
    return $base !== '' && is_dir($base) ? $base : '';
}

/**
 * Années de chaque dossier comptable : {NOM: [« 2025 », « 2026 »]} (Comptabilite › NOM › année). Lues dans SharePoint (liste gardée),
 * et, quand le CRM tourne sur un PC où OneDrive synchronise ce dossier, aussi directement sur le disque : à jour à chaque affichage.
 */
function annees_compta(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $d = lire_json(chemin_data('dossiers-reseau.json'), []);
    $out = is_array($d['compta_annees'] ?? null) ? $d['compta_annees'] : [];
    $base = chemin_compta_local();
    if ($base !== '') {
        foreach (dossiers_reseau() as $nom) {
            foreach (@scandir($base . DIRECTORY_SEPARATOR . $nom) ?: [] as $x) {
                if (preg_match('/^\d{4}$/', $x) && is_dir($base . DIRECTORY_SEPARATOR . $nom . DIRECTORY_SEPARATOR . $x)) {
                    $out[$nom][] = $x;
                }
            }
        }
        foreach ($out as $nom => $a) {
            $a = array_values(array_unique($a));
            sort($a, SORT_STRING);
            $out[$nom] = $a;
        }
    }
    return $memo = $out;
}

/** La liste gardée date d'avant la lecture des années : à relire tout de suite. */
function dossiers_reseau_sans_annees(): bool
{
    return !array_key_exists('compta_annees', lire_json(chemin_data('dossiers-reseau.json'), []));
}

/** Années des dossiers comptables montrées pour chaque client. */
function fenetre_compta(): array
{
    return fenetre_annees(array_merge([], ...array_values(annees_compta())));
}

/** Années qu'on peut créer : celles montrées, plus la suivante (« ＋ 2027 »). */
function annees_creables(array $fenetre): array
{
    return array_merge($fenetre, [(string)((int)end($fenetre) + 1)]);
}

/** Années du dossier comptable « NOM/2025 », pour l'ouverture dans l'Explorateur. */
function dossiers_compta_annees(): array
{
    $out = [];
    foreach (annees_compta() as $nom => $annees) {
        foreach ($annees as $a) {
            $out[] = "$nom/$a";
        }
    }
    return $out;
}

/** Nom proposé pour créer le dossier d'un client : sans accents, en majuscules, sans la partie entre parenthèses. */
function nom_nouveau_dossier(string $nom): string
{
    $n = trim(preg_replace('/\([^()]*\)/', ' ', $nom));
    return sans_accents_majuscules($n !== '' ? $n : $nom);
}

function dossiers_reseau_maj(): string
{
    return (string)(lire_json(chemin_data('dossiers-reseau.json'), [])['maj'] ?? '');
}

function dossiers_reseau_instant(): int
{
    return (int)(lire_json(chemin_data('dossiers-reseau.json'), [])['t'] ?? 0);
}

function nom_de_dossier_sur(string $n): bool
{
    return preg_match('/^[^\\\\\/:*?"<>|]{1,150}$/u', $n) === 1 && !str_contains($n, '..');
}

/** Listes lues dans SharePoint : noms simples seulement (jamais de chemin) ; juridique : « année/NOM » ; années : {NOM: [années]}. */
function dossiers_reseau_enregistrer(array $noms, array $juridiques = [], array $societe = [], array $compta_annees = []): int
{
    $propres = $jur = [];
    foreach ($noms as $n) {
        if (is_string($n) && nom_de_dossier_sur($n)) {
            $propres[$n] = true;
        }
    }
    foreach ($juridiques as $j) {
        $p = is_string($j) ? explode('/', $j, 2) : [];
        if (count($p) === 2 && preg_match('/^[0-9]{4}$/', $p[0]) && nom_de_dossier_sur($p[1])) {
            $jur[$j] = true;
        }
    }
    $propres = array_keys($propres);
    $jur = array_keys($jur);
    sort($propres, SORT_STRING);
    sort($jur, SORT_STRING);
    $annees = [];
    foreach ($propres as $n) {
        $a = array_values(array_unique(array_filter($compta_annees[$n] ?? [], fn($x) => is_string($x) && preg_match('/^[0-9]{4}$/', $x))));
        if ($a) {
            sort($a, SORT_STRING);
            $annees[$n] = $a;
        }
    }
    ecrire_json(chemin_data('dossiers-reseau.json'), ['maj' => maintenant_minutes(), 't' => time(), 'dossiers' => array_slice($propres, 0, 20000),
        'juridique' => array_slice($jur, 0, 20000), 'compta_annees' => (object)$annees,
        'societe' => array_values(array_filter(array_unique($societe), fn($n) => is_string($n) && nom_de_dossier_sur($n)))], false);
    return count($propres);
}

function lien_sharepoint(string $dossier, string $racine = RACINE_COMPTA): string
{
    return str_replace('%3A', ':', implode('/', array_map('rawurlencode', explode('/', SHAREPOINT_DOSSIERS . "/$racine/$dossier"))));
}

/** Mots significatifs d'un nom (sans accents, ponctuation ni forme juridique). */
function mots(string $nom): array
{
    $plat = strtoupper(sans_accents($nom));
    $out = [];
    foreach (preg_split('/[^A-Z0-9]+/', $plat) as $m) {
        if ($m !== '' && !in_array($m, MOTS_NEUTRES, true)) {
            $out[$m] = true;
        }
    }
    return array_keys($out);
}

function cle_mots(array $mots): string
{
    sort($mots, SORT_STRING);
    return implode('', $mots);
}

/** Nom du dossier réseau du client ; essaie le nom complet, puis le nom entre parenthèses
 *  (« Studio Pixel Conseil (PIXEL) » -> « STUDIO PIXEL »). Chaîne vide si rien de sûr. */
function dossier_du_client(string $nom, array $dossiers): string
{
    preg_match_all('/\(([^()]+)\)/', $nom, $m);
    foreach (array_merge([$nom], $m[1]) as $essai) {
        $d = dossier_du_nom($essai, $dossiers);
        if ($d !== '') {
            return $d;
        }
    }
    return '';
}

/** Même nom à l'ordre et à la ponctuation près, ou nom contenu dans l'autre (« SELARLU MARTIN AVOCAT » -> « MARTIN AVOCAT ») ;
 *  à défaut, pour un LMNP seulement, un mot rare (≥ 5 lettres) présent dans un seul dossier « LMNP … ». */
function dossier_du_nom(string $nom, array $dossiers): string
{
    $m = mots($nom);
    $lmnp = str_contains(strtoupper($nom), 'LMNP');
    $compact = cle_mots($m);
    $serre = fn($t) => preg_replace('/[^A-Z0-9]/', '', strtoupper(sans_accents($t)));
    $meilleur = null;
    foreach ($dossiers as $d) {
        $md = mots($d);
        if (!$md) {
            continue;
        }
        $commun = array_intersect($md, $m);
        $inclus = count($commun) === count($md) || count($commun) === count($m);
        if (cle_mots($md) === $compact || $inclus || $serre($d) === $serre($nom)) {
            $note = [str_contains(strtoupper($d), 'LMNP') === $lmnp ? 1 : 0, count($commun)];
            if ($meilleur === null || $note > $meilleur[0]) {  // à égalité, le premier (ordre alphabétique) gagne
                $meilleur = [$note, $d];
            }
        }
    }
    if ($meilleur) {
        return $meilleur[1];
    }
    if (!$lmnp) {
        return '';
    }
    $frequence = [];
    foreach ($dossiers as $d) {
        foreach (mots($d) as $x) {
            $frequence[$x] = ($frequence[$x] ?? 0) + 1;
        }
    }
    $rares = array_values(array_filter($dossiers, function ($d) use ($frequence, $m) {
        if (!str_contains(strtoupper($d), 'LMNP')) {
            return false;
        }
        foreach (mots($d) as $x) {
            if (strlen($x) >= 5 && $frequence[$x] === 1 && in_array($x, $m, true) && !in_array($x, MOTS_GENERIQUES, true)) {
                return true;
            }
        }
        return false;
    }));
    return count($rares) === 1 ? $rares[0] : '';
}

/** Clients archivés : {id client => {le, par}} (data/clients-etats.json). Archivé = masqué des listes, rien n'est effacé. */
function clients_archives(): array
{
    return lire_json(chemin_data('clients-etats.json'), []);
}

function clients_archives_ecrire(array $a): void
{
    ecrire_json(chemin_data('clients-etats.json'), (object)$a);
}

/** [clients, date d'import] du fichier importé depuis Pennylane (data/clients.json ; absent = version vierge). */
function charger_pennylane(): array
{
    $d = lire_json(chemin_data('clients.json'), []);
    return [$d['clients'] ?? [], $d['maj'] ?? ''];
}

function client_du_crm(array $p): array
{
    $c = $p['contacts'][0] ?? [];
    $t = type_prospect($p);
    return ['id' => $p['id'], 'pid' => $p['id'], 'nom' => $p['denomination'], 'siren' => $p['siren'] ?? '', 'forme' => $p['forme'] ?? '',
        'type' => ($p['regime'] ?? '') === 'LMNP' ? 'LMNP' : $t, 'email' => $c['email'] ?? '', 'telephone' => $c['telephone'] ?? '',
        'contact' => trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')), 'responsable' => $p['responsable'] ?? '',
        'missions' => implode(', ', $p['missions'] ?? []), 'signee_le' => $p['signee_le'] ?? '',
        'statut' => ($p['onboarding'] ?? '') === 'TERMINE' ? 'ACTIF' : 'ONBOARDING', 'source' => 'CRM', 'qualite' => $c['fonction'] ?? '',
        'adresse' => $p['adresse'] ?? '', 'cp' => $p['cp'] ?? '', 'ville' => $p['ville'] ?? '',
        'cree_le' => $p['registre']['creee_le'] ?? '', 'activite' => trim(($p['registre']['naf'] ?? '') . ' ' . ($p['registre']['activite'] ?? ''))];
}

/**
 * Clients Pennylane + clients signés dans le CRM (rapprochés par SIREN ou par nom), avec le dossier réseau de chacun :
 * lien SharePoint (navigateur) et lien crm-dossier: (Explorateur, par le petit programme d'ouverture des dossiers).
 */
function tous_les_clients(array $prospects, bool $avec_dossiers = true): array
{
    [$pennylane] = charger_pennylane();
    $clients = $pennylane;
    $index = $noms = [];
    foreach ($clients as $i => $c) {
        if (!empty($c['siren'])) {
            $index[str_replace(' ', '', $c['siren'])] = $i;
        }
        $noms[mb_strtoupper($c['nom'])] = $i;
    }
    foreach ($prospects as $p) {
        if (($p['statut'] ?? '') !== 'CLIENT') {
            continue;
        }
        $crm = client_du_crm($p);
        $i = $index[str_replace(' ', '', (string)$crm['siren'])] ?? $noms[mb_strtoupper($crm['nom'])] ?? null;
        if ($i !== null) {  // déjà dans Pennylane : on garde ses informations, avec le statut et le lien du CRM
            foreach (['pid', 'statut', 'signee_le', 'missions'] as $k) {
                $clients[$i][$k] = $crm[$k];
            }
            $clients[$i]['source'] = 'Pennylane + CRM';
        } else {
            $clients[] = $crm;
        }
    }
    $tous = dossiers_reseau();  // dossiers comptables sans fiche : un client « SharePoint » chacun (la liste ne part jamais d'une page blanche)
    if ($tous) {
        $pris = [];
        foreach ($clients as $c) {
            $n = dossier_du_client((string)$c['nom'], $tous);
            if ($n !== '') {
                $pris[$n] = true;
            }
        }
        foreach ($tous as $n) {
            if (!isset($pris[$n])) {
                $clients[] = ['id' => 'SP-' . substr(md5($n), 0, 12), 'nom' => $n, 'siren' => '', 'type' => '', 'statut' => 'ACTIF', 'source' => 'SharePoint',
                    'contact' => '', 'email' => '', 'telephone' => '', 'pennylane' => '', 'forme' => '', 'ville' => ''];
            }
        }
    }
    $dossiers = $avec_dossiers ? $tous : [];
    $juridiques = $avec_dossiers ? dossiers_juridiques() : [];
    $societes = $avec_dossiers ? dossiers_societe() : [];
    $annees = annees_juridique();
    $par_dossier = $avec_dossiers ? annees_compta() : [];
    $fen_compta = fenetre_compta();
    $archives = clients_archives();
    foreach ($clients as &$c) {
        $a = $archives[(string)$c['id']] ?? (!empty($c['pid']) ? ($archives[(string)$c['pid']] ?? null) : null);  // client du CRM : aussi par sa fiche
        $c['archive'] = $a !== null;
        $c['archive_le'] = $a['le'] ?? '';
        $c['archive_par'] = $a['par'] ?? '';
        $nom = $dossiers ? dossier_du_client((string)$c['nom'], $dossiers) : '';
        $c['dossier'] = $nom;
        $c['dossier_url'] = $nom !== '' ? lien_sharepoint($nom) : '';
        $c['dossier_lien'] = $nom !== '' ? 'crm-dossier:' . rawurlencode($nom) : '';  // lien d'ouverture dans l'Explorateur (petit programme local, non fourni)
        $c['juridiques'] = $juridiques ? juridiques_du_client((string)$c['nom'], $juridiques) : [];
        $c['dossier_juridique'] = $c['juridiques'][0]['dossier'] ?? '';
        $c['dossier_juridique_url'] = $c['juridiques'][0]['url'] ?? '';
        $par_annee = array_column($c['juridiques'], null, 'annee');
        $c['juridique_annees'] = array_map(fn($a) => ['annee' => $a, 'dossier' => $par_annee[$a]['dossier'] ?? '', 'url' => $par_annee[$a]['url'] ?? ''], $annees);
        $c['juridique_anciennes'] = array_values(array_reverse(array_filter($c['juridiques'], fn($j) => $j['annee'] < $annees[0])));
        $c['juridique_prochaine'] = (string)((int)end($annees) + 1);
        $c['nom_juridique'] = $c['juridiques'] ? explode('/', $c['juridiques'][0]['dossier'], 2)[1] : '';  // même nom pour une nouvelle année
        // Années du dossier comptable (Comptabilite › NOM › 2025) : celles de toutes les fiches, et les plus anciennes du client à part
        $siennes = $nom !== '' ? ($par_dossier[$nom] ?? []) : [];
        $annee_compta = fn($a) => ['annee' => $a, 'dossier' => in_array($a, $siennes, true) ? "$nom/$a" : '',
            'url' => in_array($a, $siennes, true) ? lien_sharepoint("$nom/$a") : ''];
        $c['compta_annees'] = array_map($annee_compta, $fen_compta);
        $c['compta_anciennes'] = array_map($annee_compta, array_values(array_filter($siennes, fn($a) => $a < $fen_compta[0])));
        $c['compta_prochaine'] = (string)((int)end($fen_compta) + 1);
        $soc = $societes ? dossier_du_client((string)$c['nom'], $societes) : '';
        $c['dossier_societe'] = $soc;
        $c['dossier_societe_url'] = $soc !== '' ? lien_sharepoint($soc, RACINE_SOCIETE) : '';
        $c['nom_nouveau_dossier'] = nom_nouveau_dossier((string)$c['nom']);
        $c['type_libelle'] = TYPES_CLIENT[$c['type'] ?? ''] ?? '';
        $c['pieces'] = ($c['statut'] ?? '') === 'ONBOARDING' ? (PIECES[$c['type'] ?? ''] ?? PIECES['SOCIETE']) : [];
    }
    unset($c);
    usort($clients, fn($a, $b) => [($a['statut'] ?? '') !== 'ONBOARDING', mb_strtoupper($a['nom'])]
        <=> [($b['statut'] ?? '') !== 'ONBOARDING', mb_strtoupper($b['nom'])]);
    return $clients;
}

/** Numéros de téléphone français d'un texte (signature d'un mail), au format « 06 12 34 56 78 » : mobiles d'abord, 3 au plus. */
function telephones_du_texte(string $texte): array
{
    preg_match_all('/(?<![\d+])(?:(?:\+|00)33[\s.\-]?(?:\(0\)[\s.\-]?)?|0)([1-9])(?:[\s.\-]?(\d{2})){4}(?![\d])/', $texte, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        $chiffres = preg_replace('/\D/', '', preg_replace('/^(\+|00)33(\(0\))?/', '0', preg_replace('/[\s.\-]/', '', $x[0])));
        if (strlen($chiffres) === 10) {
            $out[implode(' ', str_split($chiffres, 2))] = true;
        }
    }
    $out = array_keys($out);
    usort($out, fn($a, $b) => [!preg_match('/^0[67]/', $a)] <=> [!preg_match('/^0[67]/', $b)]);
    return array_slice($out, 0, 3);
}
