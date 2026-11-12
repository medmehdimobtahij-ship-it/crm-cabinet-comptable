<?php
/**
 * Lettres de mission et dossiers clients dans le SharePoint du cabinet (bibliothèque réglable, synchronisée par OneDrive
 * sur les postes), par Microsoft Graph avec le compte Microsoft de la personne connectée : rien à installer sur les PC.
 *
 *   - la lettre Word est déposée dans Dossiers › Lettres de mission › <CLIENT> (dossier existant réutilisé, sinon créé) ;
 *   - Microsoft la convertit en PDF (rendu Word), le PDF est déposé à côté et gardé dans le CRM ;
 *   - la liste des dossiers de Dossiers › Comptabilite donne les liens « Ouvrir le dossier ».
 * Jamais d'écrasement (« (2) » si le nom existe), jamais de suppression : le CRM ne fait qu'ajouter dans SharePoint.
 */
declare(strict_types=1);

// Bibliothèque et noms des dossiers : réglages définis dans modules.php (config.json ou variables CRM_…).
define('SP_LETTRES', SP_RACINE . '/' . RACINE_LETTRES);
define('SP_CLIENTS', SP_RACINE . '/' . RACINE_COMPTA);
define('SP_JURIDIQUE', SP_RACINE . '/' . RACINE_JURIDIQUE);
define('SP_SOCIETE', SP_RACINE . '/' . RACINE_SOCIETE);
define('SP_MODELE_COMPTA', RACINE_MODELE);  // Comptabilite › 0. Modele › <année> › sous-dossiers copiés pour chaque client
const SP_TAILLE_MAX = 4 * 1024 * 1024;  // envoi en une fois (une lettre fait quelques centaines de Ko)
const SP_REFUS = "Votre compte Microsoft n'a pas accès aux fichiers du cabinet : reconnectez votre boîte (Paramètres › Messagerie Outlook) "
    . "avec votre adresse du cabinet et acceptez l'accès aux fichiers.";

/** Compte qui peut ouvrir le SharePoint du cabinet : sinon un message qui dit quoi faire. */
function sp_verifier_compte(Boite $b): void
{
    if (MODE_DEMO) {
        throw new ErreurOutlook('Mode démo : le PDF et le rangement dans SharePoint passent par Microsoft 365, désactivés ici.');
    }
    if ($b->adresse() === '') {
        throw new ErreurOutlook('Connectez votre boîte Microsoft du cabinet (Paramètres › Messagerie Outlook) : le PDF et le rangement '
            . 'dans SharePoint passent par elle.', ['type' => 'reconnecter']);
    }
    if ($b->personnel()) {
        throw new ErreurOutlook("La boîte connectée ({$b->adresse()}) est un compte Microsoft personnel : il n'a pas accès au SharePoint du cabinet. "
            . 'Dans Paramètres › Messagerie Outlook, déconnectez-la puis connectez votre adresse @' . DOMAINE_CABINET . '.', ['type' => 'reconnecter']);
    }
}

/** Adresse web d'un dossier de la bibliothèque (espaces encodés, comme dans le navigateur). */
function sp_adresse(string $chemin): string
{
    return str_replace('%3A', ':', implode('/', array_map('rawurlencode', explode('/', SP_BIBLIOTHEQUE . '/' . $chemin))));
}

/** Dossier SharePoint [drive, id] retrouvé par son adresse (API « shares »), gardé en mémoire dans data/sharepoint.json. */
function sp_dossier(Boite $b, string $chemin, bool $rafraichir = false): array
{
    $cache = lire_json(chemin_data('sharepoint.json'), []);
    if (!$rafraichir && isset($cache[$chemin]['drive'], $cache[$chemin]['id'])) {
        return $cache[$chemin];
    }
    foreach ([sp_adresse($chemin), SP_BIBLIOTHEQUE . '/' . $chemin] as $adresse) {
        $code = 'u!' . rtrim(strtr(base64_encode($adresse), '+/', '-_'), '=');
        $r = $b->appel('GET', "/shares/$code/driveItem", ['params' => ['$select' => 'id,name,folder,parentReference'], 'refus' => SP_REFUS,
            'service' => 'SharePoint']);
        if ($r && isset($r['folder'], $r['parentReference']['driveId'])) {
            $cache[$chemin] = ['drive' => $r['parentReference']['driveId'], 'id' => $r['id']];
            ecrire_json(chemin_data('sharepoint.json'), $cache);
            return $cache[$chemin];
        }
    }
    throw new ErreurOutlook('Dossier introuvable dans SharePoint : bibliothèque › ' . str_replace('/', ' › ', $chemin)
        . '. Votre compte Microsoft y a-t-il accès ?');
}

