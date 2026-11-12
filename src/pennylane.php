<?php
/**
 * Synchro Pennylane (lecture seule) : la liste des dossiers clients du cabinet, lue avec l'API « Firm » de Pennylane
 * (GET /api/external/firm/v1/companies, jeton du cabinet de portée companies:readonly). Rien n'est jamais écrit dans Pennylane.
 *
 * Les dossiers Pennylane absents de la liste des clients y sont ajoutés ; les fiches existantes sont seulement complétées (SIREN,
 * lien, adresse vides), jamais effacées. Le compte rendu dit quels dossiers Pennylane n'ont pas de dossier dans SharePoint
 * (dossier comptable) et l'inverse. Jeton chiffré (AES-256-GCM) dans data/pennylane.json, jamais renvoyé à l'écran.
 */
declare(strict_types=1);

define('PENNYLANE', getenv('CRM_PENNYLANE') ?: 'https://app.pennylane.com');  // remplaçable pour les tests (faux Pennylane local)
const PENNYLANE_SAUVEGARDES = 20;

function pennylane_jeton(): string
{
    $p = lire_json(chemin_data('pennylane.json'), []);
    return isset($p['chiffre']) ? (string)dechiffrer($p['chiffre'], 'pennylane') : '';
}

function pennylane_etat(): array
{
    $p = lire_json(chemin_data('pennylane.json'), []);
    return ['configure' => isset($p['chiffre']), 'par' => $p['par'] ?? '', 'le' => $p['le'] ?? '', 'synchro' => $p['synchro'] ?? null];
}

function pennylane_enregistrer(array $champs): void
{
    $p = lire_json(chemin_data('pennylane.json'), []);
    ecrire_json(chemin_data('pennylane.json'), array_merge($p, $champs));
}

/** Tous les dossiers clients du cabinet dans Pennylane (pages de 1000). Lève RuntimeException avec un message clair. */
function pennylane_dossiers(string $jeton): array
{
    $out = [];
    for ($page = 1; $page <= 50; $page++) {
        [$code, $corps] = http('GET', PENNYLANE . '/api/external/firm/v1/companies?' . http_build_query(['page' => $page, 'per_page' => 1000]),
            ['Authorization: Bearer ' . $jeton, 'Accept: application/json'], null, 60);
        if ($code === 401 || $code === 403) {
            throw new RuntimeException('Pennylane refuse le jeton : vérifiez qu\'il est valide, non expiré, et qu\'il a l\'accès « companies:readonly ».');
        }
        if ($code === 429) {
            throw new RuntimeException('Pennylane limite les appels : réessayez dans une minute.');
        }
        $r = json_decode($corps, true);
        if ($code !== 200 || !is_array($r['items'] ?? null)) {
            throw new RuntimeException("Pennylane injoignable ou réponse inattendue (code $code) : réessayez plus tard.");
        }
        foreach ($r['items'] as $x) {
            if (isset($x['id'], $x['name'])) {
                $out[] = $x;
            }
        }
        if ($page >= (int)($r['total_pages'] ?? 1)) {
            break;
        }
    }
    return $out;
}

function lien_pennylane(int|string $id): string
{
    return 'https://app.pennylane.com/companies/' . rawurlencode((string)$id);
}

/** Dossiers Pennylane que la personne a choisi de ne jamais ajouter : [id => nom]. Modifiable dans Paramètres. */
function pennylane_ignores(): array
{
    return lire_json(chemin_data('pennylane.json'), [])['ignores'] ?? [];
}

/** Ajoute à la liste des clients les dossiers Pennylane qui n'y sont pas, complète les fiches existantes.
 *  Retourne [ajoutés, complétés], chacun [{id Pennylane, nom}]. $ecrire = false : aperçu seulement, rien n'est écrit.
 *  $choisis : ids Pennylane à ajouter (null = tous) ; les dossiers ignorés ne sont jamais ajoutés. */
