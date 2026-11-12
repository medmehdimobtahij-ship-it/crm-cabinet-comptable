"""API du CRM (version PHP) : mêmes vérifications que la version Python, par HTTP, contre le vrai serveur PHP.

Données dans un dossier temporaire (jamais data/ ni le OneDrive). Microsoft est remplacé par un faux serveur local :
la connexion Outlook, les mails et SharePoint (rangement des lettres, conversion en PDF, liste des dossiers) passent par le
vrai code PHP, sans toucher à aucune boîte ni au SharePoint du cabinet.
Lancer : python -m pytest tests -q   (PHP 8.1+ du PATH, ou variable CRM_PHP=chemin vers php)
"""
import base64
import io
import json
import os
import shutil
import socket
import subprocess
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import re
from urllib.parse import parse_qs, unquote, urlparse

import pytest
import requests
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from docx import Document

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP = os.environ.get("CRM_PHP") or shutil.which("php") or "php"  # PHP 8.1+ du PATH, ou variable CRM_PHP
DOSSIER = tempfile.mkdtemp(prefix="crm-php-")
H = {"X-CRM": "1"}


def port_libre():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    p = s.getsockname()[1]
    s.close()
    return p


# ---------- Faux Microsoft (connexion + Graph) ----------
MS = {"envoyes": [], "lus": []}
MAIL = {"id": "m1", "conversationId": "c1", "subject": "Votre devis", "from": {"emailAddress": {"address": "d@acme.fr", "name": "Dupont"}},
        "toRecipients": [{"emailAddress": {"address": "moi@cabinet.fr"}}], "ccRecipients": [], "receivedDateTime": "2099-11-01T09:00:00Z",
        "isRead": False, "isDraft": False, "parentFolderId": "inbox", "hasAttachments": True, "bodyPreview": "Bonjour, merci", "webLink": ""}


SP = {}  # id -> {nom, parent, dossier, contenu} : faux SharePoint du cabinet


def sp_reinitialiser():
    SP.clear()
    SP["lm"] = {"nom": "Lettres de mission", "parent": None, "dossier": True}
    SP["cp"] = {"nom": "Comptabilite", "parent": None, "dossier": True}


def sp_ajouter(parent, nom, dossier=False, contenu=b""):
    i = f"i{len(SP)}"
    SP[i] = {"nom": nom, "parent": parent, "dossier": dossier, "contenu": contenu}
    return i


def sp_enfant(parent, nom):
    return next((i for i, x in SP.items() if x["parent"] == parent and x["nom"].lower() == nom.lower()), None)


def sp_vue(i):
    x = SP[i]
    return {"id": i, "name": x["nom"], **({"folder": {}} if x["dossier"] else {"file": {}}), "parentReference": {"driveId": "d1"}}


CLE_MS = rsa.generate_private_key(public_exponent=65537, key_size=2048)  # clé de signature du faux Microsoft
CLIENT_ID = "00000000-0000-4000-8000-00000000c0de"  # application Entra fictive
TENANT_CABINET = "11111111-2222-3333-4444-555555555555"
TENANT_PERSONNEL = "9188040d-6c67-4c5b-b112-36a304b66dad"


def b64u(o):
    return base64.urlsafe_b64encode(o).decode().rstrip("=")