/** Contenu d'un dossier : [[nom, id, dossier?]]. null si le dossier n'existe plus. */
function sp_enfants(Boite $b, array $d): ?array
{
    $url = "/drives/{$d['drive']}/items/{$d['id']}/children";
    if ($b->appel('GET', "/drives/{$d['drive']}/items/{$d['id']}", ['params' => ['$select' => 'id'], 'refus' => SP_REFUS, 'service' => 'SharePoint']) === null) {
        return null;
    }
    return array_map(fn($x) => ['nom' => $x['name'], 'id' => $x['id'], 'dossier' => isset($x['folder'])],
        $b->pages($url, ['$select' => 'id,name,folder', '$top' => '999'], 20000, ['refus' => SP_REFUS, 'service' => 'SharePoint']));
}

/** Contenu d'un dossier de la bibliothèque, en retrouvant son adresse si le dossier a été déplacé depuis. */
function sp_dossier_et_enfants(Boite $b, string $chemin): array
{
    $d = sp_dossier($b, $chemin);
    $enfants = sp_enfants($b, $d);
    if ($enfants === null) {
        $d = sp_dossier($b, $chemin, true);
        $enfants = sp_enfants($b, $d) ?? [];
    }
    return [$d, $enfants];
}

/** Dépose un fichier sous un nom libre : « nom.ext », sinon « nom (2).ext »… (jamais d'écrasement). Retourne [id, nom]. */
function sp_deposer(Boite $b, array $dossier, array $pris, string $base, string $ext, string $contenu): array
{
    if (strlen($contenu) > SP_TAILLE_MAX) {
        throw new ErreurOutlook("Fichier trop volumineux pour SharePoint ($base$ext).");
    }
    $pris = array_flip(array_map('mb_strtolower', $pris));
    for ($n = 1; $n < 100; $n++) {
        $nom = $base . ($n > 1 ? " ($n)" : '') . $ext;
        if (isset($pris[mb_strtolower($nom)])) {
            continue;
        }
        $r = $b->appel('PUT', "/drives/{$dossier['drive']}/items/{$dossier['id']}:/" . rawurlencode($nom) . ':/content', [
            'params' => ['@microsoft.graph.conflictBehavior' => 'fail'], 'binaire' => $contenu, 'conflit' => true, 'refus' => SP_REFUS,
            'service' => 'SharePoint']);
        if ($r !== false && isset($r['id'])) {
            return [$r['id'], $nom];
        }
    }
    throw new ErreurOutlook("Impossible de trouver un nom libre pour $base$ext dans SharePoint.");
}

/** Dossier du client dans Lettres de mission : même nom (accents, ponctuation et forme juridique mis à part), sinon créé. */
function sp_dossier_client(Boite $b, string $nom): array
{
    [$racine, $enfants] = sp_dossier_et_enfants($b, SP_LETTRES);
    $cle = cle_mots(mots($nom));
    $noms = array_values(array_filter($enfants, fn($x) => $x['dossier']));
    usort($noms, fn($x, $y) => strcmp($x['nom'], $y['nom']));
    foreach ($noms as $x) {
        if (mb_strtoupper($x['nom']) === $nom || ($cle !== '' && cle_mots(mots($x['nom'])) === $cle)) {
            return ['drive' => $racine['drive'], 'id' => $x['id'], 'nom' => $x['nom']];
        }
    }
    $r = $b->appel('POST', "/drives/{$racine['drive']}/items/{$racine['id']}/children", ['json' => ['name' => $nom, 'folder' => new stdClass(),
        '@microsoft.graph.conflictBehavior' => 'fail'], 'conflit' => true, 'refus' => SP_REFUS, 'service' => 'SharePoint']);
    if ($r === false) {  // créé à l'instant par un collègue : on le reprend
        foreach (sp_enfants($b, $racine) ?? [] as $x) {
            if ($x['dossier'] && mb_strtoupper($x['nom']) === mb_strtoupper($nom)) {
                return ['drive' => $racine['drive'], 'id' => $x['id'], 'nom' => $x['nom']];
            }
        }
        throw new ErreurOutlook("Le dossier $nom n'a pas pu être créé dans SharePoint.");
    }
    return ['drive' => $racine['drive'], 'id' => $r['id'], 'nom' => $r['name'] ?? $nom];
}

/**
 * Range la lettre dans SharePoint et la fait convertir en PDF par Microsoft.
 * Retourne [contenu du PDF, classement {dossier, fichiers}].
 */
