<?php
/**
 * CRM Cabinet (PHP) : point d'entrée unique. Sert la page de l'interface et l'API (/api/…) ; les fichiers de l'interface
 * (app.js, styles.css, logo) sont servis directement par le serveur web. Données dans ../data, jamais publiques.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('expose_php', '0');
header_remove('X-Powered-By');
$demo = in_array(strtolower((string)getenv('CRM_DEMO')), ['1', 'true', 'oui'], true);  // la démo n'appelle aucun service : curl inutile
$manquantes = array_values(array_filter($demo ? ['openssl', 'zip', 'dom', 'mbstring', 'json'] : ['curl', 'openssl', 'zip', 'dom', 'mbstring', 'json'],
    fn($e) => !extension_loaded($e)));
if ($manquantes || PHP_VERSION_ID < 80100) {  // hébergement mal réglé : on le dit clairement plutôt qu'une page blanche
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('CRM Cabinet : PHP 8.1 ou plus récent est nécessaire (ici ' . PHP_VERSION . ')'
        . ($manquantes ? ', avec les extensions : ' . implode(', ', $manquantes) . ' (php.ini : lignes extension=…).' : '.'));
}
$src = dirname(__DIR__) . '/src';
foreach (['noyau', 'comptes', 'securite', 'donnees', 'modules', 'lettres', 'outlook', 'sharepoint', 'import', 'pennylane', 'api', 'demo'] as $m) {
    require "$src/$m.php";
}
demo_initialiser();  // mode démo : données fictives créées au premier lancement (rien sinon)

// En-têtes de sécurité (OWASP) : rien d'extérieur ne s'exécute, la page ne s'affiche dans aucun cadre, rien ne fuit vers d'autres sites.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; "
    . "connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
if (en_https()) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
}

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$chemin = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!str_starts_with($chemin, '/api/')) {
    if ($chemin === '/' || $chemin === '/index.html') {
        if (microsoft_flux_attendu((string)($_GET['state'] ?? ''))) {  // retour de la page Microsoft : connexion au CRM
            (new Api())->retour_microsoft();
        }
        if (MODE_DEMO && isset($_GET['demo'])) {  // démo seulement : connexion directe au compte de démonstration (captures d'écran)
            ouvrir_session(['identifiant' => DEMO_IDENTIFIANT, 'nom' => DEMO_NOM], 'mot_de_passe');
            $page = preg_replace('/[^a-z]/', '', (string)($_GET['page'] ?? ''));
            header('Location: /' . ($page !== '' ? "#$page" : ''), true, 302);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile("$src/page.html");
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Introuvable.');
}

try {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > ($chemin === '/api/clients/import' ? 8 : 2) * 1024 * 1024) {
        arret(413, 'Envoi trop volumineux.');
    }
    (new Api())->traiter($methode, $chemin);
} catch (Arret $e) {
    json_reponse($e->statut, $e->corps);
} catch (ErreurSaisie $e) {
    json_reponse(422, ['champs' => $e->champs]);
} catch (Throwable $e) {
    error_log('CRM Cabinet : ' . $e);
    json_reponse(500, ['erreur' => 'Erreur du serveur.']);
}