def jwks():
    n = CLE_MS.public_key().public_numbers()
    entier = lambda v: b64u(v.to_bytes((v.bit_length() + 7) // 8, "big"))
    return {"keys": [{"kty": "RSA", "kid": "cle1", "use": "sig", "n": entier(n.n), "e": entier(n.e)}]}


def signer(infos, kid="cle1", cle=None):
    debut = b64u(json.dumps({"alg": "RS256", "kid": kid, "typ": "JWT"}).encode()) + "." + b64u(json.dumps(infos).encode())
    return debut + "." + b64u((cle or CLE_MS).sign(debut.encode(), padding.PKCS1v15(), hashes.SHA256()))


def id_token(adresse):
    tid = TENANT_PERSONNEL if adresse.endswith("@outlook.com") else MS.get("tid", TENANT_CABINET)
    infos = {"aud": MS.get("aud", CLIENT_ID), "iss": f"https://login.microsoftonline.com/{tid}/v2.0", "tid": tid,
             "oid": MS.get("oid", "oid-" + adresse), "preferred_username": adresse, "name": MS.get("nom", adresse.split("@")[0]),
             "iat": int(time.time()), "nbf": int(time.time()), "exp": int(time.time()) + 3600, "nonce": MS.get("nonce", "")}
    if MS.get("mfa"):
        infos["amr"] = ["pwd", "mfa"]
    return signer(infos, cle=MS.get("cle_pirate"))


def code_pour(url):
    # Le vrai Microsoft lie le nonce de la page d'autorisation au code : le faux le reprend de l'adresse.
    MS["nonce"] = parse_qs(urlparse(url).query)["nonce"][0]
    return "c1"


class FauxMicrosoft(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def repondre(self, code, corps=None, brut=None, type_="application/json"):
        data = brut if brut is not None else json.dumps(corps or {}).encode()
        self.send_response(code)
        self.send_header("Content-Type", type_)
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def corps(self, brut=False):
        n = int(self.headers.get("Content-Length") or 0)
        data = self.rfile.read(n) if n else b""
        return data if brut else data.decode()

    def do_PUT(self):
        u = urlparse(self.path)
        contenu = self.corps(brut=True)
        if self.headers.get("Authorization") != "Bearer t":
            return self.repondre(401, {"error": {"message": "jeton"}})
        m = re.match(r"^/drives/d1/items/([^/:]+):/(.+):/content$", u.path)
        if m and m.group(1) in SP:
            nom = unquote(m.group(2))
            if sp_enfant(m.group(1), nom):
                return self.repondre(409, {"error": {"code": "nameAlreadyExists", "message": "existe"}})
            return self.repondre(201, sp_vue(sp_ajouter(m.group(1), nom, contenu=contenu)))
        self.repondre(404, {"error": {"message": "inconnu"}})

    def do_POST(self):
        u = urlparse(self.path)
        corps = self.corps()
        if u.path.endswith("/devicecode"):
            return self.repondre(400, {"error": "invalid_client", "error_description": "AADSTS70002: must be marked as 'mobile'"})
        if u.path.endswith("/token"):
            q = parse_qs(corps)
            if q.get("grant_type") == ["authorization_code"] and q.get("code") == ["c1"] and q.get("code_verifier"):
                return self.repondre(200, {"access_token": "t", "refresh_token": "r", "expires_in": 3600, "id_token": id_token(MS.get("adresse", "moi@cabinet.fr"))})
            return self.repondre(400, {"error": "invalid_grant", "error_description": "AADSTS70008: expired"})
        if u.path == "/$batch":  # lots de lectures (contenu des dossiers comptables)
            MS["lots"] = MS.get("lots", 0) + 1
            reponses = []
            for q in json.loads(corps)["requests"]:
                m = re.match(r"^/drives/d1/items/([^/?]+)/children", q["url"])
                reponses.append({"id": q["id"], "status": 200, "body": {"value": [sp_vue(i) for i, x in SP.items() if x["parent"] == m.group(1)]}}
                                if m and m.group(1) in SP else {"id": q["id"], "status": 404, "body": {}})
            return self.repondre(200, {"responses": reponses})
        m = re.match(r"^/drives/d1/items/([^/]+)/copy$", u.path)
        if m and m.group(1) in SP:  # copie (Microsoft la fait en arrière-plan) : ici immédiate, dossiers seulement
            cible, nom = json.loads(corps)["parentReference"]["id"], json.loads(corps)["name"]
            MS.setdefault("copies", []).append((m.group(1), cible, nom))
            def copier(src, parent, n):
                nouveau = sp_ajouter(parent, n, dossier=True)
                for i, x in list(SP.items()):
                    if x["parent"] == src and x["dossier"]:
                        copier(i, nouveau, x["nom"])
            copier(m.group(1), cible, nom)
            return self.repondre(202, brut=b"")
        m = re.match(r"^/drives/d1/items/([^/]+)/children$", u.path)
        if m and m.group(1) in SP:
            nom = json.loads(corps)["name"]
            if sp_enfant(m.group(1), nom):
                return self.repondre(409, {"error": {"code": "nameAlreadyExists", "message": "existe"}})
            return self.repondre(201, sp_vue(sp_ajouter(m.group(1), nom, dossier=True)))
        if u.path.endswith("/createReply"):
            return self.repondre(201, {"id": "r1", "body": {"content": "<div>cité</div>"}})
        if u.path == "/me/messages":
            MS["envoyes"].append(("nouveau", json.loads(corps)))
            return self.repondre(201, {"id": "n1", "body": {"content": ""}})
        if u.path.endswith("/attachments"):
            MS["envoyes"].append(("pj", json.loads(corps)["name"]))
            if json.loads(corps).get("isInline"):
                MS["envoyes"].append(("en_ligne", json.loads(corps)["contentId"]))
            return self.repondre(201, {})
        if u.path.endswith("/send"):
            MS["envoyes"].append(("envoi", u.path.split("/")[3]))
            return self.repondre(202, brut=b"")
        self.repondre(404, {"error": {"message": "inconnu"}})

    def do_PATCH(self):
        u = urlparse(self.path)
        corps = json.loads(self.corps() or "{}")
        if corps.get("isRead"):
            MS["lus"].append(u.path.split("/")[-1])
        else:
            MS["envoyes"].append(("brouillon", corps))
        self.repondre(200, {})

    def do_GET(self):
        u = urlparse(self.path)
        if u.path == "/api/external/firm/v1/companies":  # faux Pennylane (API Firm, lecture seule)
            MS.setdefault("pennylane", []).append((self.command, self.headers.get("Authorization")))
            if self.headers.get("Authorization") != "Bearer pl-ok":
                return self.repondre(401, {"error": "unauthorized"})
            societes = [{"id": 1, "name": "ATELIER DUPAIN", "siren": "000001016", "city": "Lyon", "postal_code": "69003", "address": "1 rue X"},
                        {"id": 7, "name": "NOUVELLE SAS", "siren": "", "city": "Paris", "postal_code": "75001", "address": ""}]
            return self.repondre(200, {"items": societes, "total_pages": 1, "current_page": 1, "total_items": 2, "per_page": 1000})
        if u.path == "/discovery/v2.0/keys":  # clés publiques de signature (publiques : sans jeton)
            MS["cles_lues"] = MS.get("cles_lues", 0) + 1
            return self.repondre(200, jwks())
        if u.path.startswith("/telechargement/"):  # adresse de téléchargement pré-signée : sans jeton
            return self.repondre(200, brut=b"%PDF-1.7 converti " + SP[u.path.split("/")[-1]]["contenu"][:2], type_="application/pdf")
        if self.headers.get("Authorization") != "Bearer t":
            return self.repondre(401, {"error": {"message": "jeton"}})
        if u.path.startswith("/shares/u!"):
            adresse = base64.urlsafe_b64decode(u.path.split("u!")[1].split("/")[0] + "==").decode()
            if MS.get("sp_refus"):
                return self.repondre(403, {"error": {"code": "accessDenied", "message": "refusé"}})
            if adresse == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Lettres%20de%20mission":
                return self.repondre(200, sp_vue("lm"))
            if adresse == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Comptabilite":
                return self.repondre(200, sp_vue("cp"))
            if adresse == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Permanent" and "so" in SP:
                return self.repondre(200, sp_vue("so"))
            if adresse == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Juridique%20annuel" and "ja" in SP:
                return self.repondre(200, sp_vue("ja"))
            return self.repondre(404, {"error": {"message": "introuvable"}})
        m = re.match(r"^/drives/d1/items/([^/]+)(/children|/content)?$", u.path)
        if m and m.group(1) in SP:
            if m.group(2) == "/children":
                return self.repondre(200, {"value": [sp_vue(i) for i, x in SP.items() if x["parent"] == m.group(1)]})
            if m.group(2) == "/content" and parse_qs(u.query).get("format") == ["pdf"]:
                self.send_response(302)
                self.send_header("Location", f"/telechargement/{m.group(1)}")
                self.send_header("Content-Length", "0")
                self.end_headers()
                return
            return self.repondre(200, sp_vue(m.group(1)))
        if u.path == "/me/mailFolders/sentitems/messages":
            return self.repondre(200, {"value": [
                {"id": "e1", "body": {"content": "<p>Sans signature</p>"}},
                {"id": "e2", "body": {"content": '<div>Bonjour,</div><div id="Signature"><table><tr><td><img src="cid:logo1" width="80"></td>'
                                                 '<td><b style="color:#2f6feb">Claire MOREAU</b><br>CABINET EXEMPLE<br><a href="tel:+33600000000">06 00 00 00 00</a>'
                                                 '</td></tr></table></div><div id="divRplyFwdMsg">De : client</div><p>texte cité</p>'}}]})
        if u.path == "/me/messages/e2/attachments":
            return self.repondre(200, {"value": [{"contentId": "logo1", "contentType": "image/png", "contentBytes": "iVBORw0KGgo=", "isInline": True}]})
        if u.path.startswith("/me/mailFolders/"):
            return self.repondre(200, {"id": u.path.split("/")[-1]})
        if u.path == "/me/messages":
            q = parse_qs(u.query)
            if q.get("$filter") == ["isRead eq false"]:  # mails non lus (pastille du pipeline)
                MS["recherches_non_lus"] = MS.get("recherches_non_lus", 0) + 1
                autres = {**MAIL, "id": "m9", "from": {"emailAddress": {"address": "inconnu@ailleurs.fr"}}}
                return self.repondre(200, {"value": ([MAIL] if "m1" not in MS["lus"] else []) + [autres]})
            if "ACME CONSEIL" in q.get("$search", [""])[0] or "Jean DUPONT" in q.get("$search", [""])[0]:  # coordonnées d'une société
                adr = lambda a, n="": {"emailAddress": {"address": a, "name": n}}
                return self.repondre(200, {"value": [
                    {"id": "m7", "from": adr("jean.dupont@acme-conseil.fr", "Jean Dupont"), "toRecipients": [adr("moi@cabinet.fr")],
                     "ccRecipients": [adr("compta@acme-conseil.fr"), adr("collegue@cabinet.fr")], "receivedDateTime": "2026-10-02T09:00:00Z", "parentFolderId": "inbox"},
                    {"id": "m8", "from": adr("no-reply@impots.gouv.fr"), "toRecipients": [adr("moi@cabinet.fr")], "receivedDateTime": "2026-10-01T09:00:00Z", "parentFolderId": "inbox"}]})
            return self.repondre(200, {"value": [MAIL] if "d@acme.fr" in q.get("$search", [""])[0] else []})
        if u.path == "/me/messages/m7":
            return self.repondre(200, {"uniqueBody": {"content": "Bonjour,\nCi-joint.\nJean Dupont\nGérant\nMob. : 06.12.34.56.78 | +33 1 45 67 89 10\nSIRET 00000101600015"}, "body": {"content": ""}})
        if u.path == "/me/messages/m1":
            return self.repondre(200, {"uniqueBody": {"content": "Bonjour, merci pour le devis."}, "body": {"content": "Bonjour, merci pour le devis. (cité)"}, "isRead": False})
        if u.path == "/me/messages/m1/attachments":
            return self.repondre(200, {"value": [{"id": "a1", "name": "devis.pdf", "size": 9, "isInline": False}, {"id": "a2", "name": "logo.png", "isInline": True}]})
        if u.path == "/me/messages/m1/attachments/a1":
            return self.repondre(200, {"name": "devis.pdf", "contentType": "application/pdf"})
        if u.path == "/me/messages/m1/attachments/a1/$value":
            return self.repondre(200, brut=b"%PDF-1.4 x", type_="application/pdf")
        self.repondre(404, {"error": {"message": "inconnu"}})


@pytest.fixture(scope="session")
def serveur():
    ms = ThreadingHTTPServer(("127.0.0.1", port_libre()), FauxMicrosoft)
    threading.Thread(target=ms.serve_forever, daemon=True).start()
    port = port_libre()
    reseau = os.path.join(DOSSIER + "-reseau"); os.makedirs(os.path.join(reseau, "ATELIER DUPAIN"), exist_ok=True)  # faux dossier comptable du PC
    juridique = DOSSIER + "-juridique"; os.makedirs(os.path.join(juridique, "2025", "ATELIER DUPAIN"), exist_ok=True)  # faux juridique annuel
    societe = DOSSIER + "-societe"; os.makedirs(os.path.join(societe, "ATELIER DUPAIN"), exist_ok=True)  # faux dossier permanent
    sigs = DOSSIER + "-signatures"; os.makedirs(os.path.join(sigs, "Perso_fichiers"), exist_ok=True)  # fausses signatures d'Outlook du PC
    with open(os.path.join(sigs, "Cabinet (moi@cabinet.fr).htm"), "wb") as f:
        f.write('<html><head><meta charset="windows-1252"></head><body><p><b>Claire</b> · Collaborateur comptable</p></body></html>'.encode("cp1252"))
    with open(os.path.join(sigs, "Perso.htm"), "w", encoding="utf-8") as f:
        f.write('<html><body><p>Claire perso</p><img src="Perso_fichiers/image001.png"></body></html>')
    with open(os.path.join(sigs, "Perso_fichiers", "image001.png"), "wb") as f:
        f.write(b"\x89PNG\r\n")
    env = dict(os.environ, CRM_DEMO="0", CRM_MICROSOFT_CLIENT_ID=CLIENT_ID, CRM_DATA=DOSSIER, CRM_RESEAU=reseau, CRM_JURIDIQUE=juridique, CRM_SOCIETE=societe, CRM_SIGNATURES=sigs, CRM_SANS_REGISTRE="1", CRM_PENNYLANE=f"http://127.0.0.1:{ms.server_port}", CRM_SANS_CLAUDE="1", CRM_SANS_EXPLORATEUR="1", CRM_GRAPH=f"http://127.0.0.1:{ms.server_port}",
               CRM_AUTORITE=f"http://127.0.0.1:{ms.server_port}", PHP_CLI_SERVER_WORKERS="4")
    p = subprocess.Popen([PHP, "-S", f"127.0.0.1:{port}", "-t", "public", "public/routeur.php"], cwd=RACINE, env=env,
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    url = f"http://127.0.0.1:{port}"
    for _ in range(50):
        try:
            requests.get(url + "/", timeout=1)
            break
        except requests.ConnectionError:
            time.sleep(0.1)
    yield {"url": url, "env": env}
    p.terminate()
    ms.shutdown()


class Client(requests.Session):
    def __init__(self, base):
        super().__init__()
        self.base = base

    def request(self, methode, url, *a, **k):
        return super().request(methode, self.base + url, *a, **k)


@pytest.fixture()
def app(serveur):
    for n in os.listdir(DOSSIER):
        p = os.path.join(DOSSIER, n)
        shutil.rmtree(p) if os.path.isdir(p) else os.remove(p)
    with open(os.path.join(DOSSIER, "clients.json"), "w", encoding="utf-8") as f:
        json.dump({"maj": "2026-10-30", "clients": [{"id": "PL-1", "nom": "ATELIER DUPAIN", "siren": "", "statut": "ACTIF", "type": "SOCIETE",
                                                      "pennylane": "https://app.pennylane.com/companies/1"}]}, f)
    mdp = json.loads(subprocess.run([PHP, "outils/initialiser.php", "--json"], cwd=RACINE, env=serveur["env"], capture_output=True,
                                    text=True, check=True).stdout)
    MS["envoyes"].clear(); MS["lus"].clear()
    for k in ("adresse", "sp_refus", "nonce", "mfa", "oid", "tid", "aud", "cle_pirate", "nom", "pennylane"):
        MS.pop(k, None)
    sp_reinitialiser()
    return {"url": serveur["url"], "mdp": mdp, "client": lambda: Client(serveur["url"])}


def connecter(app, qui="claire"):
    c = app["client"]()
    r = c.post("/api/connexion", json={"identifiant": qui, "mot_de_passe": app["mdp"][qui]})
    assert r.status_code == 200, r.text
    return c


def connecter_boite(c):
    """Connexion Microsoft complète (page Microsoft simulée) : la boîte et les fichiers du compte sont accessibles."""
    r = c.post("/api/outlook/connexion", headers=H).json()
    etat = parse_qs(urlparse(r["url"]).query)["state"][0]
    code = code_pour(r["url"])
    assert c.post("/api/outlook/connexion/retour", json={"adresse": f"http://localhost/?code={code}&state={etat}"}, headers=H).status_code == 200
    return r


def creer(c, nom="ACME"):
    r = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "type_pro": "SOCIETE", "denomination": nom, "origine": "EXISTANTE",
                                       "contact": {"nom": "Dupont", "email": "d@acme.fr", "role": "REPRESENTANT_LEGAL"}}, headers=H)
    assert r.status_code == 200, r.text
    return r.json()["resultat"]


def prospect(c, pid):
    return next(x for x in c.get("/api/etat").json()["prospects"] if x["id"] == pid)


# ---------- Mêmes vérifications que la version Python ----------
def test_tout_exige_une_connexion(app):
    c = app["client"]()
    assert c.get("/api/etat").status_code == 401
    assert c.get("/api/clients").status_code == 401
    assert c.post("/api/connexion", json={"identifiant": "claire", "mot_de_passe": "faux"}).status_code == 401
    page = c.get("/")
    assert page.status_code == 200 and "app.js" in page.text  # la page de connexion, elle, est publique
    assert "frame-ancestors 'none'" in page.headers["Content-Security-Policy"] and page.headers["X-Frame-Options"] == "DENY"
    assert c.get("/app.js").status_code == 200 and c.get("/../data/comptes.json").status_code == 404


def test_mots_de_passe_haches_et_essais_limites(app):
    texte = open(os.path.join(DOSSIER, "comptes.json"), encoding="utf-8").read()
    assert app["mdp"]["claire"] not in texte and "$2y$" in texte
    c = app["client"]()
    for _ in range(5):
        c.post("/api/connexion", json={"identifiant": "paul", "mot_de_passe": "x"})
    assert c.post("/api/connexion", json={"identifiant": "paul", "mot_de_passe": app["mdp"]["paul"]}).status_code == 429


def test_mot_de_passe_prepare_pour_l_hebergement(app):
    """Format écrit par un outil de préparation d'hébergement (PBKDF2) : accepté à la connexion."""
    import hashlib
    sel = os.urandom(16)
    h = hashlib.pbkdf2_hmac("sha256", b"mot-de-passe-2026", sel, 1000)
    comptes = json.load(open(os.path.join(DOSSIER, "comptes.json"), encoding="utf-8"))
    comptes["paul"]["hash"] = f"pbkdf2_sha256$1000${base64.b64encode(sel).decode()}${base64.b64encode(h).decode()}"
    json.dump(comptes, open(os.path.join(DOSSIER, "comptes.json"), "w", encoding="utf-8"))
    c = app["client"]()
    assert c.post("/api/connexion", json={"identifiant": "paul", "mot_de_passe": "mot-de-passe-2026"}).json()["nom"] == "Paul Bernard"


def test_requete_sans_en_tete_refusee(app):
    c = connecter(app)
    assert c.post("/api/prospects", json={"nature": "PARTICULIER", "denomination": "X"}).status_code == 403


def test_pipeline_creation_deplacement_perdu(app):
    c = connecter(app)
    pid = creer(c)
    champs = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "denomination": ""}, headers=H).json()["champs"]
    assert champs["denomination"] and champs["origine"]  # l'origine est obligatoire pour un professionnel
    reprise = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "denomination": "X", "origine": "REPRISE"}, headers=H).json()["champs"]
    assert reprise == {"cabinet_precedent": "Indiquer le cabinet précédent, ou cocher « À renseigner plus tard »."}  # reprise : le cabinet précédent aussi
    assert c.post(f"/api/prospects/{pid}/deplacer", json={"etape": "RDV_DECOUVERTE"}, headers=H).status_code == 200
    p = prospect(c, pid)
    assert p["etape"] == "RDV_DECOUVERTE" and p["contacts"][0]["nom"] == "Dupont"
    assert c.post(f"/api/prospects/{pid}/perdu", json={"motif": ""}, headers=H).status_code == 422
    assert c.post(f"/api/prospects/{pid}/perdu", json={"motif": "PRIX"}, headers=H).status_code == 200
    assert c.post("/api/prospects/" + "0" * 32 + "/perdu", json={"motif": "PRIX"}, headers=H).status_code == 404


def test_modifier_la_fiche(app):
    c = connecter(app)
    pid = creer(c)
    assert c.post(f"/api/prospects/{pid}/modifier", json={"origine": "REPRISE"}, headers=H).json()["champs"] == {"cabinet_precedent": "Indiquer le cabinet précédent, ou cocher « À renseigner plus tard »."}
    r = c.post(f"/api/prospects/{pid}/modifier", json={"origine": "REPRISE", "cabinet_precedent": "Cabinet X", "id": "pirate"}, headers=H).json()
    assert r["resultat"] is True and r["annulation"]["annuler"] == "ACME : Fiche modifiée : origine, cabinet précédent."
    p = prospect(c, pid)
    assert p["cabinet_precedent"] == "Cabinet X" and p["id"] == pid
    assert c.post(f"/api/prospects/{pid}/modifier", json={"origine": "REPRISE"}, headers=H).json()["resultat"] is False


def test_ctrl_z_seulement_pour_soi_et_si_rien_na_change(app):
    claire, paul = connecter(app), connecter(app, "paul")
    pid = creer(claire)
    claire.post(f"/api/prospects/{pid}/deplacer", json={"etape": "RDV_DECOUVERTE"}, headers=H)
    assert paul.post("/api/annuler", headers=H).status_code == 409  # Paul n'annule pas l'action de Claire
    assert claire.post("/api/annuler", headers=H).status_code == 200
    assert prospect(claire, pid)["etape"] == "A_QUALIFIER"
    assert claire.post("/api/retablir", headers=H).json()["libelle"].startswith("Action rétablie : ACME : Étape")
    assert prospect(claire, pid)["etape"] == "RDV_DECOUVERTE"
    claire.post("/api/annuler", headers=H)
    claire.post(f"/api/prospects/{pid}/deplacer", json={"etape": "RDV_DECOUVERTE"}, headers=H)
    paul.post(f"/api/prospects/{pid}/relance", json={"le": "2026-12-01"}, headers=H)  # un collègue modifie entre-temps
    assert claire.post("/api/annuler", headers=H).status_code == 409  # on n'écrase pas son travail


