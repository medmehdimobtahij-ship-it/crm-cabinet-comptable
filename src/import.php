<?php
/**
 * Import de clients : fichier CSV (Excel, Pennylane, export du CRM) ou texte collé depuis le presse-papiers (copie d'un
 * tableau Excel = colonnes séparées par des tabulations). Toujours en deux temps : aperçu (rien n'est écrit), puis import.
 *
 * Les clients sont ajoutés à data/clients.json (les mêmes fiches que l'import Pennylane). Un client déjà présent (même nom,
 * sinon même SIREN s'il n'y a qu'un dossier sous ce SIREN) est complété ou mis à jour, jamais effacé : une case vide dans le
 * fichier ne vide pas la fiche. Deux dossiers peuvent partager un SIREN (ex. « DUPONT » et « DUPONT [LMNP] »).
 * Avant chaque import, l'ancienne liste est sauvegardée dans data/sauvegardes/.
 */
declare(strict_types=1);

const IMPORT_MAX_LIGNES = 5000;
const IMPORT_SAUVEGARDES = 20;

/** Colonnes du modèle : [champ de la fiche, en-tête du modèle, autres intitulés reconnus]. */
const COLONNES_IMPORT = [
    ['nom', 'Société', ['nom', 'client', 'raison sociale', 'denomination', 'dossier', 'nom du client', 'entreprise']],
    ['type', 'Type', ['type de client', 'categorie']],
    ['forme', 'Forme juridique', ['forme']],
    ['siren', 'SIREN', ['siret', 'numero siren']],
    ['contact', 'Contact', ['dirigeant', 'nom du contact', 'interlocuteur', 'contact principal']],
    ['qualite', 'Qualité', ['fonction', 'qualite du dirigeant', 'qualite du signataire']],
    ['email', 'E-mail', ['email', 'mail', 'courriel', 'adresse mail', 'adresse e-mail', 'adresse email']],
    ['telephone', 'Téléphone', ['tel', 'portable', 'mobile', 'numero de telephone']],
    ['adresse', 'Adresse', ['adresse postale', 'rue', 'adresse du siege']],
    ['cp', 'Code postal', ['cp', 'code']],
    ['ville', 'Ville', ['commune', 'localite']],
    ['activite', 'Activité', ['activite principale', 'secteur']],
    ['fiscalite', 'Fiscalité', ['regime fiscal', 'imposition', 'ir is']],
    ['regime', 'Régime', ['regime d imposition']],
    ['tva', 'TVA', ['regime de tva', 'regime tva']],
    ['collaborateur', 'Collaborateur', ['collaborateur en charge', 'charge de mission']],
    ['responsable', 'Responsable', ['expert comptable', 'associe']],
    ['statut', 'Statut', ['etat']],
    ['pennylane', 'Lien Pennylane', ['pennylane', 'lien', 'url pennylane']],
];
const TYPES_IMPORT = ['societe' => 'SOCIETE', 'entreprise' => 'SOCIETE', 'sas' => 'SOCIETE', 'sasu' => 'SOCIETE', 'sarl' => 'SOCIETE',
    'eurl' => 'SOCIETE', 'sci' => 'SOCIETE', 'sa' => 'SOCIETE', 'ei' => 'EI', 'entreprise individuelle' => 'EI',
    'entrepreneur individuel' => 'EI', 'micro entreprise' => 'EI', 'auto entrepreneur' => 'EI', 'lmnp' => 'LMNP',
    'association' => 'ASSOCIATION', 'particulier' => 'PARTICULIER', 'personne physique' => 'PARTICULIER'];

/** « Code postal » -> « code postal » : sans accents ni ponctuation, en minuscules (comparaison des intitulés). */
function import_cle(string $t): string
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(sans_accents($t))));
}

/** Texte reçu -> UTF-8 propre (Excel enregistre souvent ses CSV en Windows-1252). */
function import_texte(string $t): string
{
    if (str_starts_with($t, "\u{feff}")) {
        $t = substr($t, 3);
    }
    if (!mb_check_encoding($t, 'UTF-8')) {
        $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }
    return str_replace(["\r\n", "\r"], "\n", $t);
}