function pennylane_fusionner(array $societes, bool $ecrire = true, ?array $choisis = null): array
{
    $ignores = pennylane_ignores();
    $fichier = chemin_data('clients.json');
    $d = lire_json($fichier, []);
    $clients = $d['clients'] ?? [];
    $par_id = $par_siren = $par_nom = $nb_siren = [];
    foreach ($clients as $i => $c) {
        if (preg_match('#/companies/(\d+)#', (string)($c['pennylane'] ?? ''), $m)) {
            $par_id[$m[1]] = $i;
        }
        $s = str_replace(' ', '', (string)($c['siren'] ?? ''));
        if ($s !== '') {
            $par_siren[$s] = $i;
            $nb_siren[$s] = ($nb_siren[$s] ?? 0) + 1;
        }
        $par_nom[mb_strtoupper(trim((string)$c['nom']))] = $i;
    }
    $ajoutes = $completes = [];
    foreach ($societes as $x) {
        $id = (string)$x['id'];
        $siren = str_replace(' ', '', (string)($x['siren'] ?? ''));
        $nom = trim((string)$x['name']);
        $i = $par_id[$id] ?? (($siren !== '' && ($nb_siren[$siren] ?? 0) === 1) ? ($par_siren[$siren] ?? null) : null) ?? $par_nom[mb_strtoupper($nom)] ?? null;
        $infos = ['siren' => $siren, 'pennylane' => lien_pennylane($id), 'adresse' => trim((string)($x['address'] ?? '')),
            'cp' => trim((string)($x['postal_code'] ?? '')), 'ville' => trim((string)($x['city'] ?? ''))];
        if ($i === null) {
            if (isset($ignores[$id]) || ($choisis !== null && !in_array($id, array_map('strval', $choisis), true))) {
                continue;  // ignoré pour toujours, ou pas coché cette fois
            }
            $clients[] = ['id' => "PL-$id", 'nom' => $nom, 'type' => 'SOCIETE', 'statut' => 'ACTIF', 'forme' => '', 'activite' => '', 'email' => '',
                'telephone' => '', 'contact' => '', 'qualite' => '', 'collaborateur' => '', 'responsable' => '', 'source' => 'Pennylane',
                'importe_le' => maintenant_minutes()] + $infos;
            $par_id[$id] = array_key_last($clients);
            $ajoutes[] = ['id' => $id, 'nom' => $nom];
            continue;
        }
        $change = false;
        foreach ($infos as $k => $v) {  // seulement ce qui manque : une fiche n'est jamais écrasée
            if ($v !== '' && trim((string)($clients[$i][$k] ?? '')) === '') {
                $clients[$i][$k] = $v;
                $change = true;
            }
        }
        if ($change) {
            $completes[] = ['id' => $id, 'nom' => (string)$clients[$i]['nom']];
        }
    }
    if ($ecrire && ($ajoutes || $completes)) {
        clients_ecrire_avec_sauvegarde($d, $clients);
    }
    return [$ajoutes, $completes];
}

/** Liste des clients écrite, l'ancienne gardée dans data/sauvegardes (comme pour un import). */
function clients_ecrire_avec_sauvegarde(array $d, array $clients): void
{
    $fichier = chemin_data('clients.json');
    if (is_file($fichier)) {
        ecrire_fichier(chemin_data('sauvegardes', 'clients-' . date('Y-m-d-His') . '.json'), (string)file_get_contents($fichier));
        $anciennes = glob(chemin_data('sauvegardes', 'clients-*.json')) ?: [];
        sort($anciennes);
        foreach (array_slice($anciennes, 0, max(0, count($anciennes) - PENNYLANE_SAUVEGARDES)) as $f) {
            @unlink($f);
        }
    }
    $d['clients'] = array_values($clients);
    $d['maj'] = aujourdhui();
    ecrire_json($fichier, $d);
}

// ---------- Compléter les fiches avec Claude Desktop et le connecteur Pennylane (comme la relance bancaire) ----------
const CHAMPS_CLAUDE = ['forme' => 'forme_juridique', 'siren' => 'siren', 'contact' => 'contact', 'email' => 'email', 'telephone' => 'telephone'];