def test_ecritures_simultanees_sans_perte(app):
    """Claire et Paul créent en même temps : aucun prospect perdu (verrou), codes tous différents."""
    claire, paul = connecter(app), connecter(app, "paul")
    erreurs = []

    def lot(c, prefixe):
        for i in range(8):
            try:
                creer(c, f"{prefixe}{i}")
            except AssertionError as e:
                erreurs.append(e)
    t = [threading.Thread(target=lot, args=(claire, "M")), threading.Thread(target=lot, args=(paul, "S"))]
    [x.start() for x in t]; [x.join() for x in t]
    ps = claire.get("/api/etat").json()["prospects"]
    assert not erreurs and len(ps) == 16 and len({p["code"] for p in ps}) == 16


def test_signature_puis_clients_avec_dossier_sharepoint(app):
    c = connecter(app)
    pid = creer(c, "NOUVELLE SAS")
    c.post(f"/api/prospects/{pid}/signee", json={"le": "2026-10-30"}, headers=H)
    d = c.get("/api/clients").json()
    assert d["clients"][0]["nom"] == "NOUVELLE SAS" and d["clients"][0]["statut"] == "ONBOARDING"
    assert next(x for x in d["clients"] if x["nom"] == "ATELIER DUPAIN")["dossier_url"] == ""  # boîte Microsoft pas connectée
    assert c.post("/api/clients/dossiers", headers=H).status_code == 409
    for nom in ("ATELIER DUPAIN", "NOUVELLE", "0. MODELES"):
        sp_ajouter("cp", nom, dossier=True)
    sp_ajouter("cp", "liste.xlsx")
    connecter_boite(c)
    clients = c.get("/api/clients").json()["clients"]  # liste lue dans SharePoint (Comptabilite), dossiers seulement
    dupain = next(x for x in clients if x["nom"] == "ATELIER DUPAIN")
    assert dupain["dossier_url"] == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Comptabilite/ATELIER%20DUPAIN"
    assert dupain["dossier_lien"] == "crm-dossier:ATELIER%20DUPAIN"
    assert next(x for x in clients if x["nom"] == "NOUVELLE SAS")["dossier"] == "NOUVELLE"  # forme juridique ignorée
    c.post(f"/api/prospects/{pid}/onboarding-termine", headers=H)
    assert next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "NOUVELLE SAS")["statut"] == "ACTIF"
    csv = c.get("/api/clients.csv")
    assert csv.content.decode("utf-8").startswith("\ufeffSociété;") and "ATELIER%20DUPAIN" in csv.text
    sp_ajouter("cp", "ZEBRE", dossier=True)
    assert c.post("/api/clients/dossiers", headers=H).json() == {"ok": True, "dossiers": 4}  # « Actualiser les dossiers »


def test_honoraires_relance_mail_et_parametres(app):
    c = connecter(app)
    pid = creer(c)
    c.post(f"/api/prospects/{pid}/honoraires", json={"lignes": [{"libelle": "Compta", "montant": "100", "unite": "mois"},
                                                               {"libelle": "x", "montant": "abc"}], "taxe": "TTC"}, headers=H)
    p = prospect(c, pid)
    assert p["recurrent"] == 1200 and p["honoraires"]["taxe"] == "HT" and len(p["honoraires"]["lignes"]) == 1
    m = c.get(f"/api/prospects/{pid}/mail-relance").json()
    assert m["a"] == "d@acme.fr" and "ACME" in m["objet"]
    assert c.post("/api/parametres", json={"DelaiSansActiviteJours": "abc"}, headers=H).status_code == 422
    assert c.post("/api/parametres", json={"DelaiSansActiviteJours": "20"}, headers=H).json()["parametres"]["DelaiSansActiviteJours"] == "20"
    r = c.post("/api/parametres", json={"relances": {"A_QUALIFIER": ["Objet perso", "Texte perso"]}}, headers=H).json()
    assert r["relances"]["A_QUALIFIER"] == ["Objet perso", "Texte perso"]
    assert c.get("/api/relances-standard").json()["A_QUALIFIER"][0] == "Suite à notre échange"


def test_changer_mot_de_passe(app):
    c = connecter(app)
    assert c.post("/api/mot-de-passe", json={"ancien": "faux", "nouveau": "x" * 12}, headers=H).status_code == 422
    assert c.post("/api/mot-de-passe", json={"ancien": app["mdp"]["claire"], "nouveau": "court"}, headers=H).status_code == 422
    assert c.post("/api/mot-de-passe", json={"ancien": app["mdp"]["claire"], "nouveau": "nouveau-mot-2026"}, headers=H).status_code == 200
    assert app["client"]().post("/api/connexion", json={"identifiant": "claire", "mot_de_passe": "nouveau-mot-2026"}).status_code == 200


def texte_docx(contenu):
    return "\n".join(p.text for p in Document(io.BytesIO(contenu)).paragraphs)


def test_lettre_de_mission_preremplie_generee_et_modifiable(app):
    c = connecter(app)
    pid = c.post("/api/prospects", json={"nature": "PARTICULIER", "denomination": "Foyer Martin", "missions": ["Déclaration de revenus (IR)"],
                                         "contact": {"civilite": "MME", "prenom": "anne", "nom": "Martin", "role": "DECLARANT"}}, headers=H).json()["resultat"]
    v = c.get(f"/api/lettres/prefill?source=prospect&ref={pid}").json()["valeurs"]
    assert v["type"] == "PARTICULIER" and v["modeles_suggeres"] == ["IR"] and v["nom"] == "Martin" and v["interlocuteur"] == "Claire Moreau"
    v.update(modele="IR", adresse="1 rue A", cp="69003", ville="Lyon", annee="2025", forfait_ir="180", composition="un célibataire",
             revenus=["salaires"], fonction="collaborateur comptable", couverture="la France", mediateur="CNPM")
    r = c.post("/api/lettres", json={"valeurs": v}, headers=H).json()["lettre"]
    assert r["restantes"] == [] and r["pdf"] is False
    f = c.get(f"/api/lettres/{r['id']}/fichier")
    texte = texte_docx(f.content)
    assert "Chère Madame Anne MARTIN" in texte and "180 Euros TTC" in texte and "[" not in texte
    assert "attachment" in f.headers["Content-Disposition"]
    assert c.get(f"/api/lettres/{r['id']}/pdf").status_code == 404  # PDF pas encore fait par le programme du poste
    v["forfait_ir"] = "200"  # modifier : même lettre, régénérée
    assert c.post("/api/lettres", json={"valeurs": v, "id": r["id"]}, headers=H).json()["lettre"]["id"] == r["id"]
    assert "200 Euros TTC" in texte_docx(c.get(f"/api/lettres/{r['id']}/fichier").content)
    assert len(c.get("/api/lettres").json()["lettres"]) == 1
    assert prospect(c, pid)["historique"][-1]["texte"].startswith("Lettre de mission modifiée")
    assert c.get("/api/lettres/../../secret/fichier").status_code == 404
    assert json.load(open(os.path.join(DOSSIER, "lettres-cabinet.json"), encoding="utf-8"))["mediateur"] == "CNPM"
    assert c.get(f"/api/lettres/prefill?source=prospect&ref={pid}").json()["valeurs"]["mediateur"] == "CNPM"  # retenu


def test_pdf_et_rangement_dans_sharepoint(app):
    """La lettre est déposée dans Lettres de mission › client (dossier existant réutilisé), convertie en PDF par Microsoft,
    le PDF déposé à côté et gardé dans le CRM. Jamais d'écrasement, jamais de suppression."""
    c = connecter(app)
    r = c.post("/api/lettres", json={"valeurs": {"modele": "ETABLISSEMENT", "type": "SOCIETE", "denomination": "Atelier Dupain SAS"}}, headers=H).json()["lettre"]
    assert r["pdf"] is False and "agent" not in r
    sans = c.post(f"/api/lettres/{r['id']}/pdf", headers=H).json()  # boîte pas connectée : la raison est dite, rien n'est perdu
    assert sans["lettre"]["pdf"] is False and "Connectez votre boîte" in sans["lettre"]["pdf_erreur"] and sans["action"]["type"] == "reconnecter"
    connecter_boite(c)
    dupain = sp_ajouter("lm", "ATELIER DUPAIN", dossier=True)
    sp_ajouter(dupain, "LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES.docx", contenu=b"existante")
    e = c.post(f"/api/lettres/{r['id']}/pdf", headers=H).json()["lettre"]
    assert e["pdf"] is True and e["pdf_erreur"] == ""
    assert e["classement"] == {"dossier": "ATELIER DUPAIN", "fichiers": ["LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES (2).docx",
                                                                        "LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES.pdf"]}
    assert SP[sp_enfant(dupain, "LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES.docx")]["contenu"] == b"existante"  # pas écrasée
    assert SP[sp_enfant(dupain, e["classement"]["fichiers"][0])]["contenu"][:2] == b"PK"  # la lettre Word déposée
    assert len([x for x in SP.values() if x["parent"] == "lm"]) == 1  # dossier existant réutilisé (forme juridique ignorée)
    voir = c.get(f"/api/lettres/{r['id']}/pdf?voir=1")
    assert voir.content == b"%PDF-1.7 converti PK" and voir.headers["Content-Disposition"].startswith("inline")
    assert c.get("/api/lettres").json()["lettres"][0]["classement"]["fichiers"][1].endswith(".pdf")
    e2 = c.post(f"/api/lettres/{r['id']}/pdf", headers=H).json()["lettre"]  # refaite : nouveaux noms, rien d'écrasé
    assert e2["classement"]["fichiers"] == ["LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES (3).docx",
                                            "LETTRE DE MISSION - ATELIER DUPAIN - ETABLISSEMENT DES COMPTES (2).pdf"]
    r2 = c.post("/api/lettres", json={"valeurs": {"modele": "IR", "type": "PARTICULIER", "prenom": "Anne", "nom": "Lécuyer"}}, headers=H).json()["lettre"]
    assert c.post(f"/api/lettres/{r2['id']}/pdf", headers=H).json()["lettre"]["classement"]["dossier"] == "ANNE LECUYER"  # nouveau dossier
    assert SP[sp_enfant("lm", "ANNE LECUYER")]["dossier"]
    MS["sp_refus"] = True  # compte sans accès au SharePoint du cabinet : message clair
    open(os.path.join(DOSSIER, "sharepoint.json"), "w").write("{}")
    e3 = c.post(f"/api/lettres/{r2['id']}/pdf", headers=H).json()
    assert "accès aux fichiers du cabinet" in e3["lettre"]["pdf_erreur"] and e3["lettre"]["pdf"] is True  # le PDF précédent reste
    c.post(f"/api/lettres/{r['id']}/supprimer", headers=H)
    assert len(c.get("/api/lettres").json()["lettres"]) == 1
    assert sp_enfant(dupain, e["classement"]["fichiers"][1])  # supprimer dans le CRM ne supprime rien dans SharePoint


def test_annexe2_tableau_de_repartition(app):
    c = connecter(app)
    defaut = c.post("/api/lettres/annexe2", json={"prestations": [{"libelle": "Bulletins de paie"}]}, headers=H).json()["lignes"]
    assert ["Fiscalité", "Demande de remboursement de TVA", "Cabinet", "à l'acte"] in defaut  # faite par le cabinet, hors forfait
    assert len(defaut) == 41 and ["Social", "Établissement des bulletins de paie", "Cabinet", "à l'acte"] in defaut
    v = {"modele": "PRESENTATION", "type": "SOCIETE", "repartition": [["Fiscalité", "Déclarations CFE", "Client", "N/A"]]}
    r = c.post("/api/lettres", json={"valeurs": v}, headers=H).json()["lettre"]
    t = Document(io.BytesIO(c.get(f"/api/lettres/{r['id']}/fichier").content)).tables
    assert len(t) == 1 and [x.text for x in t[0].rows[1].cells] == ["Fiscalité", "Déclarations CFE", "Client", "N/A"]
    r2 = c.post("/api/lettres", json={"valeurs": {"modele": "LMNP", "type": "EI"}}, headers=H).json()["lettre"]
    assert not Document(io.BytesIO(c.get(f"/api/lettres/{r2['id']}/fichier").content)).tables  # pas d'annexe 2 dans la lettre LMNP
    d = c.get("/api/lettres").json()
    assert d["modeles"]["PRESENTATION"]["annexe"] and not d["modeles"]["LMNP"]["annexe"] and d["annexe2"]["qui"][0] == "Cabinet"


def test_dossiers_pour_les_lettres(app):
    c = connecter(app)
    creer(c, "ZEBRE")
    d = c.get("/api/lettres/dossiers").json()["dossiers"]
    assert [x["nom"] for x in d] == ["ATELIER DUPAIN", "ZEBRE"] and d[0]["sous"] == "Client Pennylane" and d[1]["sous"].endswith("Prospect")
    v = c.get("/api/lettres/prefill?source=client&ref=PL-1").json()["valeurs"]
    assert v["denomination"] == "ATELIER DUPAIN" and v["source"] == "client"
    assert c.get("/api/lettres/prefill?source=client&ref=inconnu").status_code == 404