/** Lignes du tableau. Séparateur deviné sur la ligne d'en-tête : tabulation (copie Excel), point-virgule ou virgule. */
function import_tableau(string $texte): array
{
    $premiere = strtok($texte, "\n") ?: '';
    $sans_guillemets = preg_replace('/"[^"]*"/', '', $premiere);
    $compte = ["\t" => substr_count($sans_guillemets, "\t"), ';' => substr_count($sans_guillemets, ';'), ',' => substr_count($sans_guillemets, ',')];
    arsort($compte);
    $sep = array_key_first($compte);
    $f = fopen('php://temp', 'r+');
    fwrite($f, $texte);
    rewind($f);
    $lignes = [];
    while (($l = fgetcsv($f, null, $sep, '"', '')) !== false) {
        if ($l === [null] || implode('', array_map('trim', array_map('strval', $l))) === '') {
            $lignes[] = null;  // ligne vide : gardée pour que les numéros de ligne restent ceux du fichier
            continue;
        }
        $lignes[] = array_map(fn($x) => trim((string)$x), $l);
        if (count($lignes) > IMPORT_MAX_LIGNES + 1) {
            break;
        }
    }
    fclose($f);
    return $lignes;
}

/** En-têtes -> [index de colonne => champ] et intitulés ignorés. */
function import_colonnes(array $entetes): array
{
    $intitules = [];
    foreach (COLONNES_IMPORT as [$champ, $entete, $autres]) {
        foreach (array_merge([$entete], $autres) as $x) {
            $intitules[import_cle($x)] ??= $champ;
        }
    }
    $pris = $colonnes = $ignorees = [];
    foreach ($entetes as $i => $e) {
        $champ = $intitules[import_cle($e)] ?? null;
        if ($champ !== null && !isset($pris[$champ])) {
            $colonnes[$i] = $champ;
            $pris[$champ] = true;
        } elseif (trim($e) !== '') {
            $ignorees[] = $e;
        }
    }
    return [$colonnes, $ignorees];
}

/** Ligne du fichier -> [fiche (champs non vides), erreurs, avertissements]. */
function import_fiche(array $l, array $colonnes): array
{
    $v = [];
    foreach ($colonnes as $i => $champ) {
        $x = trim(preg_replace('/\s+/u', ' ', (string)($l[$i] ?? '')));
        if (preg_match('#^\[?\s*(n\s*/?\s*d|n\.d\.?|non disponible|non renseigne|non renseigné)\s*\]?$#iu', $x)) {
            $x = '';  // « [ND] » de Pennylane : information non disponible
        }
        if ($x !== '') {
            $v[$champ] = $x;
        }
    }
    $erreurs = $avert = [];
    if (($v['nom'] ?? '') === '') {
        $erreurs[] = 'Société (nom du client) manquante.';
    }
    if (isset($v['siren'])) {
        $s = preg_replace('/\D/', '', $v['siren']);
        if (strlen($s) === 14) {
            $s = substr($s, 0, 9);  // SIRET : le SIREN en est le début
        }
        if (strlen($s) !== 9) {
            $erreurs[] = "SIREN « {$v['siren']} » : il faut 9 chiffres.";
        } elseif (!siren_valide($s)) {
            $avert[] = "SIREN $s : clé de contrôle inhabituelle, à vérifier.";
        }
        $v['siren'] = $s;
    }
    if (isset($v['type'])) {
        $t = TYPES_IMPORT[import_cle($v['type'])] ?? (isset(TYPES_CLIENT[strtoupper($v['type'])]) ? strtoupper($v['type']) : null);
        if ($t === null) {
            $erreurs[] = "Type « {$v['type']} » inconnu (Société, Entreprise individuelle, LMNP, Association ou Particulier).";
        } else {
            $v['type'] = $t;
        }
    }
    if (isset($v['statut'])) {
        $k = import_cle($v['statut']);
        $st = in_array($k, ['actif', 'active', 'client', 'client actif'], true) ? 'ACTIF'
            : (in_array($k, ['onboarding', 'en onboarding', 'en cours', 'nouveau', 'nouveau client'], true) ? 'ONBOARDING'
            : (in_array($k, ['archive', 'archivee', 'archives', 'ancien', 'ancien client'], true) ? 'ARCHIVE' : null));
        if ($st === null) {
            $erreurs[] = "Statut « {$v['statut']} » inconnu (Actif, Onboarding ou Archivé).";
        } else {
            $v['statut'] = $st;
        }
    }
    if (isset($v['email'])) {
        $v['email'] = strtolower($v['email']);
        if (!email_valide($v['email'])) {
            $erreurs[] = "E-mail « {$v['email']} » invalide.";
        }
    }
    if (isset($v['cp'])) {
        if (preg_match('/^\d{4}$/', $v['cp'])) {
            $v['cp'] = '0' . $v['cp'];  // Excel enlève le zéro des départements 01 à 09
        }
        if (!preg_match('/^\d{5}$/', $v['cp'])) {
            $avert[] = "Code postal « {$v['cp']} » inhabituel.";
        }
    }
    if (isset($v['pennylane']) && !preg_match('#^https://#', $v['pennylane'])) {
        $avert[] = 'Lien Pennylane ignoré (il doit commencer par https://).';
        unset($v['pennylane']);
    }
    return [$v, $erreurs, $avert];
}