function sp_lettre_en_pdf(Boite $b, array $e, string $docx): array
{
    sp_verifier_compte($b);
    $client = sp_dossier_client($b, (string)(($e['dossier_onedrive'] ?? '') ?: nom_dossier_client($e['valeurs'] ?? [])));
    $pris = array_column(sp_enfants($b, $client) ?? [], 'nom');
    $base = ($e['modele'] ?? '') === 'CONFRATERNELLE' ? 'LETTRE CONFRATERNELLE - ' . sans_accents_majuscules($client['nom'])
        : 'LETTRE DE MISSION - ' . sans_accents_majuscules($client['nom']) . ' - ' . sans_accents_majuscules((string)$e['libelle']);
    [$id_docx, $nom_docx] = sp_deposer($b, $client, $pris, $base, '.docx', $docx);
    $pdf = null;
    for ($essai = 0; $essai < 5 && $pdf === null; $essai++) {  // juste après l'envoi, la conversion peut ne pas être prête
        $r = $b->appel('GET', "/drives/{$client['drive']}/items/$id_docx/content", ['params' => ['format' => 'pdf'], 'brut' => true, 'suivre' => true,
            'refus' => SP_REFUS, 'service' => 'SharePoint']);
        if ($r !== null && str_starts_with((string)$r[0], '%PDF')) {
            $pdf = $r[0];
        } elseif ($essai < 4) {
            sleep(2);
        }
    }
    if ($pdf === null) {
        throw new ErreurOutlook("Microsoft n'a pas pu convertir la lettre en PDF. La lettre Word est bien rangée dans SharePoint › $client[nom] ; réessayez dans un instant.");
    }
    [, $nom_pdf] = sp_deposer($b, $client, array_merge($pris, [$nom_docx]), $base, '.pdf', $pdf);
    return [$pdf, ['dossier' => $client['nom'], 'fichiers' => [$nom_docx, $nom_pdf]]];
}

/**
 * Dossiers clients (Dossiers › Comptabilite) et les années de chacun (sous-dossiers « 2025 », « 2026 »…) : [noms, {nom: [années]}].
 * Les contenus sont lus par lots de 20 dossiers (requête $batch de Graph) : quelques appels pour tout le cabinet.
 */
function sp_dossiers_clients(Boite $b): array
{
    sp_verifier_compte($b);
    [$racine, $enfants] = sp_dossier_et_enfants($b, SP_CLIENTS);
    $dossiers = array_values(array_filter($enfants, fn($x) => $x['dossier']));
    $annees = [];
    foreach (array_chunk($dossiers, 20) as $lot) {
        $requetes = [];
        foreach ($lot as $i => $x) {
            $requetes[] = ['id' => (string)$i, 'method' => 'GET', 'url' => "/drives/{$racine['drive']}/items/{$x['id']}/children?\$select=name,folder&\$top=999"];
        }
        try {
            $r = $b->appel('POST', '/$batch', ['json' => ['requests' => $requetes], 'refus' => SP_REFUS, 'service' => 'SharePoint']);
        } catch (ErreurOutlook $e) {
            continue;  // années de ce lot inconnues : la liste des dossiers, elle, reste juste
        }
        foreach ($r['responses'] ?? [] as $rep) {
            $x = $lot[(int)($rep['id'] ?? -1)] ?? null;
            if ($x === null || (int)($rep['status'] ?? 0) !== 200) {
                continue;
            }
            foreach ($rep['body']['value'] ?? [] as $e) {
                if (isset($e['folder']) && preg_match('/^\d{4}$/', (string)($e['name'] ?? ''))) {
                    $annees[$x['nom']][] = $e['name'];
                }
            }
        }
    }
    return [array_column($dossiers, 'nom'), $annees];
}

/** Dossiers juridiques (Dossiers › Juridique annuel › année › CLIENT), en « année/NOM ». [] si ce dossier n'existe pas. */
function sp_dossiers_juridiques(Boite $b): array
{
    sp_verifier_compte($b);
    try {
        [$racine, $annees] = sp_dossier_et_enfants($b, SP_JURIDIQUE);
    } catch (ErreurOutlook $e) {
        return [];
    }
    $out = [];
    foreach ($annees as $a) {
        if (!$a['dossier'] || !preg_match('/^\d{4}$/', $a['nom'])) {
            continue;
        }
        foreach (sp_enfants($b, ['drive' => $racine['drive'], 'id' => $a['id']]) ?? [] as $x) {
            if ($x['dossier']) {
                $out[] = $a['nom'] . '/' . $x['nom'];
            }
        }
    }
    return $out;
}