def test_messagerie_outlook_bout_a_bout(app):
    """Connexion par la page Microsoft (adresse de retour), mails du prospect, lecture, pièce jointe, réponse avec relance."""
    c = connecter(app)
    assert c.get("/api/outlook").json()["adresse"] == ""
    assert c.post("/api/outlook/verifier", headers=H).json() == {"ok": True, "mode": "navigateur"}
    r = c.post("/api/outlook/connexion", headers=H).json()
    assert r["mode"] == "navigateur" and r["automatique"] is True  # CRM local : retour automatique sur le CRM
    q = parse_qs(urlparse(r["url"]).query)
    port = urlparse(app["url"]).port
    assert q["redirect_uri"] == [f"http://localhost:{port}"] and q["code_challenge_method"] == ["S256"] and "Files.ReadWrite.All" in q["scope"][0]
    assert c.post("/api/outlook/connexion/retour", json={"adresse": "http://localhost/?code=c1&state=faux"}, headers=H).status_code == 409
    code_pour(r["url"])
    ok = c.post("/api/outlook/connexion/retour", json={"adresse": f"http://localhost/?code=c1&state={q['state'][0]}"}, headers=H).json()
    assert ok == {"ok": True, "adresse": "moi@cabinet.fr"}
    e = c.get("/api/outlook").json()
    assert e["adresse"] == "moi@cabinet.fr" and e["connexion"]["etat"] == "connecte"
    jetons = open(os.path.join(DOSSIER, "outlook", "claire.json"), encoding="utf-8").read()
    assert '"t"' not in jetons and "chiffre" in jetons  # jetons chiffrés sur disque
    assert connecter(app, "paul").get("/api/outlook").json()["adresse"] == ""  # chacun sa boîte

    pid = creer(c)
    d = c.get(f"/api/prospects/{pid}/mails").json()
    assert d["connecte"] and d["fils"][0]["objet"] == "Votre devis" and d["fils"][0]["non_lus"] == 1 and d["adresses"] == ["d@acme.fr"]
    m = c.get("/api/outlook/mails/m1").json()
    assert m["texte"].startswith("Bonjour") and m["pj"] == [{"id": "a1", "nom": "devis.pdf", "taille": 9}] and MS["lus"] == ["m1"]
    assert c.get(f"/api/prospects/{pid}/mails").json()["fils"][0]["non_lus"] == 0  # lu aussi dans le cache
    pj = c.get("/api/outlook/mails/m1/pj/a1?voir=1")
    assert pj.content.startswith(b"%PDF") and pj.headers["Content-Disposition"].startswith("inline") and "sandbox" in pj.headers["Content-Security-Policy"]
    assert c.post(f"/api/prospects/{pid}/mails", json={"a": [], "texte": ""}, headers=H).status_code == 422
    r = c.post(f"/api/prospects/{pid}/mails", json={"a": ["d@acme.fr"], "texte": "Merci\n\nÀ bientôt", "repondre_a": "m1"}, headers=H).json()
    assert r["ok"] and r["relance"] == "prevue"
    brouillon = next(x[1] for x in MS["envoyes"] if x[0] == "brouillon")
    assert "<p>Merci</p><p>À bientôt</p>" in brouillon["body"]["content"] and brouillon["body"]["content"].endswith("<div>cité</div>")
    assert ("envoi", "r1") in MS["envoyes"]
    p = prospect(c, pid)
    assert p["prochaine_relance"] and "Mail envoyé à d@acme.fr" in p["historique"][-2]["texte"]
    assert c.post("/api/outlook/deconnexion", headers=H).json() == {"ok": True}
    assert c.get(f"/api/prospects/{pid}/mails").json() == {"connecte": False}


def test_adresse_de_retour_microsoft_en_ligne(app):
    """CRM en ligne : retour sur http://localhost (adresse à coller), sauf si l'adresse du CRM est déclarée dans Microsoft Entra."""
    c = connecter(app)
    en_ligne = {"Host": "crm.cabinet-exemple.fr", "Cookie": f"crm_cabinet={c.cookies.get('crm_cabinet')}", **H}  # requests ne l'envoie plus seul
    r = c.post("/api/outlook/connexion", headers=en_ligne).json()
    assert r["automatique"] is False and parse_qs(urlparse(r["url"]).query)["redirect_uri"] == ["http://localhost"]
    c.post("/api/parametres", json={"MessagerieRetourCRM": "1"}, headers=H)
    r = c.post("/api/outlook/connexion", headers=en_ligne).json()
    etat = parse_qs(urlparse(r["url"]).query)["state"][0]
    code_pour(r["url"])
    assert r["automatique"] is True and parse_qs(urlparse(r["url"]).query)["redirect_uri"] == ["http://crm.cabinet-exemple.fr"]
    assert c.get("/api/outlook", headers=en_ligne).json()["retour_crm"] is True
    ok = c.post("/api/outlook/connexion/retour", json={"adresse": f"http://crm.cabinet-exemple.fr/?code=c1&state={etat}&session_state=x"}, headers=H).json()
    assert ok == {"ok": True, "adresse": "moi@cabinet.fr"}
    r = c.post("/api/outlook/connexion", headers=H).json()  # refus sur la page Microsoft : affiché dans la fenêtre du CRM
    etat = parse_qs(urlparse(r["url"]).query)["state"][0]
    c.post("/api/outlook/connexion/retour", json={"adresse": f"http://localhost/?error=access_denied&state={etat}"}, headers=H)
    assert c.get("/api/outlook").json()["connexion"]["message"] == "Connexion refusée sur la page Microsoft."


def test_compte_microsoft_personnel_explique(app):
    """Boîte personnelle (outlook.com) : mails possibles, mais le SharePoint du cabinet non : le CRM dit quoi faire."""
    MS["adresse"] = "quelquun@outlook.com"
    c = connecter(app)
    connecter_boite(c)
    r = c.post("/api/lettres", json={"valeurs": {"modele": "LMNP", "type": "EI", "nom": "Durand"}}, headers=H).json()["lettre"]
    e = c.post(f"/api/lettres/{r['id']}/pdf", headers=H).json()
    assert "quelquun@outlook.com) est un compte Microsoft personnel" in e["lettre"]["pdf_erreur"] and e["action"]["type"] == "reconnecter"
    assert "compte Microsoft personnel" in c.post("/api/clients/dossiers", headers=H).json()["erreur"]
    assert not [x for x in SP.values() if x["parent"] == "lm"]  # rien envoyé


def test_import_clients_csv_presse_papiers_et_modele(app):
    c = connecter(app)
    modele = c.get("/api/clients/modele.csv")
    assert modele.content.startswith("﻿Société;Type;Forme juridique;SIREN".encode()) and "attachment" in modele.headers["Content-Disposition"]
    # 1. Le modèle rempli tel quel : aperçu, rien n'est écrit
    a = c.post("/api/clients/import", json={"texte": modele.content.decode("utf-8")}, headers=H).json()
    assert a["totaux"] == {"ajout": 2, "maj": 0, "identique": 0, "erreur": 0} and a["applique"] is False
    assert len(c.get("/api/clients").json()["clients"]) == 1
    # 2. Copie d'un tableau Excel (tabulations) : erreurs ligne par ligne, client existant complété sans rien effacer
    colle = ("Nom du client\tSIRET\tE-mail\tCode postal\tStatut\tColonne perso\n"
             "ATELIER DUPAIN\t12345678200015\tcontact@dupain.test\t\tActif\tx\n"
             "\n"
             "Sans Mail\t\tpas-un-mail\t\t\t\n"
             "\t\t\t\t\t\n"
             "NOUVEAU CLIENT\t\tnouveau@client.fr\t2140\tOnboarding\t\n"
             "Nouveau Client\t\t\t\t\t\n")
    a = c.post("/api/clients/import", json={"texte": colle, "appliquer": True}, headers=H).json()
    assert a["totaux"] == {"ajout": 1, "maj": 1, "identique": 0, "erreur": 2} and a["applique"] is True
    assert a["ignorees"] == ["Colonne perso"] and a["colonnes"] == ["Société", "SIREN", "E-mail", "Code postal", "Statut"]
    assert [e["ligne"] for e in a["erreurs"]] == [4, 7]  # numéros de ligne du fichier ; ligne vide ignorée
    assert "E-mail « pas-un-mail » invalide" in a["erreurs"][0]["message"] and "En double" in a["erreurs"][1]["message"]
    maj = next(l for l in a["lignes"] if l["action"] == "maj")
    assert maj["nom"] == "ATELIER DUPAIN" and maj["changements"] == ["SIREN", "E-mail"]
    clients = {x["nom"]: x for x in c.get("/api/clients").json()["clients"]}
    assert clients["ATELIER DUPAIN"]["siren"] == "123456782" and clients["ATELIER DUPAIN"]["pennylane"] == "https://app.pennylane.com/companies/1"
    assert clients["NOUVEAU CLIENT"]["statut"] == "ONBOARDING" and clients["NOUVEAU CLIENT"]["pieces"]  # pièces à recevoir
    d = json.load(open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8"))
    nouveau = next(x for x in d["clients"] if x["nom"] == "NOUVEAU CLIENT")
    assert nouveau["cp"] == "02140" and nouveau["source"] == "Import CSV" and nouveau["id"].startswith("CSV-") and d["maj"] == "2026-10-30"
    assert len(os.listdir(os.path.join(DOSSIER, "sauvegardes"))) == 1  # ancienne liste gardée
    # 3. Même import une seconde fois : rien ne change
    a = c.post("/api/clients/import", json={"texte": colle, "appliquer": True}, headers=H).json()
    assert a["totaux"]["ajout"] == 0 and a["totaux"]["maj"] == 0 and a["totaux"]["identique"] == 2 and a["applique"] is False
    # 4. CSV enregistré par Excel (Windows-1252, point-virgule) et réimport de l'export du CRM
    ansi = "Société;Ville;Activité\r\nCAFÉ DES ARTS;Évry;Restauration\r\n".encode("cp1252").decode("latin-1")
    a = c.post("/api/clients/import", json={"texte": ansi, "appliquer": True}, headers=H).json()
    assert a["totaux"]["ajout"] == 1 and any(x["nom"] == "CAFÉ DES ARTS" and x["ville"] == "Évry" for x in c.get("/api/clients").json()["clients"])
    export = c.get("/api/clients.csv").content.decode("utf-8")
    a = c.post("/api/clients/import", json={"texte": export}, headers=H).json()
    assert a["totaux"]["erreur"] == 0 and a["totaux"]["ajout"] == 0 and {"Dossier comptable", "Dossier permanent", "Juridique annuel"} <= set(a["ignorees"])
    # 5. Sans ligne d'intitulés : refusé avec une explication
    r = c.post("/api/clients/import", json={"texte": "ACME;Paris\nBETA;Lyon"}, headers=H)
    assert r.status_code == 422 and "« Société »" in r.json()["champs"]["texte"]


def test_nouveaux_messages_des_prospects_pour_le_pipeline(app):
    """Mail non lu d'un contact de prospect : pastille sur la carte ; lu dans le CRM : la pastille s'en va aussitôt."""
    c = connecter(app)
    pid = creer(c)
    assert c.get("/api/outlook/nouveaux").json() == {"connecte": False}
    connecter_boite(c)
    d = c.get("/api/outlook/nouveaux").json()
    assert d["connecte"] and d["total"] == 1 and list(d["prospects"]) == [pid]  # l'expéditeur inconnu est ignoré
    assert d["prospects"][pid] == {"non_lus": 1, "dernier": "2099-11-01T09:00:00Z", "objet": "Votre devis", "de": "Dupont", "ids": ["m1"]}
    n = MS["recherches_non_lus"]
    c.get("/api/outlook/nouveaux")
    assert MS["recherches_non_lus"] == n  # gardé 40 s : le pipeline ne sollicite pas Microsoft à chaque fois
    c.get("/api/outlook/mails/m1")  # lu dans la fiche
    assert c.get("/api/outlook/nouveaux").json()["total"] == 0
    assert connecter(app, "paul").get("/api/outlook/nouveaux").json() == {"connecte": False}  # chacun sa boîte


def test_import_deux_dossiers_meme_siren_et_mentions_pennylane(app):
    """« DUPONT » et « DUPONT [LMNP] » : deux dossiers, même SIREN. « [ND] » = vide. Renommée : retrouvée par son SIREN."""
    c = connecter(app)
    t = "Société;SIREN;Code postal;Ville\nDUPONT;123456782;75002;Paris\nDUPONT [LMNP];123456782;[ND];Paris\n"
    a = c.post("/api/clients/import", json={"texte": t, "appliquer": True}, headers=H).json()
    assert a["totaux"] == {"ajout": 2, "maj": 0, "identique": 0, "erreur": 0} and not any(l["avertissements"] for l in a["lignes"])
    d = {x["nom"]: x for x in json.load(open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8"))["clients"]}
    assert d["DUPONT [LMNP]"]["cp"] == "" and d["DUPONT"]["cp"] == "75002"
    a = c.post("/api/clients/import", json={"texte": t, "appliquer": True}, headers=H).json()
    assert a["totaux"]["identique"] == 2 and a["totaux"]["ajout"] == 0  # même fichier : rien en double
    a = c.post("/api/clients/import", json={"texte": "Société;SIREN\nDUPONT SAS;123456782\n"}, headers=H).json()
    assert a["totaux"]["ajout"] == 1  # deux dossiers sous ce SIREN : pas de rapprochement hasardeux
    a = c.post("/api/clients/import", json={"texte": "Société;SIREN;Ville\nACME RENOMMEE;552081317;Lyon\n", "appliquer": True}, headers=H).json()
    a = c.post("/api/clients/import", json={"texte": "Société;SIREN;Ville\nACME NOUVEAU NOM;552081317;Nice\n"}, headers=H).json()
    assert a["totaux"]["maj"] == 1 and a["lignes"][0]["changements"] == ["Société", "Ville"]  # renommée : retrouvée par SIREN


def test_ouvrir_le_dossier_dans_l_explorateur_du_pc(app, serveur):
    """CRM ouvert sur le PC même : le serveur demande l'Explorateur (simulé ici) ; nom hors liste refusé ; poste distant : non."""
    c = connecter(app)
    for nom in ("ATELIER DUPAIN",):
        sp_ajouter("cp", nom, dossier=True)
    connecter_boite(c)
    c.get("/api/clients")  # liste des dossiers lue dans SharePoint
    assert c.post("/api/clients/ouvrir", json={"dossier": "..\\..\\Windows"}, headers=H).status_code == 404
    r = c.post("/api/clients/ouvrir", json={"dossier": "ATELIER DUPAIN"}, headers=H).json()
    assert r == {"ouvert": True} and open(os.path.join(DOSSIER, "explorateur.txt"), encoding="utf-8").read().endswith("ATELIER DUPAIN")


def test_archiver_un_client_sans_rien_perdre(app):
    c = connecter(app)
    pid = creer(c, "NOUVELLE SAS")
    c.post(f"/api/prospects/{pid}/signee", json={"le": "2026-10-30"}, headers=H)
    assert c.post("/api/clients/statut", json={"id": "PL-1", "statut": "PERDU"}, headers=H).status_code == 422
    assert c.post("/api/clients/statut", json={"id": "inconnu", "statut": "ARCHIVE"}, headers=H).status_code == 404
    assert c.post("/api/clients/statut", json={"id": "PL-1", "statut": "ARCHIVE"}, headers=H).json() == {"ok": True}
    dupain = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert dupain["archive"] is True and dupain["archive_par"] == "Claire Moreau" and dupain["statut"] == "ACTIF"
    assert dupain["pennylane"] == "https://app.pennylane.com/companies/1"  # rien de perdu
    assert ";ARCHIVE;" in c.get("/api/clients.csv").text
    a = c.post("/api/clients/import", json={"texte": c.get("/api/clients.csv").text}, headers=H).json()
    assert a["totaux"]["erreur"] == 0 and a["totaux"]["maj"] == 0  # réimport de l'export : l'archivage est reconnu
    c.post("/api/clients/statut", json={"id": "PL-1", "statut": "ONBOARDING"}, headers=H)  # désarchivé + remis en onboarding
    dupain = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert dupain["archive"] is False and dupain["statut"] == "ONBOARDING"
    c.post("/api/clients/statut", json={"id": pid, "statut": "ACTIF"}, headers=H)  # client du CRM : sa fiche prospect suit
    c.post("/api/clients/statut", json={"id": pid, "statut": "ARCHIVE"}, headers=H)
    nouvelle = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "NOUVELLE SAS")
    assert nouvelle["statut"] == "ACTIF" and nouvelle["archive"]
    assert [h["texte"] for h in prospect(c, pid)["historique"][-2:]] == ["Onboarding terminé : pièces reçues, client actif.", "Client archivé."]
    a = c.post("/api/clients/import", json={"texte": "Société;Statut\nATELIER DUPAIN;Archivé\n", "appliquer": True}, headers=H).json()
    assert a["lignes"][0]["changements"] == ["Statut"]  # archivé par import
    assert next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")["archive"]
    c.post("/api/clients/import", json={"texte": "Société\nNOUVELLE SAS\n", "appliquer": True}, headers=H)  # aussi dans un fichier importé
    nouvelle = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "NOUVELLE SAS")
    assert nouvelle["archive"]  # reste archivé (reconnu par sa fiche du CRM)
    c.post("/api/clients/statut", json={"id": nouvelle["id"], "statut": "ACTIF"}, headers=H)
    assert not next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "NOUVELLE SAS")["archive"]


