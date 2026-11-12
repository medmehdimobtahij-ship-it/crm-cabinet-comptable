<?php
/**
 * CRM Cabinet (PHP) : le socle. Chemins, réglages, réponses JSON, verrou, fichiers JSON écrits sans risque, dates, session.
 *
 * PHP repart de zéro à chaque requête : tout ce qui doit survivre (Ctrl+Z, essais de connexion, caches) vit sur disque.
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

define('RACINE', dirname(__DIR__));

/**
 * Réglage du cabinet : variable d'environnement CRM_<CLE> (en majuscules), sinon config.json à la racine du projet
 * (voir config.exemple.json), sinon la valeur par défaut. Aucune valeur réelle n'est écrite dans le code.
 */
function reglage(string $cle, string $defaut = ''): string
{
    static $fichier = null;
    $env = getenv('CRM_' . strtoupper($cle));
    if ($env !== false && $env !== '') {
        return $env;
    }
    if ($fichier === null) {
        $c = is_file(RACINE . '/config.json') ? json_decode((string)file_get_contents(RACINE . '/config.json'), true) : null;
        $fichier = is_array($c) ? $c : [];
    }
    $v = $fichier[$cle] ?? null;
    return is_bool($v) ? ($v ? '1' : '0') : (is_scalar($v) && (string)$v !== '' ? (string)$v : $defaut);
}

/** Mode démo : données fictives, aucun appel à Microsoft 365 ni à Pennylane, compte « demo » / « demo ». */
define('MODE_DEMO', in_array(strtolower(reglage('demo')), ['1', 'true', 'oui'], true));
define('DATA', getenv('CRM_DATA') ?: RACINE . (MODE_DEMO ? '/data-demo' : '/data'));  // hors du dossier public : jamais servi par le web
define('CABINET_NOM', reglage('cabinet_nom', 'Cabinet Exemple'));  // nom imprimé sur les lettres et les signatures
define('CABINET_VILLE', reglage('cabinet_ville', 'Paris'));        // « Fait à Paris, le … »
define('MODELES_DIR', RACINE . '/modeles/lettres-de-mission');
define('PUBLIC_DIR', RACINE . '/public');

final class Arret extends Exception
{
    /** Réponse HTTP immédiate (ex. 404, 401) levée n'importe où dans le traitement. */
    public function __construct(public int $statut, public array $corps) { parent::__construct((string)($corps['erreur'] ?? '')); }
}

final class ErreurSaisie extends Exception
{
    /** Champs à corriger : [clé du champ => message] (répondu en 422, affiché sous le champ). */
    public function __construct(public array $champs) { parent::__construct(implode(' ; ', $champs)); }
}

function json_reponse(int $statut, $corps): never
{
    http_response_code($statut);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function arret(int $statut, string $erreur, array $plus = []): never
{
    throw new Arret($statut, ['erreur' => $erreur] + $plus);
}

/** Corps JSON de la requête (tableau associatif ; [] si vide ou illisible). */
function corps(): array
{
    static $c = null;
    if ($c === null) {
        $c = json_decode((string)file_get_contents('php://input'), true);
        $c = is_array($c) ? $c : [];
    }
    return $c;
}

function chemin_data(string ...$parties): string
{
    return DATA . '/' . implode('/', $parties);
}

/** Lit un fichier JSON (tableau associatif) ; $defaut s'il manque ou est illisible. */
function lire_json(string $chemin, $defaut = [])
{
    if (!is_file($chemin)) {
        return $defaut;
    }
    $v = json_decode((string)file_get_contents($chemin), true);
    return $v === null ? $defaut : $v;
}

/** Écrit un fichier d'un coup (fichier temporaire puis renommage) : jamais de fichier à moitié écrit. */
function ecrire_fichier(string $chemin, string $contenu): void
{
    $dossier = dirname($chemin);
    if (!is_dir($dossier)) {
        mkdir($dossier, 0770, true);
    }
    $tmp = $chemin . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, $contenu, LOCK_EX);
    if (!@rename($tmp, $chemin)) {  // Windows : renommer sur un fichier existant peut échouer, on réessaie après suppression
        @unlink($chemin);
        rename($tmp, $chemin);
    }
}

function ecrire_json(string $chemin, $valeur, bool $indente = true): void
{
    ecrire_fichier($chemin, json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        | ($indente ? JSON_PRETTY_PRINT : 0)));
}

/** Une écriture à la fois (plusieurs collaborateurs peuvent travailler en même temps) : verrou exclusif sur data/.verrou. */
function sous_verrou(callable $fonction)
{
    static $profondeur = 0, $poignee = null;
    if ($profondeur === 0) {
        if (!is_dir(DATA)) {
            mkdir(DATA, 0770, true);
        }
        $poignee = fopen(chemin_data('.verrou'), 'c');
        flock($poignee, LOCK_EX);
    }
    $profondeur++;
    try {
        return $fonction();
    } finally {
        $profondeur--;
        if ($profondeur === 0) {
            flock($poignee, LOCK_UN);
            fclose($poignee);
        }
    }
}

