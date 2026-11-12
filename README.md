# CRM Cabinet

[![Tests](https://github.com/medmehdimobtahij-ship-it/crm-cabinet-comptable/actions/workflows/tests.yml/badge.svg)](https://github.com/medmehdimobtahij-ship-it/crm-cabinet-comptable/actions/workflows/tests.yml)

CRM web pour un cabinet d'expertise comptable : suivi des prospects jusqu'à la signature de la lettre de mission, liste des
clients, génération des lettres de mission Word, et liaison avec Microsoft 365 (Outlook, SharePoint) et Pennylane.

Écrit en PHP 8 sans framework, avec une interface en JavaScript sans bibliothèque. Il a été développé pour les besoins réels d'un cabinet ;
ce dépôt en est la **version publique et anonymisée** : identité visuelle neutre, réglages d'exemple, données de démonstration
inventées, modèles de lettres fictifs.

![Pipeline des prospects](docs/pipeline.png)

| Clients et dossiers SharePoint par année | Lettres de mission générées |
|---|---|
| ![Clients](docs/clients.png) | ![Livrables](docs/livrables.png) |

## Le problème métier

Dans un petit cabinet d'expertise comptable, le parcours d'un nouveau client est éclaté entre plusieurs outils : demandes
reçues par mail, relances notées à la main, lettre de mission copiée d'un modèle Word puis rangée dans le bon dossier SharePoint,
dossier client créé à la main pour chaque nouvelle année, informations du client dispersées entre Pennylane et SharePoint.

Les risques sont concrets : un prospect oublié faute de relance, une lettre de mission envoyée avec une zone restée à compléter,
une mission acceptée sans avoir informé le confrère qui suivait le dossier (règle de confraternité), un client présent dans Pennylane
mais sans dossier dans SharePoint, ou l'inverse.

Le CRM regroupe ce parcours en un seul outil, partagé par les collaborateurs du cabinet.

## Ce que fait le CRM

**Pipeline des prospects**
- Quatre étapes (à qualifier, rendez-vous découverte, proposition envoyée, lettre de mission envoyée), en glisser-déposer.
- Relances planifiées, alertes « relance en retard » ou « sans relance prévue », gain potentiel en honoraires annuels.
- Création assistée : recherche dans le répertoire public des entreprises (siège, forme, dirigeants), détection des contacts déjà connus.
- Reprise du dossier d'un confrère : la lettre de mission est bloquée tant que la lettre confraternelle n'est pas faite.
- Annulation (Ctrl+Z / Ctrl+Y) propre à chaque utilisateur, refusée si un collègue a modifié les prospects entre-temps.

**Clients**
- Liste des clients du cabinet, complétée par une synchronisation Pennylane en lecture seule, avec un aperçu avant toute écriture.
- Onboarding : pièces à demander selon le type de client (société, entreprise individuelle, LMNP, particulier, association).
- Dossiers SharePoint par client et par année (comptable, permanent, juridique annuel) : liens directs, et création des dossiers manquants à partir d'un modèle.
- Import CSV ou copier-coller depuis Excel, avec aperçu ligne par ligne et sauvegarde de l'ancienne liste ; export CSV.
- Archivage sans perte d'information.

**Lettres de mission**
- Neuf types de mission (présentation ou établissement des comptes, TVA, BNC, BIC, LMNP, IR, revenus fonciers, IFI).
- Le document Word est rempli à partir de la fiche, sans bibliothèque externe (un .docx est une archive zip de XML). Les zones
  non renseignées restent surlignées en jaune et l'interface les liste.
- Annexe de répartition des travaux (tableau généré), lettre confraternelle sur le papier du cabinet.
- Conversion en PDF et rangement dans SharePoint par Microsoft Graph, sans jamais écraser ni supprimer un fichier existant.

**Messagerie Outlook (Microsoft Graph)**
- Chaque utilisateur connecte sa propre boîte. Les échanges avec chaque prospect sont affichés, avec une pastille pour les mails non lus.
- Envoi et réponse depuis le CRM, avec lettres de mission jointes en PDF. Après l'envoi, la relance suivante est planifiée.
- Éditeur de signature et nettoyage du HTML des mails.

**Sécurité**
- Mots de passe hachés (`password_hash`), règle de robustesse de la CNIL (délibération 2022-100), jamais stockés ni journalisés en clair.
- Protection contre la force brute : 5 échecs en 5 minutes bloquent l'adresse IP, 10 échecs en 15 minutes bloquent le compte.
- Sessions : cookie `HttpOnly`, `SameSite=Strict`, préfixe `__Host-` en HTTPS. Identifiant régénéré à la connexion, expiration après
  2 h d'inactivité (12 h au maximum), session liée au navigateur, bouton « fermer toutes mes sessions ».
- Connexion Microsoft (OpenID Connect avec PKCE) : signature RS256 du jeton d'identité vérifiée, compte lié par identifiants Entra
  (`oid` + `tid`), mode « double authentification obligatoire ».
- Jetons Outlook et Pennylane chiffrés sur disque (AES-256-GCM).
- Journal de sécurité (connexions, refus, blocages, changements de mode) consultable par chaque utilisateur.
- En-têtes OWASP (CSP stricte, `X-Frame-Options`, `Referrer-Policy`…). Les requêtes d'écriture doivent porter un en-tête
  propre à l'interface (`X-CRM`). Les pièces jointes sont servies dans un bac à sable, et `data/` reste hors du dossier public.

## Stack

| Couche | Choix |
|---|---|
| Serveur | PHP 8.1+ sans framework ni Composer (extensions : curl, openssl, zip, dom, mbstring) |
| Stockage | Fichiers JSON dans `data/`, écriture atomique (fichier temporaire puis renommage) sous verrou exclusif (`flock`) |
| Interface | Une page HTML, JavaScript sans bibliothèque (`public/app.js`), CSS avec thème clair et sombre |
| Intégrations | Microsoft Graph (Outlook, SharePoint, conversion PDF), OAuth 2.0 / OpenID Connect, API Pennylane (Firm, lecture seule), API publique Recherche d'entreprises |
| Tests | Python : pytest, requests, python-docx, cryptography, avec de faux serveurs Microsoft, SharePoint et Pennylane |
| Hébergement | Apache (`.htaccess` fournis) ou serveur intégré `php -S` |

## Architecture

```
public/        racine web : index.php (point d'entrée), routeur.php (pour php -S), app.js, styles.css, logo.svg
src/           code serveur (jamais servi directement)
modeles/       modèles Word des lettres de mission (fictifs, régénérables)
outils/        scripts : premier lancement, génération des modèles, pont local vers Claude Desktop
tests/         tests de l'API par HTTP contre le vrai serveur PHP
docs/          captures d'écran de la démo
```

| Fichier | Rôle |
|---|---|
| `public/index.php` | Point d'entrée unique : en-têtes de sécurité, page de l'interface, aiguillage vers l'API |
| `src/noyau.php` | Socle : réglages (`config.json` ou variables `CRM_…`), JSON atomique, verrou, dates, chiffrement |
| `src/api.php` | API JSON (une soixantaine de routes), annulation par utilisateur, contrôle des saisies |
| `src/comptes.php` | Comptes, hachage, sessions, limitation des essais de connexion |
| `src/securite.php` | Connexion Microsoft, modes de connexion, journal de sécurité |
| `src/donnees.php` | Prospects : étapes, relances, alertes, doublons, honoraires, historique |
| `src/modules.php` | Clients, dossiers SharePoint par année, répertoire des entreprises, rapprochement des noms |
| `src/lettres.php` | Moteur des lettres de mission (Word en XML), annexe 2, lettre confraternelle |
| `src/outlook.php` | Client Microsoft Graph : OAuth avec PKCE, jetons chiffrés, mails, pièces jointes, signatures |
| `src/sharepoint.php` | Rangement des lettres, conversion PDF, création des dossiers clients |
| `src/pennylane.php` | Synchronisation Pennylane en lecture seule, complément des fiches via Claude Desktop |
| `src/import.php` | Import CSV ou presse-papiers, avec aperçu et sauvegarde |
| `src/demo.php` | Mode démo : données fictives, répertoire simulé, aucun appel externe |
| `public/app.js` | Interface : pipeline, fiches, clients, livrables, messagerie, paramètres |
| `tests/test_api.py` | 44 tests, dont de faux serveurs Microsoft, SharePoint et Pennylane |

## Essayer en 30 secondes

Il faut **PHP 8.1 ou plus récent** dans le PATH, avec les extensions `dom`, `mbstring`, `openssl` et `zip` (sous Windows, à activer
dans `php.ini`). Aucun compte externe ni aucune connexion Internet ne sont nécessaires.

```bash
# Windows
demo.bat

# Linux / macOS
./demo.sh

# ou à la main, depuis la racine du projet
CRM_DEMO=1 php -S 127.0.0.1:8080 -t public public/routeur.php      # PowerShell : $env:CRM_DEMO=1; php -S ...
```

Ouvrez ensuite http://127.0.0.1:8080 et connectez-vous avec l'identifiant **demo** et le mot de passe **demo** (ils sont affichés
sur la page de connexion).

Au premier lancement, la démo crée `data-demo/` : 10 clients, 8 prospects répartis dans le pipeline (dont un perdu et un signé)
et 3 lettres de mission produites par le vrai moteur de génération. Toutes les données sont inventées. Pour repartir de zéro,
supprimez `data-demo/`. Avec les lanceurs, `PHP=chemin/vers/php` et `PORT=8081` permettent de choisir l'exécutable et le port.

En mode démo, Microsoft 365 et Pennylane ne sont jamais appelés. Les actions concernées (connexion Outlook, PDF et rangement
SharePoint, synchronisation Pennylane) renvoient le message « Mode démo : … désactivée ici ». En plus, la fonction HTTP sortante
refuse toute requête. La recherche dans le répertoire des entreprises est simulée avec des sociétés fictives.

## Installation réelle

1. Copier `config.exemple.json` en `config.json` (ignoré par git) et renseigner le nom du cabinet, son domaine, les comptes,
   l'identifiant de l'application Microsoft Entra, l'adresse de la bibliothèque SharePoint et les noms des dossiers.
   Chaque clé peut aussi venir d'une variable d'environnement : `CRM_MICROSOFT_CLIENT_ID`, `CRM_SHAREPOINT`, etc.
2. `php outils/initialiser.php` crée les comptes et affiche une seule fois leurs mots de passe provisoires.
3. Faire pointer la racine du site sur `public/`. `data/` et `src/` ne doivent jamais être servis (les `.htaccess` fournis les bloquent).
4. Remplacer les modèles de `modeles/lettres-de-mission/` par ceux du cabinet, en gardant les phrases repères (voir `lettres_regles`
   dans `src/lettres.php` et `outils/generer_modeles.py`).

## Tests

```bash
pip install pytest requests python-docx cryptography
python -m pytest tests -q            # PHP du PATH, ou CRM_PHP=chemin/vers/php
```

Résultat obtenu sur ce dépôt (PHP 8.3, Python 3.12) :

```
44 passed
```

Les tests démarrent le vrai serveur PHP (`php -S`) sur un dossier de données temporaire. Microsoft (connexion, Graph, SharePoint,
conversion PDF) et Pennylane sont remplacés par un faux serveur local : le code d'intégration réel est donc exécuté de bout en bout,
sans toucher à aucune boîte mail ni à aucun SharePoint. Les tests couvrent notamment :
- l'authentification : hachage, blocage après trop d'essais, jetons Microsoft falsifiés refusés, double authentification ;
- l'écriture simultanée par deux utilisateurs (aucune perte) et l'annulation propre à chacun ;
- la génération des lettres (aucune zone restée à compléter, annexe 2, lettre confraternelle) et leur rangement sans écrasement ;
- la messagerie de bout en bout, l'import CSV, la synchronisation Pennylane, la création des dossiers SharePoint ;
- le mode démo : données fictives, aucun appel externe, connexion directe réservée à la démo.

## Limites assumées

- **Stockage en fichiers JSON** : il suffit pour un cabinet de quelques utilisateurs et quelques centaines de dossiers, où les
  écritures sont rares et protégées par un verrou. Au-delà, il faudrait une base de données (SQLite ou PostgreSQL).
- **Pas de framework** : c'est un choix de déploiement (un hébergement mutualisé avec PHP suffit, sans Composer). En contrepartie,
  le routage, la validation et les sessions sont écrits à la main.
- **Intégrations testées contre des simulations** : dans ce dépôt, Microsoft 365 et Pennylane ne sont vérifiés que contre les faux
  serveurs des tests, qui reproduisent les réponses utilisées par le code, pas toute l'API réelle.
- **Modèles de lettres fictifs** : ils montrent la mécanique de remplissage, mais n'ont aucune valeur juridique.
- **Quelques fonctions propres à Windows** : ouvrir un dossier client dans l'Explorateur, lire les signatures Outlook du poste et
  lancer Claude Desktop ne fonctionnent que lorsque le CRM tourne sur le PC de l'utilisateur.
- **Interface en français**, pensée pour un écran d'ordinateur. Les libellés des dossiers affichés dans l'interface sont fixes,
  alors que les chemins SharePoint réels sont réglables.
- La signature électronique des lettres est indiquée comme « en développement ».

## Licence et auteur

Code sous licence MIT (voir `LICENSE`). Auteur : **Mehdi Mobtahij**.