def test_dossiers_juridiques_par_annee(app):
    """Juridique annuel › année › CLIENT : le plus récent d'abord, les années précédentes gardées ; ouverture dans l'Explorateur."""
    SP["ja"] = {"nom": "Juridique annuel", "parent": None, "dossier": True}
    sp_ajouter("cp", "ATELIER DUPAIN", dossier=True)
    a24, a25 = sp_ajouter("ja", "2024", dossier=True), sp_ajouter("ja", "2025", dossier=True)
    sp_ajouter("ja", "Archives", dossier=True)  # pas une année : ignoré
    sp_ajouter(a24, "ATELIER DUPAIN", dossier=True); sp_ajouter(a25, "ATELIER DUPAIN", dossier=True)
    sp_ajouter(a24, "Juridique 2024.zip")
    c = connecter(app)
    connecter_boite(c)
    dupain = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert dupain["dossier"] == "ATELIER DUPAIN" and dupain["dossier_juridique"] == "2025/ATELIER DUPAIN"
    assert [j["annee"] for j in dupain["juridiques"]] == ["2025", "2024"]
    assert dupain["dossier_juridique_url"] == "https://exemple.sharepoint.com/sites/cabinet/Documents%20partages/Dossiers/Juridique%20annuel/2025/ATELIER%20DUPAIN"
    assert c.post("/api/clients/ouvrir", json={"dossier": "ATELIER DUPAIN", "type": "juridique"}, headers=H).status_code == 404  # pas sans l'année
    assert c.post("/api/clients/ouvrir", json={"dossier": "2025/ATELIER DUPAIN", "type": "juridique"}, headers=H).json() == {"ouvert": True}
    assert open(os.path.join(DOSSIER, "explorateur.txt"), encoding="utf-8").read().endswith(os.path.join("2025", "ATELIER DUPAIN"))
    assert "Juridique%20annuel/2025/ATELIER%20DUPAIN" in c.get("/api/clients.csv").text


def test_nouveau_prospect_plusieurs_contacts_et_registre(app):
    """Plusieurs contacts dès la création (dont un déjà connu, lié) ; siège et fonction du dirigeant repris dans la lettre."""
    c = connecter(app)
    premier = creer(c, "ANCIEN DOSSIER")
    connu = prospect(c, premier)["contacts"][0]
    r = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "type_pro": "SOCIETE", "denomination": "ATELIER DUPAIN", "siren": "000001016",
        "forme": "SAS", "origine": "EXISTANTE", "adresse": "108 Rue Nationale", "cp": "75013", "ville": "PARIS",
        "contacts": [{"prenom": "Léo", "nom": "EXEMPLE", "fonction": "Président de SAS", "role": "REPRESENTANT_LEGAL"},
                     {"prenom": "Hugo", "nom": "EXEMPLE", "fonction": "Directeur Général", "role": "REPRESENTANT_LEGAL", "email": "m@dupain.test"},
                     {"id_lie": connu["id"], "role": "ASSOCIE"}]}, headers=H).json()
    p = prospect(c, r["resultat"])
    assert [x["nom"] for x in p["contacts"]] == ["EXEMPLE", "EXEMPLE", "Dupont"] and p["contacts"][2]["id"] == connu["id"]
    assert p["contacts"][2]["role"] == "ASSOCIE" and p["contacts"][0]["fonction"] == "Président de SAS" and p["cp"] == "75013"
    v = c.get(f"/api/lettres/prefill?source=prospect&ref={p['id']}").json()["valeurs"]
    assert v["adresse"] == "108 Rue Nationale" and v["ville"] == "PARIS" and v["qualite"] == "Président de SAS" and v["prenom"] == "Léo"
    err = c.post("/api/prospects", json={"nature": "PARTICULIER", "denomination": "Foyer", "contacts": [{"nom": "A"}, {"prenom": "sans nom"}]}, headers=H)
    assert err.status_code == 422 and err.json()["champs"]["contact.nom"] == "Le nom du contact est obligatoire (contact 2)."
    assert c.get("/api/entreprises/fiche?siren=123").status_code == 404


def test_creer_les_dossiers_manquants_d_un_client(app):
    """Dossier comptable (avec le modèle de l'année), dossier permanent, juridique annuel : création seulement."""
    import datetime
    an = str(datetime.date.today().year)
    SP["so"] = {"nom": "Permanent", "parent": None, "dossier": True}
    SP["ja"] = {"nom": "Juridique annuel", "parent": None, "dossier": True}
    modele = sp_ajouter("cp", "0. Modele", dossier=True)
    m_an = sp_ajouter(modele, an, dossier=True)
    for sous in ("COMPTABLE", "FISCAL", "SALARIAL", "SOCIAL"):
        sp_ajouter(m_an, sous, dossier=True)
    sp_ajouter("so", "ATELIER DUPAIN", dossier=True)
    c = connecter(app)
    connecter_boite(c)
    dupain = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert dupain["dossier"] == "" and dupain["dossier_societe"] == "ATELIER DUPAIN" and dupain["nom_nouveau_dossier"] == "ATELIER DUPAIN"
    assert [j["annee"] for j in dupain["juridique_annees"]] == [str(int(an) - 1), an] and not any(j["dossier"] for j in dupain["juridique_annees"])
    assert c.post("/api/clients/dossier", json={"id": "PL-1", "type": "compta", "nom": "../x"}, headers=H).status_code == 422
    r = c.post("/api/clients/dossier", json={"id": "PL-1", "type": "compta", "nom": "ATELIER DUPAIN"}, headers=H).json()
    assert r == {"ok": True, "chemin": f"Comptabilite › ATELIER DUPAIN › {an}", "cree": True}
    nouveau = sp_enfant("cp", "ATELIER DUPAIN")
    assert sorted(SP[i]["nom"] for i in SP if SP[i]["parent"] == sp_enfant(nouveau, an)) == ["COMPTABLE", "FISCAL", "SALARIAL", "SOCIAL"]
    r = c.post("/api/clients/dossier", json={"id": "PL-1", "type": "societe", "nom": "ATELIER DUPAIN"}, headers=H).json()
    assert r["cree"] is False  # existait déjà : repris, rien remplacé
    r = c.post("/api/clients/dossier", json={"id": "PL-1", "type": "juridique", "annee": an, "nom": "ATELIER DUPAIN"}, headers=H).json()
    assert r["chemin"] == f"Juridique annuel › {an} › ATELIER DUPAIN" and SP[sp_enfant(sp_enfant("ja", an), "ATELIER DUPAIN")]["dossier"]
    assert c.post("/api/clients/dossier", json={"id": "PL-1", "type": "juridique", "annee": "1999", "nom": "X"}, headers=H).status_code == 422
    dupain = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")  # liens à jour aussitôt
    assert dupain["dossier"] == "ATELIER DUPAIN" and dupain["juridique_annees"][1]["dossier"] == f"{an}/ATELIER DUPAIN"
    assert c.post("/api/clients/ouvrir", json={"dossier": "ATELIER DUPAIN", "type": "societe"}, headers=H).json() == {"ouvert": True}


def test_dossier_comptable_par_annee(app):
    """Comptabilite › NOM › année : années lues par lots, même frise pour tous, « ＋ » l'année suivante, copiée du modèle le plus récent."""
    import datetime
    an = int(datetime.date.today().year)
    modele = sp_ajouter("cp", "0. Modele", dossier=True)
    for a in (an - 1, an):
        m = sp_ajouter(modele, str(a), dossier=True)
        for sous in ("COMPTABLE", "FISCAL"):
            sp_ajouter(m, sous, dossier=True)
    dupain = sp_ajouter("cp", "ATELIER DUPAIN", dossier=True)
    for a in (an - 7, an - 1, an):
        sp_ajouter(dupain, str(a), dossier=True)
    sp_ajouter(dupain, "2025 - 2026", dossier=True)  # pas une année : ignoré
    sp_ajouter(dupain, "RIB.pdf")
    c = connecter(app)
    connecter_boite(c)
    x = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert MS["lots"] >= 1
    assert [(j["annee"], bool(j["dossier"])) for j in x["compta_annees"]] == [(str(a), a != an - 2 and a != an - 3 and a != an - 4) for a in range(an - 4, an + 1)]
    assert [j["annee"] for j in x["compta_anciennes"]] == [str(an - 7)] and x["compta_prochaine"] == str(an + 1)
    assert x["compta_annees"][-1]["url"].endswith(f"/Comptabilite/ATELIER%20DUPAIN/{an}")
    os.makedirs(os.path.join(DOSSIER + "-reseau", "ATELIER DUPAIN", str(an)), exist_ok=True)  # synchronisé par OneDrive sur ce PC
    assert c.post("/api/clients/ouvrir", json={"dossier": f"ATELIER DUPAIN/{an}"}, headers=H).json() == {"ouvert": True}
    assert open(os.path.join(DOSSIER, "explorateur.txt"), encoding="utf-8").read().endswith(os.path.join("ATELIER DUPAIN", str(an)))
    assert c.post("/api/clients/ouvrir", json={"dossier": f"ATELIER DUPAIN/{an + 1}"}, headers=H).status_code == 404
    assert c.post("/api/clients/dossier", json={"id": "PL-1", "type": "compta", "annee": str(an + 2), "nom": "ATELIER DUPAIN"}, headers=H).status_code == 422
    # « ＋ » : l'année suivante, dans le dossier existant (le nom envoyé ne compte pas), copiée du modèle le plus récent
    r = c.post("/api/clients/dossier", json={"id": "PL-1", "type": "compta", "annee": str(an + 1), "nom": "AUTRE NOM"}, headers=H).json()
    assert r == {"ok": True, "chemin": f"Comptabilite › ATELIER DUPAIN › {an + 1}", "cree": True}
    nouvelle = sp_enfant(dupain, str(an + 1))
    assert sorted(SP[i]["nom"] for i in SP if SP[i]["parent"] == nouvelle) == ["COMPTABLE", "FISCAL"]
    assert MS["copies"][-1] == (sp_enfant(modele, str(an)), dupain, str(an + 1)) and sp_enfant("cp", "AUTRE NOM") is None
    x = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert x["compta_annees"][-1] == {"annee": str(an + 1), "dossier": f"ATELIER DUPAIN/{an + 1}", "url": x["compta_annees"][-1]["url"]}
    assert x["compta_prochaine"] == str(an + 2)  # plus on ajoute d'années, plus on en propose
    r = c.post("/api/clients/dossier", json={"id": "PL-1", "type": "compta", "annee": str(an), "nom": "ATELIER DUPAIN"}, headers=H).json()
    assert r["cree"] is False  # existait déjà : rien remplacé
    assert c.post("/api/clients/dossier", json={"id": "PL-1", "type": "juridique", "annee": str(an + 1), "nom": "ATELIER DUPAIN"}, headers=H).status_code != 422