/** Noms des dossiers permanents des clients (Dossiers › Permanent : Kbis, statuts, mandats…). */
function sp_dossiers_societe(Boite $b): array
{
    sp_verifier_compte($b);
    try {
        [, $enfants] = sp_dossier_et_enfants($b, SP_SOCIETE);
    } catch (ErreurOutlook $e) {
        return [];
    }
    return array_values(array_map(fn($x) => $x['nom'], array_filter($enfants, fn($x) => $x['dossier'])));
}

/** Sous-dossier $nom de $parent : créé s'il n'existe pas, repris sinon (jamais d'écrasement). [dossier, créé ?]. */
function sp_sous_dossier(Boite $b, array $parent, string $nom): array
{
    foreach (sp_enfants($b, $parent) ?? [] as $x) {
        if ($x['dossier'] && mb_strtoupper($x['nom']) === mb_strtoupper($nom)) {
            return [['drive' => $parent['drive'], 'id' => $x['id'], 'nom' => $x['nom']], false];
        }
    }
    $r = $b->appel('POST', "/drives/{$parent['drive']}/items/{$parent['id']}/children", ['json' => ['name' => $nom, 'folder' => new stdClass(),
        '@microsoft.graph.conflictBehavior' => 'fail'], 'conflit' => true, 'refus' => SP_REFUS, 'service' => 'SharePoint']);
    if ($r === false) {  // créé à l'instant par un collègue
        foreach (sp_enfants($b, $parent) ?? [] as $x) {
            if ($x['dossier'] && mb_strtoupper($x['nom']) === mb_strtoupper($nom)) {
                return [['drive' => $parent['drive'], 'id' => $x['id'], 'nom' => $x['nom']], false];
            }
        }
        throw new ErreurOutlook("Le dossier $nom n'a pas pu être créé dans SharePoint.");
    }
    return [['drive' => $parent['drive'], 'id' => $r['id'], 'nom' => $r['name'] ?? $nom], true];
}

/**
 * Crée le dossier d'un client dans SharePoint (création seulement : un dossier du même nom est repris tel quel).
 *   compta    : Comptabilite › NOM › année (NOM créé s'il manque), l'année copiée du modèle « 0. Modele » (sous-dossiers
 *               type : comptable, fiscal, social) : celui de l'année, sinon le plus récent ;
 *   societe   : Permanent › NOM ;
 *   juridique : Juridique annuel › année › NOM.
 * Retourne [chemin affiché, créé ?].
 */
function sp_creer_dossier_client(Boite $b, string $type, string $nom, string $annee): array
{
    sp_verifier_compte($b);
    if ($type === 'juridique') {
        [$racine] = sp_dossier_et_enfants($b, SP_JURIDIQUE);
        [$dossier_annee] = sp_sous_dossier($b, $racine, $annee);
        [$d, $cree] = sp_sous_dossier($b, $dossier_annee, $nom);
        return [RACINE_JURIDIQUE . " › $annee › {$d['nom']}", $cree];
    }
    [$racine, $enfants] = sp_dossier_et_enfants($b, $type === 'societe' ? SP_SOCIETE : SP_CLIENTS);
    [$d, $cree] = sp_sous_dossier($b, $racine, $nom);
    if ($type === 'societe') {
        return [RACINE_SOCIETE . " › {$d['nom']}", $cree];
    }
    foreach (sp_enfants($b, $d) ?? [] as $x) {
        if ($x['dossier'] && $x['nom'] === $annee) {
            return [RACINE_COMPTA . " › {$d['nom']} › $annee", false];
        }
    }
    $modele = null;  // le modèle de l'année, sinon le plus récent
    foreach ($enfants as $m) {
        if ($m['dossier'] && $m['nom'] === SP_MODELE_COMPTA) {
            foreach (sp_enfants($b, ['drive' => $racine['drive'], 'id' => $m['id']]) ?? [] as $x) {
                if ($x['dossier'] && preg_match('/^\d{4}$/', $x['nom']) && ($modele === null || $x['nom'] === $annee
                        || ($modele['nom'] !== $annee && $x['nom'] > $modele['nom']))) {
                    $modele = $x;
                }
            }
        }
    }
    if ($modele !== null) {  // copie de toute l'arborescence, sous le nom de l'année (Microsoft la fait en arrière-plan)
        $b->appel('POST', "/drives/{$racine['drive']}/items/{$modele['id']}/copy", ['json' => ['parentReference' => ['driveId' => $racine['drive'],
            'id' => $d['id']], 'name' => $annee], 'refus' => SP_REFUS, 'service' => 'SharePoint']);
    } else {
        sp_sous_dossier($b, $d, $annee);
    }
    return [RACINE_COMPTA . " › {$d['nom']} › $annee", true];
}