function cabinet_pennylane(): string
{
    return reglage('cabinet_pennylane');  // identifiant du cabinet dans Pennylane (config.json ou CRM_CABINET_PENNYLANE)
}

function fichier_claude(): string
{
    return chemin_data('claude', 'infos-pennylane.json');
}

/** Prompt pour Claude Desktop : il lit Pennylane (connecteur) et écrit le résultat (extension Filesystem) dans $fichier. */
function prompt_claude(array $dossiers, string $fichier): string
{
    $liste = implode("\n", array_map(fn($x) => "   - client={$x['client']} ; pennylane_id=" . ($x['pennylane_id'] ?: 'à trouver') . " ; nom={$x['nom']}", $dossiers));
    return "Compléter les fiches clients de mon CRM avec Pennylane. Travaille sans me poser de question, jusqu'au dernier dossier.\n\n"
        . "1. Avec le connecteur Pennylane, outil list_companies avec firm_id=" . cabinet_pennylane() . " (toutes les pages). Pour un dossier ci-dessous "
        . "marqué « à trouver », cherche le dossier Pennylane du même nom (à la ponctuation et aux majuscules près) ; s'il n'y en a pas, ou "
        . "s'il y a un doute, laisse pennylane_id vide. Pour chaque dossier trouvé, garde legal_form et le SIREN (siren ou registration_number).\n"
        . "2. Pour chaque dossier trouvé, outil getDashboardFirmSidepanelsCompany avec son id : main_contact (prénom et nom, e-mail, téléphone).\n"
        . "   Dossiers :\n$liste\n"
        . "3. Avec l'extension Filesystem, écris en écrasant le fichier $fichier : un tableau JSON UTF-8, un objet par dossier de la liste, "
        . "toutes les valeurs en chaînes (vide si absent), client recopié tel quel :\n"
        . '   [{"client":"","pennylane_id":"","forme_juridique":"","siren":"","contact":"","email":"","telephone":""}]' . "\n"
        . "   Recopie les valeurs brutes, n'invente rien. N'écris le fichier qu'une fois, quand tous les dossiers sont traités.\n"
        . "4. Réponds ensuite uniquement : Fiches CRM terminées : X dossiers.";
}

// Outils Pennylane permis à Claude en arrière-plan : lecture des dossiers et de leurs fiches. Rien d'autre (ni fichiers, ni commandes).
const OUTILS_PENNYLANE = ['mcp__claude_ai_Pennylane__get_context', 'mcp__claude_ai_Pennylane__list_companies', 'mcp__claude_ai_Pennylane__get_company',
    'mcp__claude_ai_Pennylane__search_operations', 'mcp__claude_ai_Pennylane__run_operations'];

/** Claude Code (ligne de commande) sur ce PC : celui de l'extension VS Code, ou installé à part. null s'il n'y en a pas. */
function claude_code_exe(): ?string
{
    if (getenv('CRM_SANS_CLAUDE')) {
        return null;
    }
    if ($e = getenv('CRM_CLAUDE_EXE')) {
        return is_file($e) ? $e : null;
    }
    $profil = getenv('USERPROFILE') ?: '';
    $trouves = array_merge(glob($profil . '/.vscode/extensions/anthropic.claude-code-*/resources/native-binary/claude.exe') ?: [],
        array_filter([$profil . '/.local/bin/claude.exe'], 'is_file'));
    usort($trouves, fn($a, $b) => filemtime($b) <=> filemtime($a));  // la version la plus récente
    return $trouves[0] ?? null;
}

function fichier_reponse_claude(): string
{
    return chemin_data('claude', 'reponse.txt');
}

/** Claude en vrai arrière-plan (sans fenêtre) : Claude Code en mode non interactif lit Pennylane avec le connecteur et répond par le
 *  tableau JSON, écrit dans reponse.txt à la fin. Retourne true si lancé. */