def test_coordonnees_trouvees_dans_outlook_et_registre_garde(app):
    """E-mails et téléphones absents des registres : retrouvés dans la boîte (signature), adresses du cabinet et automatiques écartées."""
    c = connecter(app)
    assert c.post("/api/entreprises/coordonnees", json={"societe": "ACME CONSEIL"}, headers=H).json() == {"coordonnees": [], "boite": False}
    connecter_boite(c)
    r = c.post("/api/entreprises/coordonnees", json={"societe": "ACME CONSEIL", "personnes": [{"prenom": "Jean", "nom": "DUPONT"}]}, headers=H).json()
    assert [x["email"] for x in r["coordonnees"]] == ["jean.dupont@acme-conseil.fr", "compta@acme-conseil.fr"]
    jean = r["coordonnees"][0]
    assert jean["personne"] == 0 and jean["telephones"] == ["06 12 34 56 78", "01 45 67 89 10"] and jean["nom"] == "Jean Dupont" and jean["dernier"] == "2026-10-02"
    assert r["coordonnees"][1]["personne"] is None
    p = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "type_pro": "SOCIETE", "denomination": "ACME CONSEIL", "siren": "000001016", "origine": "EXISTANTE",
        "registre": {"siret": "00000101600015", "tva": "FR11000001016", "naf": "70.22Z", "piege": "<script>", "ca": {"x": 1}}}, headers=H).json()
    fiche = prospect(c, p["resultat"])
    assert fiche["registre"] == {"siret": "00000101600015", "tva": "FR11000001016", "naf": "70.22Z"}  # clés connues, valeurs simples seulement
    sans = c.post("/api/prospects", json={"nature": "PARTICULIER", "denomination": "Foyer Martin"}, headers=H).json()
    assert c.post(f"/api/prospects/{sans['resultat']}/registre", json={}, headers=H).status_code == 422  # pas de SIREN


# ---------- Connexion avec le compte Microsoft du cabinet (Entra ID, Authenticator) ----------
def page_microsoft(c, adresse="/api/microsoft/connexion"):
    """Ouvre la page Microsoft depuis le CRM : l'adresse d'autorisation (vers le faux Microsoft)."""
    r = c.get(adresse, allow_redirects=False)
    assert r.status_code == 302, r.text
    return r.headers["Location"]


def retour_microsoft(c, url, **remplace):
    q = parse_qs(urlparse(url).query)
    code = code_pour(url)
    r = c.get("/", params={"code": code, "state": q["state"][0], "session_state": "x", **remplace})
    vers = re.search(r'url=([^"]+)"', r.text)
    assert r.status_code == 200 and vers, r.text[:200]
    return vers.group(1).replace("&amp;", "&")


def test_connexion_microsoft_liee_au_compte(app):
    MS["adresse"] = "accueil@cabinet-exemple.fr"  # adresse du cabinet qui n'est ni Claire ni Paul
    c = app["client"]()
    assert c.get("/api/connexion/options").json() == {"microsoft": True, "mot_de_passe": True, "demo": False}
    url = page_microsoft(c)
    q = parse_qs(urlparse(url).query)
    assert q["code_challenge_method"] == ["S256"] and len(q["nonce"][0]) == 32 and "prompt" not in q
    assert retour_microsoft(c, url) == "/?microsoft=inconnu"  # compte Microsoft pas encore lié : refusé
    assert c.get("/api/moi").status_code == 401

    MS["adresse"] = "cmoreau@cabinet-exemple.fr"
    m = connecter(app)  # liaison, une fois, depuis le CRM (connecté avec son mot de passe)
    url = page_microsoft(m, "/api/microsoft/lier")
    assert parse_qs(urlparse(url).query)["prompt"] == ["select_account"]
    assert retour_microsoft(m, url) == "/?microsoft=lie"
    s = m.get("/api/securite").json()
    assert s["microsoft"]["adresse"] == "cmoreau@cabinet-exemple.fr" and s["session"]["methode"] == "mot_de_passe"
    assert [x["lie"] for x in s["comptes"]] == [True, False]

    c = app["client"]()  # connexion avec Microsoft : session ouverte et boîte Outlook connectée du même coup
    MS["mfa"] = True
    assert retour_microsoft(c, page_microsoft(c)) == "/"
    assert c.get("/api/moi").json() == {"identifiant": "claire", "nom": "Claire Moreau", "methode": "microsoft", "mfa": True}
    assert c.get("/api/outlook").json()["adresse"] == "cmoreau@cabinet-exemple.fr"
    j = c.get("/api/securite").json()["journal"]
    assert j[0]["evt"] == "connexion" and j[0]["methode"] == "microsoft" and j[0]["mfa"] is True and j[0]["ip"] == "127.0.0.1"
    assert any(x["evt"] == "microsoft_lie" for x in j)

    # Un autre compte Microsoft du cabinet ne devient pas Claire ; Paul ne peut pas lier le compte de Claire
    MS["adresse"] = "intrus@cabinet-exemple.fr"  # adresse du cabinet, mais ni Claire ni Paul
    i = app["client"]()
    assert retour_microsoft(i, page_microsoft(i)) == "/?microsoft=inconnu"
    MS["adresse"] = "cmoreau@cabinet-exemple.fr"
    st = connecter(app, "paul")
    assert retour_microsoft(st, page_microsoft(st, "/api/microsoft/lier")) == "/?microsoft=deja"
    MS["adresse"], MS["tid"] = "paul@autre-cabinet.fr", "99999999-0000-0000-0000-000000000000"
    assert retour_microsoft(st, page_microsoft(st, "/api/microsoft/lier")) == "/?microsoft=organisation"


def test_jetons_microsoft_falsifies_refuses(app):
    MS["adresse"] = "cmoreau@cabinet-exemple.fr"
    m = connecter(app)
    assert retour_microsoft(m, page_microsoft(m, "/api/microsoft/lier")) == "/?microsoft=lie"
    essais = {
        "cle_pirate": rsa.generate_private_key(public_exponent=65537, key_size=2048),  # signé par quelqu'un d'autre que Microsoft
        "aud": "autre-application",  # jeton émis pour une autre application
    }
    for k, v in essais.items():
        MS[k] = v
        c = app["client"]()
        assert retour_microsoft(c, page_microsoft(c)) == "/?microsoft=erreur", k
        assert c.get("/api/moi").status_code == 401, k
        MS.pop(k)
    c = app["client"]()  # nonce d'une autre connexion (jeton rejoué)
    url = page_microsoft(c)
    MS["nonce"] = "0" * 32
    r = c.get("/", params={"code": "c1", "state": parse_qs(urlparse(url).query)["state"][0]})
    assert "microsoft=erreur" in r.text and c.get("/api/moi").status_code == 401
    pirate, victime = app["client"](), app["client"]()  # retour ouvert dans un autre navigateur (cookie de liaison absent)
    url = page_microsoft(pirate)
    assert retour_microsoft(victime, url) == "/?microsoft=expire" and victime.get("/api/moi").status_code == 401
    c = app["client"]()  # usage unique : le même retour ne sert pas deux fois
    url = page_microsoft(c)
    lien = dict(c.cookies)
    assert retour_microsoft(c, url) == "/"
    c2 = app["client"]()
    c2.cookies.update(lien)
    r = c2.get("/", params={"code": "c1", "state": parse_qs(urlparse(url).query)["state"][0]})
    assert "microsoft=" not in r.text and c2.get("/api/moi").status_code == 401  # plus attendu : simple page du CRM
    MS["adresse"] = "quelquun@outlook.com"  # compte personnel : jamais lié
    st = connecter(app, "paul")
    assert retour_microsoft(st, page_microsoft(st, "/api/microsoft/lier")) == "/?microsoft=personnel"


def test_mode_microsoft_avec_double_authentification(app):
    m = connecter(app)
    assert m.post("/api/securite/mode", json={"mode": "microsoft"}, headers=H).status_code == 422  # soi-même pas lié : se bloquerait
    for qui, adresse in (("claire", "cmoreau@cabinet-exemple.fr"), ("paul", "paul@cabinet-exemple.fr")):
        MS["adresse"] = adresse
        c = connecter(app, qui)
        assert retour_microsoft(c, page_microsoft(c, "/api/microsoft/lier")) == "/?microsoft=lie"
    r = m.post("/api/securite/mode", json={"mode": "microsoft_mfa"}, headers=H)
    assert r.status_code == 422 and "Authenticator" in r.json()["champs"]["mode"]  # pas soi-même connecté avec la double authentification
    MS["adresse"], MS["mfa"] = "cmoreau@cabinet-exemple.fr", True
    c = app["client"]()
    assert retour_microsoft(c, page_microsoft(c)) == "/"
    assert c.post("/api/securite/mode", json={"mode": "microsoft_mfa"}, headers=H).json()["mode"] == "microsoft_mfa"
    assert app["client"]().get("/api/connexion/options").json() == {"microsoft": True, "mot_de_passe": False, "demo": False}
    r = app["client"]().post("/api/connexion", json={"identifiant": "paul", "mot_de_passe": app["mdp"]["paul"]})
    assert r.status_code == 403 and r.json()["microsoft"] is True  # mot de passe seul : refusé
    MS["adresse"], MS["mfa"] = "paul@cabinet-exemple.fr", False
    s = app["client"]()
    assert retour_microsoft(s, page_microsoft(s)) == "/?microsoft=mfa" and s.get("/api/moi").status_code == 401
    MS["mfa"] = True
    assert retour_microsoft(s, page_microsoft(s)) == "/" and s.get("/api/moi").json()["identifiant"] == "paul"
    assert c.post("/api/securite/delier", headers=H).status_code == 422  # délier en mode Microsoft : se bloquerait
    assert c.post("/api/securite/mode", json={"mode": ""}, headers=H).json()["mode"] == ""


def test_sessions_et_mots_de_passe_renforces(app):
    c = connecter(app)
    for faible in ("motdepasse12", "Court1!", "Claire-Cabinet-2026!", "aaaaaaaaaaaaa1"):
        r = c.post("/api/mot-de-passe", json={"ancien": app["mdp"]["claire"], "nouveau": faible}, headers=H)
        assert r.status_code == 422, faible
    autre = connecter(app)  # même compte, autre navigateur
    assert c.post("/api/mot-de-passe", json={"ancien": app["mdp"]["claire"], "nouveau": "Cabinet#Exemple2026"}, headers=H).status_code == 200
    assert c.get("/api/moi").status_code == 200 and autre.get("/api/moi").status_code == 401  # mot de passe changé : autres sessions fermées
    autre = app["client"]()
    assert autre.post("/api/connexion", json={"identifiant": "claire", "mot_de_passe": "Cabinet#Exemple2026"}).status_code == 200
    assert c.post("/api/securite/fermer-sessions", headers=H).json() == {"ok": True}
    assert c.get("/api/moi").status_code == 200 and autre.get("/api/moi").status_code == 401
    vole = app["client"]()
    vole.cookies.update(c.cookies)  # cookie de session volé, utilisé par un autre navigateur
    assert vole.get("/api/moi", headers={"User-Agent": "Pirate/1.0"}).status_code == 401
    journal = [json.loads(l) for l in open(os.path.join(DOSSIER, "journal-securite.jsonl"), encoding="utf-8")]
    assert any(l["evt"] == "mot_de_passe_change" and l["compte"] == "claire" for l in journal)
    assert "Cabinet#Exemple2026" not in open(os.path.join(DOSSIER, "journal-securite.jsonl"), encoding="utf-8").read()
    page = app["client"]().get("/")
    for k in ("Permissions-Policy", "Cross-Origin-Opener-Policy", "Cross-Origin-Resource-Policy"):
        assert k in page.headers
    assert "base-uri 'none'" in page.headers["Content-Security-Policy"] and "X-Powered-By" not in page.headers


def test_compte_bloque_apres_trop_d_essais(app):
    """10 essais ratés sur un même compte en 15 minutes (même d'adresses différentes) : ce compte est bloqué, l'autre non."""
    essais = {"compte:paul": [int(time.time())] * 10}
    json.dump(essais, open(os.path.join(DOSSIER, "essais.json"), "w"))
    r = app["client"]().post("/api/connexion", json={"identifiant": "paul", "mot_de_passe": app["mdp"]["paul"]})
    assert r.status_code == 429
    assert app["client"]().post("/api/connexion", json={"identifiant": "claire", "mot_de_passe": app["mdp"]["claire"]}).status_code == 200