/**
 * Analyse (et, si $appliquer, importe) le texte. Les lignes en erreur sont ignorées, les autres importées.
 * Retourne {colonnes, ignorees, lignes, erreurs, totaux, applique}.
 */
function clients_importer(string $brut, bool $appliquer): array
{
    $tableau = import_tableau(import_texte($brut));
    while ($tableau && $tableau[0] === null) {
        array_shift($tableau);
    }
    if (!$tableau) {
        throw new ErreurSaisie(['texte' => 'Rien à importer : choisissez un fichier CSV ou collez le tableau.']);
    }
    [$colonnes, $ignorees] = import_colonnes($tableau[0]);
    if (!in_array('nom', $colonnes, true)) {
        throw new ErreurSaisie(['texte' => 'Première ligne non reconnue : elle doit contenir les intitulés des colonnes, dont « Société ». '
            . 'Partez du modèle CSV.']);
    }
    if (count($tableau) - 1 > IMPORT_MAX_LIGNES) {
        throw new ErreurSaisie(['texte' => 'Trop de lignes : ' . IMPORT_MAX_LIGNES . ' clients au plus par import.']);
    }
    return sous_verrou(function () use ($tableau, $colonnes, $ignorees, $appliquer) {
        $d = lire_json(chemin_data('clients.json'), []);
        $clients = $d['clients'] ?? [];
        $archives = clients_archives();
        $par_siren = $par_nom = [];
        foreach ($clients as $i => $c) {
            if (!empty($c['siren'])) {
                $par_siren[str_replace(' ', '', (string)$c['siren'])][] = $i;
            }
            $par_nom[mb_strtoupper(trim((string)$c['nom']))] = $i;
        }
        $col_nom = array_search('nom', $colonnes, true);
        $noms_fichier = [];  // un client présent du fichier sous son nom n'est pas repris par SIREN pour une autre ligne
        foreach (array_slice($tableau, 1) as $l) {
            if ($l !== null) {
                $noms_fichier[mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string)($l[$col_nom] ?? ''))))] = true;
            }
        }
        $lignes = $erreurs = [];
        $vus = [];
        $totaux = ['ajout' => 0, 'maj' => 0, 'identique' => 0, 'erreur' => 0];
        $maintenant = maintenant_minutes();
        foreach (array_slice($tableau, 1, null, true) as $n => $l) {
            if ($l === null) {
                continue;
            }
            $numero = $n + 1;  // numéro de ligne du fichier (en-tête = ligne 1)
            [$v, $err, $avert] = import_fiche($l, $colonnes);
            $archiver = null;  // « Archivé » : l'état est gardé à part, la fiche garde Actif / Onboarding
            if (($v['statut'] ?? '') === 'ARCHIVE') {
                $archiver = true;
                unset($v['statut']);
            } elseif (isset($v['statut'])) {
                $archiver = false;
            }
            $cle_nom = mb_strtoupper($v['nom'] ?? '');
            $doublon = $vus["nom:$cle_nom"] ?? null;
            if (!$err && $doublon !== null) {
                $err[] = "En double dans le fichier (déjà ligne $doublon).";
            }
            if ($err) {
                $totaux['erreur']++;
                $erreurs[] = ['ligne' => $numero, 'nom' => $v['nom'] ?? '', 'message' => implode(' ', $err)];
                continue;
            }
            $vus["nom:$cle_nom"] = $numero;
            $i = $par_nom[$cle_nom] ?? null;
            $meme_siren = !empty($v['siren']) ? ($par_siren[$v['siren']] ?? []) : [];
            if ($i === null && count($meme_siren) === 1
                && !isset($noms_fichier[mb_strtoupper(trim((string)$clients[$meme_siren[0]]['nom']))])) {
                $i = $meme_siren[0];  // même entreprise, renommée depuis
            }
            if ($i === null) {
                $fiche = $v + ['type' => (in_array($v['forme'] ?? '', ['EI', 'Entrepreneur individuel', 'Entreprise individuelle'], true) ? 'EI' : 'SOCIETE'),
                    'statut' => 'ACTIF'];
                $fiche = ['id' => 'CSV-' . substr(uuid_hex(), 0, 12)] + $fiche + ['source' => 'Import CSV', 'importe_le' => $maintenant];
                foreach (['siren', 'adresse', 'cp', 'ville', 'forme', 'activite', 'email', 'telephone', 'contact', 'qualite', 'collaborateur',
                    'responsable', 'pennylane'] as $k) {
                    $fiche[$k] ??= '';  // mêmes champs que les fiches Pennylane
                }
                $clients[] = $fiche;
                $i = array_key_last($clients);
                $par_nom[$cle_nom] = $i;
                if ($archiver) {
                    $archives[$fiche['id']] = ['le' => $maintenant, 'par' => 'Import CSV'];
                }
                $totaux['ajout']++;
                $lignes[] = ['ligne' => $numero, 'nom' => $v['nom'], 'action' => 'ajout', 'changements' => [], 'avertissements' => $avert];
                continue;
            }
            $changes = [];
            $cle_id = (string)$clients[$i]['id'];
            if ($archiver !== null && $archiver !== isset($archives[$cle_id])) {
                $changes[] = 'statut';
                if ($archiver) {
                    $archives[$cle_id] = ['le' => $maintenant, 'par' => 'Import CSV'];
                } else {
                    unset($archives[$cle_id]);
                }
            }
            foreach ($v as $k => $x) {
                if ((string)($clients[$i][$k] ?? '') !== $x && !($k === 'nom' && mb_strtoupper((string)$clients[$i]['nom']) === $cle_nom)) {
                    if (!in_array($k, $changes, true)) {
                        $changes[] = $k;
                    }
                    $clients[$i][$k] = $x;
                }
            }
            if ($changes) {
                $clients[$i]['importe_le'] = $maintenant;
                $totaux['maj']++;
            } else {
                $totaux['identique']++;
            }
            $lignes[] = ['ligne' => $numero, 'nom' => (string)$clients[$i]['nom'], 'action' => $changes ? 'maj' : 'identique',
                'changements' => array_map(fn($k) => COLONNES_IMPORT[array_search($k, array_column(COLONNES_IMPORT, 0), true)][1], $changes),
                'avertissements' => $avert];
        }
        if ($appliquer && ($totaux['ajout'] || $totaux['maj'])) {
            $fichier = chemin_data('clients.json');
            if (is_file($fichier)) {  // l'ancienne liste est gardée : on peut toujours revenir en arrière
                ecrire_fichier(chemin_data('sauvegardes', 'clients-' . date('Y-m-d-His') . '.json'), (string)file_get_contents($fichier));
                $anciennes = glob(chemin_data('sauvegardes', 'clients-*.json')) ?: [];
                sort($anciennes);
                foreach (array_slice($anciennes, 0, max(0, count($anciennes) - IMPORT_SAUVEGARDES)) as $f) {
                    @unlink($f);
                }
            }
            $d['clients'] = array_values($clients);
            $d['import_csv'] = $maintenant;
            ecrire_json($fichier, $d);
            clients_archives_ecrire($archives);
        }
        $champs = array_column(COLONNES_IMPORT, 1, 0);
        return ['colonnes' => array_values(array_map(fn($c) => $champs[$c], $colonnes)), 'ignorees' => $ignorees, 'lignes' => $lignes,
            'erreurs' => $erreurs, 'totaux' => $totaux, 'applique' => $appliquer && ($totaux['ajout'] || $totaux['maj'])];
    });
}

/** Modèle à remplir dans Excel : les intitulés reconnus et deux exemples. Point-virgule, UTF-8 avec BOM (Excel français). */
function clients_modele_csv(): string
{
    $lignes = [array_column(COLONNES_IMPORT, 1),
        ['DUPONT CONSEIL', 'Société', 'SAS', '123456782', 'Jean Dupont', 'Président', 'jean.dupont@exemple.fr', '06 12 34 56 78',
            '12 rue de la Paix', '75002', 'Paris', 'Conseil en gestion', 'IS', 'Réel simplifié', 'Mensuelle', 'Claire Moreau', 'Paul Bernard',
            'Actif', ''],
        ['MARTIN Anne', 'LMNP', '', '', 'Anne Martin', '', 'anne.martin@exemple.fr', '', '3 avenue Foch', '69003', 'Lyon', 'Location meublée',
            'IR', 'Réel', '', '', '', 'Onboarding', '']];
    $champ = fn($v) => preg_match('/[;"\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
    return "\u{feff}" . implode("\r\n", array_map(fn($l) => implode(';', array_map($champ, $l)), $lignes)) . "\r\n";
}