// ---------- Dates ----------
function aujourdhui(): string
{
    return date('Y-m-d');
}

/** « 2026-11-02… » -> « 2026-11-02 », ou null si ce n'est pas une date. */
function lire_date($texte): ?string
{
    $t = substr((string)($texte ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
        return null;
    }
    [$a, $m, $j] = array_map('intval', explode('-', $t));
    return checkdate($m, $j, $a) ? $t : null;
}

function jours_entre(string $de, string $a): int
{
    return (int)round((strtotime($a . ' 12:00') - strtotime($de . ' 12:00')) / 86400);
}

function plus_jours(string $jour, int $n): string
{
    return date('Y-m-d', strtotime("$jour 12:00 +$n days"));
}

function jjmmaaaa(?string $iso): string
{
    return $iso ? date('d/m/Y', strtotime($iso)) : '';
}

function maintenant_minutes(): string
{
    return date('Y-m-d\TH:i');
}

function uuid_hex(): string
{
    return bin2hex(random_bytes(16));
}

/** Clé du serveur (sessions, chiffrement des jetons Outlook), créée au premier lancement. */
function cle_secrete(): string
{
    $chemin = chemin_data('secret.key');
    if (!is_file($chemin)) {
        ecrire_fichier($chemin, bin2hex(random_bytes(32)));
    }
    return trim((string)file_get_contents($chemin));
}

/** Chiffre / déchiffre (AES-256-GCM, clé dérivée de secret.key) : jetons Outlook gardés sur disque. */
function chiffrer(string $clair, string $usage): string
{
    $cle = hash('sha256', $usage . ':' . cle_secrete(), true);
    $iv = random_bytes(12);
    $chiffre = openssl_encrypt($clair, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $chiffre);
}

function dechiffrer(string $paquet, string $usage): ?string
{
    $brut = base64_decode($paquet, true);
    if ($brut === false || strlen($brut) < 29) {
        return null;
    }
    $cle = hash('sha256', $usage . ':' . cle_secrete(), true);
    $clair = openssl_decrypt(substr($brut, 28), 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, substr($brut, 0, 12), substr($brut, 12, 16));
    return $clair === false ? null : $clair;
}

function en_https(): bool
{
    return getenv('CRM_HTTPS') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Adresse du CRM vue par le navigateur (« https://crm.exemple.fr ») : liens envoyés au programme des PC. */
function origine(): string
{
    $hote = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (en_https() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', $hote);
}

/** « Éléonore Gœtz » -> « Eleonore Goetz », sans l'extension intl (absente de certains hébergements). */
function sans_accents(string $texte): string
{
    static $table = null;
    $table ??= [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
        'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
        'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
        'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'ß' => 'ss', '’' => "'", '‘' => "'", '«' => '"', '»' => '"', '–' => '-', '—' => '-', "\u{a0}" => ' ', "\u{202f}" => ' ',
    ];
    $t = strtr($texte, $table);
    return preg_replace('/[^\x20-\x7E]/', '', $t) ?? $t;
}

/** Texte sans accents, en majuscules, sans caractères interdits dans un nom de fichier Windows. */
function sans_accents_majuscules(string $texte): string
{
    $t = sans_accents($texte);
    $t = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $t);
    return strtoupper(trim(preg_replace('/\s{2,}/', ' ', $t)));
}

function majuscule_initiale(string $mot): string
{
    return mb_strtoupper(mb_substr($mot, 0, 1)) . mb_substr($mot, 1);
}

/** Envoie un fichier (Word, PDF, pièce jointe) : téléchargé, ou ouvert dans le navigateur. */
function envoyer_fichier(string $chemin_ou_contenu, string $nom, string $type, bool $telecharger, bool $contenu = false, ?string $csp = null): never
{
    $ascii = preg_replace('/[^A-Za-z0-9 ._()\-]/', '_', sans_accents($nom)) ?: 'fichier';
    header('Content-Type: ' . $type);
    header('Content-Disposition: ' . ($telecharger ? 'attachment' : 'inline') . "; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($nom));
    header('Content-Length: ' . ($contenu ? strlen($chemin_ou_contenu) : filesize($chemin_ou_contenu)));
    header('Cache-Control: no-store');
    if ($csp) {
        header('Content-Security-Policy: ' . $csp);
    }
    http_response_code(200);
    if ($contenu) {
        echo $chemin_ou_contenu;
    } else {
        readfile($chemin_ou_contenu);
    }
    exit;
}