def test_microsoft_seulement_et_paul_lie_a_sa_premiere_connexion(app):
    """Mode Microsoft seulement sans attendre Paul : sa première connexion avec son adresse du cabinet le lie tout seul."""
    MS["adresse"] = "cmoreau@cabinet-exemple.fr"
    m = connecter(app)
    assert retour_microsoft(m, page_microsoft(m, "/api/microsoft/lier")) == "/?microsoft=lie"
    assert m.post("/api/securite/mode", json={"mode": "microsoft"}, headers=H).json()["mode"] == "microsoft"
    for adresse, nom in (("paul.bernard@autre.fr", "Paul Bernard"),  # pas le domaine du cabinet
                         ("comptable@cabinet-exemple.fr", "Comptable")):  # cabinet, mais pas Paul
        MS["adresse"], MS["nom"] = adresse, nom
        c = app["client"]()
        assert retour_microsoft(c, page_microsoft(c)) == "/?microsoft=inconnu", adresse
    MS["adresse"], MS["nom"] = "pbernard@cabinet-exemple.fr", "Paul BERNARD"
    s = app["client"]()
    assert retour_microsoft(s, page_microsoft(s)) == "/"
    assert s.get("/api/moi").json()["identifiant"] == "paul"
    assert json.load(open(os.path.join(DOSSIER, "comptes.json"), encoding="utf-8"))["paul"]["microsoft"]["adresse"] == "pbernard@cabinet-exemple.fr"
    MS["nom"] = "Autre Nom"  # ensuite, reconnu par son identifiant Microsoft, quel que soit le nom
    s = app["client"]()
    assert retour_microsoft(s, page_microsoft(s)) == "/"



# ---------- Suppression, source « Autre », reprise d'un confrère, lettre confraternelle, éditeur de mail ----------
def test_supprimer_un_prospect_pour_de_bon(app):
    c = connecter(app)
    pid = creer(c, "A EFFACER")
    r = c.post(f"/api/prospects/{pid}/supprimer", json={}, headers=H)
    assert r.status_code == 200, r.text
    assert all(p["id"] != pid for p in c.get("/api/etat").json()["prospects"])
    assert "A EFFACER" not in open(os.path.join(DOSSIER, "prospects.json"), encoding="utf-8").read()
    assert c.post("/api/annuler", headers=H).status_code == 200  # Ctrl+Z juste après : il revient
    assert any(p["id"] == pid for p in c.get("/api/etat").json()["prospects"])


def test_source_autre_et_cabinet_a_renseigner_plus_tard(app):
    c = connecter(app)
    r = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "type_pro": "EI", "denomination": "DUPONT CONSEIL", "origine": "REPRISE",
                                       "cabinet_plus_tard": True, "source": "AUTRE", "source_autre": "Salon des entrepreneurs",
                                       "missions": ["BIC (déclaration 2031)", "LMNP (location meublée)"]}, headers=H)
    assert r.status_code == 200, r.text
    p = prospect(c, r.json()["resultat"])
    assert p["source_autre"] == "Salon des entrepreneurs" and p["cabinet_plus_tard"] is True and p["cabinet_precedent"] == ""
    ref = c.get("/api/etat").json()["referentiels"]
    assert ref["origines_ei"]["CREATION"] == "Création d'entreprise" and ref["origines_ei"]["EXISTANTE"].startswith("Entreprise existante")
    assert "BIC (déclaration 2031)" in ref["missions"]["EI"] and "BIC au réel" not in ref["missions"]["EI"]
    assert not any("LMNP" in m for m in ref["missions"]["PARTICULIER"])


def test_reprise_lettre_confraternelle_avant_la_lettre_de_mission(app):
    c = connecter(app)
    connecter_boite(c)
    r = c.post("/api/prospects", json={"nature": "PROFESSIONNEL", "type_pro": "SOCIETE", "denomination": "ACME", "forme": "SAS", "origine": "REPRISE",
                                       "cabinet_plus_tard": True, "missions": ["Présentation des comptes"],
                                       "contacts": [{"nom": "Dupont", "email": "d@acme.fr", "role": "REPRESENTANT_LEGAL"}]}, headers=H)
    pid = r.json()["resultat"]
    lm = c.post("/api/lettres", json={"valeurs": {"modele": "PRESENTATION", "type": "SOCIETE", "denomination": "ACME", "source": "prospect", "ref": pid}}, headers=H).json()["lettre"]
    assert c.post(f"/api/lettres/{lm['id']}/pdf", headers=H).json()["lettre"]["pdf"]
    envoi = {"a": ["d@acme.fr"], "objet": "Lettre de mission", "html": "<p>Bonjour</p>", "lettres": [lm["id"]]}
    r = c.post(f"/api/prospects/{pid}/mails", json=envoi, headers=H)
    assert r.status_code == 422 and "renseignez d'abord l'ancien cabinet" in r.json()["champs"]["lettres"]
    assert c.post(f"/api/prospects/{pid}/confraternelle", headers=H).status_code == 422  # pas encore de cabinet
    a = {"cabinet": "CABINET MARTIN", "civilite": "MME", "prenom": "Claire", "nom": "Martin", "adresse": "3 rue de la Paix", "cp": "75002",
         "ville": "Paris", "email": "c.martin@cabinet-martin.fr", "telephone": ""}
    assert c.post(f"/api/prospects/{pid}/ancien-cabinet", json=a, headers=H).status_code == 200
    p = prospect(c, pid)
    assert p["cabinet_precedent"] == "CABINET MARTIN" and p["cabinet_plus_tard"] is False and p["ancien"]["email"] == a["email"]
    r = c.post(f"/api/prospects/{pid}/mails", json=envoi, headers=H)
    assert r.status_code == 422 and "lettre confraternelle" in r.json()["champs"]["lettres"]  # cabinet connu, mais pas encore informé
    lc = c.post(f"/api/prospects/{pid}/confraternelle", headers=H).json()["lettre"]
    texte = texte_docx(c.get(f"/api/lettres/{lc['id']}/fichier").content)
    assert "Chère Consœur" in texte and "CABINET MARTIN" in texte and "reprise du dossier SAS ACME" in texte and "CABINET EXEMPLE" in texte
    assert "LETTRE DE MISSION" not in texte and "présentation des comptes" in texte
    assert c.post(f"/api/lettres/{lc['id']}/pdf", headers=H).json()["lettre"]["pdf"]
    assert any(x["nom"].startswith("LETTRE CONFRATERNELLE - ") for x in SP.values())
    assert c.get(f"/api/prospects/{pid}/mails?avec=ancien").json()["adresses"] == [a["email"]]  # onglet Ancien cabinet : le confrère seul
    assert c.get(f"/api/prospects/{pid}/mails").json()["adresses"] == ["d@acme.fr"]  # Échanges : le client seul
    r = c.post(f"/api/prospects/{pid}/mails", json={"a": [a["email"]], "objet": "Reprise du dossier ACME", "html": "<p>Ci-joint</p>",
                                                   "lettres": [lc["id"]], "avec": "ancien"}, headers=H)
    assert r.status_code == 200, r.text
    assert r.json()["relance"] == "" and prospect(c, pid)["prochaine_relance"] == ""  # mail au confrère : pas de relance du client
    p = prospect(c, pid)
    assert any("Mail envoyé à l'ancien cabinet" in h["texte"] for h in p["historique"])
    assert p["confraternelle"]["envoyee_le"] and any("Lettre confraternelle envoyée par mail" in h["texte"] for h in p["historique"])
    r = c.post(f"/api/prospects/{pid}/mails", json=envoi, headers=H)  # maintenant la lettre de mission peut partir
    assert r.status_code == 200, r.text
    assert prospect(c, pid)["etape"] == "LETTRE_ENVOYEE"


def test_mail_mis_en_forme_nettoye_et_signature(app):
    c = connecter(app)
    connecter_boite(c)
    pid = creer(c)
    sale = ('<p style="color:#c00;mso-line-height:1">Bonjour <b>Monsieur</b> <u>Dupont</u></p><script>alert(1)</script>'
            '<ul><li><a href="javascript:alert(1)">lien piégé</a></li><li><a href="https://cabinet-exemple.fr">site</a></li></ul>'
            '<img src="https://pistage.example/x.gif"><o:p></o:p><p onclick="x()"><font color="#1155cc" size="4">Grand</font></p>')
    r = c.post(f"/api/prospects/{pid}/mails", json={"a": ["d@acme.fr"], "objet": "Essai", "html": sale}, headers=H)
    assert r.status_code == 200, r.text
    corps = next(x[1] for x in MS["envoyes"] if x[0] == "nouveau")["body"]["content"]
    assert "<b>Monsieur</b>" in corps and "<u>Dupont</u>" in corps and 'style="margin:0;color:#c00"' in corps
    assert "<script" not in corps and "javascript" not in corps and "onclick" not in corps and "mso" not in corps
    assert "<img src=\"https://pistage.example/x.gif\">" in corps  # logo hébergé (signatures) : gardé, sans autre attribut
    assert '<a href="https://cabinet-exemple.fr">site</a>' in corps and '<font color="#1155cc" size="4">Grand</font>' in corps
    assert c.get("/api/signature").json()["personnalisee"] is False  # rien n'est gardé sans demander
    loc = c.get("/api/signature/outlook-locales").json()["signatures"]  # signatures d'Outlook sur ce PC, celle par défaut en premier
    assert [x["nom"] for x in loc] == ["Cabinet (moi@cabinet.fr)", "Perso"] and loc[0]["defaut"] and not loc[1]["defaut"]
    assert "<b>Claire</b> · Collaborateur comptable" in loc[0]["html"]  # fichier d'Outlook classique (windows-1252)
    assert 'src="data:image/png;base64,' in loc[1]["html"]  # logo du dossier « _fichiers » remis dans la signature
    assert c.get("/api/signature/outlook-locales", headers={"X-Forwarded-For": "8.8.8.8"}).status_code == 200
    s = c.post("/api/signature/outlook", headers=H).json()  # en ligne : reprise du dernier mail envoyé, sans être gardée
    assert s["source"] == "outlook" and "Claire MOREAU" in s["html"] and "<table" in s["html"] and 'src="data:image/png;base64,iVBORw0KGgo="' in s["html"]
    assert c.get("/api/signature").json()["personnalisee"] is False
    assert "texte cité" not in s["html"] and "client" not in s["html"] and 'href="tel:+33600000000"' in s["html"]
    r = c.post(f"/api/prospects/{pid}/mails", json={"a": ["d@acme.fr"], "objet": "Avec signature", "html": "<p>Bonjour</p>" + s["html"]}, headers=H)
    assert r.status_code == 200, r.text
    envoye = [x for x in MS["envoyes"] if x[0] == "nouveau"][-1][1]["body"]["content"]
    assert "data:image" not in envoye and 'src="cid:crm-0-' in envoye and any(x[0] == "en_ligne" for x in MS["envoyes"])  # logo joint « en ligne »
    assert connecter(app, "paul").get("/api/signature").json()["personnalisee"] is False  # Paul : pas de boîte connectée, signature simple
    s = c.post("/api/signature", json={"html": "<p><b>Claire</b><br>Cabinet</p><script>x</script>"}, headers=H).json()
    assert s["html"] == "<p style=\"margin:0\"><b>Claire</b><br>Cabinet</p>"
    assert "<script" not in c.post("/api/signature/apercu", json={"html": "<html><body><p>Moi</p><script>x</script></body></html>"}, headers=H).json()["html"]


def test_archiver_un_fil_indesirable(app):
    """Fil archivé : caché dans la fiche et sans pastille dans le pipeline ; désarchivé, il revient. Rien ne change dans Outlook."""
    c = connecter(app)
    connecter_boite(c)
    pid = creer(c)
    fil = c.get(f"/api/prospects/{pid}/mails").json()["fils"][0]
    assert fil["archive"] is False and c.get("/api/outlook/nouveaux?frais=1").json()["prospects"][pid]["non_lus"] == 1
    assert c.post("/api/outlook/archiver", json={"fil": fil["id"], "archive": True}, headers=H).json() == {"ok": True, "archive": True}
    assert c.get(f"/api/prospects/{pid}/mails").json()["fils"][0]["archive"] is True
    assert pid not in c.get("/api/outlook/nouveaux").json()["prospects"]
    assert connecter(app, "paul").get("/api/outlook").status_code == 200  # chacun ses archives
    c.post("/api/outlook/archiver", json={"fil": fil["id"], "archive": False}, headers=H)
    assert c.get(f"/api/prospects/{pid}/mails").json()["fils"][0]["archive"] is False
    assert not [x for x in MS["envoyes"] if x[0] != "brouillon"]  # aucun mail déplacé ni modifié dans Outlook