function lancer_claude_arriere_plan(string $prompt): bool
{
    $exe = claude_code_exe();
    if ($exe === null || PHP_OS_FAMILY !== 'Windows' || !function_exists('popen')) {
        return false;
    }
    $dossier = chemin_data('claude');
    ecrire_fichier("$dossier/prompt.txt", $prompt);
    @unlink(fichier_reponse_claude());
    $w = fn(string $p) => str_replace('/', '\\', $p);
    ecrire_fichier("$dossier/lancer.cmd", implode("\r\n", ['@echo off', 'chcp 65001 >NUL', 'cd /d "' . $w($dossier) . '"', 'set MCP_TIMEOUT=60000',
        'type prompt.txt | "' . $w($exe) . '" -p --output-format text --allowedTools "' . implode(' ', OUTILS_PENNYLANE) . '" > reponse.tmp 2> erreurs.txt',
        'move /Y reponse.tmp reponse.txt >NUL', '']));
    $h = @popen('start "" /B cmd /c "' . $w("$dossier/lancer.cmd") . '"', 'r');
    if ($h === false) {
        return false;
    }
    pclose($h);
    return true;
}

/** Numéro de téléphone français au format « 06 89 53 45 69 » (+33, points, espaces enlevés) ; tel quel s'il n'est pas français. */
function telephone_propre(string $t): string
{
    $c = preg_replace('/\D/', '', $t);
    if (str_starts_with($c, '0033')) {
        $c = '0' . substr($c, 4);
    } elseif (str_starts_with($c, '33') && strlen($c) === 11) {
        $c = '0' . substr($c, 2);
    }
    return strlen($c) === 10 && $c[0] === '0' ? implode(' ', str_split($c, 2)) : trim($t);
}

// Formes juridiques en toutes lettres (Pennylane, INSEE) -> sigle, pour comparer « SARL » et « Société à responsabilité limitée (SARL) »
const FORMES_LONGUES = [
    'societe par actions simplifiee unipersonnelle' => 'SASU', 'societe par actions simplifiee' => 'SAS',
    'entreprise unipersonnelle a responsabilite limitee' => 'EURL', 'societe a responsabilite limitee unipersonnelle' => 'EURL',
    'societe d exercice liberal a responsabilite limitee' => 'SELARL', 'societe d exercice liberal par actions simplifiee' => 'SELAS',
    'societe a responsabilite limitee' => 'SARL', 'societe civile immobiliere' => 'SCI', 'societe civile de moyens' => 'SCM',
    'societe civile professionnelle' => 'SCP', 'societe en nom collectif' => 'SNC', 'societe anonyme' => 'SA', 'societe civile' => 'SC',
    'entrepreneur individuel' => 'EI', 'entreprise individuelle' => 'EI', 'micro entreprise' => 'EI', 'auto entrepreneur' => 'EI',
    'association loi 1901' => 'Association', 'association' => 'Association',
];

/** Forme juridique courte : le sigle entre parenthèses, sinon celui de son nom en toutes lettres, sinon la forme telle quelle. */
function forme_courte(string $forme): string
{
    $f = trim($forme);
    if (preg_match('/\(\s*([A-Z]{2,8})\s*\)/u', $f, $m)) {
        return $m[1];
    }
    $plat = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(sans_accents($f))));
    if (preg_match('/^[a-z]{2,8}$/', $plat) && in_array(strtoupper($plat), array_values(FORMES_LONGUES), true)) {
        return strtoupper($plat);  // déjà un sigle (« sarl »)
    }
    foreach (FORMES_LONGUES as $long => $sigle) {  // du plus précis au plus général (SASU avant SAS)
        if (str_starts_with($plat, $long)) {
            return $sigle;
        }
    }
    return $f;
}

/** Même valeur, à l'écriture près (majuscules, espaces ; pour un téléphone, mêmes chiffres) : rien à proposer. */
function meme_valeur(string $k, string $a, string $b): bool
{
    if ($k === 'telephone') {
        return telephone_propre($a) === telephone_propre($b);
    }
    if ($k === 'forme') {
        return mb_strtolower(forme_courte($a)) === mb_strtolower(forme_courte($b));
    }
    $plat = fn($x) => preg_replace('/\s+/', ' ', mb_strtolower(trim($x)));
    return $plat($a) === $plat($b);
}