def test_synchro_pennylane_et_dossiers_sharepoint(app):
    """Tous les dossiers comptables sont dans la liste des clients ; la synchro Pennylane (lecture seule) ajoute les dossiers
    Pennylane absents, complète les fiches sans rien écraser, et dit quels dossiers manquent dans SharePoint (et l'inverse)."""
    json.dump({"maj": "05/11/2026", "t": int(time.time()), "dossiers": ["0. Modele", "ATELIER DUPAIN", "BETA CONSEIL"], "juridique": [], "societe": [],
               "compta_annees": {}}, open(os.path.join(DOSSIER, "dossiers-reseau.json"), "w", encoding="utf-8"))
    c = connecter(app)
    noms = {x["nom"]: x for x in c.get("/api/clients").json()["clients"]}
    assert noms["BETA CONSEIL"]["source"] == "SharePoint" and noms["BETA CONSEIL"]["dossier"] == "BETA CONSEIL"  # dossier sans fiche : dans la liste
    assert "0. Modele" not in noms and noms["ATELIER DUPAIN"]["dossier"] == "ATELIER DUPAIN"
    assert c.post("/api/clients/statut", json={"id": noms["BETA CONSEIL"]["id"], "statut": "ARCHIVE"}, headers=H).status_code == 200  # archivable
    c.post("/api/clients/statut", json={"id": noms["BETA CONSEIL"]["id"], "statut": "ACTIF"}, headers=H)

    assert c.post("/api/pennylane/synchro", headers=H).status_code == 409  # pas encore relié
    r = c.post("/api/pennylane/jeton", json={"jeton": "faux"}, headers=H)
    assert r.status_code == 422 and "refuse le jeton" in r.json()["champs"]["jeton"]
    assert c.post("/api/pennylane/jeton", json={"jeton": "pl-ok"}, headers=H).json()["configure"] is True
    assert "pl-ok" not in open(os.path.join(DOSSIER, "pennylane.json"), encoding="utf-8").read()  # chiffré
    assert "pl-ok" not in c.get("/api/pennylane").text  # jamais renvoyé

    avant = open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8").read()
    r = c.post("/api/pennylane/synchro", json={"appliquer": False}, headers=H).json()  # aperçu : on demande avant d'écrire
    assert r["apercu"] is True and r["ajoutes"] == [{"id": "7", "nom": "NOUVELLE SAS"}] and "manquants" not in r
    assert open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8").read() == avant  # rien d'écrit
    r = c.post("/api/pennylane/synchro", json={"appliquer": True, "ajouter": ["7"], "ignorer": []}, headers=H).json()
    assert r["pennylane"] == 2 and [x["nom"] for x in r["ajoutes"]] == ["NOUVELLE SAS"] and [x["nom"] for x in r["completes"]] == ["ATELIER DUPAIN"]
    assert [x["nom"] for x in r["manquants"]] == ["NOUVELLE SAS"] and [x["nom"] for x in r["absents"]] == ["BETA CONSEIL"]
    fiche = next(x for x in json.load(open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8"))["clients"] if x["nom"] == "ATELIER DUPAIN")
    assert fiche["siren"] == "000001016" and fiche["pennylane"] == "https://app.pennylane.com/companies/1" and fiche["ville"] == "Lyon"
    assert len([x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "ATELIER DUPAIN"]) == 1  # pas de doublon
    r = c.post("/api/pennylane/synchro", json={"appliquer": False}, headers=H).json()  # deuxième fois : rien à valider, compte rendu direct
    assert r["ajoutes"] == [] and r["completes"] == [] and r["apercu"] is False and "manquants" in r
    assert all(m == "GET" for m, _ in MS["pennylane"])  # rien n'est jamais écrit dans Pennylane
    assert os.listdir(os.path.join(DOSSIER, "sauvegardes"))  # ancienne liste gardée



def test_synchro_pennylane_cases_ignorees_et_claude(app):
    """Dossiers non cochés : ignorés pour toujours (remis depuis Paramètres). Infos manquantes : Claude Desktop les lit dans Pennylane,
    la personne valide, seules les cases vides des fiches sont remplies."""
    c = connecter(app)
    c.post("/api/pennylane/jeton", json={"jeton": "pl-ok"}, headers=H)
    r = c.post("/api/pennylane/synchro", json={"appliquer": True, "ajouter": [], "ignorer": ["7"]}, headers=H).json()  # NOUVELLE SAS décochée
    assert r["ajoutes"] == [] and "NOUVELLE SAS" not in [x["nom"] for x in c.get("/api/clients").json()["clients"]]
    assert c.get("/api/pennylane").json()["ignores"] == [{"id": "7", "nom": "NOUVELLE SAS"}]
    apercu = c.post("/api/pennylane/synchro", json={"appliquer": False}, headers=H).json()
    assert not [x for x in apercu["ajoutes"] if x["id"] == "7"]  # plus jamais proposé
    assert c.post("/api/pennylane/ignores", json={"id": "7", "ignorer": False}, headers=H).json()["ignores"] == []  # Paramètres : remis
    assert [x["id"] for x in c.post("/api/pennylane/synchro", json={"appliquer": False}, headers=H).json()["ajoutes"]] == ["7"]

    d = c.post("/api/pennylane/claude", headers=H).json()  # ATELIER DUPAIN (lien Pennylane 1) : sans contact ni e-mail
    assert d["dossiers"] == 1 and d["lance"] is False and "getDashboardFirmSidepanelsCompany" in d["prompt"] and "client=PL-1 ; pennylane_id=1 ; nom=ATELIER DUPAIN" in d["prompt"]
    assert c.get("/api/pennylane/claude").json()["etat"] == "attente"
    reponse = 'Voici le résultat :\n```json\n[{"pennylane_id":"1","forme_juridique":"SAS","contact":"Jean Dupain","email":"pas-un-mail","telephone":"0612345678"},' \
              '{"pennylane_id":"999","contact":"Inconnu"}]\n```'
    p = c.post("/api/pennylane/claude/resultat", json={"texte": reponse}, headers=H).json()["propositions"]
    assert p == [{"id": "PL-1", "nom": "ATELIER DUPAIN", "champs": {"forme": {"valeur": "SAS", "ancien": ""}, "contact": {"valeur": "Jean Dupain", "ancien": ""},
                                                                  "telephone": {"valeur": "06 12 34 56 78", "ancien": ""}}}]  # e-mail faux écarté
    avant = open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8").read()
    assert "Jean Dupain" not in avant  # rien d'écrit avant validation
    r = c.post("/api/pennylane/claude/appliquer", json={"lignes": [{"id": "PL-1", "champs": {"contact": "Jean Dupain", "forme": "SAS"}}]}, headers=H).json()
    assert r["champs"] == 2
    fiche = next(x for x in json.load(open(os.path.join(DOSSIER, "clients.json"), encoding="utf-8"))["clients"] if x["id"] == "PL-1")
    assert fiche["contact"] == "Jean Dupain" and fiche["forme"] == "SAS" and fiche.get("telephone", "") == ""  # téléphone décoché : pas écrit
    d = c.post("/api/pennylane/claude", json={"id": "PL-1"}, headers=H).json()  # fiche client : ce dossier seul, même s'il est complet
    assert d["dossiers"] == 1 and "client=PL-1 ; pennylane_id=1 ; nom=ATELIER DUPAIN" in d["prompt"]
    c.post("/api/pennylane/claude/appliquer", json={"lignes": [{"id": "PL-1", "champs": {"telephone": "06 12 34 56 78"}}]}, headers=H)
    p = c.post("/api/pennylane/claude/resultat", json={"texte": '[{"pennylane_id":"1","contact":"Marie Dupain","forme_juridique":"Société par actions simplifiée (SAS)","telephone":"+33612345678"}]'},
               headers=H).json()["propositions"]  # même téléphone autrement écrit : pas proposé
    assert p[0]["champs"] == {"contact": {"valeur": "Marie Dupain", "ancien": "Jean Dupain"}}  # changé dans Pennylane : montré avec l'ancienne valeur
    assert c.post("/api/pennylane/claude/appliquer", json={"lignes": [{"id": "PL-1", "champs": {"contact": "Marie Dupain"}}]}, headers=H).json()["champs"] == 1
    assert c.post("/api/pennylane/claude", json={"id": "SP-inconnu"}, headers=H).status_code == 404
    assert d["mode"] == "manuel"  # tests : ni Claude Code ni Claude Desktop
    # Dossier SharePoint sans lien Pennylane : Claude le cherche par son nom ; validé, sa fiche est créée et reliée
    json.dump({"maj": "05/11/2026", "t": int(time.time()) + 99999, "dossiers": ["ATELIER DUPAIN", "DELTA CONSULTING"], "juridique": [], "societe": [],
               "compta_annees": {}}, open(os.path.join(DOSSIER, "dossiers-reseau.json"), "w", encoding="utf-8"))
    delta = next(x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "DELTA CONSULTING")
    d = c.post("/api/pennylane/claude", json={"id": delta["id"]}, headers=H).json()
    assert f"client={delta['id']} ; pennylane_id=à trouver ; nom=DELTA CONSULTING" in d["prompt"]
    r = c.post("/api/pennylane/claude/resultat", json={"texte": json.dumps([{"client": delta["id"], "pennylane_id": ""}])}, headers=H).json()
    assert r["propositions"] == [] and r["introuvables"] == ["DELTA CONSULTING"]  # pas dans Pennylane : dit à la personne
    rep = json.dumps([{"client": delta["id"], "pennylane_id": "55", "forme_juridique": "SASU", "siren": "000001016", "contact": "Paul Delta"}])
    p = c.post("/api/pennylane/claude/resultat", json={"texte": rep}, headers=H).json()["propositions"]
    assert p[0]["champs"]["pennylane"]["valeur"] == "https://app.pennylane.com/companies/55" and p[0]["champs"]["siren"]["valeur"] == "000001016"
    champs = {k: v["valeur"] for k, v in p[0]["champs"].items()}
    assert c.post("/api/pennylane/claude/appliquer", json={"lignes": [{"id": delta["id"], "champs": champs}]}, headers=H).json()["champs"] == 4
    apres = [x for x in c.get("/api/clients").json()["clients"] if x["nom"] == "DELTA CONSULTING"]
    assert len(apres) == 1 and apres[0]["id"] == delta["id"] and apres[0]["pennylane"].endswith("/55") and apres[0]["dossier"] == "DELTA CONSULTING"


# ---------- Mode démo : hors ligne, données fictives, aucun service externe ----------
@pytest.fixture(scope="module")
def demo():
    donnees = tempfile.mkdtemp(prefix="crm-demo-")
    port = port_libre()
    env = dict(os.environ, CRM_DEMO="1", CRM_DATA=donnees, CRM_GRAPH="http://127.0.0.1:9", CRM_PENNYLANE="http://127.0.0.1:9")
    p = subprocess.Popen([PHP, "-S", f"127.0.0.1:{port}", "-t", "public", "public/routeur.php"], cwd=RACINE, env=env,
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    url = f"http://127.0.0.1:{port}"
    for _ in range(50):
        try:
            requests.get(url + "/", timeout=30)  # première requête : la démo crée ses données fictives
            break
        except (requests.ConnectionError, requests.Timeout):
            time.sleep(0.1)
    yield {"url": url, "donnees": donnees}
    p.terminate()


def test_demo_connexion_et_donnees_fictives(demo):
    c = Client(demo["url"])
    assert c.get("/api/connexion/options").json() == {"microsoft": False, "mot_de_passe": True, "demo": True}
    assert c.post("/api/connexion", json={"identifiant": "demo", "mot_de_passe": "faux"}).status_code == 401
    assert c.post("/api/connexion", json={"identifiant": "demo", "mot_de_passe": "demo"}).status_code == 200
    e = c.get("/api/etat").json()
    assert len(e["prospects"]) >= 8 and {p["etape"] for p in e["prospects"]} == {"A_QUALIFIER", "RDV_DECOUVERTE", "PROPOSITION_ENVOYEE", "LETTRE_ENVOYEE"}
    assert len(c.get("/api/clients").json()["clients"]) >= 10
    lettres = c.get("/api/lettres").json()["lettres"]
    assert len(lettres) == 3 and all(l["restantes"] == [] for l in lettres)  # générées par le vrai moteur, toutes les zones remplies
    texte = texte_docx(c.get(f"/api/lettres/{lettres[0]['id']}/fichier").content)
    assert "CABINET EXEMPLE" in texte and "[" not in texte


def test_demo_aucun_appel_externe(demo):
    c = Client(demo["url"])
    c.post("/api/connexion", json={"identifiant": "demo", "mot_de_passe": "demo"})
    for route in ("/api/outlook/connexion", "/api/pennylane/synchro", "/api/pennylane/jeton", "/api/clients/dossiers"):
        r = c.post(route, json={}, headers=H)
        assert r.status_code == 409 and r.json()["demo"] is True and "Mode démo" in r.json()["erreur"], route
    lid = c.get("/api/lettres").json()["lettres"][0]["id"]
    e = c.post(f"/api/lettres/{lid}/pdf", headers=H).json()["lettre"]
    assert e["pdf"] is False and "Mode démo" in e["pdf_erreur"]
    assert c.get("/api/outlook").json()["adresse"] == ""
    r = c.get("/api/entreprises?q=dupain").json()["resultats"]  # répertoire simulé : sociétés inventées
    assert r and r[0]["nom"] == "ATELIER DUPAIN" and r[0]["siren"].startswith("000")


def test_demo_connexion_directe_reservee_a_la_demo(demo, app):
    c = Client(demo["url"])
    r = c.get("/?demo=1&page=clients", allow_redirects=False)
    assert r.status_code == 302 and r.headers["Location"] == "/#clients"
    assert c.get("/api/moi").json()["identifiant"] == "demo"
    hors_demo = app["client"]()  # serveur normal : le paramètre ne fait rien
    assert hors_demo.get("/?demo=1", allow_redirects=False).status_code == 200
    assert hors_demo.get("/api/moi").status_code == 401