/** Le tableau JSON de la réponse de Claude (texte libre autour, bloc de code…), ou null. */
function json_de_reponse(string $texte): ?array
{
    if (preg_match('/\[[\s\S]*\]/', $texte, $m) === 1) {
        $texte = $m[0];
    }
    $r = json_decode($texte, true);
    return is_array($r) ? $r : null;
}

/** Dossiers que Claude n'a pas trouvés dans Pennylane (pas de pennylane_id pour un client qui n'en avait pas) : leurs noms. */
function introuvables_claude(array $resultat, array $clients): array
{
    $par_client = array_column($clients, null, 'id');
    $out = [];
    foreach ($resultat as $x) {
        $c = is_array($x) ? ($par_client[(string)($x['client'] ?? '')] ?? null) : null;
        if ($c && preg_replace('/\D/', '', (string)($x['pennylane_id'] ?? '')) === '' && !preg_match('#/companies/\d+#', (string)($c['pennylane'] ?? ''))) {
            $out[] = $c['nom'];
        }
    }
    return $out;
}

/** Ouvre Claude Desktop et y envoie le prompt (outils/claude_desktop.py, sur le PC où tourne le CRM). Retourne true si lancé. */
function lancer_claude(string $prompt): bool
{
    if (PHP_OS_FAMILY !== 'Windows' || getenv('CRM_SANS_CLAUDE') || !function_exists('popen')) {
        return false;
    }
    $fichier = chemin_data('claude', 'prompt.txt');
    ecrire_fichier($fichier, $prompt);
    $script = RACINE . '\outils\claude_desktop.py';
    $h = @popen('start "" /B pythonw "' . $script . '" "' . $fichier . '"', 'r');
    if ($h === false) {
        return false;
    }
    pclose($h);
    return true;
}

/** Ce que Claude a trouvé, rapproché des clients ($clients : la liste complète, dossiers SharePoint compris) : les informations qui
 *  manquent ou qui diffèrent de la fiche, et le lien Pennylane d'un dossier qui n'en avait pas, [{id, nom, champs: {clé: {valeur, ancien}}}]. */
function propositions_claude(array $resultat, array $clients): array
{
    $par_id = $par_client = [];
    foreach ($clients as $c) {
        $par_client[(string)$c['id']] = $c;
        if (preg_match('#/companies/(\d+)#', (string)($c['pennylane'] ?? ''), $m)) {
            $par_id[$m[1]] = $c;
        }
    }
    $out = [];
    foreach ($resultat as $x) {
        if (!is_array($x)) {
            continue;
        }
        $pid = preg_replace('/\D/', '', (string)($x['pennylane_id'] ?? ''));
        $c = $par_client[(string)($x['client'] ?? '')] ?? ($pid !== '' ? ($par_id[$pid] ?? null) : null);
        if (!$c) {
            continue;
        }
        $champs = [];
        if ($pid !== '' && !preg_match('#/companies/\d+#', (string)($c['pennylane'] ?? ''))) {  // dossier trouvé par son nom : relié à Pennylane
            $champs['pennylane'] = ['valeur' => lien_pennylane($pid), 'ancien' => ''];
        }
        foreach (CHAMPS_CLAUDE as $k => $source) {
            $v = mb_substr(trim((string)($x[$source] ?? '')), 0, 150);
            if ($k === 'siren') {
                $v = str_replace(' ', '', $v);
            }
            if ($k === 'telephone') {
                $v = telephone_propre($v);
            }
            if ($k === 'forme') {
                $v = forme_courte($v);  // écrite en sigle, comme les autres fiches
            }
            $ancien = trim((string)($c[$k] ?? ''));
            if ($v !== '' && !meme_valeur($k, $v, $ancien) && ($k !== 'email' || email_valide($v)) && ($k !== 'siren' || siren_valide($v))) {
                $champs[$k] = ['valeur' => $v, 'ancien' => $ancien];
            }
        }
        if ($champs) {
            $out[] = ['id' => $c['id'], 'nom' => $c['nom'], 'champs' => $champs];
        }
    }
    return $out;
}
