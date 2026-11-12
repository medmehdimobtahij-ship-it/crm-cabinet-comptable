/* CRM Cabinet : l'interface (JavaScript sans bibliothèque). Données et règles côté serveur (src/*.php) ; ici l'affichage,
   les gestes et le mouvement (jamais sec : l'ancien part PUIS le nouveau arrive). */
"use strict";

// ---------- Outils ----------
const ICONES = {
  // Icônes du rail : tracés Lucide (lucide.dev, licence ISC)
  pipeline: '<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/>',
  livrables: '<path d="m18 5-2.414-2.414A2 2 0 0 0 14.172 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2"/><path d="M21.378 12.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><path d="M8 18h1"/>',
  clients: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  parametres: '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
  lune: '<path d="M19.5 14.6A7.6 7.6 0 0 1 9.4 4.5a7.6 7.6 0 1 0 10.1 10.1z"/>',
  soleil: '<circle cx="12" cy="12" r="3.8"/><path d="M12 2.8v2M12 19.2v2M2.8 12h2M19.2 12h2M5.5 5.5l1.4 1.4M17.1 17.1l1.4 1.4M5.5 18.5l1.4-1.4M17.1 6.9l1.4-1.4"/>',
  recherche: '<circle cx="10.8" cy="10.8" r="6.3"/><path d="M15.5 15.5l4.5 4.5"/>',
  annuler: '<path d="M9 13.5 4.5 9 9 4.5"/><path d="M4.5 9h10a5 5 0 0 1 0 10H11"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  points: '<circle cx="6" cy="12" r="1.1"/><circle cx="12" cy="12" r="1.1"/><circle cx="18" cy="12" r="1.1"/>',
  ouvrir: '<path d="M8 16 16 8M9.5 8H16v6.5"/>',
  dossier: '<path d="M3.5 7.5a2 2 0 0 1 2-2h3.7l2 2h7.3a2 2 0 0 1 2 2v7.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
  mail: '<rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/>',
  chevron: '<path d="m10 7 5 5-5 5"/>',
  actualiser: '<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>',
  trombone: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
  telecharger: '<path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>',
  fermer: '<path d="M6 6l12 12M18 6 6 18"/>',
  retour: '<path d="M15 6l-6 6 6 6"/>',
  sortie: '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M9 8l-4 4 4 4M5 12h10"/>',
  archive: '<rect x="3.5" y="4.5" width="17" height="4.5" rx="1.2"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>',
  bouclier: '<path d="M12 21.5s7.5-3.6 7.5-9.5V5.6L12 2.8 4.5 5.6V12c0 5.9 7.5 9.5 7.5 9.5z"/>',
  bouclierOk: '<path d="M12 21.5s7.5-3.6 7.5-9.5V5.6L12 2.8 4.5 5.6V12c0 5.9 7.5 9.5 7.5 9.5z"/><path d="m8.8 11.9 2.2 2.2 4.3-4.4"/>',
};
const icone = (nom, cls = "") => `<svg class="ic ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONES[nom]}</svg>`;

function el(html) { const t = document.createElement("template"); t.innerHTML = html.trim(); return t.content.firstElementChild; }
const echap = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const $ = (sel, racine = document) => racine.querySelector(sel);
const $$ = (sel, racine = document) => [...racine.querySelectorAll(sel)];
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const MOUVEMENT = !matchMedia("(prefers-reduced-motion: reduce)").matches;

/** Ce que le registre des entreprises apprend en plus : SIRET, TVA, activité, effectif, dernier chiffre d'affaires publié. */
function resumeRegistre(r) {
  if (!r) return "";
  return [r.siret && `SIRET ${r.siret.replace(/^(\d{3})(\d{3})(\d{3})(\d{5})$/, "$1 $2 $3 $4")}`, r.tva && `TVA ${r.tva}`,
    r.naf && `${r.naf}${r.activite ? ` ${r.activite}` : ""}`, r.effectif, r.ca && `CA ${r.annee_finances} : ${euros(+r.ca)}`].filter(Boolean).join(" · ");
}
function euros(x) { return Math.round(x).toLocaleString("fr-FR").replace(/ /g, " ") + " €"; }
function dateFr(iso, annee = false) {
  if (!iso) return "";
  const [a, m, j] = iso.slice(0, 10).split("-");
  return annee ? `${j}/${m}/${a}` : `${j}/${m}`;
}
function instantFr(iso) { return iso ? `${dateFr(iso, true)} ${iso.slice(11, 16)}` : ""; }
function joursEntre(a, b) { return Math.round((new Date(b) - new Date(a)) / 864e5); }
function initiales(nom) {
  const mots = (nom || "").replace(/-/g, " ").split(/\s+/).filter((m) => /[\p{L}\p{N}]/u.test(m[0] || ""));
  if (!mots.length) return "?";
  return (mots[0][0] + (mots[1] ? mots[1][0] : mots[0].slice(1, 2))).toUpperCase();
}
const TEINTES = [["#E8EFFD", "#1F56C4"], ["#E2F2E9", "#1C7347"], ["#FCEFD9", "#9A5B00"], ["#E4F3F5", "#1D6A7A"], ["#F8E6EE", "#A23462"], ["#EEF0F2", "#3A3D43"]];
const TEINTES_SOMBRE = [["#2A2850", "#B3B1FF"], ["#15302A", "#5CCB96"], ["#3A2A14", "#F2B154"], ["#16283F", "#8CB8EC"], ["#3A1D2B", "#F08DB6"], ["#2A2C31", "#C9CCD2"]];
function teinte(nom) {
  let h = 0; for (const c of (nom || "").toLowerCase()) h = (h * 31 + c.codePointAt(0)) >>> 0;
  return (S.theme === "sombre" ? TEINTES_SOMBRE : TEINTES)[h % TEINTES.length];
}
function avatar(nom, cls = "", texte = null, balise = "span") {
  const [fond, encre] = teinte(nom);
  return `<${balise} class="avatar ${cls}" style="background:${fond};color:${encre}" title="${echap(nom)}">${echap(texte ?? initiales(nom))}</${balise}>`;
}

// ---------- Échanges avec le serveur ----------
async function api(methode, url, corps) {
  const r = await fetch(url, { method: methode, credentials: "same-origin",
    headers: { "Content-Type": "application/json", "X-CRM": "1" }, body: corps === undefined ? undefined : JSON.stringify(corps) });
  if (r.status === 401 && url !== "/api/connexion") { pageConnexion(url === "/api/moi" ? "" : "Session expirée : reconnectez-vous."); throw new Error("401"); }
  const d = r.headers.get("content-type")?.includes("json") ? await r.json() : {};
  if (!r.ok) { const e = new Error(d.erreur || "Erreur du serveur."); e.champs = d.champs; e.statut = r.status; throw e; }
  return d;
}

function toast(texte, options = {}) {
  const t = el(`<div class="toast ${options.erreur ? "erreur" : ""}"><span>${echap(texte)}</span></div>`);
  if (options.action) {
    const b = el(`<button>${echap(options.action)}</button>`); b.onclick = () => { options.auClic(); partir(); }; t.append(b);
  }
  $("#toasts").append(t);
  const partir = () => { t.classList.add("part"); setTimeout(() => t.remove(), 260); };
  setTimeout(partir, options.duree || 3600);
}
function signalerErreur(e) { if (e.message !== "401") toast(e.message, { erreur: true }); }

/** PDF d'une lettre : le serveur la range dans SharePoint (Lettres de mission › client) et la fait convertir par Microsoft,
 *  avec le compte Microsoft de la personne connectée. Retourne { lettre, action }. */
async function fairePdf(id) { return api("POST", `/api/lettres/${id}/pdf`); }

// ---------- État ----------
const S = {
  moi: null, etat: null, page: "pipeline", recherche: "", filtre: "TOUS", clients: null, clientsMaj: "",
  clientFiltre: "TOUS", clientRecherche: "", clientTri: ["nom", 1], clientCourant: null,
  theme: (() => { try { return localStorage.getItem("crm-theme") || "clair"; } catch { return "clair"; } })(),
  geste: false,
  nonLus: {}, mailsVus: null,  // mails non lus des prospects (boîte Outlook connectée) ; ids déjà signalés
};
document.documentElement.dataset.theme = S.theme;
const R = () => S.etat.referentiels;

async function charger() {
  S.etat = await api("GET", "/api/etat");
  majAnnuler();
}

// ---------- Connexion ----------
const LOGO_MICROSOFT = `<svg class="logo-ms" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/>
  <rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>`;
const MESSAGES_MICROSOFT = {
  refuse: "Connexion annulée sur la page Microsoft.",
  inconnu: "Ce compte Microsoft n'est pas un compte du CRM. Utilisez votre adresse e-mail du cabinet.",
  mfa: "Double authentification exigée : validez la connexion dans Microsoft Authenticator. Si Microsoft ne vous la demande pas, un administrateur doit l'activer pour le cabinet (Microsoft Entra).",
  expire: "Connexion trop ancienne, ou commencée dans un autre navigateur : recommencez.",
  indisponible: "La connexion Microsoft n'est pas encore activée pour cette adresse du CRM (Paramètres › Messagerie Outlook › Réglages avancés).",
  personnel: "Utilisez votre compte Microsoft du cabinet, pas un compte personnel.",
  deja: "Ce compte Microsoft est déjà lié à l'autre compte du CRM.",
  organisation: "Ce compte Microsoft n'appartient pas à l'organisation du cabinet.",
  consentement: "Le cabinet doit d'abord autoriser l'application « CRM Cabinet » (un administrateur Microsoft, une seule fois).",
  erreur: "Microsoft n'a pas pu confirmer la connexion : réessayez.",
};
let RETOUR_CONNEXION = (() => {  // /?microsoft=… : résultat de la page Microsoft (connexion au CRM, liaison du compte), puis adresse nettoyée
  const q = new URLSearchParams(location.search);
  const r = q.get("microsoft");
  if (!r) return null;
  history.replaceState(null, "", "/");
  return r;
})();

function pageConnexion(message = "") {
  S.moi = null;
  $("#couche-modales").innerHTML = "";
  $("#racine").innerHTML = "";
  if (!message && RETOUR_CONNEXION) { message = MESSAGES_MICROSOFT[RETOUR_CONNEXION] || ""; RETOUR_CONNEXION = null; }  // affiché une fois
  const f = el(`<div class="connexion"><form autocomplete="on">
      <div class="logo"><img src="logo.svg" alt=""></div>
      <div><h1>CRM Cabinet</h1><div style="color:var(--muted)">Connectez-vous pour accéder au pipeline du cabinet.</div></div>
      <a class="btn principal btn-microsoft cache" href="/api/microsoft/connexion">${LOGO_MICROSOFT}Se connecter avec Microsoft</a>
      <div class="separateur-ou cache"><span>ou avec votre mot de passe</span></div>
      <div class="par-mdp">
        <div class="champ"><label for="id">Identifiant</label><input id="id" class="saisie" name="username" autocomplete="username" required></div>
        <div class="champ"><label for="mdp">Mot de passe</label><input id="mdp" class="saisie" type="password" name="password" autocomplete="current-password" required></div>
        <button class="btn principal" type="submit">Se connecter</button>
      </div>
      <div class="msg-erreur">${echap(message)}</div>
      <div class="bandeau-demo cache"><b>Mode démo</b> · données fictives, aucun service externe appelé.<br>
        Identifiant <code>demo</code> · mot de passe <code>demo</code></div>
      <div class="pied-connexion">Connexion chiffrée · session fermée après 2 h sans activité</div>
    </form></div>`);
  $("#racine").append(f);
  $("#id", f).focus();
  $("form", f).onsubmit = async (ev) => {
    ev.preventDefault();
    try {
      S.moi = await api("POST", "/api/connexion", { identifiant: $("#id", f).value, mot_de_passe: $("#mdp", f).value });
      await demarrer();
    } catch (e) { $(".msg-erreur", f).textContent = e.message; }
  };
  fetch("/api/connexion/options", { credentials: "same-origin" }).then((r) => r.json()).then((o) => {
    $(".btn-microsoft", f).classList.toggle("cache", !o.microsoft);
    $(".separateur-ou", f).classList.toggle("cache", !(o.microsoft && o.mot_de_passe));
    $(".par-mdp", f).classList.toggle("cache", !o.mot_de_passe);
    if (o.demo) {  // démo : identifiants affichés et pré-remplis
      $(".bandeau-demo", f).classList.remove("cache");
      if (!$("#id", f).value) { $("#id", f).value = "demo"; $("#mdp", f).value = "demo"; }
    }
    if (o.microsoft) $(".btn-microsoft", f).focus();
  }).catch(() => { /* options illisibles : mot de passe seul */ });
}

// ---------- Coque : rail, en-tête, pages ----------
const PAGES = [
  ["pipeline", "Pipeline", "pipeline"], ["livrables", "Livrables", "livrables"],
  ["clients", "Clients", "clients"], ["parametres", "Paramètres", "parametres"],
];

function construireCoque() {
  $("#racine").innerHTML = "";
  const c = el(`<div id="coque">
    <nav id="gauche" aria-label="Navigation">
      <div class="logo"><img src="logo.svg" alt="CRM Cabinet"></div>
      <div class="rail">
        <div class="rail-encre"></div><div class="rail-survol"><span></span></div>
        ${PAGES.map(([code, nom, ic]) => `<button data-page="${code}" aria-label="${nom}">${icone(ic)}</button>`).join("")}
      </div>
      <button class="bouton-theme" title="Changer de thème">${icone(S.theme === "sombre" ? "soleil" : "lune")}</button>
    </nav>
    <main id="droite">
      <header class="entete">
        <div><h1 id="titre"></h1><div class="sous" id="sous"></div></div>
        <div class="outils">
          ${avatar(S.moi.nom, "", null, "button").replace("<button", '<button id="moi"')}
        </div>
      </header>
      <section id="page"></section>
    </main>
  </div>`);
  $("#racine").append(c);
  const rail = $(".rail", c), survol = $(".rail-survol", c), nom = $(".rail-survol span", c);
  let sortie = null;
  $$(".rail button", c).forEach((b) => {
    b.onclick = () => aller(b.dataset.page);
    b.onmouseenter = () => {
      clearTimeout(sortie);
      // La bulle noire glisse jusqu'à l'icône et s'étire pour porter le nom (largeur mesurée sur le texte)
      if (!rail.classList.contains("survol")) { survol.style.transition = "none"; survol.style.top = b.offsetTop + "px"; void survol.offsetWidth; survol.style.transition = ""; }
      survol.style.top = b.offsetTop + "px";
      nom.textContent = b.getAttribute("aria-label");
      survol.style.width = nom.scrollWidth + "px";  // marges comprises (46 px sous l'icône + 22 px)
      $$(".rail button", c).forEach((x) => x.classList.toggle("survole", x === b));
      rail.classList.add("survol");
    };
    b.onmouseleave = () => {
      sortie = setTimeout(() => {
        rail.classList.remove("survol"); survol.style.width = "46px";
        $$(".rail button", c).forEach((x) => x.classList.remove("survole"));
      }, 80);
    };
  });
  $(".bouton-theme", c).onclick = basculerTheme;
  $("#moi", c).onclick = async (ev) => {
    const ancre = ev.currentTarget;
    let boiteOutlook = "";
    try { boiteOutlook = (await etatOutlook()).adresse; } catch { /* menu sans la messagerie */ }
    menu(ancre, [
      [`Connecté : ${S.moi.nom}`, null],
      ["Changer mon mot de passe", () => aller("parametres")],
      "-",
      ...(boiteOutlook ? [[`Outlook : ${boiteOutlook}`, null], ["Déconnecter ma boîte Outlook", () => deconnecterOutlook()]]
        : [["Connecter ma boîte Outlook", () => connecterOutlook()]]),
      "-",
      ["Se déconnecter du CRM", async () => { await api("POST", "/api/deconnexion"); pageConnexion(); }],
    ]);
  };
}

/** Déconnecte la boîte Outlook de la personne (le CRM oublie son jeton ; rien n'est supprimé dans Outlook). */
async function deconnecterOutlook(apres) {
  if (!confirm("Déconnecter votre boîte Outlook du CRM ?\nVos mails restent dans Outlook ; vous pourrez la reconnecter quand vous voulez.")) return false;
  await api("POST", "/api/outlook/deconnexion");
  S.outlook = null;
  toast("Boîte Outlook déconnectée.");
  const fiche = $(".fenetre[data-fiche]");
  if (fiche && fiche.onglet === "echanges") ongletFiche(fiche, fiche.dataset.fiche);
  apres?.();
  return true;
}

function majRail() {
  majBadgeRail();
  const i = PAGES.findIndex(([code]) => code === S.page);
  const b = $$(".rail button")[i];
  $(".rail-encre").style.top = b.offsetTop + "px";
  $$(".rail button").forEach((x, k) => x.classList.toggle("actif", k === i));
}

function majAnnuler() { /* plus de bouton : Ctrl+Z / Ctrl+Y et le lien « Annuler » des messages suffisent */ }

function basculerTheme() {
  S.theme = S.theme === "sombre" ? "clair" : "sombre";
  try { localStorage.setItem("crm-theme", S.theme); } catch { /* navigateur privé : thème non retenu */ }
  document.documentElement.dataset.theme = S.theme;
  $(".bouton-theme").innerHTML = icone(S.theme === "sombre" ? "soleil" : "lune");
  const moi = $("#moi"), [fond, encre] = teinte(S.moi.nom);
  moi.style.background = fond; moi.style.color = encre;
  rendre(false);
}

/** Changement de page : l'ancienne s'efface, PUIS la nouvelle arrive en remontant (jamais les deux l'une sur l'autre). */
async function aller(page, avecTransition = true) {
  fermerMenus();
  const change = page !== S.page;
  S.page = page;
  majRail();
  if (page === "clients" && change) S.clientCourant = null;
  if (change && avecTransition && MOUVEMENT) {
    const zone = $("#page");
    zone.classList.remove("entree"); zone.classList.add("sortie");
    await attendre(140);
    if (page === "clients" && !S.clients) await chargerClients().catch(signalerErreur);
    rendre(false);
    zone.classList.remove("sortie"); void zone.offsetWidth; zone.classList.add("entree");
  } else {
    if (page === "clients" && !S.clients) await chargerClients().catch(signalerErreur);
    rendre(false);
  }
}

function rendre(transition = true) {
  const titres = {
    pipeline: ["Pipeline", ""], livrables: ["Livrables", "Propositions et lettres de mission"],
    clients: ["Clients", "Clients du cabinet (dossiers Pennylane) et nouveaux clients en onboarding"],
    parametres: ["Paramètres", "Réglages du CRM, mails de relance et compte"],
  }[S.page];
  $("#titre").textContent = titres[0]; $("#sous").textContent = titres[1];
  majResumePipeline();
  const zone = $("#page");
  zone.innerHTML = "";
  ({ pipeline: pagePipeline, livrables: pageLivrables, clients: pageClients, parametres: pageParametres })[S.page](zone);
  if (transition && MOUVEMENT) { zone.classList.remove("entree"); void zone.offsetWidth; zone.classList.add("entree"); }
}

// ---------- Pipeline ----------
const COULEUR_ETAPE = { A_QUALIFIER: "var(--e-gris)", RDV_DECOUVERTE: "var(--e-orange)", PROPOSITION_ENVOYEE: "var(--e-bleu)", LETTRE_ENVOYEE: "var(--e-vert)" };
const enCours = () => S.etat.prospects.filter((p) => p.statut === "PROSPECT");

function sousTitrePipeline() {
  const ps = enCours(), gain = ps.reduce((s, p) => s + p.recurrent, 0);
  return `${ps.length} prospect${ps.length > 1 ? "s" : ""} en cours` + (gain ? ` · ${euros(gain)} HT/an de gain potentiel` : "");
}
/** En-tête du pipeline, à droite : nombre de prospects en cours et gain potentiel total, bien lisible. */
function majResumePipeline() {
  const outils = $(".entete .outils");
  if (!outils) return;
  let r = $(".resume-pipeline", outils);
  if (S.page !== "pipeline") return r?.remove();
  if (!r) { r = el('<div class="resume-pipeline"></div>'); outils.prepend(r); }
  const ps = enCours(), gain = ps.reduce((s, p) => s + p.recurrent, 0);
  r.innerHTML = `<div class="rp-gain"><b>${gain ? euros(gain) : "0 €"}</b><span>HT/an</span></div>
    <div class="rp-sous">Gain potentiel · ${ps.length} prospect${ps.length > 1 ? "s" : ""} en cours</div>`;
}

/** « À relancer » : relance en retard ou du jour, et prospects « sans relance prévue ». Les relances à venir (7 jours)
 *  sont seulement signalées en orange sur les cartes. */
const HORIZON_RELANCE = 7;
function joursAvantRelance(p) { return p.prochaine_relance ? joursEntre(S.etat.aujourdhui, p.prochaine_relance) : null; }
function aRelancer(p) { const j = joursAvantRelance(p); return p.alertes.length > 0 || (j !== null && j <= 0); }

function prospectsFiltres() {
  const q = S.recherche.trim().toLowerCase();
  const ls = enCours().filter((p) => {
    if (q && !`${p.denomination} ${p.code}`.toLowerCase().includes(q)) return false;
    if (S.filtre === "RELANCER" && !aRelancer(p)) return false;
    return true;
  });
  // À relancer : les plus urgentes en haut de chaque colonne
  if (S.filtre === "RELANCER") ls.sort((a, b) => (joursAvantRelance(a) ?? -999) - (joursAvantRelance(b) ?? -999));
  return ls;
}

function pagePipeline(zone) {
  const barre = el(`<div class="barre">
      <label class="recherche">${icone("recherche")}<input class="saisie" placeholder="Rechercher un prospect, un code…" value="${echap(S.recherche)}"></label>
      <div class="segments" id="filtres"><span class="pilule"></span>
        <button data-v="TOUS">Tous <span class="compte" id="nb-tous"></span></button><button data-v="RELANCER">À relancer <span class="compte" id="nb-relancer"></span></button></div>
      <span class="espace"></span>
      <button class="btn principal" id="nouveau">${icone("plus")} Nouveau prospect</button>
    </div>`);
  zone.append(barre);
  const champ = $("input", barre);
  champ.oninput = () => { S.recherche = champ.value; remplirColonnes(); };
  segments($("#filtres", barre), S.filtre, (v) => { S.filtre = v; remplirColonnes(true); });
  $("#nouveau", barre).onclick = () => nouveauProspect();
  const parcours = el(`<div class="parcours"></div>`);
  R().etapes.forEach(([code, libelle], i) => {
    const col = el(`<div class="colonne" data-etape="${code}">
        <div class="tete"><span class="point" style="background:${COULEUR_ETAPE[code]}"></span>
          <span class="titre" title="${echap(libelle)}">${echap(libelle)}</span><span class="nb"></span><span class="total cache"></span></div>
        <div class="corps"></div>
        ${i < 3 ? `<span class="noeud" style="color:${COULEUR_ETAPE[R().etapes[i + 1][0]]}">${icone("chevron")}</span>` : ""}
      </div>`);
    parcours.append(col);
  });
  zone.append(parcours);
  remplirColonnes();
}

/** Remplit les colonnes. doux : fondu enchaîné (filtre). arrivee : id de la carte à déplier. */
function remplirColonnes(doux = false, arrivee = null) {
  if (S.page !== "pipeline" || !$(".parcours")) return;
  majResumePipeline();
  const nbR = enCours().filter(aRelancer).length;
  $("#nb-tous").textContent = enCours().length; $("#nb-relancer").textContent = nbR;
  $("#filtres .pilule") && segmentsReplacer($("#filtres"));
  const liste = prospectsFiltres();
  $(".bandeau-relance")?.remove();
  if (S.filtre === "RELANCER") {  // le filtre s'explique : ce qu'il montre, ou pourquoi c'est vide
    const prochaine = enCours().filter((p) => p.prochaine_relance).sort((a, b) => a.prochaine_relance.localeCompare(b.prochaine_relance))[0];
    $(".parcours").before(el(`<div class="bandeau-relance">${icone("mail")}<span>${liste.length
      ? `<b>${liste.length} prospect${liste.length > 1 ? "s" : ""} à relancer</b> : relances en retard ou du jour, et prospects sans relance prévue.`
      : `<b>Rien à relancer aujourd'hui.</b>${prochaine ? ` Prochaine relance : ${echap(prochaine.denomination)}, le ${dateFr(prochaine.prochaine_relance, true)}.` : ""}`}</span></div>`));
  }
  for (const col of $$(".colonne")) {
    const etape = col.dataset.etape, ps = liste.filter((p) => p.etape === etape);
    const corps = $(".corps", col);
    const total = ps.reduce((s, p) => s + p.recurrent, 0);
    $(".nb", col).textContent = ps.length;
    const t = $(".total", col); t.textContent = total ? `${euros(total)}/an` : ""; t.classList.toggle("cache", !total);
    t.title = "Honoraires récurrents de la colonne, sur un an";
    corps.innerHTML = ps.length ? "" : `<div class="vide">Glissez un prospect ici</div>`;
    for (const p of ps) {
      const c = carte(p);
      if (p.id === arrivee) c.classList.add("deploie");
      corps.append(c);
    }
    if (doux && MOUVEMENT) { corps.animate([{ opacity: 0.2 }, { opacity: 1 }], { duration: 260, easing: "cubic-bezier(.33,1,.68,1)" }); }
  }
}

function carte(p) {
  const contact = (p.contacts || [])[0] || {};
  const nomContact = [contact.prenom, contact.nom].filter(Boolean).join(" ");
  const auj = S.etat.aujourdhui;
  const badges = [];
  const nl = S.nonLus[p.id];
  if (p.prochaine_relance) {
    if (p.alertes.includes("RELANCE_EN_RETARD")) badges.push(["rouge", `Relance ${dateFr(p.prochaine_relance)} · en retard`]);
    else if (p.prochaine_relance === auj) badges.push(["vert", "Relance aujourd'hui"]);
    else {
      const j = joursEntre(auj, p.prochaine_relance);  // bientôt : en orange, en jours
      badges.push(j <= HORIZON_RELANCE ? ["orange", j === 1 ? "Relance demain" : `Relance dans ${j} j`] : ["gris", `Relance ${dateFr(p.prochaine_relance)}`]);
    }
  }
  if (p.alertes.includes("SANS_RELANCE")) badges.push(["orange", R().alertes.SANS_RELANCE]);
  if (p.en_creation) badges.push(["violet", "Création"]);
  if (p.etape === "LETTRE_ENVOYEE") badges.push(["orange", "À signer" + (p.etape_le ? ` · depuis le ${dateFr(p.etape_le)}` : "")]);
  const j = p.derniere_activite ? joursEntre(p.derniere_activite, auj) : null;
  const delai = parseInt(S.etat.parametres.DelaiSansActiviteJours, 10) || 15;
  const gain = [p.recurrent ? `${euros(p.recurrent)} HT/an` : "", p.ponctuel ? `${euros(p.ponctuel)} HT ponctuel` : ""].filter(Boolean).join(" · ");
  const c = el(`<article class="carte" data-id="${p.id}">
      <div class="haut">${avatar(nomContact || p.denomination)}
        <div style="min-width:0"><div class="nom">${echap(p.denomination)}</div>
          <div class="meta">${echap([p.code, nomContact].filter(Boolean).join(" · "))}</div></div>
        <button class="menu" title="Actions">${icone("points")}</button></div>
      ${badges.length || nl ? `<div class="badges">${nl ? `<button class="pastille p-message" data-a="messages" title="${echap(`${nl.de} : « ${nl.objet} »`)}"><span class="point-message"></span>${nl.non_lus} nouveau${nl.non_lus > 1 ? "x" : ""} message${nl.non_lus > 1 ? "s" : ""}</button>` : ""}${badges.map(([f, t]) => `<span class="pastille p-${f}">${echap(t)}</span>`).join("")}</div>` : ""}
      <button class="gain ${gain ? "" : "vide"}" title="Gain potentiel : honoraires récurrents sur un an · missions ponctuelles">${gain ? echap(gain) : "＋ Gain potentiel"}</button>
      <div class="bas"><span class="activite ${j === 0 ? "jour" : j === null || j >= delai ? "ancienne" : ""}">${j === null ? "Aucune activité" : j === 0 ? "Aujourd'hui" : `Il y a ${j} j`}</span>
        <button class="btn ${p.etape === "LETTRE_ENVOYEE" ? "principal" : ""}" data-a="signee">Lettre signée</button>
        <button class="btn" data-a="perdu">Perdu</button></div>
    </article>`);
  $(".menu", c).onclick = (ev) => { ev.stopPropagation(); menuCarte(ev.currentTarget, p); };
  $('[data-a="messages"]', c)?.addEventListener("click", (ev) => { ev.stopPropagation(); ouvrirFiche(p.id, "echanges"); });
  if (nl) c.classList.add("a-message");
  $(".gain", c).onclick = (ev) => { ev.stopPropagation(); fenetreHonoraires(p); };
  $('[data-a="signee"]', c).onclick = (ev) => { ev.stopPropagation(); fenetreSignee(p); };
  $('[data-a="perdu"]', c).onclick = (ev) => { ev.stopPropagation(); fenetrePerdu(p); };
  geste(c, p);
  return c;
}

function menuCarte(ancre, p) {
  const items = [["Déplacer vers…", null]];
  for (const [e, l] of R().etapes) if (e !== p.etape) items.push(["    " + l, () => deplacer(p.id, e)]);
  items.push("-");
  if (p.prochaine_relance) {
    items.push(["Préparer le mail de relance", () => fenetreMailRelance(p)]);
    items.push(["Relance faite", () => relanceFaite(p)]);
  }
  items.push(["Prévoir une relance…", () => fenetreRelance(p)], ["Saisir le gain potentiel…", () => fenetreHonoraires(p)], "-",
    ["Lettre de mission signée…", () => fenetreSignee(p)], ["Marquer perdu…", () => fenetrePerdu(p)], "-",
    ["Supprimer définitivement…", () => fenetreSupprimer(p)]);
  menu(ancre, items);
}

async function executer(promesse, message) {
  try {
    const r = await promesse;
    if (r.annulation) S.etat.annulation = r.annulation;
    await charger();
    if (message) toast(message, S.etat.annulation?.annuler ? { action: "Annuler", auClic: annuler } : {});
    return r;
  } catch (e) { signalerErreur(e); throw e; }
}

async function deplacer(pid, etape, deposee = false) {
  const p = S.etat.prospects.find((x) => x.id === pid);
  await executer(api("POST", `/api/prospects/${pid}/deplacer`, { etape }), `${p.denomination} : ${R().etapes.find(([e]) => e === etape)[1]}.`);
  remplirColonnes(false, deposee ? null : pid);
  const i = R().etapes.findIndex(([e]) => e === etape);
  const noeud = $$(".noeud")[i - 1];
  if (noeud && MOUVEMENT) { noeud.classList.remove("pulse"); void noeud.offsetWidth; noeud.classList.add("pulse"); }
}

async function relanceFaite(p) {
  const r = await executer(api("POST", `/api/prospects/${p.id}/relance-faite`, {}), null);
  toast(r.resultat ? `Relance notée. Prochaine relance le ${dateFr(r.resultat, true)}.` : "Relance notée.", { action: "Annuler", auClic: annuler });
  rafraichirPage();
}

function rafraichirPage() {
  if (S.page === "pipeline") remplirColonnes();
  const fiche = $(".fenetre[data-fiche]");
  if (fiche) remplirFiche(fiche);
}

/** Glisser-déposer : après 6 px, la carte suit le pointeur et bascule autour de son centre (pendule amorti) ; déposée sur
 *  une colonne, elle glisse jusqu'à sa place et se redresse. Un simple clic ouvre la fiche. */
function geste(c, p) {
  c.addEventListener("pointerdown", (ev) => {
    if (ev.button !== 0 || ev.target.closest("button")) return;
    const depart = { x: ev.clientX, y: ev.clientY };
    let flottante = null, colonne = null;
    const rect = c.getBoundingClientRect();
    const prise = { x: ev.clientX - rect.left, y: ev.clientY - rect.top };
    const etat = { x: ev.clientX, y: ev.clientY, px: ev.clientX, vx: 0, angle: 0, elan: 0, echelle: 1, anim: 0 };

    const pas = () => {
      const dx = etat.x - etat.px; etat.px = etat.x;
      etat.vx = etat.vx * 0.6 + dx * 0.4;
      const cible = MOUVEMENT ? Math.max(-14, Math.min(14, etat.vx * 0.9)) : 0;
      etat.elan = (etat.elan + (cible - etat.angle) * 0.12) * 0.8; etat.angle += etat.elan;
      etat.echelle += (1.03 - etat.echelle) * 0.2;
      flottante.style.transform = `translate(${etat.x - prise.x}px, ${etat.y - prise.y}px) rotate(${etat.angle}deg) scale(${etat.echelle})`;
      etat.anim = requestAnimationFrame(pas);
    };
    const bouger = (e) => {
      etat.x = e.clientX; etat.y = e.clientY;
      if (!flottante) {
        if (Math.abs(e.clientX - depart.x) + Math.abs(e.clientY - depart.y) < 6) return;
        S.geste = true;
        flottante = c.cloneNode(true);
        flottante.classList.add("suspendue");
        Object.assign(flottante.style, { left: "0px", top: "0px", width: rect.width + "px", transformOrigin: "50% 50%" });
        document.body.append(flottante);
        c.classList.add("fantome");
        document.body.style.cursor = "grabbing";
        etat.anim = requestAnimationFrame(pas);
      }
      flottante.style.display = "none";
      const sous = document.elementFromPoint(e.clientX, e.clientY)?.closest(".colonne") || null;
      flottante.style.display = "";
      if (sous !== colonne) {
        colonne?.classList.remove("survol");
        colonne = sous && sous.dataset.etape !== p.etape ? sous : null;
        colonne?.classList.add("survol");
      }
    };
    const lacher = async () => {
      removeEventListener("pointermove", bouger); removeEventListener("pointerup", lacher); removeEventListener("pointercancel", lacher);
      if (!flottante) { ouvrirFiche(p.id); return; }
      cancelAnimationFrame(etat.anim);
      document.body.style.cursor = "";
      colonne?.classList.remove("survol");
      const depuis = flottante.getBoundingClientRect();
      if (colonne) {
        const etape = colonne.dataset.etape;
        try { await deplacer(p.id, etape, true); } catch { /* erreur déjà signalée : la carte revient */ }
      }
      const cible = $(`.carte[data-id="${p.id}"]`);
      atterrir(flottante, etat, prise, cible);
      S.geste = false;
    };
    addEventListener("pointermove", bouger); addEventListener("pointerup", lacher); addEventListener("pointercancel", lacher);
  });
}

/** La carte en l'air glisse jusqu'à sa place (nouvelle ou d'origine), se redresse, puis la vraie carte prend le relais. */
function atterrir(flottante, etat, prise, cible) {
  if (!cible) { flottante.remove(); return; }
  cible.classList.remove("fantome"); cible.classList.add("cachee");
  const r = cible.getBoundingClientRect();
  flottante.style.width = r.width + "px";
  const fin = () => { cible.classList.remove("cachee"); flottante.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160 }).onfinish = () => flottante.remove(); };
  if (!MOUVEMENT) { flottante.remove(); cible.classList.remove("cachee"); return; }
  const a = flottante.animate([
    { transform: `translate(${etat.x - prise.x}px, ${etat.y - prise.y}px) rotate(${etat.angle}deg) scale(${etat.echelle})` },
    { transform: `translate(${r.left}px, ${r.top}px) rotate(0deg) scale(1)`, boxShadow: "0 4px 16px var(--ombre)" },
  ], { duration: 340, easing: "cubic-bezier(.65,0,.35,1)", fill: "forwards" });
  a.onfinish = fin;
}

/** Remet la pilule sous le bouton actif (son texte a changé de largeur, ex. un compteur). */
function segmentsReplacer(conteneur) {
  const b = $("button.actif", conteneur), pilule = $(".pilule", conteneur);
  if (b && pilule) { pilule.style.left = b.offsetLeft + "px"; pilule.style.width = b.offsetWidth + "px"; }
}

// ---------- Pilule glissante ----------
function segments(conteneur, valeur, auChoix) {
  const pilule = $(".pilule", conteneur);
  const placer = (b, anime) => {
    if (!anime) pilule.style.transition = "none";
    pilule.style.left = b.offsetLeft + "px"; pilule.style.width = b.offsetWidth + "px";
    if (!anime) { void pilule.offsetWidth; pilule.style.transition = ""; }
    $$("button", conteneur).forEach((x) => x.classList.toggle("actif", x === b));
  };
  const boutons = $$("button", conteneur);
  requestAnimationFrame(() => placer(boutons.find((b) => b.dataset.v === valeur) || boutons[0], false));
  boutons.forEach((b) => b.onclick = () => { placer(b, true); auChoix(b.dataset.v); });
  return { placer };
}

// ---------- Menus ----------
function fermerMenus() { $$(".menu-flottant").forEach((m) => m.remove()); }
function menu(ancre, items) {
  fermerMenus();
  const m = el(`<div class="menu-flottant" role="menu"></div>`);
  for (const it of items) {
    if (it === "-") { m.append(el("<hr>")); continue; }
    const [libelle, fn] = it;
    if (!fn) { m.append(el(`<div class="titre-menu">${echap(libelle)}</div>`)); continue; }
    const b = el(`<button role="menuitem">${echap(libelle.trim())}</button>`);
    if (libelle.startsWith("    ")) b.style.paddingLeft = "22px";
    b.onclick = () => { fermerMenus(); fn(); };
    m.append(b);
  }
  document.body.append(m);
  const r = ancre.getBoundingClientRect(), mr = m.getBoundingClientRect();
  m.style.left = Math.min(r.left, innerWidth - mr.width - 10) + "px";
  m.style.top = (r.bottom + mr.height + 6 > innerHeight ? r.top - mr.height - 6 : r.bottom + 6) + "px";
  setTimeout(() => addEventListener("pointerdown", function dehors(e) {
    if (!m.contains(e.target)) { m.remove(); removeEventListener("pointerdown", dehors); }
  }), 0);
}

// ---------- Fenêtres ----------
function fenetre({ titre, sous = "", corps = "", pied = "", classe = "" }) {
  const v = el(`<div class="voile"><div class="fenetre ${classe}" role="dialog" aria-modal="true">
      <div class="f-tete"><div style="min-width:0"><h2>${titre}</h2>${sous ? `<div class="sous">${sous}</div>` : ""}</div>
        <button class="rond fermer" title="Fermer (Échap)">${icone("fermer")}</button></div>
      <div class="f-corps">${corps}</div>${pied ? `<div class="f-pied">${pied}</div>` : ""}</div></div>`);
  const f = $(".fenetre", v);
  const fermer = () => {
    if (!v.isConnected) return;
    removeEventListener("keydown", echap_);
    v.classList.add("part"); setTimeout(() => v.remove(), MOUVEMENT ? 180 : 0);
  };
  const echap_ = (e) => { if (e.key === "Escape" && $("#couche-modales").lastElementChild === v) fermer(); };
  addEventListener("keydown", echap_);
  $(".fermer", f).onclick = fermer;
  v.addEventListener("pointerdown", (e) => { if (e.target === v) v.dataset.dehors = "1"; });
  v.addEventListener("pointerup", (e) => { if (e.target === v && v.dataset.dehors) fermer(); delete v.dataset.dehors; });
  $("#couche-modales").append(v);
  f.fermer = fermer;
  setTimeout(() => $("input:not([type=checkbox]):not([type=radio]), select, textarea", f)?.focus(), 60);
  return f;
}

function erreursChamps(f, champs = {}) {
  $$(".msg-erreur[data-pour]", f).forEach((m) => m.remove());
  $$(".saisie.erreur", f).forEach((x) => x.classList.remove("erreur"));
  for (const [cle, msg] of Object.entries(champs)) {
    const champ = $(`[name="${cle}"]`, f);
    if (champ) { champ.classList.add("erreur"); champ.closest(".champ")?.append(el(`<div class="msg-erreur" data-pour="${cle}">${echap(msg)}</div>`)); }
    else toast(msg, { erreur: true });
  }
}

const champ = (nom, libelle, controle, facultatif = false) =>
  `<div class="champ"><label for="c-${nom}">${libelle}${facultatif ? ' <span class="facultatif">(facultatif)</span>' : ""}</label>${controle}</div>`;
const saisie = (nom, valeur = "", attrs = "") => `<input id="c-${nom}" name="${nom}" class="saisie" value="${echap(valeur)}" ${attrs}>`;
const liste = (nom, options, valeur = "") => `<select id="c-${nom}" name="${nom}" class="saisie">${Object.entries(options)
  .map(([k, l]) => `<option value="${echap(k)}" ${k === valeur ? "selected" : ""}>${echap(l)}</option>`).join("")}</select>`;
const zoneTexte = (nom, valeur = "", attrs = "") => `<textarea id="c-${nom}" name="${nom}" class="saisie" ${attrs}>${echap(valeur)}</textarea>`;
/** Champ qui n'apparaît que si une réponse le demande (ex. « Cabinet précédent » après « Reprise d'un confrère ») :
 *  il se déplie en douceur ; replié, il n'est ni visible ni atteignable au clavier. */
const revele = (html, ouvert, cle = "") => `<div class="revele ${ouvert ? "ouvert" : ""}" data-revele="${cle}" ${ouvert ? "" : "inert"}><div>${html}</div></div>`;
function ouvrirRevele(bloc, ouvert) {
  bloc.classList.toggle("ouvert", ouvert); bloc.toggleAttribute("inert", !ouvert);
  if (ouvert) setTimeout(() => $("input", bloc)?.focus(), 200);
}
function brancherReprise(zone) {
  const origine = $('[name="origine"]', zone), bloc = $('[data-revele="reprise"]', zone);
  if (origine && bloc) origine.addEventListener("change", () => ouvrirRevele(bloc, origine.value === "REPRISE"));
  const source = $('[name="source"]', zone), autre = $('[data-revele="source"]', zone);
  if (source && autre) source.addEventListener("change", () => ouvrirRevele(autre, source.value === "AUTRE"));
  const plusTard = $('[name="cabinet_plus_tard"]', zone), cabinet = $('[name="cabinet_precedent"]', zone);
  const suivre = () => { if (!plusTard || !cabinet) return; cabinet.disabled = plusTard.checked; if (plusTard.checked) cabinet.value = ""; };
  plusTard?.addEventListener("change", suivre); suivre();
  brancherMissions(zone);
}
/** Origines selon la forme : une entreprise individuelle se crée, elle n'est pas une société. */
const originesDe = (typePro) => (typePro === "EI" ? R().origines_ei : R().origines);
/** Champs « Autre (précisez) » et « Cabinet précédent » (avec « À renseigner plus tard »), communs à l'assistant et à la fiche. */
function champsSuivi(v, pro) {
  return `${revele(champ("source_autre", "Précisez la source", saisie("source_autre", v.source_autre || "", 'maxlength="80" placeholder="ex. salon, ancien client, LinkedIn…"')), v.source === "AUTRE", "source")}
    ${pro ? revele(`${champ("cabinet_precedent", "Cabinet précédent", saisie("cabinet_precedent", v.cabinet_precedent || ""))}
      <label class="case"><input type="checkbox" name="cabinet_plus_tard" ${v.cabinet_plus_tard ? "checked" : ""}>À renseigner plus tard
        <span class="facultatif">(obligatoire avant d'envoyer la lettre de mission)</span></label>`, v.origine === "REPRISE", "reprise") : ""}`;
}
/** Missions d'une entreprise individuelle : LMNP fait partie des BIC, il n'apparaît que si « BIC (déclaration 2031) » est coché. */
const MISSION_BIC = "BIC (déclaration 2031)", MISSION_LMNP = "LMNP (location meublée)";
function casesMissions(liste, cochees) {
  return liste.map((m) => `<label class="case ${m === MISSION_LMNP && liste.includes(MISSION_BIC) ? `sous-mission ${cochees.includes(MISSION_BIC) ? "" : "cache"}` : ""}">
    <input type="checkbox" data-mission="${echap(m)}" ${cochees.includes(m) ? "checked" : ""}>${echap(m)}</label>`).join("");
}
function brancherMissions(zone, apres) {
  const bic = $(`[data-mission="${MISSION_BIC}"]`, zone), lmnp = $(`[data-mission="${MISSION_LMNP}"]`, zone);
  $$("[data-mission]", zone).forEach((x) => x.addEventListener("change", () => {
    if (x === bic && lmnp) { lmnp.closest(".case").classList.toggle("cache", !bic.checked); if (!bic.checked) lmnp.checked = false; }
    apres?.();
  }));
}
const valeurs = (f) => Object.fromEntries($$("[name]", f).map((x) => [x.name, x.type === "checkbox" ? x.checked : x.value]));

function fenetrePerdu(p) {
  const f = fenetre({
    titre: `Marquer « ${echap(p.denomination)} » comme perdu ?`, sous: "Le dossier quitte le pipeline.",
    corps: champ("motif", "Motif", liste("motif", { "": "Choisir un motif…", ...R().motifs })) + champ("precision", "Précision", zoneTexte("precision"), true),
    pied: `<button class="btn fantome" data-a="non">Garder</button><button class="btn principal" data-a="ok">Marquer perdu</button>`,
  });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try { await executer(api("POST", `/api/prospects/${p.id}/perdu`, valeurs(f)), `${p.denomination} : perdu.`); f.fermer(); fermerFiche(); rafraichirPage(); }
    catch (e) { erreursChamps(f, e.champs); }
  };
}

function fenetreSupprimer(p) {
  const f = fenetre({
    titre: `Supprimer « ${echap(p.denomination)} » ?`, classe: "moyenne",
    sous: "Êtes-vous sûr ? Le prospect est effacé du CRM, avec son historique : ce n'est pas « Perdu ».",
    corps: `<div class="alerte">Les lettres déjà préparées restent dans Livrables, et les mails restent dans Outlook.</div>`,
    pied: `<button class="btn fantome" data-a="non">Garder</button><button class="btn principal danger-plein" data-a="ok">Supprimer définitivement</button>`,
  });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try { await executer(api("POST", `/api/prospects/${p.id}/supprimer`, {}), `${p.denomination} : supprimé.`); f.fermer(); fermerFiche(); rafraichirPage(); }
    catch (e) { signalerErreur(e); }
  };
}

function fenetreSignee(p) {
  const f = fenetre({
    titre: "Lettre de mission signée", sous: `${echap(p.denomination)} devient client du cabinet (onboarding : pièces à recevoir) et quitte le pipeline.`,
    corps: champ("le", "Signée le", saisie("le", S.etat.aujourdhui, 'type="date"')),
    pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Confirmer la signature</button>`,
  });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try {
      await executer(api("POST", `/api/prospects/${p.id}/signee`, valeurs(f)), `${p.denomination} : client du cabinet (onboarding).`);
      S.clients = null; f.fermer(); fermerFiche(); rafraichirPage();
    } catch (e) { erreursChamps(f, e.champs); }
  };
}

function fenetreRelance(p) {
  const f = fenetre({
    titre: "Prévoir une relance", sous: echap(p.denomination),
    corps: champ("le", "Date de relance", saisie("le", p.prochaine_relance || "", 'type="date"')) + champ("note", "Note", saisie("note", p.note_relance || "", 'maxlength="200"'), true),
    pied: `${p.prochaine_relance ? '<button class="btn fantome gauche danger" data-a="suppr">Supprimer la relance</button>' : ""}
      <button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Enregistrer</button>`,
  });
  $('[data-a="non"]', f).onclick = f.fermer;
  const envoyer = async (d) => {
    try { await executer(api("POST", `/api/prospects/${p.id}/relance`, d), d.le ? `Relance prévue le ${dateFr(d.le, true)}.` : "Relance supprimée."); f.fermer(); rafraichirPage(); }
    catch (e) { erreursChamps(f, e.champs); }
  };
  $('[data-a="ok"]', f).onclick = () => { const d = valeurs(f); if (!d.le) return erreursChamps(f, { le: "Choisir une date." }); envoyer(d); };
  const s = $('[data-a="suppr"]', f); if (s) s.onclick = () => envoyer({ le: "", note: "" });
}

async function fenetreMailRelance(p) {
  let m;
  try { m = await api("GET", `/api/prospects/${p.id}/mail-relance`); } catch (e) { return signalerErreur(e); }
  const f = fenetre({
    titre: "Mail de relance", sous: `${echap(p.denomination)} · modèle de l'étape « ${echap(R().etapes.find(([e]) => e === p.etape)[1])} » (modifiable dans Paramètres)`,
    classe: "moyenne",
    corps: champ("a", "À", saisie("a", m.a, 'type="email"')) + champ("objet", "Objet", saisie("objet", m.objet)) + champ("texte", "Message", zoneTexte("texte", m.texte, 'style="min-height:220px"')),
    pied: `<button class="btn fantome gauche" data-a="copier">Copier le message</button>
      <button class="btn" data-a="faite">Relance faite</button><button class="btn principal" data-a="outlook">${icone("mail")} Ouvrir dans Outlook</button>`,
  });
  $('[data-a="copier"]', f).onclick = async () => { await navigator.clipboard?.writeText($('[name="texte"]', f).value); toast("Message copié."); };
  $('[data-a="faite"]', f).onclick = () => { f.fermer(); relanceFaite(p); };
  etatOutlook().then((e) => {
    if (!e.adresse || !f.isConnected) return;
    const b = el(`<button class="btn principal">${icone("mail")} Envoyer depuis le CRM</button>`);
    $('[data-a="outlook"]', f).classList.remove("principal"); $('[data-a="outlook"]', f).after(b);
    b.onclick = async () => {
      const d = valeurs(f);
      try {
        b.disabled = true; b.textContent = "Envoi…";
        const r = await api("POST", `/api/prospects/${p.id}/mails`, { a: [d.a], objet: d.objet, texte: d.texte });
        await charger(); f.fermer(); rafraichirPage();
        toast(r.prochaine ? `Relance envoyée. Prochaine relance le ${dateFr(r.prochaine, true)}.` : "Relance envoyée.");
      } catch (e2) { b.disabled = false; b.innerHTML = `${icone("mail")} Envoyer depuis le CRM`; signalerErreur(e2); }
    };
  }).catch(() => {});
  $('[data-a="outlook"]', f).onclick = () => {
    const d = valeurs(f);
    location.href = `mailto:${encodeURIComponent(d.a)}?subject=${encodeURIComponent(d.objet)}&body=${encodeURIComponent(d.texte)}`;
  };
}

const UNITES = { mois: "par mois", an: "par an", trimestre: "par trimestre", forfait: "forfait (ponctuel)" };
function fenetreHonoraires(p) {
  const h = p.honoraires || { lignes: [] };
  const lignes = h.lignes.length ? h.lignes : [{ libelle: "Mission comptable", montant: "", unite: "mois" }];
  const ligne = (l) => `<div class="honoraires-ligne">
      <input class="saisie" data-k="libelle" value="${echap(l.libelle)}" placeholder="Libellé">
      <input class="saisie" data-k="montant" value="${l.montant === "" ? "" : echap(l.montant)}" placeholder="Montant HT" inputmode="decimal">
      <select class="saisie" data-k="unite">${Object.entries(UNITES).map(([k, v]) => `<option value="${k}" ${k === l.unite ? "selected" : ""}>${v}</option>`).join("")}</select>
      <label class="case" title="Offert : non compté"><input type="checkbox" data-k="offert" ${l.offert ? "checked" : ""}>offert</label>
      <button class="btn fantome petit" data-a="suppr" title="Retirer">${icone("fermer")}</button></div>`;
  const f = fenetre({
    titre: "Gain potentiel", sous: `${echap(p.denomination)} · honoraires envisagés, toujours en HT (récurrents sur un an, ponctuels à part)`, classe: "moyenne",
    corps: `<div class="lignes-h" style="display:flex;flex-direction:column;gap:8px">${lignes.map(ligne).join("")}</div>
      <div><button class="btn petit" data-a="ajout">${icone("plus")} Ajouter une ligne</button></div>
      <div class="total-h" style="font-weight:700;color:var(--green)"></div>`,
    pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Enregistrer</button>`,
  });
  const taxe = "HT";  // le gain potentiel est toujours exprimé hors taxes
  const lire = () => $$(".honoraires-ligne", f).map((x) => ({ libelle: $('[data-k="libelle"]', x).value, montant: $('[data-k="montant"]', x).value,
    unite: $('[data-k="unite"]', x).value, offert: $('[data-k="offert"]', x).checked })).filter((l) => String(l.montant).trim() !== "");
  const total = () => {
    let r = 0, pt = 0;
    for (const l of lire()) {
      const m = parseFloat(String(l.montant).replace(",", ".").replace(/\s/g, "")) || 0;
      if (l.offert) continue;
      if (l.unite === "forfait") pt += m; else r += m * { mois: 12, trimestre: 4, an: 1 }[l.unite];
    }
    $(".total-h", f).textContent = `${euros(r)} ${taxe} par an` + (pt ? ` · ${euros(pt)} ${taxe} ponctuel` : "");
  };
  const brancher = () => { $$('[data-a="suppr"]', f).forEach((b) => b.onclick = () => { b.closest(".honoraires-ligne").remove(); total(); }); };
  f.addEventListener("input", total); brancher(); total();
  $('[data-a="ajout"]', f).onclick = () => { $(".lignes-h", f).append(el(ligne({ libelle: "", montant: "", unite: "forfait" }))); brancher(); };
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try { await executer(api("POST", `/api/prospects/${p.id}/honoraires`, { lignes: lire(), taxe }), "Gain potentiel enregistré."); f.fermer(); rafraichirPage(); }
    catch (e) { erreursChamps(f, e.champs); }
  };
}

// ---------- Fiche prospect ----------
function ouvrirFiche(pid, onglet = "infos") {
  const p = S.etat.prospects.find((x) => x.id === pid);
  if (!p) return;
  fermerFiche();
  const f = fenetre({ titre: echap(p.denomination), sous: "", classe: "large", corps: "" });
  f.dataset.fiche = pid;
  f.onglet = onglet;
  remplirFiche(f, true);
}
function fermerFiche() { $(".fenetre[data-fiche]")?.fermer(); }

function remplirFiche(f, premiere = false) {
  const p = S.etat.prospects.find((x) => x.id === f.dataset.fiche);
  if (!p) return f.fermer();
  const etape = R().etapes.find(([e]) => e === p.etape)?.[1] || "";
  $(".f-tete h2", f).textContent = p.denomination;
  const sous = $(".f-tete .sous", f) || $(".f-tete > div", f).appendChild(el('<div class="sous"></div>'));
  sous.innerHTML = `${echap(p.code)} · ${echap(R().natures[p.nature] || "")}${p.siren ? " · SIREN " + echap(p.siren) : ""} · <span class="pastille p-gris">${echap(p.statut === "PROSPECT" ? etape : p.statut === "CLIENT" ? "Client" : "Perdu")}</span>`;
  const corps = $(".f-corps", f);
  if (premiere) {
    corps.innerHTML = `<div class="segments" id="onglets" style="align-self:flex-start"><span class="pilule"></span>
        <button data-v="infos">Informations</button><button data-v="echanges">Échanges<span class="compte-message"></span></button><button data-v="contacts">Contacts</button>
        ${estReprise(p) ? `<button data-v="ancien" class="onglet-ancien">Ancien cabinet<span class="point-jaune ${repriseReglee(p) ? "cache" : ""}"></span></button>` : ""}
        <button data-v="historique">Historique</button></div><div class="onglets-contenu"></div>`;
    segments($("#onglets", corps), f.onglet, async (v) => {
      f.onglet = v;
      const zone = $(".onglets-contenu", corps);
      if (MOUVEMENT) { await zone.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 110, fill: "forwards" }).finished; }
      ongletFiche(f, p.id);
      if (MOUVEMENT) zone.animate([{ opacity: 0, transform: "translateY(6px)" }, { opacity: 1, transform: "none" }], { duration: 220, easing: "cubic-bezier(.33,1,.68,1)", fill: "forwards" });
    });
  }
  ongletFiche(f, p.id);
  majCompteFiche(f);
  $(".onglet-ancien .point-jaune", f)?.classList.toggle("cache", repriseReglee(p));
}
/** Échanges avec l'ancien cabinet, dans une fenêtre par-dessus la fiche (qui reste ouverte) : à part des échanges avec le client.
 *  o : mail à écrire tout de suite (lettre confraternelle jointe). */
function echangesAncien(p, o = null) {
  const a = p.ancien || {};
  const f = fenetre({ titre: "Échanges avec l'ancien cabinet", sous: echap([p.cabinet_precedent || a.cabinet, a.email].filter(Boolean).join(" · ")),
    classe: "large", corps: '<div class="mx-pop"></div>' });
  f.classList.add("fenetre-echanges");
  if (o) S.aEcrire = { pid: `${p.id}:ancien`, o };
  ongletEchanges($(".mx-pop", f), { ...p, _avec: "ancien", contacts: [{ email: a.email, civilite: a.civilite, prenom: a.prenom, nom: a.nom || a.cabinet }] });
}
const estReprise = (p) => p.nature === "PROFESSIONNEL" && p.origine === "REPRISE";
const repriseReglee = (p) => !!(String(p.cabinet_precedent || "").trim() && p.confraternelle?.envoyee_le);
/** Pourquoi la lettre de mission ne peut pas encore partir (même règle que le serveur), ou "". */
function blocageReprise(p) {
  if (!p || !estReprise(p)) return "";
  if (!String(p.cabinet_precedent || "").trim()) return "Reprise d'un confrère : renseignez d'abord l'ancien cabinet.";
  if (!p.confraternelle?.envoyee_le) return "Reprise d'un confrère : envoyez d'abord la lettre confraternelle à l'ancien cabinet.";
  return "";
}

/** Onglet « Ancien cabinet » : coordonnées du confrère, puis lettre confraternelle (créée, envoyée), avant la lettre de mission. */
async function ongletAncien(zone, p, f) {
  const a = { cabinet: p.cabinet_precedent || "", ...(p.ancien || {}) };
  a.cabinet = a.cabinet || p.cabinet_precedent || "";
  let lettre = null;
  if (p.confraternelle?.lettre) { try { lettre = (await api("GET", `/api/lettres/${p.confraternelle.lettre}`)).lettre; } catch { /* supprimée */ } }
  const envoyee = p.confraternelle?.envoyee_le;
  const etat = [!!a.cabinet, !!lettre, !!envoyee];
  zone.innerHTML = `<div class="bandeau-jaune">${icone("bouclier")}<div><b>Reprise du dossier d'un confrère</b>
      Envoyez d'abord la <b>lettre confraternelle</b> à l'ancien expert-comptable (obligation déontologique) : la lettre de mission ne part qu'ensuite.</div></div>
    <ol class="etapes-reprise">${["Coordonnées de l'ancien cabinet", "Lettre confraternelle créée", envoyee ? `Envoyée le ${dateFr(envoyee, true)}` : "Lettre confraternelle envoyée",
      "Lettre de mission : envoi possible"].map((l, i) => `<li class="${(i < 3 ? etat[i] : etat.every(Boolean)) ? "fait" : ""}">${echap(l)}</li>`).join("")}</ol>
    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px">
      <div class="groupe"><div class="groupe-titre">ANCIEN EXPERT-COMPTABLE</div>
        ${champ("cabinet", "Cabinet", saisie("cabinet", a.cabinet, 'placeholder="ex. CABINET MARTIN & ASSOCIÉS"'))}
        <div class="grille2">${champ("civilite", "Civilité", liste("civilite", R().civilites, a.civilite || ""), true)}<div></div>
          ${champ("prenom", "Prénom de l'expert-comptable", saisie("prenom", a.prenom || ""), true)}${champ("nom", "Nom de l'expert-comptable", saisie("nom", a.nom || ""), true)}</div>
        ${champ("adresse", "Adresse", saisie("adresse", a.adresse || ""), true)}
        <div class="grille2">${champ("cp", "Code postal", saisie("cp", a.cp || "", 'maxlength="10"'), true)}${champ("ville", "Ville", saisie("ville", a.ville || ""), true)}
          ${champ("email", "E-mail", saisie("email", a.email || "", 'type="email"'), true)}${champ("telephone", "Téléphone", saisie("telephone", a.telephone || ""), true)}</div>
        <div><button class="btn principal petit" data-a="enreg">Enregistrer</button></div></div>
      <div class="groupe"><div class="groupe-titre">LETTRE CONFRATERNELLE</div>
        ${lettre ? `<div>Créée le ${instantFr(lettre.le)} par ${echap(lettre.par)}${envoyee ? ` · <b style="color:var(--green)">envoyée le ${dateFr(envoyee, true)}</b>` : ""}.</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn principal petit" data-a="envoyer" ${a.email ? "" : 'disabled title="Indiquez l\'e-mail de l\'ancien cabinet"'}>${icone("mail")} Envoyer par mail</button>
            <a class="btn petit" href="/api/lettres/${lettre.id}/fichier">Word</a>
            ${lettre.pdf ? `<a class="btn petit" href="/api/lettres/${lettre.id}/pdf?voir=1" target="_blank" rel="noopener">PDF</a>`
              : `<button class="btn petit" data-a="pdf" title="${echap(lettre.pdf_erreur || "Ranger dans SharePoint et convertir en PDF")}">${lettre.pdf_erreur ? "⚠ " : ""}PDF</button>`}
            <button class="btn petit fantome" data-a="creer">Refaire la lettre</button>
            ${a.email ? `<button class="btn petit" data-a="echanges">${icone("mail")} Échanges</button>` : ""}</div>
          ${envoyee ? "" : '<div><button class="btn lien" data-a="courrier">Envoyée par courrier (recommandé) : la noter comme envoyée</button></div>'}`
        : `<div style="color:var(--muted)">Le CRM la rédige sur le papier du cabinet, avec les coordonnées ci-contre. Vous pourrez l'ajuster dans Word.</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="btn principal petit" data-a="creer" ${a.cabinet ? "" : 'disabled title="Indiquez d\'abord le cabinet"'}>Créer la lettre confraternelle</button>
            ${a.email ? `<button class="btn petit" data-a="echanges">${icone("mail")} Échanges</button>` : ""}</div>`}
        <div class="msg-erreur" data-err="cabinet"></div></div></div>`;
  $$('[data-a="echanges"]', zone).forEach((b) => b.onclick = () => echangesAncien(p));
  $('[data-a="pdf"]', zone)?.addEventListener("click", async (ev) => {  // PDF pas encore fait : créé (SharePoint + Microsoft), puis ouvert
    const b = ev.currentTarget, onglet = window.open("", "_blank");
    b.disabled = true; b.textContent = "PDF en cours…";
    let r = null;
    try { r = await fairePdf(lettre.id); } catch (err) { signalerErreur(err); }
    if (r?.lettre.pdf) { if (onglet) onglet.location = `/api/lettres/${lettre.id}/pdf?voir=1`; toast(`PDF créé et rangé dans SharePoint › ${r.lettre.classement.dossier}.`); }
    else { onglet?.close(); if (r) toast(r.lettre.pdf_erreur, { erreur: true, duree: 9000 }); }
    recharger();
  });
  const recharger = async () => { await charger(); const q = S.etat.prospects.find((x) => x.id === p.id); if (q) { remplirFiche(f); } };
  $('[data-a="enreg"]', zone).onclick = async () => {
    try { await executer(api("POST", `/api/prospects/${p.id}/ancien-cabinet`, valeurs($(".groupe", zone))), "Ancien cabinet enregistré."); recharger(); }
    catch (e) { erreursChamps(zone, e.champs); }
  };
  $$('[data-a="creer"]', zone).forEach((b) => b.onclick = async () => {
    b.disabled = true; b.textContent = "Rédaction…";
    try { await api("POST", `/api/prospects/${p.id}/confraternelle`); toast("Lettre confraternelle créée (aussi dans Livrables)."); recharger(); }
    catch (e) { $('[data-err="cabinet"]', zone).textContent = e.champs ? Object.values(e.champs)[0] : e.message; b.disabled = false; b.textContent = "Créer la lettre confraternelle"; }
  });
  $('[data-a="courrier"]', zone)?.addEventListener("click", async () => {
    if (!confirm("Noter la lettre confraternelle comme envoyée aujourd'hui, par courrier ?")) return;
    try { await executer(api("POST", `/api/prospects/${p.id}/confraternelle-envoyee`, { comment: "par courrier" }), "Lettre confraternelle notée comme envoyée."); recharger(); }
    catch (e) { signalerErreur(e); }
  });
  $('[data-a="envoyer"]', zone)?.addEventListener("click", () => envoyerLettre({ ...lettre, modele: "CONFRATERNELLE", dossier: { source: "prospect", ref: p.id, nom: p.denomination } }));
}

function majCompteFiche(f) {
  const n = S.nonLus[f.dataset.fiche]?.non_lus || 0;
  const b = $(".compte-message", f);
  if (b) { b.textContent = n || ""; b.classList.toggle("cache", !n); }
}

function ongletFiche(f, pid) {
  const p = S.etat.prospects.find((x) => x.id === pid);
  const zone = $(".onglets-contenu", f);
  const r = R();
  if (f.onglet === "infos") {
    const missions = r.missions[p.nature === "PARTICULIER" ? "PARTICULIER" : p.type_pro === "EI" ? "EI" : "SOCIETE"];
    const toutes = [...new Set([...missions, ...(p.missions || [])])];
    zone.innerHTML = `<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px">
      <div class="groupe"><div class="groupe-titre">DOSSIER</div>
        <div class="grille2">
          ${champ("denomination", p.nature === "PARTICULIER" ? "Nom du foyer" : "Dénomination", saisie("denomination", p.denomination))}
          ${p.nature === "PROFESSIONNEL" ? champ("siren", "SIREN", saisie("siren", p.siren, 'maxlength="11"'), true) : "<div></div>"}
          ${champ("source", "Source", liste("source", { "": "–", ...r.sources }, p.source || ""), true)}
          ${p.nature === "PROFESSIONNEL" ? champ("origine", "Origine", liste("origine", { "": "Choisir…", ...originesDe(p.type_pro) }, p.origine || "")) : ""}
        </div>
        ${champsSuivi(p, p.nature === "PROFESSIONNEL")}
        <div class="champ"><label>Missions envisagées</label>${casesMissions(toutes, p.missions || [])}</div>
        <div><button class="btn principal petit" data-a="enreg">Enregistrer la fiche</button></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="groupe"><div class="groupe-titre">RELANCE</div>
          <div>${p.prochaine_relance ? `Prochaine relance le <b>${dateFr(p.prochaine_relance, true)}</b>${p.note_relance ? ` · ${echap(p.note_relance)}` : ""}` : "Aucune relance prévue."}</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn petit" data-a="mail">${icone("mail")} Préparer la relance</button>
            ${p.prochaine_relance ? '<button class="btn petit" data-a="faite">Relance faite</button>' : ""}
            <button class="btn petit fantome" data-a="prevoir">${p.prochaine_relance ? "Reporter…" : "Prévoir…"}</button></div></div>
        ${p.nature === "PROFESSIONNEL" && p.siren ? (() => { const g = p.registre || {}, l = (t, v) => v ? `<dt>${t}</dt><dd>${echap(v)}</dd>` : "";
          return `<div class="groupe"><div class="groupe-titre">REGISTRE DES ENTREPRISES</div>
          ${Object.keys(g).length ? `<dl class="lignes">${l("Forme", p.forme)}${l("Siège", [p.adresse, p.cp, p.ville].filter(Boolean).join(" "))}${l("SIRET du siège", g.siret)}
            ${l("TVA intracom.", g.tva)}${l("Activité", [g.naf, g.activite].filter(Boolean).join(" · "))}${l("Nom commercial", g.nom_commercial)}
            ${l("Créée le", g.creee_le && dateFr(g.creee_le, true))}${l("Début d'activité", g.debut_activite && dateFr(g.debut_activite, true))}${l("Effectif", g.effectif)}
            ${l("Établissements", g.etablissements && g.etablissements !== "1" ? g.etablissements : "")}${l("Convention", g.convention)}
            ${l(`Chiffre d'affaires ${g.annee_finances || ""}`, g.ca && euros(+g.ca))}${l(`Résultat net ${g.annee_finances || ""}`, g.resultat && euros(+g.resultat))}</dl>
            <div style="color:var(--muted);font-size:12px">Lu le ${dateFr(g.maj, true)} · INSEE et INPI</div>` : '<div style="color:var(--muted)">Pas encore lu.</div>'}
          <div><button class="btn petit" data-a="registre">Actualiser depuis le registre</button></div></div>`; })() : ""}
        <div class="groupe"><div class="groupe-titre">GAIN POTENTIEL</div>
          <div style="font-weight:700;color:var(--green)">${p.recurrent || p.ponctuel ? `${euros(p.recurrent)} HT/an${p.ponctuel ? ` · ${euros(p.ponctuel)} HT ponctuel` : ""}` : '<span style="color:var(--muted);font-weight:500">Non saisi</span>'}</div>
          <div><button class="btn petit" data-a="gain">Saisir le gain potentiel</button></div></div>
        ${p.statut === "PROSPECT" ? `<div class="groupe"><div class="groupe-titre">ISSUE</div><div style="display:flex;gap:6px">
          <button class="btn principal petit" data-a="signee">Lettre signée</button><button class="btn petit danger" data-a="perdu">Perdu</button>
          <span class="espace"></span><button class="btn petit fantome danger" data-a="supprimer">Supprimer</button></div></div>` : ""}
      </div></div>`;
    brancherReprise(zone);
    $('[data-a="enreg"]', zone).onclick = async () => {
      const d = valeurs(zone);
      d.missions = $$("[data-mission]", zone).filter((x) => x.checked).map((x) => x.dataset.mission);
      const avant = p.origine;
      try {
        await executer(api("POST", `/api/prospects/${p.id}/modifier`, d), "Fiche enregistrée."); rafraichirPage();
        if ((avant === "REPRISE") !== (d.origine === "REPRISE")) ouvrirFiche(p.id, "infos");  // onglet « Ancien cabinet » ajouté ou retiré
      } catch (e) { erreursChamps(zone, e.champs); }
    };
    $('[data-a="mail"]', zone).onclick = () => fenetreMailRelance(p);
    $('[data-a="prevoir"]', zone).onclick = () => fenetreRelance(p);
    $('[data-a="gain"]', zone).onclick = () => fenetreHonoraires(p);
    $('[data-a="registre"]', zone)?.addEventListener("click", async (ev) => {
      ev.currentTarget.disabled = true; ev.currentTarget.textContent = "Lecture du registre…";
      try { await executer(api("POST", `/api/prospects/${p.id}/registre`, {}), `${p.denomination} : informations du registre à jour.`); rafraichirPage(); }
      catch (e) { signalerErreur(e); ongletFiche(f, pid); }
    });
    $('[data-a="faite"]', zone)?.addEventListener("click", () => relanceFaite(p));
    $('[data-a="signee"]', zone)?.addEventListener("click", () => fenetreSignee(p));
    $('[data-a="perdu"]', zone)?.addEventListener("click", () => fenetrePerdu(p));
    $('[data-a="supprimer"]', zone)?.addEventListener("click", () => fenetreSupprimer(p));
  } else if (f.onglet === "contacts") {
    zone.innerHTML = `<div class="groupe"><div class="groupe-titre">CONTACTS</div>
      <div>${(p.contacts || []).map((c) => `<div class="contact-ligne">${avatar([c.prenom, c.nom].filter(Boolean).join(" "))}
          <div class="qui"><b>${echap([r.civilites[c.civilite] !== "–" ? r.civilites[c.civilite] : "", c.prenom, (c.nom || "").toUpperCase()].filter(Boolean).join(" "))}</b>
          <small>${echap([r.roles[c.role], c.email, c.telephone].filter(Boolean).join(" · "))}</small></div>
          ${c.email ? `<a class="btn petit" href="mailto:${echap(c.email)}">${icone("mail")} Écrire</a>` : ""}
          <button class="btn petit fantome" data-c="${c.id}">Modifier</button></div>`).join("") || '<div style="color:var(--muted)">Aucun contact.</div>'}</div>
      <div><button class="btn petit" data-a="ajout">${icone("plus")} Ajouter un contact</button></div></div>`;
    $('[data-a="ajout"]', zone).onclick = () => fenetreContact(p, {});
    $$("[data-c]", zone).forEach((b) => b.onclick = () => fenetreContact(p, p.contacts.find((c) => c.id === b.dataset.c)));
  } else if (f.onglet === "ancien") {
    ongletAncien(zone, p, $(".fenetre[data-fiche]"));
  } else if (f.onglet === "historique") {
    zone.innerHTML = `<div class="groupe"><div class="groupe-titre">HISTORIQUE</div><div class="historique">
      ${[...(p.historique || [])].reverse().map((h) => `<div><time>${instantFr(h.le)}</time><span>${echap(h.texte)}</span></div>`).join("")}</div></div>`;
  } else {
    ongletEchanges(zone, p);
  }
}

function fenetreContact(p, c) {
  const r = R();
  const f = fenetre({
    titre: c.id ? "Modifier le contact" : "Ajouter un contact", sous: echap(p.denomination),
    corps: `<div class="grille2">${champ("civilite", "Civilité", liste("civilite", r.civilites, c.civilite || ""), true)}
      ${champ("role", "Rôle", liste("role", r.roles, c.role || (p.nature === "PARTICULIER" ? "DECLARANT" : "REPRESENTANT_LEGAL")))}
      ${champ("prenom", "Prénom", saisie("prenom", c.prenom), true)}${champ("nom", "Nom", saisie("nom", c.nom))}
      ${champ("email", "E-mail", saisie("email", c.email, 'type="email"'), true)}${champ("telephone", "Téléphone", saisie("telephone", c.telephone), true)}</div>`,
    pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Enregistrer</button>`,
  });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try { await executer(api("POST", `/api/prospects/${p.id}/contacts`, { ...valeurs(f), id: c.id }), "Contact enregistré."); f.fermer(); rafraichirPage(); }
    catch (e) { erreursChamps(f, e.champs); }
  };
}

// ---------- Nouveau prospect (assistant) ----------
function nouveauProspect() {
  const ETAPES = ["Nature", "Identité", "Suivi", "Contact", "Relance"];
  const d = { nature: "", type_pro: "", en_creation: false, missions: [], contacts: [{}], repertoire: null };
  let etape = 0;
  const f = fenetre({ titre: "Nouveau prospect", classe: "moyenne", corps: "",
    pied: `<button class="btn fantome gauche" data-a="prec">Précédent</button><button class="btn principal" data-a="suiv">Suivant</button>` });
  const corps = $(".f-corps", f), prec = $('[data-a="prec"]', f), suiv = $('[data-a="suiv"]', f);
  const pro = () => d.nature === "PROFESSIONNEL";
  const typeActuel = () => (pro() ? d.type_pro || "SOCIETE" : "PARTICULIER");

  const recueillir = () => {
    const v = valeurs(corps);
    Object.assign(d, Object.fromEntries(Object.entries(v).filter(([k]) => !k.startsWith("contact."))));
    for (const [k, x] of Object.entries(v)) {
      const m = k.match(/^contact\.(\d+)\.(\w+)$/);
      if (m && d.contacts[+m[1]]) d.contacts[+m[1]][m[2]] = x;
    }
  };
  /** Fiche du registre (INSEE + INPI) : forme, siège et dirigeants, repris dans le prospect et proposés comme contacts. */
  const chargerRepertoire = async (siren, z) => {
    const s = String(siren || "").replace(/\s/g, "");
    if (!/^\d{9}$/.test(s) || d.repertoire?.siren === s) return;
    const info = $(".repertoire-info", z);
    if (info) info.innerHTML = '<span style="color:var(--muted)">Lecture du registre des entreprises…</span>';
    let e;
    try { e = (await api("GET", "/api/entreprises/fiche?siren=" + s)).entreprise; } catch { if (info) info.innerHTML = ""; return; }
    d.repertoire = e;
    d.coordonnees = null; d.recherche_coord = false;  // nouvelle société : les adresses trouvées dans Outlook sont à rechercher de nouveau
    Object.assign(d, { adresse: e.adresse, cp: e.cp, ville: e.ville, registre: e.registre });
    const forme = $('[name="forme"]', z);
    if (forme && !forme.value) forme.value = e.forme || "";
    const vide = (c) => !c.nom && !c.prenom && !c.email && !c.lie;
    if (e.dirigeants.length && d.contacts.every(vide)) d.contacts = [{ ...contactDuDirigeant(e.dirigeants[0]), auto: true }];
    if (info) info.innerHTML = e.ferme ? '<div class="alerte"><b style="color:var(--red)">Entreprise fermée au registre.</b></div>' : "";
  };
  /** Autre société tapée : le SIREN, la forme, le siège et les dirigeants de la précédente ne la suivent pas. */
  function oublierSociete(nom) {
    const e = d.repertoire;
    if (!e || plat(nom) === plat(e.nom)) return;
    const z = corps;
    const siren = $('[name="siren"]', z), forme = $('[name="forme"]', z);
    if (siren && siren.value.replace(/\s/g, "") === e.siren) siren.value = "";
    if (forme && forme.value === (e.forme || "")) forme.value = "";
    Object.assign(d, { repertoire: null, registre: null, adresse: "", cp: "", ville: "", siren: siren ? siren.value : "", forme: forme ? forme.value : "",
      coordonnees: null, recherche_coord: false });
    d.contacts = d.contacts.filter((c) => !c.auto);
    if (!d.contacts.length) d.contacts = [{}];
    const info = $(".repertoire-info", z);
    if (info) info.innerHTML = "";
  }
  const contactDuDirigeant = (x) => {
    const c = { civilite: "", prenom: x.prenom, nom: x.nom, fonction: x.fonction, role: x.role, email: "", telephone: "" };
    remplirDepuisMails(c);
    return c;
  };
  /** Adresses et téléphones de la société retrouvés dans la boîte Outlook (aucun registre public ne les donne). */
  const plat = (t) => String(t || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]/g, "");
  const personnesCherchees = () => [...(d.repertoire?.dirigeants || []), ...d.contacts.filter((c) => c.nom && !c.lie)]
    .filter((p, i, t) => t.findIndex((q) => plat(q.nom) === plat(p.nom) && plat(q.prenom) === plat(p.prenom)) === i).slice(0, 4)
    .map((p) => ({ prenom: p.prenom || "", nom: p.nom }));
  const coordonneeDe = (c) => {
    const ps = d.coordonnees?.personnes || [];
    return (d.coordonnees?.liste || []).find((x) => x.personne != null && plat(ps[x.personne]?.nom) === plat(c.nom) && (!c.prenom || !ps[x.personne]?.prenom
      || plat(ps[x.personne].prenom) === plat(c.prenom)));
  };
  function remplirDepuisMails(c) {
    const x = c.nom ? coordonneeDe(c) : null;
    if (!x) return false;
    let fait = false;
    if (!c.email) { c.email = x.email; fait = true; }
    if (!c.telephone && x.telephones[0]) { c.telephone = x.telephones[0]; fait = true; }
    if (fait) c.depuis_mails = x;
    return fait;
  }
  async function chercherCoordonnees(z) {
    if (d.coordonnees || d.recherche_coord || !String(d.denomination || "").trim()) return;
    d.recherche_coord = true;
    const zone = $(".coordonnees-mails", z);
    zone.innerHTML = '<span style="color:var(--muted)">Recherche des e-mails et téléphones dans votre boîte Outlook…</span>';
    const personnes = personnesCherchees();
    let r;
    try { r = await api("POST", "/api/entreprises/coordonnees", { societe: d.denomination, personnes }); } catch { zone.innerHTML = ""; d.recherche_coord = false; return; }
    d.coordonnees = { liste: r.coordonnees, personnes, boite: r.boite, erreur: r.erreur };
    recueillir();
    let rempli = false;
    d.contacts.forEach((c) => { if (!c.lie && remplirDepuisMails(c)) rempli = true; });
    if (etape !== 3) return;
    corps.lastElementChild.remove(); pageContact();
    if (rempli) toast("E-mail et téléphone repris de vos échanges Outlook : vérifiez-les.");
  }

  const titres = () => ({
    0: ["Quelle nature de dossier ?", "Le dossier est créé au statut Prospect, à l'étape « À qualifier »."],
    1: [d.en_creation ? "Quel projet ?" : pro() ? (d.type_pro === "EI" ? "Quelle entreprise individuelle ?" : "Quelle société ?") : "Quel foyer ?",
      pro() && !d.en_creation ? "Tapez au moins 3 lettres : le répertoire officiel des entreprises propose les correspondances." : ""],
    2: ["Suivi du dossier", "Source, origine et missions envisagées (les lettres de mission du cabinet)."],
    3: ["Contact", "La personne à qui écrire. Facultatif : vous pourrez l'ajouter plus tard."],
    4: ["Première relance", "Facultatif : sans date, le prospect est signalé « Sans relance prévue » après 15 jours sans activité."],
  })[etape];

  async function afficher(sens = 0) {
    if (sens && MOUVEMENT) await corps.animate([{ opacity: 1 }, { opacity: 0, transform: `translateX(${-sens * 8}px)` }], { duration: 120, fill: "forwards" }).finished;
    const [t, s] = titres();
    corps.innerHTML = `<div class="etapes">${ETAPES.map((e, i) => `<span class="${i === etape ? "courante" : i < etape ? "faite" : ""}">${i + 1} ${e}</span>`).join("<span>›</span>")}</div>
      <div><h3 style="margin:4px 0 2px;font-size:18px">${t}</h3><div style="color:var(--muted)">${s}</div></div>`;
    [pageNature, pageIdentite, pageSuivi, pageContact, pageRelance][etape]();
    prec.style.visibility = etape ? "visible" : "hidden";
    suiv.textContent = etape === 4 ? "Créer le prospect" : "Suivant";
    suiv.classList.toggle("cache", etape === 0 && !(pro() && d.type_pro));
    if (sens && MOUVEMENT) corps.animate([{ opacity: 0, transform: `translateX(${sens * 10}px)` }, { opacity: 1, transform: "none" }], { duration: 240, easing: "cubic-bezier(.33,1,.68,1)", fill: "forwards" });
    setTimeout(() => $("input.saisie", corps)?.focus(), 50);
  }

  function pageNature() {
    const z = el(`<div><div class="cartes-choix">
        <button class="choix ${d.nature === "PROFESSIONNEL" ? "choisi" : ""}" data-n="PROFESSIONNEL"><b>Professionnel</b><span>Société ou entreprise individuelle (micro, libéral, LMNP…).</span></button>
        <button class="choix ${d.nature === "PARTICULIER" ? "choisi" : ""}" data-n="PARTICULIER"><b>Particulier</b><span>Foyer : déclaration de revenus, revenus fonciers, IFI.</span></button></div>
      <div class="bloc-pro ${pro() ? "" : "cache"}"><div class="arbre"></div>
        <div class="cartes-choix">
          <button class="choix ${d.type_pro === "SOCIETE" ? "choisi" : ""}" data-t="SOCIETE"><b>Société</b><span>SAS, SASU, SARL, EURL, SCI…</span></button>
          <button class="choix ${d.type_pro === "EI" ? "choisi" : ""}" data-t="EI"><b>Entreprise individuelle</b><span>Profession libérale (BNC), BIC (déclaration 2031), location meublée (LMNP), micro-entreprise.</span></button></div>
        <label class="case" style="margin-top:10px"><input type="checkbox" name="en_creation" ${d.en_creation ? "checked" : ""}>En cours de création (pas encore immatriculée : ni SIREN ni recherche)</label>
      </div></div>`);
    corps.append(z);
    $$("[data-n]", z).forEach((b) => b.onclick = () => {
      const avant = d.nature;
      d.nature = b.dataset.n;
      $$("[data-n]", z).forEach((x) => x.classList.toggle("choisi", x === b));
      if (d.nature === "PARTICULIER") { d.type_pro = ""; d.en_creation = false; etape = 1; afficher(1); return; }
      const bloc = $(".bloc-pro", z);
      if (avant !== "PROFESSIONNEL") {
        bloc.classList.remove("cache"); dessinerArbre(z, true);
        $$("[data-t]", z).forEach((x, i) => { x.classList.remove("arrive"); void x.offsetWidth; x.style.animationDelay = `${300 + i * 90}ms`; x.classList.add("arrive"); });
      }
    });
    $$("[data-t]", z).forEach((b) => b.onclick = () => {
      d.type_pro = b.dataset.t; $$("[data-t]", z).forEach((x) => x.classList.toggle("choisi", x === b));
      dessinerArbre(z, false); suiv.classList.remove("cache");
    });
    $('[name="en_creation"]', z).onchange = (e) => { d.en_creation = e.target.checked; };
    if (pro()) requestAnimationFrame(() => dessinerArbre(z, false));
  }

  /** Embranchement Professionnel -> Société / EI : le trait part de la carte, se divise, descend en flèches. */
  function dessinerArbre(z, anime) {
    const arbre = $(".arbre", z), source = $('[data-n="PROFESSIONNEL"]', z), cibles = $$("[data-t]", z);
    const base = arbre.getBoundingClientRect();
    const xs = source.getBoundingClientRect().left + source.offsetWidth / 2 - base.left;
    const h = 64, mi = 27, r = 10;
    const branches = cibles.map((c) => {
      const x = c.getBoundingClientRect().left + c.offsetWidth / 2 - base.left, s = x > xs ? 1 : -1;
      const chemin = Math.abs(x - xs) < 2 * r ? `M${xs},0 L${x},${h - 3}`
        : `M${xs},0 L${xs},${mi - r} Q${xs},${mi} ${xs + s * r},${mi} L${x - s * r},${mi} Q${x},${mi} ${x},${mi + r} L${x},${h - 3}`;
      const etat = !d.type_pro ? "" : c.dataset.t === d.type_pro ? "actif" : "inactif";
      return { x, chemin, etat };
    });
    const loin = branches.reduce((a, b) => (Math.abs(b.x - xs) > Math.abs(a.x - xs) ? b : a)).x;
    const q = "Sous quelle forme ?", lq = q.length * 6.6 + 22;
    arbre.innerHTML = `<svg class="embranchement" viewBox="0 0 ${base.width} ${h}" preserveAspectRatio="none">
      ${branches.map((b) => `<path class="${anime ? "trace" : ""} ${b.etat}" d="${b.chemin}"/>
        <path class="${anime ? "fleche" : ""} ${b.etat === "actif" ? "actif" : ""}" d="M${b.x - 4.5},${h - 8.5} L${b.x},${h - 3} L${b.x + 4.5},${h - 8.5}"/>`).join("")}
      <g class="question" style="${anime ? "" : "opacity:1;animation:none"}"><rect x="${(xs + loin) / 2 - lq / 2}" y="${mi - 12}" width="${lq}" height="24" rx="12"/>
        <text x="${(xs + loin) / 2}" y="${mi + 4}" text-anchor="middle">${q}</text></g></svg>`;
    if (anime) $$("path.trace", arbre).forEach((pth) => pth.style.setProperty("--l", pth.getTotalLength()));
  }

  function pageIdentite() {
    const z = el(`<div style="display:flex;flex-direction:column;gap:12px">
      ${champ("denomination", pro() ? (d.en_creation ? "Nom du projet" : "Dénomination") : "Nom du foyer", saisie("denomination", d.denomination || "", 'autocomplete="off"'))}
      <div class="suggestions cache"></div>
      ${pro() && !d.en_creation ? `<div class="grille2">${champ("siren", "SIREN", saisie("siren", d.siren || "", 'maxlength="11"'), true)}${champ("forme", "Forme juridique", saisie("forme", d.forme || ""), true)}</div>
        <div class="repertoire-info"></div>` : ""}
      ${pro() && d.en_creation ? `<div class="grille2">${d.type_pro === "SOCIETE" ? champ("forme_envisagee", "Forme envisagée", liste("forme_envisagee", R().formes, d.forme_envisagee || ""), true) : "<div></div>"}
        ${champ("creation_prevue", "Création prévue", saisie("creation_prevue", d.creation_prevue || "", 'placeholder="ex. janvier 2027" maxlength="40"'), true)}</div>` : ""}
    </div>`);
    corps.append(z);
    if (!pro() || d.en_creation) return;
    const champDen = $('[name="denomination"]', z), sugg = $(".suggestions", z);
    let minuterie, derniere = "";
    champDen.oninput = () => {
      clearTimeout(minuterie);
      const q = champDen.value.trim();
      oublierSociete(q);
      if (q.length < 3 || q === derniere) { sugg.classList.add("cache"); return; }
      minuterie = setTimeout(async () => {
        const { resultats } = await api("GET", "/api/entreprises?q=" + encodeURIComponent(q)).catch(() => ({ resultats: [] }));
        if (champDen.value.trim() !== q) return;
        sugg.innerHTML = resultats.map((e, i) => `<button data-i="${i}"><b>${echap(e.nom)}</b> <small>${echap([e.siren, e.forme, e.ville, e.ferme ? "fermée" : ""].filter(Boolean).join(" · "))}</small></button>`).join("");
        sugg.classList.toggle("cache", !resultats.length);
        $$("button", sugg).forEach((b) => b.onclick = () => {
          const e = resultats[b.dataset.i];
          derniere = e.nom; champDen.value = e.nom; $('[name="siren"]', z).value = e.siren; $('[name="forme"]', z).value = e.forme || "";
          sugg.classList.add("cache");
          chargerRepertoire(e.siren, z);
        });
      }, 350);
    };
    $('[name="siren"]', z).addEventListener("change", (ev) => chargerRepertoire(ev.target.value, z));
    if (d.repertoire && d.repertoire.siren === String(d.siren || "").replace(/\s/g, "")) { const r = d.repertoire; d.repertoire = null; chargerRepertoire(r.siren, z); }
  }

  function pageSuivi() {
    const r = R(), missions = r.missions[typeActuel()];
    const z = el(`<div style="display:flex;flex-direction:column;gap:12px"><div class="grille2">
      ${champ("source", "Source", liste("source", { "": "–", ...r.sources }, d.source || ""), true)}
      ${pro() ? champ("origine", "Origine", liste("origine", { "": "Choisir…", ...originesDe(d.type_pro) }, d.origine || (d.en_creation ? "CREATION" : ""))) : ""}
      ${!pro() ? champ("nb_biens", "Nombre de biens locatifs", saisie("nb_biens", d.nb_biens || "", 'inputmode="numeric"'), true) + champ("nb_personnes", "Personnes du foyer", saisie("nb_personnes", d.nb_personnes || "", 'inputmode="numeric"'), true) : ""}
      </div>
      ${champsSuivi(d, pro())}
      <div class="champ"><label>Missions envisagées <span class="facultatif">(facultatif)</span></label>
        ${casesMissions(missions, d.missions)}
        ${!pro() ? `<label class="case"><input type="checkbox" name="partenaire" ${d.partenaire ? "checked" : ""}>Partenaire services à la personne (lettre avec crédit d'impôt)</label>` : ""}</div></div>`);
    corps.append(z);
    brancherReprise(z);
    const lire = () => { d.missions = $$("[data-mission]", z).filter((y) => y.checked).map((y) => y.dataset.mission); };
    $$("[data-mission]", z).forEach((x) => x.addEventListener("change", lire));
  }

  function pageContact() {
    const r = R();
    if (!d.contacts.length) d.contacts = [{}];
    const dirigeants = (d.repertoire?.dirigeants || []).filter((x) => !d.contacts.some((c) => c.nom === x.nom && c.prenom === x.prenom));
    const z = el(`<div style="display:flex;flex-direction:column;gap:12px">
      <label class="case"><input type="checkbox" name="avec_contact" ${d.avec_contact !== false ? "checked" : ""}>Ajouter un contact maintenant</label>
      <div class="bloc-contact">
        ${d.contacts.map((c, i) => `<div class="contact-saisi" data-i="${i}">
          <div class="contact-tete"><b>${d.contacts.length > 1 ? `Contact ${i + 1}` : "Contact"}</b>
            ${c.auto ? '<span class="pastille p-bleu">repris du registre</span>' : ""}${c.depuis_mails ? `<span class="pastille p-vert" title="${echap(`${c.depuis_mails.mails} mail${c.depuis_mails.mails > 1 ? "s" : ""}, le dernier le ${dateFr(c.depuis_mails.dernier, true)}`)}">e-mail trouvé dans Outlook</span>` : ""}${c.fonction ? `<span class="pastille p-gris">${echap(c.fonction)}</span>` : ""}
            ${d.contacts.length > 1 ? `<button class="btn lien danger" data-retirer="${i}">Retirer</button>` : ""}</div>
          ${c.lie ? `<div class="alerte">Contact lié : <b>${echap(c.lie.prenom || "")} ${echap(c.lie.nom)}</b> (même personne que dans un autre dossier).
              <button class="btn lien" data-delier="${i}">Délier</button></div>
            ${champ(`contact.${i}.role`, "Rôle dans ce dossier", liste(`contact.${i}.role`, r.roles, c.role || "AUTRE"))}`
          : `<div class="grille2">${champ(`contact.${i}.civilite`, "Civilité", liste(`contact.${i}.civilite`, r.civilites, c.civilite || ""), true)}
            ${champ(`contact.${i}.role`, "Rôle", liste(`contact.${i}.role`, r.roles, c.role || (i ? "AUTRE" : pro() ? "REPRESENTANT_LEGAL" : "DECLARANT")))}
            ${champ(`contact.${i}.prenom`, "Prénom", saisie(`contact.${i}.prenom`, c.prenom || ""), true)}${champ(`contact.${i}.nom`, "Nom", saisie(`contact.${i}.nom`, c.nom || ""))}
            ${champ(`contact.${i}.email`, "E-mail", saisie(`contact.${i}.email`, c.email || "", 'type="email"'), true)}${champ(`contact.${i}.telephone`, "Téléphone", saisie(`contact.${i}.telephone`, c.telephone || ""), true)}</div>`}
          <div class="doublons"></div></div>`).join("")}
        ${dirigeants.length ? `<div class="dirigeants-proposes"><span>Autres dirigeants au registre :</span>
          ${dirigeants.map((x, k) => `<button class="btn petit" data-dirigeant="${k}">＋ ${echap(`${x.prenom} ${x.nom}`)} <small>${echap(x.fonction)}</small></button>`).join("")}</div>` : ""}
        <div class="coordonnees-mails">${blocCoordonnees()}</div>
        <div><button class="btn" data-a="plus-contact">＋ Ajouter un contact supplémentaire</button></div>
      </div></div>`);
    corps.append(z);
    chercherCoordonnees(z);
    $$("[data-coord]", z).forEach((b) => b.onclick = () => {
      recueillir();
      const x = d.coordonnees.liste[+b.dataset.coord];
      const [prenom, ...reste] = (x.nom.includes("@") ? "" : x.nom).split(" ");
      let i = d.contacts.findIndex((c) => !c.lie && !c.email && (!c.nom || coordonneeDe(c) === x));
      if (i < 0) { d.contacts.push({ role: "AUTRE", prenom: reste.length ? prenom : "", nom: reste.join(" ") || prenom || "" }); i = d.contacts.length - 1; }
      const c = d.contacts[i];
      c.email = x.email; if (!c.telephone && x.telephones[0]) c.telephone = x.telephones[0];
      if (!c.nom && x.nom && !x.nom.includes("@")) { c.prenom = reste.length ? prenom : ""; c.nom = reste.join(" ") || prenom; }
      c.depuis_mails = x;
      corps.lastElementChild.remove(); pageContact();
    });
    const bascule = () => $(".bloc-contact", z).classList.toggle("cache", !$('[name="avec_contact"]', z).checked);
    $('[name="avec_contact"]', z).onchange = bascule; bascule();
    const redessiner = () => { recueillir(); corps.lastElementChild.remove(); pageContact(); };
    $('[data-a="plus-contact"]', z).onclick = () => { recueillir(); d.contacts.push({ role: "AUTRE" }); corps.lastElementChild.remove(); pageContact();
      setTimeout(() => $$(".contact-saisi", corps).pop()?.querySelector('input[name$=".prenom"]')?.focus(), 30); };
    $$("[data-retirer]", z).forEach((b) => b.onclick = () => { recueillir(); d.contacts.splice(+b.dataset.retirer, 1); corps.lastElementChild.remove(); pageContact(); });
    $$("[data-delier]", z).forEach((b) => b.onclick = () => { recueillir(); const c = d.contacts[+b.dataset.delier]; c.lie = null; c.doublons_vus = true; corps.lastElementChild.remove(); pageContact(); });
    $$("[data-dirigeant]", z).forEach((b) => b.onclick = () => {
      recueillir();
      const x = dirigeants[+b.dataset.dirigeant], vide = d.contacts.findIndex((c) => !c.nom && !c.prenom && !c.lie);
      const c = { ...contactDuDirigeant(x), auto: true };
      if (vide >= 0) d.contacts[vide] = c; else d.contacts.push(c);
      corps.lastElementChild.remove(); pageContact();
    });
    void redessiner;
  }

  /** Adresses trouvées dans Outlook qui ne sont pas encore dans un contact : « Utiliser » les reprend. */
  function blocCoordonnees() {
    const k = d.coordonnees;
    if (!k) return "";
    if (!k.boite) return '<span style="color:var(--muted)">Connectez votre boîte Outlook (Paramètres) pour retrouver les e-mails et téléphones de vos échanges avec cette société.</span>';
    const pris = new Set(d.contacts.map((c) => String(c.email || "").toLowerCase()));
    const libres = k.liste.map((x, i) => [x, i]).filter(([x]) => !pris.has(x.email));
    if (!libres.length) return k.liste.length ? "" : `<span style="color:var(--muted)">${k.erreur ? echap(k.erreur) : "Aucun échange avec cette société dans votre boîte Outlook : e-mail et téléphone à saisir."}</span>`;
    return `<div class="trouves-mails"><span>Trouvés dans vos mails :</span>${libres.map(([x, i]) => `<button class="btn petit" data-coord="${i}"
      title="${echap(`${x.mails} mail${x.mails > 1 ? "s" : ""}, le dernier le ${dateFr(x.dernier, true)}`)}">＋ ${echap(x.nom && !x.nom.includes("@") ? `${x.nom} · ` : "")}${echap(x.email)}${x.telephones.length ? ` · ${echap(x.telephones.join(", "))}` : ""}</button>`).join("")}</div>`;
  }

  function pageRelance() {
    const z = el(`<div class="grille2">${champ("prochaine_relance", "Date de relance", saisie("prochaine_relance", d.prochaine_relance || "", 'type="date"'), true)}
      ${champ("note_relance", "Note", saisie("note_relance", d.note_relance || "", 'maxlength="200"'), true)}</div>`);
    corps.append(z);
  }

  async function verifierDoublons() {
    for (const [i, c] of d.contacts.entries()) {
      if (c.lie || c.doublons_vus || !(c.nom || c.email)) continue;
      const { doublons } = await api("GET", `/api/doublons?prenom=${encodeURIComponent(c.prenom || "")}&nom=${encodeURIComponent(c.nom || "")}&email=${encodeURIComponent(c.email || "")}`);
      c.doublons_vus = true;
      if (!doublons.length) continue;
      const zone = $(`.contact-saisi[data-i="${i}"] .doublons`, corps);
      zone.innerHTML = `<div class="alerte"><b>Contact déjà connu.</b> Même personne ? Liez-la pour garder une seule fiche (ses coordonnées seront partagées).
        ${doublons.map((x, k) => `<div style="margin-top:6px">${echap([x.contact.prenom, x.contact.nom].filter(Boolean).join(" "))} · ${echap(x.contact.email || "")} · dans ${echap(x.dossiers.join(", "))}
          <button class="btn petit" data-lier="${k}">Lier</button></div>`).join("")}
        <div style="margin-top:6px">Sinon, « Suivant » crée un nouveau contact.</div></div>`;
      $$("[data-lier]", zone).forEach((b) => b.onclick = () => { recueillir(); c.lie = doublons[+b.dataset.lier].contact; corps.lastElementChild.remove(); pageContact(); });
      zone.scrollIntoView({ block: "nearest", behavior: "smooth" });
      return false;
    }
    return true;
  }

  prec.onclick = () => { recueillir(); etape = etape === 1 && !pro() ? 0 : etape - 1; afficher(-1); };
  suiv.onclick = async () => {
    recueillir();
    if (etape === 1 && !String(d.denomination || "").trim()) return erreursChamps(corps, { denomination: pro() ? "La dénomination est obligatoire." : "Le nom du foyer est obligatoire." });
    if (etape === 2 && pro() && !d.origine) return erreursChamps(corps, { origine: "Choisir l'origine du dossier." });
    if (etape === 2 && pro() && d.origine === "REPRISE" && !String(d.cabinet_precedent || "").trim() && !d.cabinet_plus_tard)
      return erreursChamps(corps, { cabinet_precedent: "Indiquer le cabinet précédent, ou cocher « À renseigner plus tard »." });
    if (etape === 3) {
      d.avec_contact = $('[name="avec_contact"]', corps).checked;
      if (d.avec_contact) {
        d.contacts = d.contacts.filter((c, i) => i === 0 || c.lie || c.nom || c.prenom || c.email || c.telephone);  // lignes vides ajoutées par erreur
        const manque = d.contacts.findIndex((c) => !c.lie && !String(c.nom || "").trim());
        if (manque >= 0) return erreursChamps(corps, { [`contact.${manque}.nom`]: "Le nom du contact est obligatoire." });
        if (!(await verifierDoublons())) return;
      }
    }
    if (etape < 4) { etape += 1; return afficher(1); }
    const { contacts, repertoire, coordonnees, recherche_coord, ...reste } = d;
    const envoi = { ...reste, missions: d.missions, contacts: d.avec_contact === false ? []
      : contacts.map((c) => c.lie ? { id_lie: c.lie.id, role: c.role } : { civilite: c.civilite, role: c.role, prenom: c.prenom, nom: c.nom, email: c.email, telephone: c.telephone, fonction: c.fonction || "" }) };
    try {
      suiv.disabled = true;
      const r = await executer(api("POST", "/api/prospects", envoi), `${d.denomination} : prospect créé (À qualifier).`);
      f.fermer();
      if (S.page !== "pipeline") await aller("pipeline"); else remplirColonnes(false, r.resultat);
      if (S.page === "pipeline") remplirColonnes(false, r.resultat);
    } catch (e) {
      suiv.disabled = false;
      const c = e.champs || {};
      if (c.nature) { etape = 0; } else if (c.denomination || c.siren) { etape = 1; } else if (c.origine || c.cabinet_precedent || c.nb_biens || c.nb_personnes) { etape = 2; }
      else if (Object.keys(c).some((k) => k.startsWith("contact."))) { etape = 3; } else if (c.prochaine_relance) { etape = 4; }
      await afficher(0);
      erreursChamps(corps, Object.fromEntries(Object.entries(c).map(([k, v]) => [k === "contact.nom" || k === "contact.email" ? `contact.0.${k.slice(8)}` : k, v])));
    }
  };
  afficher(0);
}

// ---------- Clients ----------
async function chargerClients() {
  const d = await api("GET", "/api/clients");
  // Lien d'ouverture dans l'Explorateur : fourni par le serveur, sinon refait ici à partir du nom du dossier
  S.clients = d.clients.map((c) => ({ ...c, dossier_lien: c.dossier_lien || (c.dossier ? "crm-dossier:" + encodeURIComponent(c.dossier) : "") }));
  S.clientsMaj = d.maj; S.dossiersMaj = d.dossiers_maj;
}

/** Import de clients : fichier CSV, tableau collé (presse-papiers, Excel) ou modèle à remplir. Aperçu d'abord, import ensuite. */
function fenetreImport() {
  let texte = "", analyse = null, n = 0;
  const f = fenetre({ titre: "Importer des clients", classe: "large",
    sous: "Fichier CSV (Excel, Pennylane, export du CRM) ou tableau copié. Vous voyez le résultat avant d'importer : rien n'est modifié avant.",
    corps: `<div class="import-choix">
        <label class="import-carte" data-a="fichier"><input type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" hidden>${icone("dossier")}
          <b>Choisir un fichier CSV</b><span>ou le glisser ici</span></label>
        <button class="import-carte" data-a="coller" type="button">${icone("trombone")}<b>Coller le presse-papiers</b><span>tableau copié dans Excel</span></button>
        <a class="import-carte" href="/api/clients/modele.csv" download>${icone("telecharger")}<b>Télécharger le modèle</b><span>CSV à remplir dans Excel</span></a></div>
      <div class="champ"><label>Ou collez directement ici (Ctrl+V)</label>
        <textarea class="saisie" name="texte" spellcheck="false" placeholder="Société;Type;Forme juridique;SIREN;Contact;E-mail;…" style="min-height:110px;font-family:ui-monospace,Consolas,monospace;font-size:12px"></textarea></div>
      <div class="import-apercu"></div>`,
    pied: `<button class="btn fantome" data-a="annuler">Annuler</button><button class="btn principal" data-a="importer" disabled>Importer</button>` });
  const zoneTexte_ = $('[name="texte"]', f), apercu = $(".import-apercu", f), bouton = $('[data-a="importer"]', f);
  const pastille = (classe, x, libelle) => x ? `<span class="pastille ${classe}">${x} ${libelle}</span>` : "";
  const afficher = () => {
    if (!analyse) { apercu.innerHTML = ""; bouton.disabled = true; bouton.textContent = "Importer"; return; }
    const t = analyse.totaux;
    n = t.ajout + t.maj;
    bouton.disabled = !n; bouton.textContent = n ? `Importer ${n} client${n > 1 ? "s" : ""}` : "Rien à importer";
    const resultat = (l) => l.action === "ajout" ? '<span class="pastille p-vert">Nouveau</span>'
      : l.action === "maj" ? `<span class="pastille p-bleu">Mis à jour</span> <small>${echap(l.changements.join(", "))}</small>` : '<span class="pastille p-gris">Inchangé</span>';
    apercu.innerHTML = `<div class="import-totaux">${pastille("p-vert", t.ajout, t.ajout > 1 ? "nouveaux" : "nouveau")}${pastille("p-bleu", t.maj, "mis à jour")}
        ${pastille("p-gris", t.identique, t.identique > 1 ? "inchangés" : "inchangé")}${pastille("p-rouge", t.erreur, t.erreur > 1 ? "lignes en erreur, ignorées" : "ligne en erreur, ignorée")}</div>
      <div class="import-colonnes"><b>Colonnes reconnues :</b> ${analyse.colonnes.map((c) => `<span class="pastille p-gris">${echap(c)}</span>`).join(" ")}
        ${analyse.ignorees.length ? `<div style="color:var(--muted)">Ignorées : ${echap(analyse.ignorees.join(", "))}</div>` : ""}</div>
      ${analyse.erreurs.length ? `<div class="alerte">${analyse.erreurs.slice(0, 50).map((e) => `<div>Ligne ${e.ligne}${e.nom ? ` · ${echap(e.nom)}` : ""} : ${echap(e.message)}</div>`).join("")}
        ${analyse.erreurs.length > 50 ? `<div>… et ${analyse.erreurs.length - 50} autres.</div>` : ""}</div>` : ""}
      ${analyse.lignes.length ? `<div class="import-table"><table><thead><tr><th>LIGNE</th><th>CLIENT</th><th>RÉSULTAT</th></tr></thead><tbody>
        ${analyse.lignes.slice(0, 300).map((l) => `<tr><td>${l.ligne}</td><td><b>${echap(l.nom)}</b>${l.avertissements.map((a) => `<div class="import-avert">${echap(a)}</div>`).join("")}</td>
          <td>${resultat(l)}</td></tr>`).join("")}</tbody></table>
        ${analyse.lignes.length > 300 ? `<div style="color:var(--muted);padding:8px 12px">… et ${analyse.lignes.length - 300} autres lignes.</div>` : ""}</div>` : ""}`;
  };
  let tour = 0;
  const verifier = async () => {
    const mien = ++tour;
    if (!texte.trim()) { analyse = null; return afficher(); }
    apercu.innerHTML = '<div style="color:var(--muted)">Vérification…</div>';
    try {
      const r = await api("POST", "/api/clients/import", { texte, appliquer: false });
      if (mien !== tour) return;
      analyse = r; afficher();
    } catch (e) {
      if (mien !== tour) return;
      analyse = null; afficher();
      apercu.innerHTML = `<div class="alerte">${echap(e.champs?.texte || e.message)}</div>`;
    }
  };
  const prendre = (t, origine) => { texte = t; zoneTexte_.value = t.length > 200000 ? t.slice(0, 200000) + "\n…" : t; if (origine) toast(origine); verifier(); };
  // Fichier : UTF-8, sinon Windows-1252 (CSV enregistré par Excel)
  const lireFichier = async (fichier) => {
    if (!fichier) return;
    if (fichier.size > 8 * 1024 * 1024) return toast("Fichier trop volumineux (8 Mo au plus).", { erreur: true });
    const octets = await fichier.arrayBuffer();
    let t;
    try { t = new TextDecoder("utf-8", { fatal: true }).decode(octets); } catch { t = new TextDecoder("windows-1252").decode(octets); }
    prendre(t, `Fichier lu : ${fichier.name}`);
  };
  $('[data-a="fichier"] input', f).onchange = (e) => lireFichier(e.target.files[0]);
  const carte = $('[data-a="fichier"]', f);
  carte.addEventListener("dragover", (e) => { e.preventDefault(); carte.classList.add("survol"); });
  carte.addEventListener("dragleave", () => carte.classList.remove("survol"));
  carte.addEventListener("drop", (e) => { e.preventDefault(); carte.classList.remove("survol"); lireFichier(e.dataTransfer.files[0]); });
  $('[data-a="coller"]', f).onclick = async () => {
    try {
      const t = await navigator.clipboard.readText();
      if (!t.trim()) return toast("Le presse-papiers est vide : copiez d'abord le tableau (avec sa ligne d'intitulés) dans Excel.", { erreur: true });
      prendre(t, "Tableau collé depuis le presse-papiers.");
    } catch {
      zoneTexte_.focus();
      toast("Le navigateur ne laisse pas lire le presse-papiers ici : collez avec Ctrl+V dans la zone.", { erreur: true, duree: 6000 });
    }
  };
  let attente;
  zoneTexte_.addEventListener("input", () => { clearTimeout(attente); attente = setTimeout(() => { texte = zoneTexte_.value; verifier(); }, 450); });
  $('[data-a="annuler"]', f).onclick = f.fermer;
  bouton.onclick = async () => {
    bouton.disabled = true; bouton.textContent = "Import…";
    try {
      const r = await api("POST", "/api/clients/import", { texte, appliquer: true });
      const t = r.totaux;
      toast(`Import terminé : ${t.ajout} nouveau${t.ajout > 1 ? "x" : ""}, ${t.maj} mis à jour.`);
      f.fermer();
      await chargerClients(); if (S.page === "clients") rendre(false);
    } catch (e) { signalerErreur(e); afficher(); }
  };
}

function pageClients(zone) {
  if (!S.clients) { zone.innerHTML = '<div class="chantier">Chargement…</div>'; chargerClients().then(() => S.page === "clients" && rendre(false)).catch(signalerErreur); return; }
  if (S.clientCourant) return ficheClient(zone, S.clients.find((c) => c.id === S.clientCourant));
  const n = (s) => S.clients.filter((c) => s === "ARCHIVE" ? c.archive : !c.archive && (!s || c.statut === s)).length;
  const barre = el(`<div class="barre">
      <label class="recherche">${icone("recherche")}<input class="saisie" placeholder="Rechercher : société, contact, e-mail, SIREN…" value="${echap(S.clientRecherche)}"></label>
      <div class="segments" id="f-clients"><span class="pilule"></span><button data-v="TOUS">Tous <span class="compte">${n()}</span></button>
        <button data-v="ACTIF">Actifs <span class="compte">${n("ACTIF")}</span></button><button data-v="ONBOARDING">Onboarding <span class="compte">${n("ONBOARDING")}</span></button>
        <button data-v="ARCHIVE" title="Anciens clients : masqués des autres listes, toutes leurs informations sont gardées">Archivés <span class="compte">${n("ARCHIVE")}</span></button></div>
      <span style="color:var(--muted);white-space:nowrap">${S.clientsMaj ? `Pennylane : ${dateFr(S.clientsMaj, true)}` : ""}</span>
      <span class="espace"></span><button class="btn principal" data-a="synchro">${icone("actualiser")} Synchro Pennylane</button>
      <button class="btn rond-plus" data-a="plus" title="Importer, exporter">${icone("points")}</button></div>`);
  zone.append(barre);
  $('[data-a="plus"]', barre).onclick = (ev) => menu(ev.currentTarget, [["Importer des clients (CSV, Excel)…", () => fenetreImport()],
    ["Exporter la liste (CSV)", () => { location.href = "/api/clients.csv"; }]]);
  $('[data-a="synchro"]', barre).onclick = () => synchroPennylane();
  majBoutonSynchro();
  const tab = el(`<div class="tableau tableau-clients"><table><thead><tr></tr></thead><tbody></tbody></table></div>`);
  zone.append(tab);
  const COLS = [["SOCIÉTÉ", "nom"], ["CONTACT", "contact"], ["E-MAIL", "email"], ["STATUT", "statut"], ["PENNYLANE", "pennylane"], ["DOSSIER COMPTABLE", "dossier"], ["DOSSIER PERMANENT", "dossier_societe"], ["JURIDIQUE ANNUEL", "dossier_juridique"]];
  const entete = () => {
    $("thead tr", tab).innerHTML = COLS.map(([t, k]) => `<th data-k="${k}">${t}${S.clientTri[0] === k ? `<span class="tri">${S.clientTri[1] > 0 ? "▲" : "▼"}</span>` : ""}</th>`).join("");
    $$("th", tab).forEach((th) => th.onclick = () => { S.clientTri = [th.dataset.k, S.clientTri[0] === th.dataset.k ? -S.clientTri[1] : 1]; entete(); lignes(); });
  };
  const lignes = (doux) => {
    const q = S.clientRecherche.trim().toLowerCase();
    const [k, s] = S.clientTri;
    const ls = S.clients.filter((c) => (S.clientFiltre === "ARCHIVE" ? c.archive : !c.archive && (S.clientFiltre === "TOUS" || c.statut === S.clientFiltre))
      && (!q || [c.nom, c.contact, c.email, c.siren].join(" ").toLowerCase().includes(q)))
      .sort((a, b) => String(a[k] || "").localeCompare(String(b[k] || ""), "fr", { sensitivity: "base" }) * s);
    $("tbody", tab).innerHTML = ls.map((c) => `<tr data-id="${echap(c.id)}"><td class="nom" title="${echap(c.nom)}">${echap(c.nom)}</td><td title="${echap(c.contact || "")}">${echap(c.contact || "")}</td><td title="${echap(c.email || "")}">${echap(c.email || "")}</td>
      <td class="statut-${c.archive ? "ARCHIVE" : c.statut}">${c.archive ? "Archivé" : c.statut === "ACTIF" ? "Actif" : "Onboarding"}</td>
      <td>${c.pennylane ? `<a href="${echap(c.pennylane)}" target="_blank" rel="noopener">Ouvrir ↗</a>` : "–"}</td>
      <td class="annees">${c.dossier ? `<a class="racine" href="${echap(c.dossier_url)}" data-ouvrir="${echap(c.dossier)}" title="Comptabilité › ${echap(c.dossier)} : ouvrir le dossier">${icone("dossier")}</a>` : '<span class="racine"></span>'}${friseAnnees(c, "compta")}</td>
      <td>${c.dossier_societe ? `<a href="${echap(c.dossier_societe_url)}" data-ouvrir="${echap(c.dossier_societe)}" data-type="societe" title="Permanent › ${echap(c.dossier_societe)}">Ouvrir ↗</a>`
        : `<button class="lien-creer" data-creer="societe" data-client="${echap(c.id)}" title="Créer SOCIETE › ${echap(c.nom_nouveau_dossier)}">＋ Créer</button>`}</td>
      <td class="annees">${friseAnnees(c, "juridique")}</td></tr>`).join("")
      || `<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Aucun client.</td></tr>`;
    $$("tbody tr[data-id]", tab).forEach((tr) => tr.onclick = (e) => { if (!e.target.closest("a, button")) ouvrirClient(tr.dataset.id); });
    if (doux && MOUVEMENT) $("tbody", tab).animate([{ opacity: 0.2 }, { opacity: 1 }], { duration: 240 });
  };
  const champR = $("input", barre);
  champR.oninput = () => { S.clientRecherche = champR.value; lignes(); };
  segments($("#f-clients", barre), S.clientFiltre, (v) => { S.clientFiltre = v; lignes(true); });
  entete(); lignes();
}

async function changerEcranClients(id) {
  const zone = $("#page");
  if (MOUVEMENT) { zone.classList.remove("entree"); zone.classList.add("sortie"); await attendre(140); }
  S.clientCourant = id; rendre(false);
  if (MOUVEMENT) { zone.classList.remove("sortie"); void zone.offsetWidth; zone.classList.add("entree"); }
}
const ouvrirClient = (id) => changerEcranClients(id);

function ficheClient(zone, c) {
  if (!c) { S.clientCourant = null; return pageClients(zone); }
  const ligne = (l, v) => `<dt>${l}</dt><dd>${v ? echap(v) : "–"}</dd>`;
  const f = el(`<div class="fiche-client">
      <div><button class="btn lien" data-a="retour">${icone("retour")} Clients</button></div>
      <div style="display:flex;gap:12px;align-items:flex-start"><div style="flex:1"><h2 style="margin:0;font-size:24px">${echap(c.nom)}</h2>
        <div style="color:var(--muted)">${echap([c.type_libelle, c.forme, c.siren && "SIREN " + c.siren, c.ville].filter(Boolean).join(" · "))}</div></div>
        <div class="statut-client"><div class="segments" id="statut-client"><span class="pilule"></span>
            <button data-v="ACTIF">Actif</button><button data-v="ONBOARDING">Onboarding</button></div>
          <button class="btn ${c.archive ? "principal" : "fantome"}" data-a="archiver" title="${c.archive ? "Remettre ce client dans la liste" : "Ancien client : masqué des listes, toutes ses informations sont gardées"}">${c.archive ? "Désarchiver" : "Archiver"}</button></div></div>
      ${c.archive ? `<div class="bandeau-archive"><b>Client archivé</b>${c.archive_le ? ` le ${echap(instantFr(c.archive_le))}` : ""}${c.archive_par ? ` par ${echap(c.archive_par)}` : ""}.
        Il n'apparaît plus dans la liste des clients (filtre « Archivés ») ; toutes ses informations sont gardées.</div>` : ""}
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        ${c.pennylane ? `<a class="btn principal" href="${echap(c.pennylane)}" target="_blank" rel="noopener">Dossier Pennylane ↗</a>` : ""}
        <button class="btn" data-a="maj-infos" title="Contact, e-mail, téléphone, forme, SIREN : relus dans Pennylane par Claude Desktop (dossier cherché par son nom s'il n'est pas encore relié), vous validez">${icone("actualiser")} Mettre à jour les infos</button>
        ${c.dossier ? `<a class="btn principal" href="${echap(c.dossier_url)}" data-ouvrir="${echap(c.dossier)}" title="Dans l'Explorateur : Dossiers › Comptabilité › ${echap(c.dossier)} (Ctrl+clic : SharePoint)">${icone("dossier")} Dossier comptable</a>`
          : `<button class="btn" data-creer="compta" data-client="${echap(c.id)}">＋ Créer le dossier comptable</button>`}
        ${c.dossier_societe ? `<a class="btn principal" href="${echap(c.dossier_societe_url)}" data-ouvrir="${echap(c.dossier_societe)}" data-type="societe" title="Dans l'Explorateur : Dossiers › Permanent › ${echap(c.dossier_societe)} (Ctrl+clic : SharePoint)">${icone("dossier")} Dossier permanent</a>`
          : `<button class="btn" data-creer="societe" data-client="${echap(c.id)}">＋ Créer le dossier permanent</button>`}
        ${c.dossier ? `<a class="btn fantome" href="${echap(c.dossier_url)}" target="_blank" rel="noopener" title="Le dossier comptable, en ligne">SharePoint ↗</a>` : ""}
        ${c.pid ? '<button class="btn" data-a="fiche">Fiche prospect et historique</button>' : ""}</div>
      <div class="dossiers-annees">
        ${[["compta", "Dossier comptable", `Comptabilité › ${c.dossier || c.nom_nouveau_dossier} › année`, [...c.compta_anciennes, ...c.compta_annees]],
           ["juridique", "Juridique annuel", `Juridique annuel › année › ${c.nom_juridique || c.nom_nouveau_dossier}`, [...c.juridique_anciennes, ...c.juridique_annees]]]
          .map(([sorte, titre, chemin, annees]) => { const n = annees.filter((j) => j.dossier).length;
            return `<div class="ligne-annees"><div class="titre-annees" title="${echap(chemin)}"><b>${titre}</b>
              <span>${n ? `${n} année${n > 1 ? "s" : ""}` : "Aucune année pour l'instant"}</span></div>${friseAnnees(c, sorte, true)}</div>`; }).join("")}
        <div class="frise-legende"><span><i class="an ok"></i>dossier existant · cliquer pour l'ouvrir</span><span><i class="an vide">25</i>à créer</span>
          <span><i class="an plus">＋</i>année suivante</span></div></div>
      <div class="cartes">
        <div class="groupe"><div class="groupe-titre">CONTACT</div><dl class="lignes">${ligne("Dirigeant", [c.contact, c.qualite].filter(Boolean).join(" · "))}${c.email ? `<dt>E-mail</dt><dd><a href="mailto:${echap(c.email)}" title="Écrire dans Outlook">${echap(c.email)}</a></dd>` : ligne("E-mail", c.email)}
          ${c.telephone ? `<dt>Téléphone</dt><dd><a href="tel:${echap(c.telephone.replace(/[^\d+]/g, ""))}" title="Appeler (Teams ou téléphone du PC)">${echap(c.telephone)}</a></dd>` : ligne("Téléphone", c.telephone)}</dl></div>
        <div class="groupe"><div class="groupe-titre">SOCIÉTÉ</div><dl class="lignes">${ligne("Forme", c.forme)}${ligne("SIREN", c.siren)}
          ${ligne("Adresse", [c.adresse, c.cp, c.ville].filter(Boolean).join(" "))}${ligne("Créée le", c.cree_le && dateFr(c.cree_le, true))}${ligne("Activité", c.activite)}</dl></div>
        <div class="groupe"><div class="groupe-titre">FISCAL</div><dl class="lignes">${ligne("Fiscalité", c.fiscalite)}${ligne("Régime", c.regime)}${ligne("TVA", c.tva)}</dl></div>
        <div class="groupe"><div class="groupe-titre">CABINET</div><dl class="lignes">${ligne("Collaborateur", c.collaborateur)}${ligne("Expert-comptable", c.responsable)}
          ${ligne("Mandat Pennylane", c.mandat)}${ligne("Missions", c.missions)}${ligne("Lettre signée le", c.signee_le && dateFr(c.signee_le, true))}${ligne("Source", c.source)}</dl></div>
      </div>
      ${c.statut === "ONBOARDING" ? `<div class="onboarding"><div class="groupe-titre">ONBOARDING · PIÈCES À RECEVOIR</div>
        ${c.pieces.map((x) => `<div>○ ${echap(x)}</div>`).join("")}
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
          <span class="en-dev" style="padding:6px 12px">Formulaire des pièces en ligne · en développement</span>
          <span class="en-dev" style="padding:6px 12px">Parcours d'onboarding (impots.gouv, guide) · en développement</span>
          ${c.pid ? '<button class="btn principal petit" data-a="termine">Onboarding terminé</button>' : ""}</div></div>` : ""}
    </div>`);
  zone.append(f);
  $('[data-a="retour"]', f).onclick = () => changerEcranClients(null);
  $('[data-a="fiche"]', f)?.addEventListener("click", () => ouvrirFiche(c.pid));
  $('[data-a="maj-infos"]', f)?.addEventListener("click", () => completerAvecClaude(c.id));
  const changerStatut = async (statut, message) => {
    try { await api("POST", "/api/clients/statut", { id: c.id, statut }); toast(message); } catch (e) { return signalerErreur(e); }
    await chargerClients(); rendre(false);
  };
  segments($("#statut-client", f), c.statut, (v) => {
    if (v !== c.statut) changerStatut(v, `${c.nom} : ${v === "ACTIF" ? "client actif" : "remis en onboarding"}.`);
  });
  $('[data-a="archiver"]', f).onclick = () => {
    if (c.archive) return changerStatut(c.statut, `${c.nom} : désarchivé, de retour dans la liste.`);
    const v = fenetre({ titre: `Archiver « ${echap(c.nom)} » ?`, classe: "moyenne",
      sous: "Le client quitte la liste des clients. Toutes ses informations sont gardées : retrouvez-le dans le filtre « Archivés », d'où « Désarchiver » le remet.",
      pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="oui">Archiver</button>` });
    $('[data-a="non"]', v).onclick = v.fermer;
    $('[data-a="oui"]', v).onclick = () => { v.fermer(); changerStatut("ARCHIVE", `${c.nom} : archivé. Retrouvez-le dans le filtre « Archivés ».`); };
    setTimeout(() => $('[data-a="oui"]', v)?.focus(), 80);
  };
  $('[data-a="termine"]', f)?.addEventListener("click", async () => {
    await executer(api("POST", `/api/prospects/${c.pid}/onboarding-termine`, {}), `${c.nom} : onboarding terminé, client actif.`);
    await chargerClients(); rendre(false);
  });
}

// Dossier client dans l'Explorateur, sans rien installer : par le CRM lui-même s'il tourne sur ce PC ; sinon par la recherche
// Windows (chemin du dossier comptable de ce PC, indiqué une fois dans Paramètres) ; à défaut, SharePoint.
const CLE_CHEMIN = "crm-chemin-comptabilite";
function cheminLocal() { try { return localStorage.getItem(CLE_CHEMIN) || ""; } catch { return ""; } }
function lienRechercheWindows(base, dossier, type) {
  const compta = base.replace(/[\\/]+$/, "");
  const racine = type === "juridique" ? compta.replace(/[^\\/]+$/, "Juridique annuel") : type === "societe" ? compta.replace(/[^\\/]+$/, "Permanent") : compta;  // à côté du dossier comptable
  const chemin = racine + "\\" + dossier.replace(/\//g, "\\");
  return `search-ms:displayname=${encodeURIComponent(dossier)}&crumb=location:${encodeURIComponent(chemin)}`;
}
async function ouvrirDossier(dossier, adresseSharePoint, type = "") {
  let r = { ouvert: false };
  try { r = await api("POST", "/api/clients/ouvrir", { dossier, type }); } catch { /* serveur distant : on passe à la suite */ }
  if (r.ouvert) return toast(`Dossier ouvert dans l'Explorateur : ${dossier.replace("/", " › ")}`);
  const base = cheminLocal();
  if (base) { location.href = lienRechercheWindows(base, dossier, type); return; }
  window.open(adresseSharePoint, "_blank", "noopener");
  toast("Ouvert dans SharePoint. Pour l'Explorateur sur ce PC : indiquez une fois le dossier comptable de ce PC (Paramètres).",
    { action: "Paramètres", auClic: () => aller("parametres"), duree: 8000 });
}
/**
 * Frise des années d'un client, la même pour le dossier comptable (Comptabilité › NOM › année) et le juridique annuel
 * (Juridique annuel › année › NOM) : année marquée d'un point = le dossier existe (clic : l'ouvrir) ; année pâle = il manque
 * (clic : le créer) ; « ＋ » au bout : l'année suivante. L'année en cours est soulignée. La grande frise (fiche) montre aussi
 * les années plus anciennes du client.
 */
function friseAnnees(c, sorte, grande = false) {
  const courante = String(new Date().getFullYear());
  const compta = sorte === "compta";
  const titre = compta ? "Dossier comptable" : "Juridique annuel";
  const annees = compta ? [...(grande ? c.compta_anciennes : []), ...c.compta_annees] : [...(grande ? c.juridique_anciennes : []), ...c.juridique_annees];
  const prochaine = compta ? c.compta_prochaine : c.juridique_prochaine;
  const cases = annees.map((j) => {
    const libelle = grande ? j.annee : j.annee.slice(2);
    const cls = `an${j.annee === courante ? " courante" : ""}`;
    return j.dossier
      ? `<a class="${cls} ok" href="${echap(j.url)}" data-ouvrir="${echap(j.dossier)}" data-type="${compta ? "" : "juridique"}" title="${titre} ${j.annee} : ouvrir le dossier (Ctrl+clic : SharePoint)">${libelle}</a>`
      : `<button class="${cls} vide" data-creer="${sorte}" data-annee="${j.annee}" data-client="${echap(c.id)}" title="${titre} ${j.annee} : pas encore de dossier · cliquer pour le créer">${libelle}</button>`;
  });
  cases.push(`<button class="an plus" data-creer="${sorte}" data-annee="${prochaine}" data-client="${echap(c.id)}" title="${titre} : ajouter l'année ${prochaine}">${grande ? `＋ ${prochaine}` : "＋"}</button>`);
  return `<div class="frise${grande ? " grande" : ""}" role="group" aria-label="${titre}">${cases.join("")}</div>`;
}

/** Crée le dossier manquant d'un client dans SharePoint (synchronisé dans OneDrive) : nom proposé, modifiable. Jamais de suppression. */
// ---------- Synchro Pennylane : la liste des dossiers du cabinet, et les dossiers qui manquent dans SharePoint ----------
// La synchro continue en arrière-plan si on change de page ; à la fin, la fenêtre s'ouvre (page Clients) ou un message propose « Voir ».
function majBoutonSynchro() {
  const b = $('[data-a="synchro"]');
  if (!b) return;
  b.disabled = !!S.synchroPl;
  b.innerHTML = S.synchroPl ? `<span class="point-attente"></span> Lecture de Pennylane…` : `${icone("actualiser")} Synchro Pennylane`;
}
function quandPret(ouvrir, message) {  // fenêtre tout de suite sur la page Clients, sinon un message « Voir »
  if (S.page === "clients" && !$("#couche-modales").children.length) return ouvrir();
  toast(message, { action: "Voir", auClic: ouvrir, duree: 15000 });
}

async function synchroPennylane() {
  if (S.synchroPl) return;
  let etat;
  try { etat = await api("GET", "/api/pennylane"); } catch (e) { return signalerErreur(e); }
  if (!etat.configure) return fenetreJetonPennylane(() => synchroPennylane());
  S.synchroPl = true; majBoutonSynchro();
  let r;
  try { r = await api("POST", "/api/pennylane/synchro", { appliquer: false }); }  // aperçu : rien n'est écrit
  catch (e) { S.synchroPl = false; majBoutonSynchro(); return toast(e.message, { erreur: true, duree: 9000, action: "Jeton", auClic: () => fenetreJetonPennylane(() => synchroPennylane()) }); }
  S.synchroPl = false; majBoutonSynchro();
  if (r.apercu) return quandPret(() => confirmerSynchro(r), `Synchro Pennylane : ${r.ajoutes.length} dossier${r.ajoutes.length > 1 ? "s" : ""} à ajouter, à valider.`);
  terminerSynchro(r);
}

async function terminerSynchro(r) {
  S.clients = null;
  await chargerClients().catch(signalerErreur);
  if (S.page === "clients" && !S.clientCourant) rendre(false);
  quandPret(() => fenetreSynchroPennylane(r), "Synchro Pennylane terminée.");
}

/** Avant d'écrire quoi que ce soit : les dossiers à ajouter, cochés un par un (pas cochés = ignorés pour toujours, Paramètres pour
 *  revenir en arrière), et les fiches qui seraient complétées. */
function confirmerSynchro(r) {
  const noms = (xs) => `<div class="synchro-liste">${xs.map((x) => `<div class="synchro-ligne"><span>${echap(x.nom)}</span></div>`).join("")}</div>`;
  const f = fenetre({ titre: "Mettre à jour la liste des clients ?", sous: `${r.pennylane} dossier${r.pennylane > 1 ? "s" : ""} lus dans Pennylane. Rien n'a encore été modifié.`,
    classe: "moyenne",
    corps: `${r.ajoutes.length ? `<div class="groupe"><div class="groupe-titre">À AJOUTER À LA LISTE DES CLIENTS : COCHEZ CEUX QUI MANQUENT VRAIMENT</div>
        <label class="case tout-cocher"><input type="checkbox" checked data-tout>Tout cocher</label>
        <div class="synchro-liste">${r.ajoutes.map((x) => `<label class="case synchro-ligne"><input type="checkbox" checked data-ajout="${echap(x.id)}">${echap(x.nom)}</label>`).join("")}</div>
        <small class="aide">Les dossiers non cochés sont ignorés pour toujours : ils ne seront plus proposés (Paramètres › Pennylane pour revenir en arrière).</small></div>` : ""}
      ${r.completes.length ? `<div class="groupe"><div class="groupe-titre">FICHES COMPLÉTÉES : SIREN, LIEN, ADRESSE VIDES (${r.completes.length})</div>${noms(r.completes)}</div>` : ""}
      <small class="aide">Aucune fiche n'est effacée ni écrasée, rien n'est créé dans SharePoint ni écrit dans Pennylane. L'ancienne liste est sauvegardée.</small>`,
    pied: `<button class="btn fantome" data-a="non">Ne rien changer</button><button class="btn principal" data-a="oui">Mettre à jour la liste</button>` });
  const cases = $$("[data-ajout]", f);
  $("[data-tout]", f)?.addEventListener("change", (e) => cases.forEach((c) => { c.checked = e.target.checked; }));
  const compter = () => {
    const n = cases.filter((c) => c.checked).length;
    $('[data-a="oui"]', f).textContent = cases.length ? `Ajouter ${n} dossier${n > 1 ? "s" : ""}${r.completes.length ? " et compléter" : ""}` : "Mettre à jour la liste";
  };
  f.addEventListener("change", compter); compter();
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="oui"]', f).onclick = async () => {
    const ajouter = cases.filter((c) => c.checked).map((c) => c.dataset.ajout), ignorer = cases.filter((c) => !c.checked).map((c) => c.dataset.ajout);
    if (ignorer.length && !confirm(`${ignorer.length} dossier${ignorer.length > 1 ? "s" : ""} non coché${ignorer.length > 1 ? "s" : ""} : ne plus jamais le${ignorer.length > 1 ? "s" : ""} proposer ?\n(Paramètres › Pennylane pour revenir en arrière.)`)) return;
    f.fermer();
    S.synchroPl = true; majBoutonSynchro();
    let res;
    try { res = await api("POST", "/api/pennylane/synchro", { appliquer: true, ajouter, ignorer }); } catch (e) { S.synchroPl = false; majBoutonSynchro(); return signalerErreur(e); }
    S.synchroPl = false; majBoutonSynchro();
    terminerSynchro(res);
  };
}

/** Relier Pennylane une fois : jeton du cabinet en lecture seule (créé par un administrateur Pennylane). */
function fenetreJetonPennylane(apres) {
  const f = fenetre({ titre: "Relier Pennylane", sous: "Une seule fois, par un administrateur du cabinet dans Pennylane. Le CRM ne fait que lire la liste des dossiers.", classe: "moyenne",
    corps: `<ol class="etapes-action">
        <li>Dans Pennylane : <b>Paramètres du cabinet › Jetons du cabinet › Générer un jeton API</b>.</li>
        <li>Nom : « CRM Cabinet ». Accès : seulement <b>companies:readonly</b> (lecture des dossiers). Choisissez une date d'expiration.</li>
        <li>« Générer le jeton », copiez-le (Pennylane ne le montre qu'une fois) et collez-le ci-dessous.</li></ol>
      ${champ("jeton", "Jeton Pennylane du cabinet", saisie("jeton", "", 'type="password" autocomplete="off" placeholder="Collez le jeton ici"'))}
      <small class="aide">Gardé chiffré dans le CRM, jamais affiché ni envoyé ailleurs qu'à Pennylane.</small>`,
    pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Relier Pennylane</button>` });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async (ev) => {
    const b = ev.currentTarget; b.disabled = true; b.textContent = "Vérification…";
    try { await api("POST", "/api/pennylane/jeton", { jeton: $('[name="jeton"]', f).value }); f.fermer(); toast("Pennylane relié."); apres?.(); }
    catch (e) { erreursChamps(f, e.champs); if (!e.champs) signalerErreur(e); b.disabled = false; b.textContent = "Relier Pennylane"; }
  };
}

/** Compte rendu : dossiers ajoutés depuis Pennylane, dossiers Pennylane sans dossier SharePoint (à créer), et l'inverse. */
function fenetreSynchroPennylane(r) {
  const an = String(S.etat.aujourdhui).slice(0, 4);
  const liste = (xs, creer) => xs.length ? `<div class="synchro-liste">${xs.map((x) => `<div class="synchro-ligne"><span>${echap(x.nom)}</span>
      ${creer ? `<button class="btn petit" data-creer-compta="${echap(x.id)}">${icone("dossier")} Créer le dossier</button>` : ""}</div>`).join("")}</div>`
    : '<div class="succes-ligne">Rien à signaler.</div>';
  const f = fenetre({ titre: "Synchro Pennylane", sous: `${r.pennylane} dossier${r.pennylane > 1 ? "s" : ""} dans Pennylane · ${instantFr(r.le)}`, classe: "moyenne",
    corps: `<div class="synchro-chiffres">
        <div><b>${r.ajoutes.length}</b><span>ajouté${r.ajoutes.length > 1 ? "s" : ""} à la liste des clients</span></div>
        <div><b>${r.completes.length}</b><span>fiche${r.completes.length > 1 ? "s" : ""} complétée${r.completes.length > 1 ? "s" : ""} (SIREN, lien, adresse)</span></div>
        <div class="${r.manquants.length ? "alerte-chiffre" : ""}"><b>${r.manquants.length}</b><span>sans dossier comptable dans SharePoint</span></div></div>
      <div class="groupe"><div class="groupe-titre">DANS PENNYLANE, SANS DOSSIER COMPTABLE</div>${liste(r.manquants, true)}</div>
      <div class="groupe"><div class="groupe-titre">DOSSIER COMPTABLE, MAIS PAS DANS PENNYLANE</div>${liste(r.absents, false)}
        ${r.absents.length ? '<small class="aide">Ancien client, nom différent dans Pennylane, ou dossier à ajouter dans Pennylane : à vérifier.</small>' : ""}</div>
      ${r.incomplets ? `<div class="groupe"><div class="groupe-titre">FICHES SANS CONTACT, E-MAIL, TÉLÉPHONE OU FORME (${r.incomplets})</div>
        <div>Claude Desktop peut les chercher dans Pennylane (connecteur Pennylane), comme pour la relance bancaire. Vous validez avant toute écriture.</div>
        <div><button class="btn petit" data-a="claude">Compléter avec Claude</button></div></div>` : ""}`,
    pied: '<button class="btn principal" data-a="ok">Fermer</button>' });
  $('[data-a="ok"]', f).onclick = f.fermer;
  $('[data-a="claude"]', f)?.addEventListener("click", () => { f.fermer(); completerAvecClaude(); });
  $$("[data-creer-compta]", f).forEach((b) => b.onclick = () => {
    const c = (S.clients || []).find((x) => x.id === b.dataset.creerCompta);
    if (c) fenetreCreerDossier(c, "compta", an);
  });
}

// ---------- Compléter les fiches avec Claude Desktop (connecteur Pennylane) : comme la relance bancaire ----------
/** Sur ce PC : Claude Desktop s'ouvre et reçoit le prompt, le CRM attend le fichier en arrière-plan. En ligne : prompt à coller dans
 *  Claude Desktop, puis sa réponse à recoller ici. Dans les deux cas, rien n'est écrit sans validation. */
async function completerAvecClaude(id = "") {
  let r;
  try { r = await api("POST", "/api/pennylane/claude", id ? { id } : {}); } catch (e) { return toast(e.champs ? Object.values(e.champs)[0] : e.message, { erreur: true, duree: 8000 }); }
  if (!r.dossiers) return toast("Toutes les fiches Pennylane ont déjà contact, e-mail, téléphone et forme.");
  if (r.lance) {
    toast(r.mode === "arriere-plan" ? `Claude relit ${r.dossiers > 1 ? `${r.dossiers} fiches` : "la fiche"} dans Pennylane, en arrière-plan (aucune fenêtre). Continuez à travailler : le CRM vous prévient.`
      : `Claude Desktop relit ${r.dossiers > 1 ? `${r.dossiers} fiches` : "la fiche"} dans Pennylane. Vous pouvez continuer à travailler : le CRM vous prévient.`, { duree: 9000 });
    return attendreClaude();
  }
  const f = fenetre({ titre: "Compléter avec Claude", sous: `${r.dossiers} fiche${r.dossiers > 1 ? "s" : ""} à compléter. Ouvrez Claude Desktop, collez ce texte, puis collez ici sa réponse.`,
    classe: "moyenne", corps: `<textarea class="saisie" readonly style="min-height:140px;font-size:12px">${echap(r.prompt)}</textarea>
      <div><button class="btn petit" data-a="copier">Copier le texte pour Claude</button></div>
      ${champ("texte", "Réponse de Claude (le tableau JSON)", zoneTexte("texte", "", 'style="min-height:110px" placeholder="[{&quot;pennylane_id&quot;: …}]"'))}`,
    pied: '<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Voir ce qui sera complété</button>' });
  $('[data-a="copier"]', f).onclick = async () => { try { await navigator.clipboard.writeText(r.prompt); toast("Texte copié : collez-le dans Claude Desktop."); } catch { /* sans presse-papiers */ } };
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    try { const res = await api("POST", "/api/pennylane/claude/resultat", { texte: $('[name="texte"]', f).value }); f.fermer(); validerClaude(res.propositions, res.introuvables); }
    catch (e) { erreursChamps(f, e.champs); }
  };
}

function attendreClaude() {
  clearInterval(S.attenteClaude);
  S.attenteClaude = setInterval(async () => {
    let e;
    try { e = await api("GET", "/api/pennylane/claude"); } catch { return; }
    if (e.etat === "attente") return;
    clearInterval(S.attenteClaude); S.attenteClaude = null;
    if (e.etat === "pret") quandPret(() => validerClaude(e.propositions, e.introuvables),
      e.introuvables?.length && !e.propositions.length ? `Pas dans Pennylane : ${e.introuvables.join(", ")}.` : `Claude a fini : ${e.propositions.length} fiche${e.propositions.length > 1 ? "s" : ""} à mettre à jour, à valider.`);
    else toast(e.etat === "expire" ? "Pas de réponse de Claude Desktop après 30 minutes." : "Réponse de Claude illisible : relancez « Compléter avec Claude ».", { erreur: true, duree: 9000 });
  }, 8000);
}

/** Informations trouvées par Claude : une case vide de la fiche est cochée d'office ; une valeur différente de la fiche est montrée avec
 *  l'actuelle, non cochée (à cocher pour la remplacer). Seules les cases cochées sont écrites. */
function validerClaude(props, introuvables = []) {
  const absents = introuvables.length ? `<div class="alerte"><b>Pas trouvé dans Pennylane : ${echap(introuvables.join(", "))}.</b>
    Le dossier n'existe pas dans Pennylane, ou sous un autre nom : à vérifier dans Pennylane (ou à y créer).</div>` : "";
  if (!props.length) {
    if (!introuvables.length) return toast("Fiche à jour : rien de nouveau dans Pennylane.");
    const f = fenetre({ titre: "Pas dans Pennylane", classe: "moyenne", corps: absents, pied: '<button class="btn principal" data-a="ok">Compris</button>' });
    $('[data-a="ok"]', f).onclick = f.fermer;
    return;
  }
  const NOMS = { forme: "Forme", siren: "SIREN", contact: "Contact", email: "E-mail", telephone: "Téléphone", pennylane: "Relier au dossier Pennylane" };
  const f = fenetre({ titre: "Mettre à jour les infos ?", sous: "D'après Pennylane (lu par Claude). Cochez ce qui doit aller dans la fiche.", classe: "moyenne",
    corps: `${absents}<div class="claude-liste">${props.map((p, i) => `<div class="claude-fiche"><b>${echap(p.nom)}</b>
      <table class="comparaison"><thead><tr><th></th><th>Information</th><th>Actuellement dans la fiche</th><th></th><th>D'après Pennylane</th></tr></thead><tbody>
      ${Object.entries(p.champs).map(([k, x]) => `<tr class="${x.ancien ? "change" : "nouveau"}">
        <td><label class="case"><input type="checkbox" ${x.ancien ? "" : "checked"} data-p="${i}" data-k="${k}" aria-label="${NOMS[k]}"></label></td>
        <td class="muted">${NOMS[k]}</td><td class="ancien">${x.ancien ? echap(x.ancien) : '<span class="vide">vide</span>'}</td><td class="fleche">→</td>
        <td class="nouveau-v">${echap(x.valeur)}</td></tr>`).join("")}</tbody></table></div>`).join("")}</div>`,
    pied: '<button class="btn fantome" data-a="non">Ne rien changer</button><button class="btn principal" data-a="ok">Mettre à jour</button>' });
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = async () => {
    const lignes = props.map((p, i) => ({ id: p.id, champs: Object.fromEntries($$(`[data-p="${i}"]`, f).filter((c) => c.checked).map((c) => [c.dataset.k, p.champs[c.dataset.k].valeur])) }))
      .filter((l) => Object.keys(l.champs).length);
    try { const r = await api("POST", "/api/pennylane/claude/appliquer", { lignes }); f.fermer(); toast(`${r.champs} information${r.champs > 1 ? "s" : ""} ajoutée${r.champs > 1 ? "s" : ""} aux fiches.`);
      S.clients = null; await chargerClients().catch(signalerErreur); if (S.page === "clients") rendre(false); }
    catch (e) { signalerErreur(e); }
  };
}

function fenetreCreerDossier(c, type, annee) {
  const anneeSeule = type === "compta" && c.dossier;  // nouvelle année dans le dossier comptable existant : rien à nommer
  const propose = type === "juridique" ? c.nom_juridique || c.nom_nouveau_dossier : c.nom_nouveau_dossier || c.nom;
  const lieu = { compta: anneeSeule ? `Dossiers › Comptabilité › ${c.dossier} › ${annee}` : `Dossiers › Comptabilité (avec l'année ${annee})`,
    societe: "Dossiers › Permanent", juridique: `Dossiers › Juridique annuel › ${annee}` }[type];
  const titre = { compta: anneeSeule ? `Dossier comptable : ajouter ${annee}` : "Créer le dossier comptable", societe: "Créer le dossier permanent",
    juridique: `Juridique annuel : ajouter ${annee}` }[type];
  const libelleOk = anneeSeule || type === "juridique" ? `Créer ${annee}` : "Créer le dossier";
  const f = fenetre({ titre, sous: `${echap(c.nom)} · SharePoint › ${echap(lieu)}`, classe: "moyenne",
    corps: `${anneeSeule ? "" : champ("nom", "Nom du dossier", saisie("nom", propose, 'maxlength="150"'))}
      <div style="color:var(--muted)">${type === "compta" ? `L'année ${annee} est créée avec les sous-dossiers du modèle de l'année (comptable, fiscal, social). ` : ""}
        Le dossier est créé dans SharePoint et apparaît dans OneDrive sur chaque PC. S'il existe déjà, il est repris tel quel : rien n'est remplacé.</div>`,
    pied: `<button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">${libelleOk}</button>` });
  $('[data-a="non"]', f).onclick = f.fermer;
  if (anneeSeule) setTimeout(() => $('[data-a="ok"]', f)?.focus(), 80);
  $('[data-a="ok"]', f).onclick = async (ev) => {
    const b = ev.currentTarget; b.disabled = true; b.textContent = "Création…";
    try {
      const r = await api("POST", "/api/clients/dossier", { id: c.id, type, annee, nom: anneeSeule ? c.dossier : $('[name="nom"]', f).value });
      toast(r.cree ? `Dossier créé : ${r.chemin}` : `Ce dossier existait déjà : ${r.chemin}`);
      f.fermer();
      await chargerClients(); if (S.page === "clients") rendre(false);
    } catch (e) {
      b.disabled = false; b.textContent = libelleOk;
      if (e.champs) erreursChamps(f, e.champs); else signalerErreur(e);
    }
  };
}
document.addEventListener("click", (e) => {
  const b = e.target.closest("[data-creer]");
  if (!b) return;
  e.preventDefault(); e.stopPropagation();
  const c = (S.clients || []).find((x) => String(x.id) === b.dataset.client);
  if (c) fenetreCreerDossier(c, b.dataset.creer, b.dataset.annee || String(new Date().getFullYear()));
}, true);
document.addEventListener("click", (e) => {
  const a = e.target.closest("a[data-ouvrir]");
  if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;  // Ctrl+clic : SharePoint dans un nouvel onglet, comme un lien normal
  e.preventDefault(); e.stopPropagation();
  ouvrirDossier(a.dataset.ouvrir, a.href, a.dataset.type || "");
}, true);

// ---------- Livrables : lettres de mission ----------
const TYPES_DOSSIER = { SOCIETE: "Société", EI: "Entreprise individuelle", PARTICULIER: "Particulier" };

async function pageLivrables(zone) {
  let d;
  try { d = await api("GET", "/api/lettres"); } catch (e) { return signalerErreur(e); }
  S.modelesLettres = d.modeles; S.annexe2 = d.annexe2;
  const z = el(`<div class="page-scroll"><div class="barre"><span style="color:var(--muted)">Lettres de mission préparées dans le CRM, à partir des modèles du cabinet.</span>
      <span class="espace"></span><button class="btn principal" data-a="nouvelle">${icone("plus")} Nouvelle lettre de mission</button></div>
    <div class="liste-lettres"></div></div>`);
  zone.append(z);
  $('[data-a="nouvelle"]', z).onclick = () => assistantLettre();
  const liste = $(".liste-lettres", z);
  if (!d.lettres.length) {  // page vide : un seul appel, au centre de la page
    liste.replaceWith(el(`<div class="vide-page"><div class="rond-ic">${icone("livrables")}</div><h2>Aucune lettre pour l'instant</h2>
      <p>Choisissez un dossier : le CRM reprend ce qu'il sait déjà (fiche, Pennylane, répertoire des entreprises) et ne vous demande que le reste.</p>
      <button class="btn principal">${icone("plus")} Nouvelle lettre de mission</button></div>`));
    $(".barre", z).remove();  // pas de bouton en double : celui du centre suffit
    $(".vide-page .btn", z).onclick = () => assistantLettre();
    return;
  }
  for (const e of d.lettres) {
    const ligne = el(`<div class="lettre-ligne">${icone("livrables")}
        <div class="qui"><b>${echap(e.dossier.nom || "Sans nom")}</b><small>${echap(e.libelle)}${e.partenaire ? " · avec partenaire" : ""} · ${instantFr(e.le)} · ${echap(e.par)}</small>
          ${e.restantes.length ? `<span class="pastille p-orange">${e.restantes.length} zone${e.restantes.length > 1 ? "s" : ""} à compléter</span>` : ""}</div>
        ${e.pdf ? `<a class="btn petit" href="/api/lettres/${e.id}/pdf?voir=1" target="_blank" rel="noopener">PDF</a>`
          : `<button class="btn petit" data-a="pdf" title="${echap(e.pdf_erreur || "Ranger dans SharePoint et convertir en PDF")}">${e.pdf_erreur ? "⚠ " : ""}Créer le PDF</button>`}
        <a class="btn petit" href="/api/lettres/${e.id}/fichier">Word</a>
        <button class="btn petit principal" data-a="envoyer" title="Écrire le mail, la lettre en pièce jointe">${icone("mail")} Envoyer</button>
        <button class="btn petit" data-a="modifier">Modifier</button>
        <button class="btn petit fantome danger" data-a="suppr" title="Supprimer">${icone("fermer")}</button></div>`);
    $('[data-a="modifier"]', ligne).onclick = async () => {
      if (e.modele === "CONFRATERNELLE") { aller("pipeline"); return ouvrirFiche(e.dossier.ref, "ancien"); }
      const r = await api("GET", `/api/lettres/${e.id}`); assistantLettre(r.lettre.valeurs, e.id);
    };
    $('[data-a="envoyer"]', ligne).onclick = (ev) => envoyerLettre(e, ev.currentTarget);
    $('[data-a="pdf"]', ligne)?.addEventListener("click", async (ev) => {
      const b = ev.currentTarget;
      b.disabled = true; b.textContent = "PDF en cours…";
      let r;
      try { r = await fairePdf(e.id); } catch (err) { signalerErreur(err); return rendre(false); }
      if (r.lettre.pdf) toast(`PDF créé et rangé dans SharePoint › ${r.lettre.classement.dossier}.`);
      else toast(r.lettre.pdf_erreur, r.action?.type === "reconnecter" ? { erreur: true, action: "Paramètres", auClic: () => aller("parametres"), duree: 9000 } : { erreur: true, duree: 9000 });
      rendre(false);
    });
    $('[data-a="suppr"]', ligne).onclick = async () => {
      if (!confirm(`Supprimer la lettre « ${e.fichier} » ?`)) return;
      await api("POST", `/api/lettres/${e.id}/supprimer`); toast("Lettre supprimée."); rendre(false);
    };
    liste.append(ligne);
  }
}

/** Prestations proposées en plus de la mission (comme dans l'application de bureau) : un clic ajoute la ligne, il ne reste que le
 *  montant. Sans montant, une ligne n'est pas écrite dans la lettre. */
function propositionsPresta(m, v) {
  const P = {
    juridique: { libelle: "Juridique annuel (PV d’AGO et dépôt des comptes)", unite: "an", precision: "hors frais de greffe" },
    creationSoc: { libelle: "Création de société", unite: "forfait", precision: "hors débours (annonce légale, frais de greffe)" },
    creationEi: { libelle: "Création d’entreprise", unite: "forfait", precision: "immatriculation au guichet unique" },
    paie: { libelle: "Paie", unite: "bulletin", precision: "bulletins de salaire et DSN" },
    previsionnel: { libelle: "Business plan / prévisionnel financier", unite: "forfait", precision: "" },
    situation: { libelle: "Situation intermédiaire", unite: "forfait", precision: "" },
    tva: { libelle: "Demande de remboursement de TVA", unite: "declaration", precision: "" },
    attestation: { libelle: "Attestation", unite: "forfait", precision: "" },
  };
  if (["PRESENTATION", "ETABLISSEMENT"].includes(m)) return [P.juridique, P.creationSoc, P.paie, P.previsionnel, P.situation, P.tva, P.attestation];
  if (["BNC", "BIC"].includes(m)) return [P.creationEi, P.paie, P.previsionnel, P.situation, P.tva, P.attestation];
  if (m === "TVA") return [P.tva, v.type === "SOCIETE" ? P.juridique : P.creationEi, P.paie];
  return [];
}

/** « Bonjour Monsieur DUPONT » (ou Madame, ou Bonjour) pour le premier interlocuteur. */
function bonjour(c) {
  if (!c || !c.nom) return "Bonjour";
  return `Bonjour ${c.civilite === "MME" ? "Madame " : c.civilite === "M" ? "Monsieur " : ""}${c.civilite ? c.nom.toUpperCase() : [c.prenom, c.nom].filter(Boolean).join(" ")}`;
}

/** Envoie une lettre (Livrables, ou onglet Ancien cabinet) : le PDF est créé s'il manque, puis le mail s'ouvre, déjà rédigé, la lettre
 *  en pièce jointe. Prospect : dans ses Échanges. Client du cabinet : dans une fenêtre. Reprise : pas de lettre de mission avant la
 *  lettre confraternelle. */
async function envoyerLettre(e, bouton = null) {
  const confr = e.modele === "CONFRATERNELLE";
  const p = e.dossier.source === "prospect" ? S.etat.prospects.find((x) => x.id === e.dossier.ref) : null;
  const blocage = !confr && blocageReprise(p);
  if (blocage) return toast(blocage, { erreur: true, duree: 9000, action: "Ancien cabinet", auClic: () => { aller("pipeline"); ouvrirFiche(p.id, "ancien"); } });
  let lettre = e;
  if (!e.pdf) {
    if (bouton) { bouton.disabled = true; bouton.textContent = "PDF en cours…"; }
    try { const r = await fairePdf(e.id); lettre = { ...e, ...r.lettre }; } catch (err) { signalerErreur(err); }
    if (bouton) { bouton.disabled = false; bouton.innerHTML = `${icone("mail")} Envoyer`; }
    if (!lettre.pdf) return toast(lettre.pdf_erreur || "Le PDF n'a pas pu être créé : réessayez.", { erreur: true, duree: 9000 });
  }
  let valeurs = {};
  try { valeurs = (await api("GET", `/api/lettres/${e.id}`)).lettre.valeurs || {}; } catch { /* sans pré-remplissage */ }
  const nom = e.dossier.nom || "";
  let o;
  if (confr) {
    const a = p?.ancien || {};
    const appel = a.civilite === "MME" ? "Chère Consœur" : a.civilite === "M" ? "Cher Confrère" : "Chère Consœur, Cher Confrère";
    o = { a: [a.email].filter(Boolean), objet: `Reprise du dossier ${nom} : lettre confraternelle`, lettres: [e.id],
      html: paragraphes(`${appel},`, `Notre cabinet a été sollicité par ${echap(nom)} pour reprendre son dossier, que vous suiviez jusqu'à présent.
        Vous trouverez ci-joint notre lettre confraternelle à ce sujet.`, "Je vous remercie par avance de votre retour et reste à votre disposition pour organiser la transmission du dossier.",
        "Bien confraternellement,") };
  } else {
    const c = p ? (p.contacts || []).find((x) => ["REPRESENTANT_LEGAL", "DECLARANT"].includes(x.role) && x.email) || (p.contacts || []).find((x) => x.email)
      : { civilite: valeurs.civilite, prenom: valeurs.prenom, nom: valeurs.nom, email: valeurs.email };
    o = { a: [c?.email].filter(Boolean), objet: `Lettre de mission : ${e.libelle}`, lettres: [e.id],
      html: paragraphes(`${echap(bonjour(c))},`, `Comme convenu, vous trouverez ci-joint notre lettre de mission (${echap(e.libelle.toLowerCase())}).`,
        "Je vous remercie de bien vouloir nous la retourner signée. Je reste à votre disposition pour toute question.", "Bien à vous,") };
  }
  if (p && confr) {  // ancien cabinet : fiche sur « Ancien cabinet », et la fenêtre de ses échanges par-dessus
    if ($(".fenetre[data-fiche]")?.dataset.fiche !== p.id) { if (S.page !== "pipeline") await aller("pipeline"); ouvrirFiche(p.id, "ancien"); }
    return echangesAncien(p, o);
  }
  if (p) {  // prospect : dans ses Échanges, comme un mail normal (historique, relance)
    S.aEcrire = { pid: p.id, o };
    if (S.page !== "pipeline") await aller("pipeline");
    return ouvrirFiche(p.id, "echanges");
  }
  const contact = { email: valeurs.email || "", prenom: valeurs.prenom || "", nom: valeurs.nom || "" };
  const f = fenetre({ titre: "Envoyer la lettre de mission", sous: echap(nom), classe: "large", corps: '<div class="mx mx-seul"><section class="mx-lecture"></section></div>' });
  composer($(".mx-seul", f).parentElement, { id: e.dossier.ref, denomination: nom, contacts: contact.email ? [contact] : [] }, null,
    { ...o, url: "/api/outlook/envoyer", apres: () => f.fermer(), annuler: () => f.fermer() });
  if (!contact.email) toast("Pas d'e-mail connu pour ce client : ajoutez-le dans la lettre (Modifier) ou dans Pennylane.", { erreur: true, duree: 7000 });
}

/** Assistant « lettre de mission » : Dossier › Lettre › Destinataire › Mission › Honoraires › Récapitulatif.
 *  Tout ce qui est connu est repris (fiche prospect, Pennylane, répertoire officiel) ; les calculs se font seuls
 *  (minimum annuel, premier exercice, périodicité TVA) ; chaque étape reste modifiable jusqu'à la fin. */
function assistantLettre(depart = null, lettreId = null) {
  const ETAPES = ["Dossier", "Lettre", "Destinataire", "Mission", "Honoraires", "Récapitulatif"];
  const M = S.modelesLettres;
  let v = depart ? { ...depart } : {}, etape = depart ? 5 : 0, vues = depart ? 5 : 0, dossiers = null, genere = null;
  const f = fenetre({ titre: lettreId ? "Modifier la lettre de mission" : "Nouvelle lettre de mission", classe: "large", corps: "",
    pied: `<button class="btn fantome gauche" data-a="prec">Précédent</button><button class="btn principal" data-a="suiv">Suivant</button>` });
  const corps = $(".f-corps", f), prec = $('[data-a="prec"]', f), suiv = $('[data-a="suiv"]', f);
  const perso = () => v.modele && M[v.modele].public === "PERSO";
  const societe = () => v.type === "SOCIETE";
  const aujourdhui = S.etat.aujourdhui, an = +aujourdhui.slice(0, 4);

  const lire = () => {
    for (const x of $$("[name]", corps)) v[x.name] = x.type === "checkbox" ? x.checked : x.value;
    const multi = $$("[data-multi]", corps);
    if (multi.length) v.revenus = multi.filter((x) => x.checked).map((x) => x.value);
    if ($(".prestations", corps)) v.prestations = $$(".presta-ligne", corps).map((l) => ({ libelle: $('[data-c="libelle"]', l).value,
      precision: $('[data-c="precision"]', l).value, montant: $('[data-c="montant"]', l).value, unite: $('[data-c="unite"]', l).value,
      offert: $('[data-c="offert"]', l).checked })).filter((l) => l.libelle.trim() || l.montant.trim());
    if ($(".ifi", corps)) v.ifi = $$(".ifi-ligne", corps).map((l) => ({ annee: l.dataset.k, retenue: $("input[type=checkbox]", l).checked,
      an: $('[data-c="an"]', l)?.value || "", montant: $('[data-c="montant"]', l).value }));
  };

  async function afficher(sens = 0) {
    if (sens && MOUVEMENT) await corps.animate([{ opacity: 1 }, { opacity: 0, transform: `translateX(${-sens * 8}px)` }], { duration: 120, fill: "forwards" }).finished;
    corps.innerHTML = `<div class="etapes">${ETAPES.map((e, i) => `<button class="etape-btn ${i === etape ? "courante" : i <= vues ? "faite" : ""}" data-i="${i}" ${i <= vues ? "" : "disabled"}>${i + 1} ${e}</button>`).join("<span>›</span>")}</div>`;
    $$(".etape-btn", corps).forEach((b) => b.onclick = () => { lire(); const i = +b.dataset.i; const s2 = Math.sign(i - etape); etape = i; afficher(s2); });
    await [pDossier, pLettre, pDestinataire, pMission, pHonoraires, pRecap][etape]();
    prec.style.visibility = etape ? "visible" : "hidden";
    suiv.textContent = etape === 5 ? (lettreId ? "Enregistrer la lettre" : "Générer la lettre") : "Suivant";
    suiv.classList.toggle("cache", (etape === 0 && !v.ref && !v.libre) || (etape === 1 && !v.modele));
    if (sens && MOUVEMENT) corps.animate([{ opacity: 0, transform: `translateX(${sens * 10}px)` }, { opacity: 1, transform: "none" }], { duration: 240, easing: "cubic-bezier(.33,1,.68,1)", fill: "forwards" });
  }
  const titre = (t, s = "") => corps.append(el(`<div><h3 style="margin:4px 0 2px;font-size:18px">${t}</h3>${s ? `<div style="color:var(--muted)">${s}</div>` : ""}</div>`));
  const bloc = (html) => { const b = el(`<div style="display:flex;flex-direction:column;gap:12px">${html}</div>`); corps.append(b); return b; };
  const vu = (cle) => (v[cle] ?? "");

  async function pDossier() {
    titre("Pour quel dossier ?", "Le CRM reprend ce qu'il sait déjà : fiche du prospect, dossier Pennylane, répertoire des entreprises.");
    if (!dossiers) dossiers = (await api("GET", "/api/lettres/dossiers")).dossiers;
    const b = bloc(`<label class="recherche">${icone("recherche")}<input class="saisie" id="chercheDossier" placeholder="Rechercher un prospect ou un client…" style="width:100%"></label>
      <div class="choix-dossiers"></div>
      <div style="color:var(--muted)">Pas dans la liste ? <button class="btn lien" data-a="libre">Saisir un nouveau dossier</button></div>`);
    const remplirListe = () => {
      const q = $("#chercheDossier", b).value.trim().toLowerCase();
      const ls = dossiers.filter((x) => !q || x.nom.toLowerCase().includes(q)).slice(0, 40);
      $(".choix-dossiers", b).innerHTML = ls.map((x) => `<button class="dossier-ligne ${x.ref === v.ref ? "choisi" : ""}" data-i="${dossiers.indexOf(x)}">
          ${avatar(x.nom)}<span><b>${echap(x.nom)}</b><small>${echap(TYPES_DOSSIER[x.type] || "")} · ${echap(x.sous)}</small></span></button>`).join("")
        || '<div style="color:var(--muted);padding:12px">Aucun dossier.</div>';
      $$(".dossier-ligne", b).forEach((x) => x.onclick = async () => {
        const dos = dossiers[x.dataset.i];
        if (v.ref === dos.ref) { etape = 1; vues = Math.max(vues, 1); return afficher(1); }  // même dossier : on garde la saisie
        x.classList.add("choisi");
        const r = await api("GET", `/api/lettres/prefill?source=${dos.source}&ref=${encodeURIComponent(dos.ref)}`);
        v = { ...r.valeurs, modele: (r.valeurs.modeles_suggeres || [])[0] || "" };
        etape = 1; vues = 1; afficher(1);
      });
    };
    $("#chercheDossier", b).oninput = remplirListe; remplirListe();
    setTimeout(() => $("#chercheDossier", b)?.focus(), 60);
    $('[data-a="libre"]', b).onclick = async () => {
      const r = await api("GET", "/api/lettres/prefill?source=libre&type=SOCIETE");
      v = { ...r.valeurs, libre: true, modele: "" }; etape = 1; vues = 1; afficher(1);
    };
  }

  function pLettre() {
    titre("Quelle lettre de mission ?", v.modeles_suggeres?.length ? "Proposée d'après les missions envisagées sur la fiche. Vous pouvez en choisir une autre." : "Les lettres possibles pour ce type de dossier.");
    const b = bloc(`<div class="segments" id="typeDos" style="align-self:flex-start"><span class="pilule"></span>
        ${Object.entries(TYPES_DOSSIER).map(([k, l]) => `<button data-v="${k}">${l}</button>`).join("")}</div>
      <div class="cartes-choix lettres-choix"></div>
      <label class="case partenaire ${perso() ? "" : "cache"}"><input type="checkbox" name="partenaire" ${v.partenaire ? "checked" : ""}>Version avec partenaire (crédit d'impôt services à la personne)</label>`);
    const dessiner = () => {
      const ms = Object.entries(M).filter(([, m]) => m.types.includes(v.type));
      $(".lettres-choix", b).innerHTML = ms.map(([c, m]) => `<button class="choix ${v.modele === c ? "choisi" : ""}" data-m="${c}"><b>${echap(m.libelle)}</b>
          <span>${m.public === "PERSO" ? "Particulier" : "Professionnel"}${(v.modeles_suggeres || []).includes(c) ? " · proposée d'après la fiche" : ""}</span></button>`).join("");
      $$("[data-m]", b).forEach((x) => x.onclick = () => {
        v.modele = x.dataset.m; $$("[data-m]", b).forEach((y) => y.classList.toggle("choisi", y === x));
        $(".partenaire", b).classList.toggle("cache", !perso()); suiv.classList.remove("cache");
      });
    };
    segments($("#typeDos", b), v.type, (t) => {
      v.type = t;
      if (v.modele && !M[v.modele].types.includes(t)) { v.modele = ""; suiv.classList.add("cache"); }
      dessiner(); $(".partenaire", b).classList.toggle("cache", !perso());
    });
    dessiner();
  }

  function pDestinataire() {
    titre("À qui est adressée la lettre ?", v.repertoire ? "Adresse du siège reprise du répertoire officiel des entreprises." : "Repris de la fiche ; complétez ce qui manque.");
    bloc(`${societe() ? `<div class="grille2">${champ("forme", "Forme juridique", saisie("forme", vu("forme"), 'placeholder="SAS, SARL…"'))}${champ("denomination", "Dénomination", saisie("denomination", vu("denomination")))}</div>` : ""}
      <div class="grille3">${champ("civilite", "Civilité", liste("civilite", { "": "–", M: "Monsieur", MME: "Madame" }, vu("civilite")))}
        ${champ("prenom", "Prénom", saisie("prenom", vu("prenom")))}${champ("nom", societe() ? "Nom du dirigeant" : "Nom", saisie("nom", vu("nom")))}</div>
      ${societe() ? champ("qualite", "Qualité du signataire", saisie("qualite", vu("qualite"), 'placeholder="Président, Gérant… (déduite de la forme juridique si vide)"')) : ""}
      ${champ("adresse", "Adresse", saisie("adresse", vu("adresse")))}
      <div class="grille2">${champ("cp", "Code postal", saisie("cp", vu("cp"), 'maxlength="5" inputmode="numeric"'))}${champ("ville", "Ville", saisie("ville", vu("ville")))}</div>`);
  }

  function pMission() {
    const m = v.modele;
    titre("La mission", `${echap(M[m].libelle)} : seulement ce que la lettre demande.`);
    const parts = [];
    if (["PRESENTATION", "ETABLISSEMENT", "TVA", "BIC"].includes(m)) parts.push(champ("activite", "Activité exercée", saisie("activite", vu("activite"), 'placeholder="ex. conseil en gestion"')));
    if (m === "PRESENTATION") parts.push(champ("salaries", "Nombre de salariés", saisie("salaries", vu("salaries") || "0", 'inputmode="numeric"')));
    if (m === "ETABLISSEMENT") parts.push(champ("exercice", "Exercice concerné", liste("exercice", { CREATION: "Ouvert depuis la création de la société", OUVERT: "Ouvert le…" }, vu("exercice") || "CREATION"))
      + `<div class="si-ouvert ${v.exercice === "OUVERT" ? "" : "cache"}">${champ("exercice_ouvert", "Ouvert le", saisie("exercice_ouvert", vu("exercice_ouvert"), 'type="date"'))}</div>`);
    if (m === "TVA") parts.push(`<div class="grille2">${champ("regime_tva", "Régime de TVA", liste("regime_tva", { "": "Choisir…", CA3: "Réel normal (CA3)", CA12: "Réel simplifié (CA12, annuelle)" }, vu("regime_tva")))}
        <div class="si-ca3 ${v.regime_tva === "CA3" ? "" : "cache"}">${champ("periodicite", "Déclarations", liste("periodicite", { mensuelle: "Mensuelles", trimestrielle: "Trimestrielles" }, vu("periodicite") || "mensuelle"))}</div></div>`
      + champ("date_debut", "Déclarations déposées à compter du", saisie("date_debut", vu("date_debut"), 'type="date"')));
    if (m === "BNC") parts.push(champ("profession", "Profession libérale", saisie("profession", vu("profession"), 'placeholder="ex. kinésithérapeute"')));
    if (m === "BIC") parts.push(champ("regime_bic", "Régime", liste("regime_bic", { SIMPLIFIE: "Réel simplifié", NORMAL: "Réel normal" }, vu("regime_bic") || "SIMPLIFIE")));
    if (m === "IR") parts.push(champ("composition", "Le foyer fiscal se compose de", saisie("composition", vu("composition") || (v.nb_personnes ? `${v.nb_personnes} personne${v.nb_personnes > 1 ? "s" : ""}` : ""), 'placeholder="ex. un couple marié avec deux enfants"'))
      + `<div class="champ"><label>Revenus du foyer</label><div class="cases-ligne">${["salaires", "pensions", "revenus fonciers", "revenus de capitaux mobiliers", "plus-values", "revenus d'activité indépendante"]
        .map((r) => `<label class="case"><input type="checkbox" data-multi="1" value="${r}" ${(v.revenus || []).includes(r) ? "checked" : ""}>${r}</label>`).join("")}</div></div>`);
    if (["IR", "FONCIER", "LMNP"].includes(m)) parts.push(champ("annee", m === "LMNP" ? "Première liasse fiscale (année)" : "Année fiscale", saisie("annee", vu("annee") || String(an - 1), 'inputmode="numeric" maxlength="4"')));
    if (v.partenaire && M[m].partenaire) parts.push(`<div class="groupe"><div class="groupe-titre">PARTENAIRE SERVICES À LA PERSONNE · ATTESTATION DE PASSAGE À DOMICILE</div><div class="grille2">
        ${champ("adresse_passage", "Adresse du domicile", saisie("adresse_passage", vu("adresse_passage") || [v.adresse, [v.cp, v.ville].filter(Boolean).join(" ")].filter(Boolean).join(", ")))}
        ${champ("date_passage", "Date du passage", saisie("date_passage", vu("date_passage"), 'type="date"'), true)}</div></div>`);
    if (!parts.length) parts.push('<div style="color:var(--muted)">Rien de plus à préciser pour cette lettre : passez aux honoraires.</div>');
    const b = bloc(parts.join(""));
    $('[name="exercice"]', b)?.addEventListener("change", (e) => $(".si-ouvert", b).classList.toggle("cache", e.target.value !== "OUVERT"));
    $('[name="regime_tva"]', b)?.addEventListener("change", (e) => $(".si-ca3", b).classList.toggle("cache", e.target.value !== "CA3"));
  }

  function pHonoraires() {
    const m = v.modele, taxe = ["LMNP", "IR", "FONCIER", "IFI"].includes(m) ? "TTC" : "HT";
    titre("Honoraires", `Montants en euros ${taxe}. Les totaux se calculent seuls ; vous pouvez les corriger.`);
    const mt = (cle, lib, aide = "") => champ(cle, `${lib} (€ ${taxe})`, saisie(cle, vu(cle), 'inputmode="decimal"') + (aide ? `<small class="aide">${aide}</small>` : ""));
    // Forfait tenue et fiscal : par mois, par trimestre ou par an (la lettre suit : « Forfait mensuel / trimestriel / annuel »)
    const forfaitTenue = () => champ("forfait_mensuel", `Forfait tenue et fiscal (€ ${taxe})`, `<div class="montant-unite">${saisie("forfait_mensuel", vu("forfait_mensuel"), 'inputmode="decimal"')}
      ${liste("unite_tenue", { mois: "par mois", trimestre: "par trimestre", an: "par an" }, vu("unite_tenue") || "mois")}</div>`);
    const parts = [];
    if (["PRESENTATION", "BNC", "BIC"].includes(m)) {
      parts.push(`<div class="grille2">${forfaitTenue()}${mt("forfait_comptes", "Établissement des comptes annuels")}</div>`,
        mt("minimum", "Minimum annuel en cas d'exercice court ou de reprise", "Calculé : la moitié d'une année complète (forfait sur un an + comptes annuels)."),
        `<label class="case"><input type="checkbox" name="premier_exercice" ${v.premier_exercice ? "checked" : ""}>Premier exercice de reprise, court ou long : honoraires particuliers</label>
         <div class="grille2 si-premier ${v.premier_exercice ? "" : "cache"}">${champ("premier_mois", "Durée (mois)", saisie("premier_mois", vu("premier_mois"), 'inputmode="numeric"'))}
           ${mt("premier_montant", "Honoraires de ce premier exercice", "Suggestion : mensuel × mois + comptes annuels.")}</div>`);
    }
    if (m === "ETABLISSEMENT") parts.push(`<div class="grille3b">${forfaitTenue()}${mt("forfait_etablissement", "Établissement des comptes")}${mt("forfait_depot", "Dépôt des comptes")}</div>`);
    if (m === "TVA") parts.push(mt("forfait_tva", `Par déclaration ${v.regime_tva === "CA12" ? "annuelle" : v.periodicite || "mensuelle"}`));
    if (m === "LMNP") parts.push(`<label class="case"><input type="checkbox" name="creation_lmnp" ${v.creation_lmnp ? "checked" : ""}>Création du statut LMNP (guichet unique)</label>
        <div class="si-creation ${v.creation_lmnp ? "" : "cache"}">${mt("forfait_creation_lmnp", "Création du statut")}</div>
        <div class="grille2">${mt("forfait_liasse", `Liasse fiscale ${v.annee || an - 1}`)}${mt("forfait_suivants", `Liasse ${(+v.annee || an - 1) + 1} et suivantes`)}</div>`);
    if (m === "IR") parts.push(mt("forfait_ir", "Forfait déclaration de revenus"));
    if (m === "FONCIER") parts.push(mt("forfait_bien", "Forfait par bien immobilier déclaré", v.nb_biens ? `${v.nb_biens} bien(s) d'après la fiche.` : ""));
    if (m === "IFI") {
      const ls = v.ifi || [];
      const l = (k, lib, avecAn, anDef) => { const x = ls.find((y) => y.annee === k) || {}; return `<div class="ifi-ligne" data-k="${k}">
          <label class="case"><input type="checkbox" ${x.retenue ? "checked" : ""}>${lib}</label>
          ${avecAn ? `<input class="saisie" data-c="an" value="${echap(x.an || anDef)}" inputmode="numeric" maxlength="4" placeholder="année">` : "<span></span>"}
          <input class="saisie" data-c="montant" value="${echap(x.montant || "")}" placeholder="€ TTC" inputmode="decimal"></div>`; };
      parts.push(`<div class="ifi"><div style="color:var(--muted)">Cochez les déclarations à inclure : les lignes non cochées disparaissent de la lettre.</div>
        ${l("R2023", "IFI 2023 · déclaration rectificative")}${l("R2024", "IFI 2024 · déclaration rectificative")}${l("R2025", "IFI 2025 · déclaration rectificative")}
        ${l("COURANTE", "Déclaration IFI de l'année", true, String(an))}${l("OPTION", "Option : autre déclaration rectificative", true, "")}</div>`);
    }
    parts.push(`<div class="groupe prestations"><div class="groupe-titre">PRESTATIONS COMPLÉMENTAIRES <span class="facultatif">(facultatif)</span></div>
        <div class="presta-tete"><span>Prestation</span><span>Précision</span><span>Montant (€ ${taxe})</span><span>Unité</span><span></span><span></span></div>
        <div class="presta-lignes"></div>
        ${propositionsPresta(m, v).length ? `<div class="presta-proposees"><span>Proposer aussi :</span>${propositionsPresta(m, v).map((x, i) =>
          `<button class="btn petit" data-prop="${i}" title="${echap(x.precision || "")}">＋ ${echap(x.libelle)} <small>${echap(UNITES_PRESTA[x.unite] || "")}</small></button>`).join("")}</div>` : ""}
        <div><button class="btn petit" data-a="ajout-presta">${icone("plus")} Ajouter une autre prestation</button></div></div>
      <div class="total-honoraires"></div>`);
    const b = bloc(parts.join(""));
    const n = (k) => parseFloat(String($(`[name="${k}"]`, b)?.value || "").replace(",", ".").replace(/\s/g, "")) || 0;
    const nb = (x) => parseFloat(String(x || "").replace(",", ".").replace(/\s/g, "")) || 0;
    const FACTEUR = { mois: 12, trimestre: 4, an: 1 };
    const total = () => {
      let rec = n("forfait_mensuel") * (FACTEUR[$('[name="unite_tenue"]', b)?.value || "mois"] || 0), pon = 0;
      for (const l of $$(".presta-ligne", b)) {
        if ($('[data-c="offert"]', l).checked) continue;
        const u = $('[data-c="unite"]', l).value, x = nb($('[data-c="montant"]', l).value);
        if (FACTEUR[u]) rec += x * FACTEUR[u]; else if (u === "forfait" || u === "declaration") pon += x;
      }
      $(".total-honoraires", b).innerHTML = rec || pon ? `<b>${rec ? `${euros(rec)} ${taxe} par an` : ""}${rec && pon ? " · " : ""}${pon ? `${euros(pon)} ${taxe} ponctuel` : ""}</b>
        <span>Forfait et prestations ramenés sur un an ; prestations offertes et unités « par bien / par bulletin » non comptées.</span>` : "";
    };
    const lignePresta = (l = {}) => {
      const x = el(`<div class="presta-ligne">
        <input class="saisie" data-c="libelle" value="${echap(l.libelle || "")}" placeholder="ex. Bulletins de paie">
        <input class="saisie" data-c="precision" value="${echap(l.precision || "")}" placeholder="ex. à partir de 2027">
        <input class="saisie" data-c="montant" value="${echap(l.montant || "")}" inputmode="decimal" placeholder="0">
        <select class="saisie" data-c="unite">${Object.entries(UNITES_PRESTA).map(([k, t]) => `<option value="${k}" ${k === (l.unite || "mois") ? "selected" : ""}>${t}</option>`).join("")}</select>
        <label class="case" title="Offert la première année (le montant reste écrit dans la lettre)"><input type="checkbox" data-c="offert" ${l.offert ? "checked" : ""}>offert</label>
        <button class="btn fantome petit" title="Retirer cette prestation">${icone("fermer")}</button></div>`);
      $("button", x).onclick = () => {
        x.animate([{ opacity: 1 }, { opacity: 0, transform: "translateX(8px)" }], { duration: 160 }).onfinish = () => { x.remove(); recalculer(); majPropositions(); };
      };
      $('[data-c="libelle"]', x).addEventListener("input", majPropositions);
      $(".presta-lignes", b).append(x);
      return x;
    };
    /** Propositions déjà dans le tableau : leur bouton disparaît (il revient si la ligne est retirée). */
    const majPropositions = () => {
      const pris = new Set($$('.presta-ligne [data-c="libelle"]', b).map((i) => i.value.trim().toLowerCase()));
      $$("[data-prop]", b).forEach((bt) => bt.classList.toggle("cache", pris.has(propositionsPresta(m, v)[+bt.dataset.prop].libelle.toLowerCase())));
      const zone = $(".presta-proposees", b);
      if (zone) zone.classList.toggle("cache", $$("[data-prop]:not(.cache)", b).length === 0);
    };
    $$("[data-prop]", b).forEach((bt) => bt.onclick = () => {
      const x = lignePresta(propositionsPresta(m, v)[+bt.dataset.prop]);
      x.animate([{ opacity: 0, transform: "translateY(-6px)" }, { opacity: 1, transform: "none" }], { duration: 220, easing: "cubic-bezier(.33,1,.68,1)" });
      $('[data-c="montant"]', x).focus();
      majPropositions();
    });
    (v.prestations || []).forEach((l) => lignePresta(l));
    majPropositions();
    $('[data-a="ajout-presta"]', b).onclick = () => {
      const x = lignePresta();
      x.animate([{ opacity: 0, transform: "translateY(-6px)" }, { opacity: 1, transform: "none" }], { duration: 220, easing: "cubic-bezier(.33,1,.68,1)" });
      $("input", x).focus();
    };
    const auto = (cle, valeur) => { const x = $(`[name="${cle}"]`, b); if (x && (!x.value || x.dataset.auto)) { x.value = valeur ? String(Math.round(valeur)) : ""; x.dataset.auto = "1"; } };
    function recalculer() {
      const parAn = n("forfait_mensuel") * (FACTEUR[$('[name="unite_tenue"]', b)?.value || "mois"] || 12);
      if ($('[name="minimum"]', b)) auto("minimum", (parAn + n("forfait_comptes")) / 2);
      if ($('[name="premier_montant"]', b) && n("premier_mois")) auto("premier_montant", parAn / 12 * n("premier_mois") + n("forfait_comptes"));
      total();
    }
    b.addEventListener("change", recalculer);
    b.addEventListener("input", (e) => { if (e.target.name === "minimum" || e.target.name === "premier_montant") delete e.target.dataset.auto; recalculer(); });
    $('[name="premier_exercice"]', b)?.addEventListener("change", (e) => $(".si-premier", b).classList.toggle("cache", !e.target.checked));
    $('[name="creation_lmnp"]', b)?.addEventListener("change", (e) => $(".si-creation", b).classList.toggle("cache", !e.target.checked));
    if (!v.minimum) recalculer(); else total();
  }

  function pRecap() {
    titre("Récapitulatif", "Vérifiez avant de générer : « Modifier » revient à l'étape concernée, rien n'est perdu.");
    const mediateur = M[v.modele].public === "PERSO" || v.modele === "LMNP";
    const b = bloc(`<div class="grille2">${champ("date_lettre", "Date de la lettre", saisie("date_lettre", vu("date_lettre") || aujourdhui, 'type="date"'))}
        <div class="grille2">${champ("interlocuteur", "Interlocuteur principal", saisie("interlocuteur", vu("interlocuteur")))}${champ("fonction", "Sa fonction", saisie("fonction", vu("fonction"), 'placeholder="ex. collaborateur comptable"'))}</div></div>
      ${champ("couverture", "Assurance RCP : « Cette assurance couvre… »", saisie("couverture", vu("couverture"), 'placeholder="d’après l’attestation MMA (retenu pour les prochaines lettres)"'))}
      ${mediateur ? champ("mediateur", "Médiateur de la consommation (nom, adresse, site)", saisie("mediateur", vu("mediateur"), 'placeholder="retenu pour les prochaines lettres"')) : ""}
      <div class="recap"></div>
      ${M[v.modele].annexe ? `<div class="groupe annexe2-resume"><div style="display:flex;align-items:center;gap:10px">
          <div style="flex:1"><div class="groupe-titre">ANNEXE 2 · TABLEAU DE RÉPARTITION DES TRAVAUX</div>
            <div class="a2-etat">${Array.isArray(v.repartition) ? `<b>Modifiée</b> · ${v.repartition.length} lignes, dont ${v.repartition.filter((l) => l[2] === "Cabinet").length} prises en charge par le cabinet`
              : "Tableau du modèle, ajusté au dossier (paie, création de société)."}</div></div>
          <button class="btn petit" data-a="annexe2">Modifier l'annexe 2</button></div></div>` : ""}`);
    const manque = (x) => (x === undefined || x === null || String(x).trim() === "" ? '<span class="manque">à compléter</span>' : echap(x));
    const civ = { M: "Monsieur", MME: "Madame" }[v.civilite];
    const sections = [
      [1, "Lettre", [[M[v.modele].libelle + (v.partenaire && M[v.modele].partenaire ? " · avec partenaire" : ""), ""]]],
      [2, "Destinataire", [...(societe() ? [["Société", [v.forme, v.denomination].filter(Boolean).join(" ")]] : []),
        ["À l'attention de", civ && (v.prenom || v.nom) ? [civ, v.prenom, (v.nom || "").toUpperCase()].filter(Boolean).join(" ") + (societe() && v.qualite ? `, ${v.qualite}` : "") : ""],
        ["Adresse", v.adresse && v.cp && v.ville ? `${v.adresse}, ${v.cp} ${v.ville}` : ""]]],
      [3, "Mission", Object.entries({ activite: "Activité", salaries: "Salariés", profession: "Profession", regime_tva: "Régime TVA", periodicite: "Périodicité",
        date_debut: "À compter du", regime_bic: "Régime BIC", composition: "Foyer", annee: "Année" })
        .filter(([k]) => missionUtilise(v.modele, k, v)).map(([k, l]) => [l, k === "date_debut" ? dateFr(v[k], true) : v[k]])],
      [4, "Honoraires", resumeHonoraires(v)],
    ];
    $(".recap", b).innerHTML = sections.map(([i, t, ls]) => `<div class="groupe"><div style="display:flex;align-items:center"><div class="groupe-titre" style="flex:1">${t.toUpperCase()}</div>
        <button class="btn lien" data-aller="${i}">Modifier</button></div><dl class="lignes">${(ls.length ? ls : [["Rien à préciser", ""]]).map(([l, x]) => x === "" && i === 1 ? `<dt></dt><dd><b>${echap(l)}</b></dd>` : `<dt>${echap(l)}</dt><dd>${manque(x)}</dd>`).join("")}</dl></div>`).join("");
    $$("[data-aller]", b).forEach((x) => x.onclick = () => { lire(); etape = +x.dataset.aller; afficher(-1); });
    $('[data-a="annexe2"]', b)?.addEventListener("click", () => { lire(); editeurAnnexe2(v, () => afficher(0)); });
  }

  function pResultat() {
    corps.innerHTML = "";
    titre("Lettre prête", echap(genere.fichier.replace(/\.docx$/, "")));
    bloc(`<div style="display:flex;gap:8px;flex-wrap:wrap">
        ${genere.pdf ? `<a class="btn principal" href="/api/lettres/${genere.id}/pdf?voir=1" target="_blank" rel="noopener">${icone("livrables")} Voir le PDF</a>
          <a class="btn" href="/api/lettres/${genere.id}/pdf">Télécharger le PDF</a>` : ""}
        <a class="btn ${genere.pdf ? "fantome" : "principal"}" href="/api/lettres/${genere.id}/fichier">Word</a>
        <button class="btn" data-a="modif">Modifier encore</button><button class="btn fantome" data-a="autre">Autre lettre pour ce dossier</button></div>
      ${genere.classement ? `<div class="pdf-pret">${icone("dossier")}<div><b>Rangée dans SharePoint (bibliothèque du cabinet, synchronisée dans OneDrive)</b><br>
          <span>Dossiers › Lettres de mission › ${echap(genere.classement.dossier)} : ${echap(genere.classement.fichiers.join(", "))}</span></div></div>` : ""}
      ${genere.pdf ? `<div class="pdf-pret">${icone("livrables")}<div><b>PDF enregistré dans le CRM</b><br><span>C'est la version à faire signer : elle reste dans Livrables, avec la lettre Word.</span></div>
          <span class="en-dev" style="padding:6px 12px;margin-left:auto">Signature électronique · en développement</span></div>`
        : genere.pdf_erreur ? `<div class="alerte"><b>${echap(genere.pdf_erreur)}</b> La lettre Word est disponible.
            <div style="margin-top:8px;display:flex;gap:8px"><button class="btn petit" data-a="repdf">Réessayer</button>
              ${genere.action?.type === "reconnecter" ? '<button class="btn petit principal" data-a="boite">Connecter ma boîte</button>' : ""}</div></div>`
        : `<div class="pdf-attente"><span class="point-attente"></span><div><b>PDF en cours</b><br>
            <span>La lettre est rangée dans SharePoint › Lettres de mission, puis Microsoft la convertit en PDF.</span></div></div>`}
      ${genere.restantes.length ? `<div class="alerte"><b>${genere.restantes.length} zone(s) laissée(s) en jaune dans le document, à compléter :</b>
        ${genere.restantes.map((r) => `<div>· ${echap(r)}…</div>`).join("")}</div>` : '<div class="en-dev" style="border-style:solid;color:var(--green)">Tout est rempli : aucune zone jaune dans la lettre.</div>'}`);
    $('[data-a="modif"]', corps).onclick = () => { genere = null; etape = 5; afficher(-1); };
    const ici = genere;
    const demanderPdf = async () => {
      let r;
      try { r = await fairePdf(ici.id); } catch (e) { r = { lettre: { ...ici, pdf_erreur: e.message } }; }
      if (genere !== ici || !f.isConnected) return;
      genere = { ...ici, pdf: r.lettre.pdf, pdf_erreur: r.lettre.pdf_erreur, classement: r.lettre.classement, action: r.action, essaye: true };
      if (genere.pdf) toast(`PDF créé et rangé dans SharePoint › ${genere.classement.dossier}.`);
      if (S.page === "livrables") rendre(false);
      pResultat();
    };
    $('[data-a="repdf"]', corps)?.addEventListener("click", () => { genere = { ...ici, pdf_erreur: "", essaye: false }; pResultat(); });
    $('[data-a="boite"]', corps)?.addEventListener("click", () => { f.fermer(); aller("parametres"); });
    if (!genere.pdf && !genere.pdf_erreur && !genere.essaye) demanderPdf();
    $('[data-a="autre"]', corps).onclick = () => { genere = null; lettreId = null; v = { ...v, modele: "" }; etape = 1; vues = 1; afficher(-1); };
    prec.style.visibility = "hidden"; suiv.textContent = "Fermer"; suiv.classList.remove("cache");
  }

  prec.onclick = () => { lire(); if (etape > 0) { etape -= 1; afficher(-1); } };
  suiv.onclick = async () => {
    if (genere) { f.fermer(); if (S.page === "livrables") rendre(false); return; }
    lire();
    if (etape === 1 && !v.modele) return toast("Choisissez une lettre de mission.", { erreur: true });
    if (etape < 5) { etape += 1; vues = Math.max(vues, etape); return afficher(1); }
    try {
      suiv.disabled = true; suiv.textContent = "Création de la lettre…";
      const r = await api("POST", "/api/lettres", { valeurs: v, id: lettreId });
      genere = r.lettre; lettreId = genere.id;
      toast("Lettre de mission générée.");
      if (S.page === "livrables") rendre(false);
      pResultat();
    } catch (e) { signalerErreur(e); } finally { suiv.disabled = false; }
  };
  afficher(0);
}

/** Annexe 2 « Tableau de répartition des travaux » : qui s'en charge (cabinet / client) et comment c'est facturé, ligne
 *  par ligne. Proposé d'après le dossier (paie, création), modifiable, et « Rétablir le tableau du modèle ». */
async function editeurAnnexe2(v, apres) {
  const ref = S.annexe2 || { qui: ["Cabinet", "Client", "Non défini"], tarifs: ["Forfait comptable", "à l'acte", "au temps passé", "N/A", "Non définie"], couleurs: {} };
  const modele = (await api("POST", "/api/lettres/annexe2", v)).lignes;
  let lignes = (Array.isArray(v.repartition) ? v.repartition : modele).map((l) => [...l, true]);
  const f = fenetre({ titre: "Annexe 2 · Tableau de répartition des travaux", classe: "large",
    sous: "Pour chaque mission : qui s'en charge et comment c'est facturé. Décochez une ligne pour la retirer du tableau.",
    corps: '<div class="a2"></div>',
    pied: `<button class="btn fantome gauche" data-a="modele">Rétablir le tableau du modèle</button>
      <button class="btn fantome" data-a="non">Annuler</button><button class="btn principal" data-a="ok">Enregistrer l'annexe 2</button>` });
  const zone = $(".a2", f);
  const couleur = (val) => { const c = ref.couleurs[val]; return c ? `background:#${c[0]};color:#${c[1]}` : ""; };
  const choix = (classe, liste, val) => `<select class="a2-choix ${classe}" style="${couleur(val)}">${liste.map((x) => `<option ${x === val ? "selected" : ""}>${echap(x)}</option>`).join("")}</select>`;
  const dessiner = () => {
    const types = [...new Set(lignes.map((l) => l[0]))];
    zone.innerHTML = `<div class="a2-legende"><span>Missions</span><span>Qui s'en charge ?</span><span>Tarification</span></div>` + types.map((t) => `
      <section class="a2-groupe"><h4>${echap(t)}</h4>
        ${lignes.map((l, i) => l[0] !== t ? "" : `<div class="a2-ligne ${l[4] ? "" : "retiree"}" data-i="${i}">
          <label class="case"><input type="checkbox" data-c="garder" ${l[4] ? "checked" : ""}><input class="a2-mission" data-c="mission" value="${echap(l[1])}"></label>
          ${choix("qui", ref.qui, l[2])}${choix("tarif", ref.tarifs, l[3])}</div>`).join("")}
        <button class="btn lien a2-ajout" data-type="${echap(t)}">${icone("plus")} Ajouter une ligne</button></section>`).join("");
    $$(".a2-ligne", zone).forEach((x) => {
      const i = +x.dataset.i;
      $('[data-c="garder"]', x).onchange = (e) => { lignes[i][4] = e.target.checked; x.classList.toggle("retiree", !e.target.checked); };
      $('[data-c="mission"]', x).oninput = (e) => { lignes[i][1] = e.target.value; };
      $$(".a2-choix", x).forEach((s) => s.onchange = () => { lignes[i][s.classList.contains("qui") ? 2 : 3] = s.value; s.style.cssText = couleur(s.value); });
    });
    $$(".a2-ajout", zone).forEach((b) => b.onclick = () => {
      const t = b.dataset.type, apresDernier = lignes.map((l) => l[0]).lastIndexOf(t) + 1;
      lignes.splice(apresDernier, 0, [t, "", "Cabinet", "Forfait comptable", true]);
      dessiner(); const nouv = $$(".a2-mission", zone)[apresDernier]; nouv?.focus();
    });
  };
  dessiner();
  $('[data-a="modele"]', f).onclick = () => { lignes = modele.map((l) => [...l, true]); dessiner(); toast("Tableau du modèle rétabli."); };
  $('[data-a="non"]', f).onclick = f.fermer;
  $('[data-a="ok"]', f).onclick = () => {
    const gardees = lignes.filter((l) => l[4] && l[1].trim()).map((l) => [l[0], l[1].trim(), l[2], l[3]]);
    const pareil = JSON.stringify(gardees) === JSON.stringify(modele);
    v.repartition = pareil ? null : gardees;  // identique au modèle : on garde « le tableau du modèle » (suit le dossier)
    f.fermer(); apres?.(); toast(pareil ? "Annexe 2 : tableau du modèle." : "Annexe 2 modifiée.");
  };
}

const UNITES_PRESTA = { mois: "par mois", trimestre: "par trimestre", an: "par an", forfait: "forfait", declaration: "par déclaration", bien: "par bien", bulletin: "par bulletin" };

function missionUtilise(m, k, v) {
  const ut = { PRESENTATION: ["activite", "salaries"], ETABLISSEMENT: ["activite"], TVA: ["activite", "regime_tva", "date_debut", ...(v.regime_tva === "CA3" ? ["periodicite"] : [])],
    BNC: ["profession"], BIC: ["activite", "regime_bic"], LMNP: ["annee"], IR: ["composition", "annee"], FONCIER: ["annee"], IFI: [] };
  return (ut[m] || []).includes(k);
}

function resumeHonoraires(v) {
  const e = (x) => (String(x ?? "").trim() ? euros(parseFloat(String(x).replace(",", ".").replace(/\s/g, "")) || 0) : undefined);
  const m = v.modele, L = [];
  const unite = { mois: "par mois", trimestre: "par trimestre", an: "par an" }[v.unite_tenue || "mois"];
  if (["PRESENTATION", "BNC", "BIC"].includes(m)) {
    L.push(["Forfait tenue et fiscal", e(v.forfait_mensuel) && `${e(v.forfait_mensuel)} ${unite}`], ["Comptes annuels", e(v.forfait_comptes)], ["Minimum annuel", e(v.minimum)]);
    if (v.premier_exercice) L.push(["Premier exercice", v.premier_mois ? `${v.premier_mois} mois · ${e(v.premier_montant) || "montant à compléter"}` : undefined]);
  }
  if (m === "ETABLISSEMENT") L.push(["Forfait tenue et fiscal", e(v.forfait_mensuel) && `${e(v.forfait_mensuel)} ${unite}`], ["Établissement", e(v.forfait_etablissement)], ["Dépôt", e(v.forfait_depot)]);
  if (m === "TVA") L.push(["Par déclaration", e(v.forfait_tva)]);
  if (m === "LMNP") { if (v.creation_lmnp) L.push(["Création du statut", e(v.forfait_creation_lmnp)]); L.push([`Liasse ${v.annee || ""}`, e(v.forfait_liasse)], ["Années suivantes", e(v.forfait_suivants)]); }
  if (m === "IR") L.push(["Déclaration de revenus", e(v.forfait_ir)]);
  if (m === "FONCIER") L.push(["Par bien déclaré", e(v.forfait_bien)]);
  if (m === "IFI") {
    const noms = { R2023: "IFI 2023 rectificative", R2024: "IFI 2024 rectificative", R2025: "IFI 2025 rectificative" };
    for (const l of (v.ifi || []).filter((x) => x.retenue)) L.push([noms[l.annee] || `IFI ${l.an}${l.annee === "OPTION" ? " rectificative" : ""}`, e(l.montant)]);
    if (!L.length) L.push(["Aucune déclaration cochée", undefined]);
  }
  for (const l of v.prestations || []) {
    L.push([l.libelle || "Prestation", e(l.montant) && [e(l.montant), UNITES_PRESTA[l.unite], l.precision && `(${l.precision})`, l.offert && "· offert"].filter(Boolean).join(" ")]);
  }
  return L;
}

// ---------- Paramètres ----------
async function pageParametres(zone) {
  const p = S.etat.parametres, rel = S.etat.relances;
  const z = el(`<div class="page-scroll"><div class="parametres">
    <div class="groupe"><div class="groupe-titre">ÉQUIPE ET ALERTES</div>
      ${champ("Responsables", "Responsables (un par ligne)", zoneTexte("Responsables", p.Responsables, 'style="min-height:70px"'))}
      <div class="grille2">${champ("DelaiSansActiviteJours", "Alerte « Sans relance prévue » après (jours sans activité)", saisie("DelaiSansActiviteJours", p.DelaiSansActiviteJours, 'inputmode="numeric"'))}
        ${champ("RelanceSuivanteJours", "Prochaine relance après une relance faite (jours, 0 = aucune)", saisie("RelanceSuivanteJours", p.RelanceSuivanteJours, 'inputmode="numeric"'))}</div></div>
    <div class="groupe"><div class="groupe-titre">MAILS DE RELANCE</div>
      <div style="color:var(--muted)">Un modèle par étape du pipeline. « Bonjour Monsieur NOM » et « Bien à vous » sont ajoutés automatiquement.</div>
      ${R().etapes.map(([e, l]) => `<div class="champ"><label>${echap(l)}</label>
        <input class="saisie" data-rel="${e}" data-k="0" value="${echap(rel[e][0])}">
        <textarea class="saisie" data-rel="${e}" data-k="1" style="min-height:110px">${echap(rel[e][1])}</textarea>
        <div><button class="btn lien" data-std="${e}">Revenir au texte standard</button></div></div>`).join("")}
    </div>
    <div><button class="btn principal" data-a="enreg">Enregistrer les paramètres</button></div>
    <div class="groupe" id="bloc-outlook"></div>
    <div class="groupe" id="bloc-pennylane"></div>
    <div class="groupe"><div class="groupe-titre">OUVERTURE DES DOSSIERS CLIENTS · CE PC</div>
      <div>« Ouvrir le dossier » (page Clients) ouvre le dossier du client dans l'Explorateur, sans rien installer.
        Sur le PC où tourne le CRM, c'est automatique. Sur un autre PC, indiquez une fois ci-dessous le dossier comptable de ce PC :
        ouvrez-le dans l'Explorateur, cliquez dans la barre d'adresse, copiez, collez ici. Sans lui, le dossier s'ouvre dans SharePoint.</div>
      <div class="champ"><label>Dossier comptable sur ce PC</label>
        <div class="montant-unite"><input class="saisie" name="chemin_local" placeholder="C:\\Users\\…\\OneDrive - Cabinet\\Dossiers\\Comptabilite">
          <button class="btn" data-a="chemin">Enregistrer</button></div>
        <small class="aide">Gardé dans ce navigateur seulement (chaque PC a le sien). La première fois, le navigateur demande d'ouvrir l'Explorateur : cochez « Toujours autoriser ».</small></div></div>
    <div class="groupe" id="bloc-securite"></div>
    <div class="groupe"><div class="groupe-titre">MOT DE PASSE · ${echap(S.moi.nom.toUpperCase())}</div>
      <div class="grille2">${champ("ancien", "Mot de passe actuel", saisie("ancien", "", 'type="password" autocomplete="current-password"'))}
        ${champ("nouveau", "Nouveau mot de passe", saisie("nouveau", "", 'type="password" autocomplete="new-password"'))}</div>
      <small class="aide">12 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial, ou 14 caractères sans caractère spécial
        (recommandation de la CNIL). Changer le mot de passe ferme vos sessions sur les autres navigateurs.</small>
      <div><button class="btn" data-a="mdp">Changer mon mot de passe</button></div></div>
  </div></div>`);
  zone.append(z);
  sectionOutlook($("#bloc-outlook", z));
  sectionSecurite($("#bloc-securite", z));
  sectionPennylane($("#bloc-pennylane", z));
  const chemin = $('[name="chemin_local"]', z);
  chemin.value = cheminLocal();
  $('[data-a="chemin"]', z).onclick = () => {
    const v = chemin.value.trim().replace(/^"|"$/g, "");
    if (v && !/^[a-zA-Z]:\\/.test(v)) return toast("Collez un chemin Windows complet, qui commence par C:\\", { erreur: true });
    try { v ? localStorage.setItem(CLE_CHEMIN, v) : localStorage.removeItem(CLE_CHEMIN); } catch { return toast("Ce navigateur ne garde pas ce réglage (navigation privée ?).", { erreur: true }); }
    chemin.value = v; toast(v ? "Chemin enregistré pour ce PC." : "Chemin effacé : les dossiers s'ouvriront dans SharePoint.");
  };
  $$("[data-std]", z).forEach((b) => b.onclick = async () => {
    const std = await api("GET", "/api/relances-standard");
    $$(`[data-rel="${b.dataset.std}"]`, z).forEach((x) => { x.value = std[b.dataset.std][x.dataset.k]; });
  });
  $('[data-a="enreg"]', z).onclick = async () => {
    const d = { Responsables: $('[name="Responsables"]', z).value, DelaiSansActiviteJours: $('[name="DelaiSansActiviteJours"]', z).value,
      RelanceSuivanteJours: $('[name="RelanceSuivanteJours"]', z).value, relances: {} };
    for (const [e] of R().etapes) d.relances[e] = [$(`[data-rel="${e}"][data-k="0"]`, z).value, $(`[data-rel="${e}"][data-k="1"]`, z).value];
    try { const r = await api("POST", "/api/parametres", d); S.etat.parametres = r.parametres; S.etat.relances = r.relances; erreursChamps(z, {}); toast("Paramètres enregistrés."); }
    catch (e) { erreursChamps(z, e.champs); }
  };
  $('[data-a="mdp"]', z).onclick = async () => {
    try {
      await api("POST", "/api/mot-de-passe", { ancien: $('[name="ancien"]', z).value, nouveau: $('[name="nouveau"]', z).value });
      $('[name="ancien"]', z).value = $('[name="nouveau"]', z).value = ""; erreursChamps(z, {}); toast("Mot de passe changé.");
    } catch (e) { erreursChamps(z, e.champs); if (!e.champs) signalerErreur(e); }
  };
}

// ---------- Messagerie Outlook (chacun connecte SA boîte, par code Microsoft) ----------
async function etatOutlook() {
  S.outlook = await api("GET", "/api/outlook");
  return S.outlook;
}

/** Ce qu'il faut faire pour régler un souci de mise en place (lien direct + étapes), affiché tel quel à l'écran. */
function htmlAction(action) {
  if (!action || !action.etapes) return "";
  return `<ol class="etapes-action">${action.etapes.map((e) => `<li>${echap(e)}</li>`).join("")}</ol>
    ${action.lien ? `<a class="btn principal petit" href="${echap(action.lien)}" target="_blank" rel="noopener">${action.type === "entra" ? "Ouvrir le réglage Microsoft" : "Ouvrir la page d'autorisation"} ↗</a>` : ""}`;
}

/** Connexion par code : le CRM affiche un code, la personne le tape sur la page Microsoft (son navigateur, son poste). */
async function connecterOutlook(apres) {
  let r;
  try { r = await api("POST", "/api/outlook/connexion"); }
  catch (e) {
    const f = fenetre({ titre: "Connexion Outlook impossible pour l'instant", classe: "moyenne",
      corps: `<div class="alerte">${echap(e.message)}</div>${htmlAction(e.action || (await api("POST", "/api/outlook/verifier")).action)}` });
    return f;
  }
  const f = r.mode === "navigateur" ? fenetre({ titre: "Connecter ma boîte Outlook", sous: "Une seule fois : le CRM restera connecté à votre boîte. Tout compte Microsoft convient (cabinet ou personnel).", classe: "moyenne",
    corps: `<div class="code-outlook">
        <ol class="etapes-action"><li>Cliquez sur « Se connecter chez Microsoft » : la page s'ouvre dans un nouvel onglet.</li>
          <li>Choisissez votre compte, puis acceptez : le CRM pourra lire et envoyer vos mails avec les prospects. Rien d'autre.</li>
          ${r.automatique ? "<li>Vous revenez automatiquement dans le CRM : c'est terminé.</li>" : `<li>La page de retour affiche « Ce site est inaccessible » : c'est normal.
            Copiez son adresse (barre d'adresse, elle commence par http://localhost) et collez-la ci-dessous.</li>`}</ol>
        <div style="display:flex;justify-content:center"><a class="btn principal" href="${echap(r.url)}" target="_blank" rel="noopener">Se connecter chez Microsoft ↗</a></div>
        <div class="montant-unite coller ${r.automatique ? "cache" : ""}"><input class="saisie" name="retour" placeholder="Collez ici l'adresse http://localhost/?code=…"><button class="btn" data-a="valider">Valider</button></div>
        <div class="attente"><span class="point-attente"></span> En attente de votre connexion sur la page Microsoft…</div></div>` })
  : fenetre({ titre: "Connecter ma boîte Outlook", sous: "Une seule fois : le CRM restera connecté à votre boîte.", classe: "moyenne",
    corps: `<div class="code-outlook">
        <ol class="etapes-action"><li>Ouvrez la page Microsoft (bouton ci-dessous).</li><li>Tapez ce code, puis connectez-vous avec <b>votre</b> adresse du cabinet.</li>
          <li>Acceptez : le CRM pourra lire et envoyer vos mails avec les prospects. Rien d'autre.</li></ol>
        <div class="code" title="Cliquer pour copier">${echap(r.code)}</div>
        <div style="display:flex;gap:8px;justify-content:center"><a class="btn principal" href="${echap(r.url)}" target="_blank" rel="noopener">Ouvrir la page Microsoft ↗</a>
          <button class="btn" data-a="copier">Copier le code</button></div>
        <div class="attente"><span class="point-attente"></span> En attente de votre connexion sur la page Microsoft…</div></div>` });
  const copier = async () => { try { await navigator.clipboard.writeText(r.code); toast("Code copié."); } catch { /* sans presse-papiers */ } };
  if (r.mode === "navigateur") {
    const valider = async () => {
      const adresse = $('[name="retour"]', f).value.trim();
      if (!adresse) return;
      try { await api("POST", "/api/outlook/connexion/retour", { adresse }); }
      catch (e) { toast(e.message, { erreur: true }); }
    };
    $('[data-a="valider"]', f).onclick = valider;
    $('[name="retour"]', f).addEventListener("paste", () => setTimeout(valider, 50));  // collé : validé aussitôt
  } else { $('[data-a="copier"]', f).onclick = copier; $(".code", f).onclick = copier; }
  const suivre = setInterval(async () => {
    if (!f.isConnected) return clearInterval(suivre);
    let e;
    try { e = await etatOutlook(); } catch { return; }
    const cx = e.connexion || {};
    if (cx.etat === "connecte" || e.adresse) {
      clearInterval(suivre);
      $(".f-corps", f).innerHTML = `<div class="succes-outlook">${icone("mail")}<b>Boîte connectée</b><span>${echap(e.adresse)}</span></div>`;
      setTimeout(() => { f.fermer(); apres?.(); }, 1400);
    } else if (cx.etat === "erreur") {
      clearInterval(suivre);
      $(".attente", f).outerHTML = `<div class="alerte">${echap(cx.message)}</div>${htmlAction(cx.action)}
        <div><button class="btn" data-a="encore">Recommencer</button></div>`;
      $('[data-a="encore"]', f).onclick = () => { f.fermer(); connecterOutlook(apres); };
    }
  }, 2500);
  return f;
}

/** Paramètres › Pennylane : jeton du cabinet, et dossiers ignorés par la synchro (à remettre). */
async function sectionPennylane(bloc) {
  let e;
  try { e = await api("GET", "/api/pennylane"); } catch (err) { bloc.innerHTML = `<div class="alerte">${echap(err.message)}</div>`; return; }
  bloc.innerHTML = `<div class="groupe-titre">PENNYLANE</div>
    <div class="ligne-outlook ${e.configure ? "ok" : ""}">${icone("actualiser")}<div><b>${e.configure ? "Pennylane relié" : "Pennylane pas encore relié"}</b><br>
      <span>${e.configure ? `Jeton du cabinet en lecture seule, enregistré par ${echap(e.par)} le ${instantFr(e.le)}.${e.synchro ? ` Dernière synchro : ${instantFr(e.synchro.le)}.` : ""}`
        : "Collez le jeton du cabinet (lecture des dossiers) pour le bouton « Synchro Pennylane » de la page Clients."}</span></div>
      <button class="btn petit" data-a="jeton">${e.configure ? "Changer le jeton" : "Relier Pennylane"}</button></div>
    <div class="champ"><label>Dossiers Pennylane ignorés par la synchro (${e.ignores.length})</label>
      ${e.ignores.length ? `<div class="synchro-liste">${e.ignores.map((x) => `<div class="synchro-ligne"><span>${echap(x.nom || x.id)}</span>
        <button class="btn petit fantome" data-remettre="${echap(x.id)}">Ne plus ignorer</button></div>`).join("")}</div>`
        : '<div style="color:var(--muted)">Aucun : tous les dossiers de Pennylane sont proposés.</div>'}
      <small class="aide">Un dossier remis sera de nouveau proposé à la prochaine « Synchro Pennylane ».</small></div>`;
  $('[data-a="jeton"]', bloc).onclick = () => fenetreJetonPennylane(() => sectionPennylane(bloc));
  $$("[data-remettre]", bloc).forEach((b) => b.onclick = async () => {
    try { await api("POST", "/api/pennylane/ignores", { id: b.dataset.remettre, ignorer: false }); toast("Dossier de nouveau proposé à la synchro."); sectionPennylane(bloc); }
    catch (err) { signalerErreur(err); }
  });
}

// ---------- Sécurité : compte Microsoft (Entra ID, Authenticator), mode de connexion, dernières connexions ----------
const LIBELLES_SECURITE = {
  connexion: "Connexion", connexion_refusee: "Connexion refusée", connexion_bloquee: "Trop d'essais : bloqué", deconnexion: "Déconnexion",
  mot_de_passe_change: "Mot de passe changé", microsoft_lie: "Compte Microsoft lié", microsoft_delie: "Compte Microsoft délié",
  liaison_refusee: "Liaison refusée", mode_connexion: "Mode de connexion changé", sessions_fermees: "Autres sessions fermées",
};
const dateHeure = (iso) => new Date(iso).toLocaleString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

async function sectionSecurite(bloc) {
  const titre = `<div class="groupe-titre">SÉCURITÉ DE LA CONNEXION · ${echap(S.moi.nom.toUpperCase())}</div>`;
  bloc.innerHTML = `${titre}<div style="color:var(--muted)">Chargement…</div>`;
  let s;
  try { s = await api("GET", "/api/securite"); } catch (e) { bloc.innerHTML = `${titre}<div class="alerte">${echap(e.message)}</div>`; return; }
  const ses = s.session;
  const sansLien = s.comptes.filter((c) => !c.lie).map((c) => c.nom);
  const moyen = (l) => l.methode === "microsoft" ? `Microsoft${l.mfa === true ? " + Authenticator" : l.mfa === false ? " (sans double auth.)" : ""}`
    : l.methode === "mot_de_passe" ? "Mot de passe" : "";
  bloc.innerHTML = `${titre}
    <div class="ligne-outlook ${s.microsoft ? "ok" : ""}">${LOGO_MICROSOFT}<div>
      ${s.microsoft ? `<b>Compte Microsoft lié : ${echap(s.microsoft.adresse)}</b><br><span>Vous vous connectez au CRM avec Microsoft, et votre boîte Outlook est connectée du même coup.</span>`
        : `<b>Compte Microsoft non lié</b><br><span>Liez une fois votre compte du cabinet : vous vous connecterez ensuite avec Microsoft et Microsoft Authenticator, sans mot de passe du CRM.</span>`}</div>
      ${s.microsoft ? `<button class="btn petit fantome danger" data-a="delier">Délier</button>`
        : s.disponible ? `<a class="btn principal petit" href="/api/microsoft/lier">Lier mon compte Microsoft</a>` : ""}</div>
    ${!s.disponible ? `<div class="alerte">Connexion Microsoft pas encore possible sur cette adresse : un administrateur ajoute l'adresse du CRM dans Microsoft Entra,
      puis on coche « Retour automatique » dans Messagerie Outlook › Réglages avancés (voir le README).</div>` : ""}
    <div class="session-actuelle">${icone(ses.mfa ? "bouclierOk" : "bouclier")}<span>Cette session : <b>${echap(moyen(ses) || "Mot de passe")}</b>.
      Fermée après ${Math.round(s.duree.inactivite / 3600)} h sans activité, et au plus après ${Math.round(s.duree.max / 3600)} h.</span></div>
    <div class="champ"><label>Mode de connexion du cabinet (tous les comptes)</label>
      <div class="choix-mode">${Object.entries(s.modes).map(([k, l]) => `<label class="case"><input type="radio" name="mode" value="${k}" ${s.mode === k ? "checked" : ""}>
        <span><b>${echap(l)}</b>${k === "" ? " · le mot de passe reste possible"
          : k === "microsoft" ? " · plus de mot de passe du CRM"
          : " · recommandé : Microsoft Authenticator obligatoire à chaque connexion"}</span></label>`).join("")}</div>
      <small class="aide">${sansLien.length ? `${echap(sansLien.join(" et "))} : lié tout seul à sa première connexion avec son adresse du cabinet.`
        : "Tous les comptes sont liés."} En cas de souci avec Microsoft : sur le serveur, data/parametres.json › supprimer la ligne « ModeConnexion ».</small>
      <div class="msg-erreur" data-err="mode"></div></div>
    <div class="champ"><label>Dernières connexions à votre compte</label>
      ${s.journal.length ? `<table class="journal-securite"><thead><tr><th>Date</th><th>Événement</th><th>Moyen</th><th>Navigateur</th><th>Adresse IP</th></tr></thead><tbody>
        ${s.journal.map((l) => `<tr class="${l.evt.includes("refus") || l.evt.includes("bloq") ? "refus" : ""}"><td>${dateHeure(l.t)}</td>
          <td>${echap(LIBELLES_SECURITE[l.evt] || l.evt)}${l.motif ? ` <span class="muted">(${echap(l.motif)})</span>` : ""}</td>
          <td>${echap(moyen(l))}</td><td>${echap(l.nav || "")}</td><td>${echap(l.ip || "")}</td></tr>`).join("")}</tbody></table>`
        : `<div style="color:var(--muted)">Rien pour l'instant.</div>`}
      <small class="aide">Une connexion que vous ne reconnaissez pas ? Changez votre mot de passe et fermez les autres sessions. Journal gardé 12 mois.</small>
      <div><button class="btn petit" data-a="fermer">Fermer mes sessions sur les autres navigateurs</button></div></div>`;
  $('[data-a="delier"]', bloc)?.addEventListener("click", async () => {
    if (!confirm("Délier votre compte Microsoft ?\nVous vous connecterez de nouveau avec votre mot de passe du CRM.")) return;
    try { await api("POST", "/api/securite/delier"); toast("Compte Microsoft délié."); sectionSecurite(bloc); }
    catch (e) { toast(e.champs?.microsoft || e.message, { erreur: true, duree: 7000 }); }
  });
  $$('[name="mode"]', bloc).forEach((r) => r.addEventListener("change", async () => {
    try { await api("POST", "/api/securite/mode", { mode: r.value }); toast("Mode de connexion enregistré."); sectionSecurite(bloc); }
    catch (e) { $('[data-err="mode"]', bloc).textContent = e.champs?.mode || e.message; $$('[name="mode"]', bloc).forEach((x) => { x.checked = x.value === s.mode; }); }
  }));
  $('[data-a="fermer"]', bloc).onclick = async () => {
    try { await api("POST", "/api/securite/fermer-sessions"); toast("Vos autres sessions sont fermées."); sectionSecurite(bloc); } catch (e) { signalerErreur(e); }
  };
}

/** Paramètres › Messagerie Outlook : état de la boîte, vérification de la configuration Microsoft, connexion. */
async function sectionOutlook(bloc) {
  bloc.innerHTML = `<div class="groupe-titre">MESSAGERIE OUTLOOK · ${echap(S.moi.nom.toUpperCase())}</div><div style="color:var(--muted)">Vérification…</div>`;
  let e;
  try { e = await etatOutlook(); } catch (err) { bloc.innerHTML += `<div class="alerte">${echap(err.message)}</div>`; return; }
  const corps = e.adresse
    ? `<div class="ligne-outlook ok">${icone("mail")}<div><b>Connectée : ${echap(e.adresse)}</b><br><span>Vos mails avec les prospects apparaissent dans leur fiche, onglet « Échanges ».</span></div>
        <button class="btn petit fantome danger" data-a="deconnecter">Déconnecter</button></div>`
    : `<div class="ligne-outlook">${icone("mail")}<div><b>Boîte non connectée</b><br><span>Connectez votre boîte du cabinet pour voir et envoyer les mails des prospects depuis le CRM.</span></div>
        <button class="btn principal petit" data-a="connecter">Connecter ma boîte</button></div>`;
  bloc.innerHTML = `<div class="groupe-titre">MESSAGERIE OUTLOOK · ${echap(S.moi.nom.toUpperCase())}</div>${corps}
    ${e.adresse && "Notification" in window ? `<label class="case"><input type="checkbox" name="notifications" ${Notification.permission === "granted" ? "checked" : ""}>
      Notification Windows quand un prospect m'écrit, même si le CRM est en arrière-plan</label>` : ""}
    <div class="champ"><label>Ma signature, en bas de mes mails envoyés depuis le CRM</label>
      <div class="sig-editeur"></div>
      <small class="aide">Votre signature d'Outlook, avec sa mise en forme et son logo. Chacun la sienne : celle de ${echap(S.moi.nom)} ici.
        Sur un autre PC ou en ligne : « Choisir dans mes signatures Outlook », puis tapez <b>%APPDATA%\Microsoft\Signatures</b> dans la barre d'adresse
        de la fenêtre et validez « Importer » : toutes vos signatures s'affichent, vous choisissez.</small>
      <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="btn petit" data-a="signature">Enregistrer ma signature</button>
        <button class="btn petit fantome" data-a="sig-outlook">${icone("actualiser")} Reprendre ma signature Outlook</button>
        <button class="btn petit fantome" data-a="sig-dossier">${icone("dossier")} Choisir dans mes signatures Outlook</button>
        <label class="btn petit fantome">Importer un fichier (.htm)<input type="file" accept=".htm,.html" multiple data-a="sig-fichier" hidden></label></div></div>
    <div class="verif"></div>
    <details class="avance"><summary>Réglages avancés</summary>
      <div class="champ"><label>Identifiant de l'application Microsoft « CRM Cabinet »</label>
        <div class="montant-unite"><input class="saisie" name="MessagerieClientId" value="${echap(e.client_id)}"><button class="btn" data-a="verifier">Vérifier</button></div>
        <small class="aide">Déjà rempli. À changer seulement si l'application est recréée dans Microsoft Entra.</small></div>
      <label class="case"><input type="checkbox" name="MessagerieRetourCRM" ${e.retour_crm ? "checked" : ""}>Retour automatique dans le CRM après la connexion Microsoft (nécessaire pour « Se connecter avec Microsoft »)</label>
      <small class="aide">À cocher une fois l'adresse <b>${echap(e.adresse_crm)}</b> ajoutée par un administrateur dans Microsoft Entra :
        application « CRM Cabinet » › Authentification › Applications mobiles et de bureau › URI de redirection. Sans elle, on colle l'adresse de retour.
        En local (localhost), le retour est toujours automatique.</small></details>`;
  S.signature = null;
  let actuelle = { html: "" };
  try { actuelle = await api("GET", "/api/signature"); } catch { /* sans signature */ }
  S.signature = actuelle.personnalisee ? actuelle.html : null;
  const sig = editeurTexte(actuelle.html, "Votre signature…");
  $(".sig-editeur", bloc).append(sig.el);
  $('[data-a="signature"]', bloc).onclick = async () => {
    try { S.signature = (await api("POST", "/api/signature", { html: sig.html() })).html; toast("Signature enregistrée."); } catch (err) { signalerErreur(err); }
  };
  const reprise = async (html) => { sig.zone.innerHTML = html; if (await proposerSignature(html)) sig.zone.innerHTML = S.signature; };
  $('[data-a="sig-outlook"]', bloc).onclick = async (ev) => {
    const bt = ev.currentTarget; bt.disabled = true;
    try {
      const locale = await signatureOutlook(false);  // Outlook de ce PC (choix s'il y en a plusieurs)
      if (locale) await reprise(locale);
      else await reprise((await api("POST", "/api/signature/outlook")).html);  // sinon : dans le dernier mail envoyé
    } catch (err) { toast(err.message, { erreur: true, duree: 8000 }); }
    bt.disabled = false;
  };
  const depuisFichiers = async (liste) => {
    if (!liste.length) return toast("Aucune signature trouvée dans ces fichiers.", { erreur: true });
    const choisie = liste.length === 1 ? liste[0] : await choisirSignature(liste);
    if (choisie) await reprise(choisie.html);
  };
  $('[data-a="sig-dossier"]', bloc).onclick = async () => depuisFichiers(await choisirDossierSignatures());
  $('[data-a="sig-fichier"]', bloc).onchange = async (ev) => { await depuisFichiers(await signaturesDesFichiers(ev.target.files)); ev.target.value = ""; };
  const verif = $(".verif", bloc);
  const verifier = async () => {
    verif.innerHTML = '<div style="color:var(--muted)">Vérification de la configuration Microsoft…</div>';
    const v = await api("POST", "/api/outlook/verifier");
    verif.innerHTML = v.ok ? (e.adresse ? "" : '<div class="succes-ligne">Prêt : chacun peut connecter sa boîte, avec n\u2019importe quel compte Microsoft.</div>')
      : `<div class="alerte"><b>${echap(v.erreur)}</b></div>${htmlAction(v.action)}`;
  };
  $('[data-a="connecter"]', bloc)?.addEventListener("click", () => connecterOutlook(() => sectionOutlook(bloc)));
  $('[data-a="deconnecter"]', bloc)?.addEventListener("click", () => deconnecterOutlook(() => sectionOutlook(bloc)));
  $('[name="notifications"]', bloc)?.addEventListener("change", async (ev) => {
    if (!ev.target.checked) { toast("Pour couper les notifications : réglages du site dans le navigateur (cadenas à gauche de l'adresse)."); ev.target.checked = Notification.permission === "granted"; return; }
    const r = await Notification.requestPermission();
    ev.target.checked = r === "granted";
    toast(r === "granted" ? "Notifications activées." : "Le navigateur a refusé les notifications (réglages du site, cadenas à gauche de l'adresse).", { erreur: r !== "granted" });
  });
  $('[name="MessagerieRetourCRM"]', bloc).onchange = (ev) => api("POST", "/api/parametres", { MessagerieRetourCRM: ev.target.checked ? "1" : "" })
    .then(() => toast(ev.target.checked ? "Retour automatique activé." : "Retour automatique désactivé.")).catch(signalerErreur);
  $('[data-a="verifier"]', bloc).onclick = async () => {
    await api("POST", "/api/parametres", { MessagerieClientId: $('[name="MessagerieClientId"]', bloc).value }).catch(signalerErreur);
    verifier();
  };
  if (!e.adresse) verifier();
}

/** Heure courte façon messagerie : « 14:20 » aujourd'hui, « hier », « 28 oct. », « 28/10/2025 » les autres années. */
function quandCourt(iso) {
  if (!iso) return "";
  const d = new Date(iso), now = new Date(), jour = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate());
  const ecart = Math.round((jour(now) - jour(d)) / 864e5);
  if (ecart === 0) return d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
  if (ecart === 1) return "hier";
  if (d.getFullYear() === now.getFullYear()) return d.toLocaleDateString("fr-FR", { day: "numeric", month: "short" });
  return d.toLocaleDateString("fr-FR");
}
function quandLong(iso) {
  return iso ? new Date(iso).toLocaleString("fr-FR", { weekday: "long", day: "numeric", month: "long", hour: "2-digit", minute: "2-digit" }) : "";
}
const nomDe = (m) => (m.entrant ? m.de.nom || m.de.adresse : "Vous");
const correspondant = (f) => { const m = [...f.mails].reverse().find((x) => x.entrant) || f.mails[0]; return m.entrant ? m.de.nom || m.de.adresse : (m.a[0] || {}).nom || (m.a[0] || {}).adresse || ""; };

/** Fiche prospect › Échanges : la messagerie du prospect (boîte de la personne connectée), façon messagerie moderne. */
async function ongletEchanges(zone, p, frais = false, garderFil = null) {
  if (!zone.querySelector(".mx")) {
    zone.innerHTML = `<div class="mx"><aside class="mx-liste">${'<div class="mx-squelette"><i></i><i></i><i></i></div>'.repeat(4)}</aside>
      <section class="mx-lecture"><div class="mx-vide"></div></section></div>`;
  }
  let d;
  const q = [frais ? "frais=1" : "", p._avec ? `avec=${p._avec}` : ""].filter(Boolean).join("&");
  try { d = await api("GET", `/api/prospects/${p.id}/mails${q ? `?${q}` : ""}`); }
  catch (e) {
    zone.innerHTML = `<div class="mx-etat">${icone("mail")}<b>${echap(e.message)}</b>${htmlAction(e.action)}
      ${e.action?.type === "reconnecter" ? '<button class="btn principal" data-a="cx">Reconnecter ma boîte</button>' : ""}</div>`;
    $('[data-a="cx"]', zone)?.addEventListener("click", () => connecterOutlook(() => ongletEchanges(zone, p, true)));
    return;
  }
  if (!d.connecte) {
    zone.innerHTML = `<div class="mx-etat">${icone("mail")}<b>Connectez votre boîte Outlook</b>
      <span>Vos mails avec ${echap(p.denomination)} s'afficheront ici, et vous répondrez sans quitter le CRM.</span>
      <button class="btn principal" data-a="cx">Connecter ma boîte</button></div>`;
    $('[data-a="cx"]', zone).onclick = () => connecterOutlook(() => ongletEchanges(zone, p, true));
    return;
  }
  const contacts = (p.contacts || []).filter((c) => c.email);
  zone.innerHTML = `<div class="mx">
      <aside class="mx-liste">
        <div class="mx-outils"><label class="mx-cherche">${icone("recherche")}<input placeholder="Rechercher"></label>
          <button class="mx-rond" data-a="actualiser" title="Relire la boîte">${icone("actualiser")}</button>
          <button class="mx-rond noir" data-a="nouveau" title="Nouveau mail" ${contacts.length ? "" : "disabled"}>${icone("plus")}</button></div>
        <div class="mx-fils"></div>
        ${nbArchivesDe(d) || zone.voirArchives ? `<button class="mx-voir-archives">${icone("archive")}${zone.voirArchives ? "Revenir aux conversations" : `Fils archivés (${nbArchivesDe(d)})`}</button>` : ""}
        <button class="mx-compte" title="Changer de compte ou déconnecter">${icone("mail")}<span>${echap(d.adresse)}</span>${icone("points")}</button>
      </aside>
      <section class="mx-lecture"></section></div>`;
  const fils = $(".mx-fils", zone), lecture = $(".mx-lecture", zone);
  zone.dataset.moi = d.adresse;
  const vide = () => {
    lecture.innerHTML = d.fils.length
      ? `<div class="mx-vide">${icone("mail")}<b>Choisissez une conversation</b><span>ou écrivez un nouveau mail.</span></div>`
      : `<div class="mx-vide">${icone("mail")}<b>Aucun échange depuis un an</b><span>${contacts.length ? `avec ${echap(contacts.map((c) => c.email).join(", "))}`
          : p._avec ? "Indiquez l'e-mail de l'ancien cabinet ci-dessus." : "Ajoutez l'e-mail d'un contact (onglet Contacts)."}</span>
          ${contacts.length ? '<button class="btn principal" data-a="ecrire">Écrire un mail</button>' : ""}</div>`;
    $('[data-a="ecrire"]', lecture)?.addEventListener("click", () => composer(zone, p, null));
  };
  const nbArchives = d.fils.filter((x) => x.archive).length;
  const dessiner = (q = "") => {
    const ls = d.fils.filter((f) => !!f.archive === !!zone.voirArchives)
      .filter((f) => !q || `${f.objet} ${correspondant(f)} ${f.mails.map((m) => m.apercu).join(" ")}`.toLowerCase().includes(q));
    fils.innerHTML = ls.map((f) => { const der = f.mails[f.mails.length - 1]; return `<button class="mx-fil ${f.non_lus ? "non-lu" : ""}" data-fil="${echap(f.id)}">
        ${avatar(correspondant(f), "mx-av")}
        <span class="mx-fil-txt"><span class="mx-l1"><b>${echap(correspondant(f))}</b>${f.mails.length > 1 ? `<em>${f.mails.length}</em>` : ""}${f.mails.some((m) => m.pj) ? icone("trombone", "mx-tr") : ""}<time>${quandCourt(f.dernier)}</time></span>
          <span class="mx-l2">${echap(f.objet)}</span>
          <span class="mx-l3">${der.entrant ? "" : "Vous : "}${echap(der.apercu)}</span></span>
        ${f.non_lus ? '<i class="mx-point"></i>' : ""}
        <span class="mx-archiver" role="button" data-archiver="${echap(f.id)}" title="${f.archive ? "Désarchiver" : "Archiver ce fil (il disparaît du CRM, rien ne change dans Outlook)"}">${icone("archive")}</span></button>`; }).join("")
      || `<div class="mx-rien">${q ? "Aucun résultat." : zone.voirArchives ? "Aucun fil archivé." : "Aucune conversation."}</div>`;
    $$("[data-archiver]", fils).forEach((x) => x.onclick = (ev) => { ev.stopPropagation(); archiverFil(zone, p, d.fils.find((f) => f.id === x.dataset.archiver)); });
    $$("[data-fil]", fils).forEach((b) => b.onclick = () => {
      $$("[data-fil]", fils).forEach((x) => x.classList.toggle("choisi", x === b));
      lireFil(zone, p, d.fils.find((f) => f.id === b.dataset.fil), b);
    });
  };
  dessiner();
  void nbArchives;
  $(".mx-voir-archives", zone)?.addEventListener("click", () => { zone.voirArchives = !zone.voirArchives; ongletEchanges(zone, p); });
  $(".mx-cherche input", zone).oninput = (e) => dessiner(e.target.value.trim().toLowerCase());
  $(".mx-compte", zone).onclick = (ev) => menu(ev.currentTarget, [
    [`Connectée : ${d.adresse}`, null],
    ["Changer de compte", async () => { if (await deconnecterOutlook()) connecterOutlook(() => ongletEchanges(zone, p, true)); }],
    ["Déconnecter ma boîte", () => deconnecterOutlook(() => ongletEchanges(zone, p))],
  ]);
  $('[data-a="actualiser"]', zone).onclick = (e) => { e.currentTarget.classList.add("tourne"); ongletEchanges(zone, p, true); };
  $('[data-a="nouveau"]', zone).onclick = () => { $$("[data-fil]", fils).forEach((x) => x.classList.remove("choisi")); composer(zone, p, null); };
  const choix = garderFil && $(`[data-fil="${CSS.escape(garderFil)}"]`, fils);
  if (S.aEcrire?.pid === p.id + (p._avec ? `:${p._avec}` : "")) { const o = S.aEcrire.o; S.aEcrire = null; return composer(zone, p, null, o); }
  if (choix) choix.click(); else vide();
}

const nbArchivesDe = (d) => d.fils.filter((x) => x.archive).length;

/** Archive (ou désarchive) un fil indésirable : il quitte la liste du CRM et ne compte plus dans les nouveaux messages. */
async function archiverFil(zone, p, fil) {
  try {
    await api("POST", "/api/outlook/archiver", { fil: fil.id, archive: !fil.archive });
    toast(fil.archive ? "Fil remis dans les conversations." : "Fil archivé : il n'apparaît plus ici (rien ne change dans Outlook).",
      fil.archive ? {} : { action: "Annuler", auClic: async () => { await api("POST", "/api/outlook/archiver", { fil: fil.id, archive: false }); ongletEchanges(zone, p, true); verifierMessages(true); } });
    ongletEchanges(zone, p, true);
    verifierMessages(true);
  } catch (e) { signalerErreur(e); }
}

async function lireFil(zone, p, fil, bouton) {
  const lecture = $(".mx-lecture", zone);
  const moi = zone.dataset.moi || "";  // les participants, sans soi-même
  const gens = [...new Set(fil.mails.flatMap((m) => [m.de, ...m.a, ...m.cc]).filter((x) => x.adresse && x.adresse !== moi).map((x) => x.nom || x.adresse))];
  const avecPj = fil.mails.filter((m) => m.pj).length;
  const lien = [...fil.mails].reverse().find((m) => m.lien)?.lien;
  lecture.innerHTML = `<header class="mx-tete">
      <div class="mx-tete-l1"><h3>${echap(fil.objet)}</h3>
        <div class="mx-actions">${fil.mails.length > 1 ? '<button class="btn fantome petit" data-a="tout">Tout déplier</button>' : ""}
          <button class="btn fantome petit" data-a="archiver">${icone("archive")} ${fil.archive ? "Désarchiver" : "Archiver"}</button>
          ${lien ? `<a class="btn fantome petit" href="${echap(lien)}" target="_blank" rel="noopener">Ouvrir dans Outlook ↗</a>` : ""}</div></div>
      <div class="mx-gens">${gens.slice(0, 5).map((g) => `<span>${echap(g)}</span>`).join("")}${gens.length > 5 ? `<span>+${gens.length - 5}</span>` : ""}
        <span class="mx-compteur">${fil.mails.length} message${fil.mails.length > 1 ? "s" : ""}${avecPj ? ` · ${icone("trombone")} ${avecPj} avec pièce jointe` : ""}</span></div></header>
    <div class="mx-mails"></div>
    <div class="mx-reponse"><button class="mx-repondre">${avatar(S.moi.nom, "mx-av-p")}<span>Répondre à ${echap(correspondant(fil))}…</span></button></div>`;
  const liste = $(".mx-mails", lecture);
  const ouvertures = [];
  fil.mails.forEach((m, i) => {
    const dernier = i === fil.mails.length - 1;
    const bloc = el(`<article class="mx-mail ${m.entrant ? "" : "moi"}" tabindex="0">
        <div class="mx-mail-tete">${avatar(m.entrant ? m.de.nom || m.de.adresse : S.moi.nom, "mx-av")}
          <div class="mx-qui"><div class="mx-nom"><b>${echap(nomDe(m))}</b>${m.entrant ? `<small>${echap(m.de.adresse)}</small>` : ""}</div>
            <div class="mx-apercu-ligne">${echap(m.apercu)}</div>
            <div class="mx-dest">à ${echap(m.a.map((x) => x.nom || x.adresse).join(", "))}${m.cc.length ? ` · cc ${echap(m.cc.map((x) => x.nom || x.adresse).join(", "))}` : ""}</div></div>
          ${m.pj ? `<span class="mx-trombone" title="Pièce(s) jointe(s)">${icone("trombone")}</span>` : ""}
          <time title="${quandLong(m.le)}">${quandCourt(m.le)}</time></div>
        <div class="mx-corps"><div class="mx-texte"><div class="mx-squelette"><i></i><i></i><i></i></div></div><div class="mx-pj"></div></div></article>`);
    liste.append(bloc);
    let charge = false;
    const ouvrir = async () => {
      bloc.classList.add("ouvert");
      if (charge) return;
      charge = true;
      try {
        const c = await api("GET", `/api/outlook/mails/${encodeURIComponent(m.id)}`);
        if (!m.lu) verifierMessages(true);
        const texte = (c.texte || c.complet || "(message vide)").replace(/\r/g, "").replace(/[ \t]+\n/g, "\n").replace(/\n{3,}/g, "\n\n").trim();
        $(".mx-texte", bloc).textContent = texte;
        $(".mx-pj", bloc).innerHTML = c.pj.length ? `<div class="mx-pj-titre">${icone("trombone")} ${c.pj.length} pièce${c.pj.length > 1 ? "s" : ""} jointe${c.pj.length > 1 ? "s" : ""}</div>
          <div class="mx-pj-liste">${c.pj.map((a) => { const ext = (a.nom.includes(".") ? a.nom.split(".").pop() : "").slice(0, 4).toUpperCase() || "FICH";
            const url = `/api/outlook/mails/${encodeURIComponent(m.id)}/pj/${encodeURIComponent(a.id)}`;
            return `<div class="mx-fichier"><span class="mx-ext e-${ext.toLowerCase()}">${echap(ext)}</span>
              <span class="mx-fichier-nom"><b title="${echap(a.nom)}">${echap(a.nom)}</b><small>${a.taille > 1048576 ? (a.taille / 1048576).toFixed(1).replace(".", ",") + " Mo" : Math.max(1, Math.round(a.taille / 1024)) + " Ko"}</small></span>
              <a class="mx-rond" href="${url}?voir=1" target="_blank" rel="noopener" title="Ouvrir">${icone("ouvrir")}</a>
              <a class="mx-rond" href="${url}" title="Télécharger">${icone("telecharger")}</a></div>`; }).join("")}</div>` : "";
      } catch (e) { $(".mx-texte", bloc).innerHTML = `<div class="alerte">${echap(e.message)}</div>`; }
      if (dernier) liste.scrollTo({ top: Math.max(0, bloc.offsetTop - 16), behavior: "auto" });  // on arrive sur le début du dernier mail
    };
    ouvertures.push(ouvrir);
    bloc.addEventListener("click", (e) => {
      if (e.target.closest("a, button")) return;
      if (!bloc.classList.contains("ouvert")) return ouvrir();
      if (e.target.closest(".mx-mail-tete") && !dernier) bloc.classList.remove("ouvert");  // replier : clic sur l'en-tête
    });
    bloc.addEventListener("keydown", (e) => { if (e.key === "Enter" && e.target === bloc) ouvrir(); });
    if (dernier || (!m.lu && m.entrant)) ouvrir();  // le dernier et les non lus s'ouvrent d'office
  });
  $('[data-a="tout"]', lecture)?.addEventListener("click", (e) => {
    const tout = e.currentTarget.textContent === "Tout déplier";
    if (tout) ouvertures.forEach((o) => o()); else $$(".mx-mail", liste).forEach((b, i, ls) => i < ls.length - 1 && b.classList.remove("ouvert"));
    e.currentTarget.textContent = tout ? "Tout replier" : "Tout déplier";
  });
  bouton?.classList.remove("non-lu"); bouton?.querySelector(".mx-point")?.remove();
  $('[data-a="archiver"]', lecture).onclick = () => archiverFil(zone, p, fil);
  $(".mx-repondre", lecture).onclick = () => composer(zone, p, fil);
}

// ---------- Éditeur de texte des mails : gras, italique, souligné, couleur, taille, listes, liens (comme Outlook) ----------
const TAILLES_PT = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 36];  // comme Outlook ; 11 par défaut (Calibri 11)
// Couleurs proposées (celles d'Outlook), puis celles du cabinet ; « Autre couleur… » pour le reste
const COULEURS_TEXTE = [["#000000", "Noir"], ["#404040", "Gris foncé"], ["#7F7F7F", "Gris"], ["#C00000", "Rouge foncé"], ["#FF0000", "Rouge"],
  ["#ED7D31", "Orange"], ["#FFC000", "Or"], ["#00B050", "Vert"], ["#375623", "Vert foncé"], ["#00B0F0", "Bleu clair"], ["#0070C0", "Bleu"],
  ["#002060", "Bleu foncé"], ["#7030A0", "Violet"]];
const COULEURS_CABINET = [["#2F6FEB", "Bleu cabinet"], ["#1F2937", "Anthracite"], ["#4B5563", "Gris (signature)"]];
function editeurTexte(html = "", indication = "") {
  const e = el(`<div class="editeur">
      <div class="ed-barre" role="toolbar" aria-label="Mise en forme">
        <select class="ed-taille" title="Taille du texte (points)">${TAILLES_PT.map((t) => `<option value="${t}" ${t === 11 ? "selected" : ""}>${t}</option>`).join("")}</select>
        <span class="ed-sep"></span>
        <button type="button" data-c="bold" title="Gras (Ctrl+B)"><b>G</b></button>
        <button type="button" data-c="italic" title="Italique (Ctrl+I)"><i>I</i></button>
        <button type="button" data-c="underline" title="Souligné (Ctrl+U)"><u>S</u></button>
        <button type="button" data-c="strikeThrough" title="Barré"><s>ab</s></button>
        <span class="ed-couleurs"><button type="button" class="ed-couleur" data-a="couleur" title="Couleur du texte"><span>A</span><i></i></button><button type="button"
          class="ed-couleur-fleche" data-a="palette" title="Choisir la couleur" aria-haspopup="true">▾</button>
          <div class="ed-palette cache" role="menu">
            <div class="ed-palette-titre">Couleurs</div>
            <div class="ed-nuancier">${COULEURS_TEXTE.map(([c, nom]) => `<button type="button" data-couleur="${c}" title="${nom}" style="background:${c}"></button>`).join("")}</div>
            <div class="ed-palette-titre">Cabinet</div>
            <div class="ed-nuancier">${COULEURS_CABINET.map(([c, nom]) => `<button type="button" data-couleur="${c}" title="${nom}" style="background:${c}"></button>`).join("")}</div>
            <label class="ed-autre">Autre couleur…<input type="color" value="#c00000"></label></div></span>
        <span class="ed-sep"></span>
        <button type="button" data-c="insertUnorderedList" title="Liste à puces"><svg viewBox="0 0 24 24"><circle cx="5" cy="7" r="1.4"/><circle cx="5" cy="12" r="1.4"/><circle cx="5" cy="17" r="1.4"/><path d="M9 7h11M9 12h11M9 17h11"/></svg></button>
        <button type="button" data-c="insertOrderedList" title="Liste numérotée"><svg viewBox="0 0 24 24"><path d="M10 7h10M10 12h10M10 17h10"/><text x="2.5" y="9" font-size="6.5">1</text><text x="2.5" y="14.5" font-size="6.5">2</text><text x="2.5" y="20" font-size="6.5">3</text></svg></button>
        <button type="button" data-c="outdent" title="Diminuer le retrait"><svg viewBox="0 0 24 24"><path d="M4 6h16M10 10h10M10 14h10M4 18h16M7 9.5 4 12l3 2.5"/></svg></button>
        <button type="button" data-c="indent" title="Augmenter le retrait"><svg viewBox="0 0 24 24"><path d="M4 6h16M10 10h10M10 14h10M4 18h16M4 9.5 7 12l-3 2.5"/></svg></button>
        <span class="ed-sep"></span>
        <button type="button" data-a="lien" title="Insérer un lien"><svg viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg></button>
        <button type="button" data-c="removeFormat" title="Effacer la mise en forme"><span class="ed-tx">T<sub>x</sub></span></button>
      </div>
      <div class="ed-zone" contenteditable="true" role="textbox" aria-multiline="true" data-indication="${echap(indication)}">${html}</div></div>`);
  const zone = $(".ed-zone", e);
  let plage = null;  // sélection gardée quand on clique dans la barre (couleur, taille)
  const garder = () => { const s = getSelection(); if (s.rangeCount && zone.contains(s.anchorNode)) plage = s.getRangeAt(0).cloneRange(); };
  const remettre = () => { zone.focus(); if (plage) { const s = getSelection(); s.removeAllRanges(); s.addRange(plage); } };
  const faire = (c, v = null) => { remettre(); document.execCommand("styleWithCSS", false, false); document.execCommand(c, false, v); garder(); etat(); };
  const etat = () => {
    $$("[data-c]", e).forEach((b) => { try { b.classList.toggle("actif", document.queryCommandState(b.dataset.c)); } catch { /* commande sans état */ } });
    const n = getSelection().anchorNode, x = n && (n.nodeType === 1 ? n : n.parentElement);  // taille du texte là où est le curseur
    if (x && zone.contains(x)) { const pt = Math.round(parseFloat(getComputedStyle(x).fontSize) * 0.75); if (TAILLES_PT.includes(pt)) $(".ed-taille", e).value = String(pt); }
  };
  /** Taille précise en points : le navigateur ne connaît que 7 tailles, on pose la 7 puis on la remplace par la taille choisie. */
  const taille = (pt) => {
    faire("fontSize", "7");
    $$('font[size="7"]', zone).forEach((f) => {
      const s = document.createElement("span"); s.style.fontSize = `${pt}pt`;
      while (f.firstChild) s.appendChild(f.firstChild);
      f.replaceWith(s);
    });
    garder(); etat();
  };
  $$(".ed-barre button, .ed-barre label, .ed-barre select", e).forEach((b) => b.addEventListener("mousedown", (ev) => { garder(); if (b.tagName === "BUTTON") ev.preventDefault(); }));
  $$("[data-c]", e).forEach((b) => b.onclick = () => faire(b.dataset.c));
  $(".ed-taille", e).onchange = (ev) => taille(ev.target.value);
  let couleur = "#C00000";  // la dernière choisie : « A » la remet d'un clic
  const palette = $(".ed-palette", e);
  const fermerPalette = () => palette.classList.add("cache");
  const appliquerCouleur = (c) => { couleur = c; $(".ed-couleur i", e).style.background = c; fermerPalette(); faire("foreColor", c); };
  $('[data-a="couleur"]', e).onclick = () => faire("foreColor", couleur);
  $('[data-a="palette"]', e).onclick = () => palette.classList.toggle("cache");
  $$("[data-couleur]", e).forEach((b) => b.onclick = () => appliquerCouleur(b.dataset.couleur));
  $(".ed-autre input", e).addEventListener("input", (ev) => appliquerCouleur(ev.target.value));
  document.addEventListener("mousedown", (ev) => { if (!ev.target.closest(".ed-couleurs") || !e.contains(ev.target)) fermerPalette(); });
  $('[data-a="lien"]', e).onclick = () => {
    const url = prompt("Adresse du lien (https://…)", "https://");
    if (url && /^(https?:\/\/|mailto:)/i.test(url.trim())) faire("createLink", url.trim());
  };
  zone.addEventListener("keyup", () => { garder(); etat(); });
  zone.addEventListener("mouseup", () => { garder(); etat(); });
  zone.addEventListener("paste", () => { const avant = new Set($$("img", zone)); setTimeout(() => $$("img", zone).forEach((i) => { if (!avant.has(i)) i.remove(); }), 0); });
  return { el: e, zone, html: () => zone.innerHTML, texte: () => zone.innerText.trim(), focus: () => zone.focus(),
    debut: (html) => {  // remplace le texte au-dessus de la signature (texte de relance)
      const sig = $(".signature-crm", zone);
      while (sig && zone.firstChild !== sig) zone.removeChild(zone.firstChild);
      if (!sig) zone.innerHTML = "";
      zone.insertAdjacentHTML("afterbegin", html + (sig ? "<p><br></p>" : ""));
    } };
}
/** Paragraphes séparés d'une ligne vide, comme quand on tape dans Outlook (le mail envoyé a le même espacement). */
const paragraphes = (...blocs) => blocs.map((b) => `<p>${b}</p>`).join("<p><br></p>");
const enParagraphes = (texte) => paragraphes(...String(texte || "").trim().split(/\n\s*\n/).map((b) => echap(b).replace(/\n/g, "<br>")));
/** Signature des mails : celle gardée dans le CRM ; sinon, la première fois, celle d'Outlook (au choix s'il y en a plusieurs), en
 *  demandant s'il faut la garder par défaut. */
async function maSignature() {
  if (S.signature != null) return S.signature;
  let s = { html: "", personnalisee: true };
  try { s = await api("GET", "/api/signature"); } catch { /* sans signature */ }
  S.signature = s.html;
  if (!s.personnalisee) {
    const outlook = await signatureOutlook();
    if (outlook) S.signature = outlook;
  }
  return S.signature;
}

/** Fichiers de signature d'Outlook choisis dans le navigateur (dossier %APPDATA%\Microsoft\Signatures, ou des .htm) : chaque
 *  signature nettoyée par le serveur, avec ses images (dossier « …_fichiers ») remises dedans. */
async function signaturesDesFichiers(fichiers) {
  const tous = [...fichiers];
  const relatif = (f) => (f.webkitRelativePath || f.name).split("/").slice(f.webkitRelativePath ? 1 : 0).join("/").toLowerCase();
  const parChemin = new Map(tous.map((f) => [relatif(f), f]));
  const base64 = (f) => new Promise((ok) => { const r = new FileReader(); r.onload = () => ok(String(r.result).split(",")[1] || ""); r.readAsDataURL(f); });
  const liste = [];
  for (const f of tous.filter((x) => /\.html?$/i.test(x.name) && !relatif(x).includes("/"))) {
    const octets = await f.arrayBuffer();
    let texte = new TextDecoder("utf-8").decode(octets);
    if (texte.includes("\ufffd") || /charset=windows-1252/i.test(texte)) texte = new TextDecoder("windows-1252").decode(octets);  // Outlook classique
    for (const src of new Set([...texte.matchAll(/src="([^"]+)"/gi)].map((m) => m[1]).filter((s) => !/^(data:|https?:|cid:)/i.test(s)))) {
      let chemin = src; try { chemin = decodeURIComponent(src); } catch { /* tel quel */ }
      const img = parChemin.get(chemin.replace(/\\/g, "/").replace(/&amp;/g, "&").toLowerCase());
      if (img && img.size < 700000) texte = texte.split(`src="${src}"`).join(`src="data:${img.type || "image/png"};base64,${await base64(img)}"`);
    }
    try {
      const html = (await api("POST", "/api/signature/apercu", { html: texte })).html;
      if (html.replace(/<[^>]+>/g, "").trim() || html.includes("<img")) liste.push({ nom: f.name.replace(/\.html?$/i, ""), html, defaut: false });
    } catch { /* fichier illisible : ignoré */ }
  }
  const moi = (S.outlook?.adresse || "").toLowerCase();  // Outlook nomme souvent la signature « … (adresse) »
  liste.forEach((s) => { s.defaut = !!moi && s.nom.toLowerCase().includes(moi); });
  return liste.sort((a, b) => b.defaut - a.defaut || a.nom.localeCompare(b.nom));
}

/** Choix de la personne, puis « la garder par défaut ? ». Retourne le HTML choisi, ou null. */
async function choisirEtProposer(liste, demander = true) {
  if (!liste.length) return null;
  const choisie = liste.length === 1 ? liste[0] : await choisirSignature(liste);
  if (!choisie) return null;
  if (demander) await proposerSignature(choisie.html);
  return choisie.html;
}

/** Sélecteur de dossier : la personne choisit son dossier de signatures Outlook (le navigateur demande la confirmation). */
function choisirDossierSignatures() {
  return new Promise((fin) => {
    const i = el('<input type="file" webkitdirectory multiple hidden>');
    i.onchange = async () => { const l = await signaturesDesFichiers(i.files); i.remove(); fin(l); };
    i.addEventListener("cancel", () => { i.remove(); fin([]); });
    document.body.append(i); i.click();
  });
}

/** Signatures d'Outlook de ce PC : une seule → elle ; plusieurs → la personne choisit. Puis : « la garder par défaut ? ». */
async function signatureOutlook(demander = true) {
  let liste = [];
  try { liste = (await api("GET", "/api/signature/outlook-locales")).signatures; } catch { return null; }
  if (!liste.length) {  // autre PC, ou CRM en ligne : proposé une fois, la personne choisit son dossier de signatures
    if (demander && !S.signatureProposee) {
      S.signatureProposee = true;
      toast("Mettre votre signature Outlook dans les mails du CRM ? Choisissez le dossier %APPDATA%\\Microsoft\\Signatures.", { duree: 12000, action: "Choisir",
        auClic: async () => { const l = await choisirDossierSignatures(); const h = await choisirEtProposer(l); if (h) S.signature = h; } });
    }
    return null;
  }
  const choisie = liste.length === 1 ? liste[0] : await choisirSignature(liste);
  if (!choisie) return null;
  if (demander) await proposerSignature(choisie.html);
  return choisie.html;
}

function choisirSignature(liste) {
  return new Promise((fin) => {
    const f = fenetre({ titre: "Quelle signature Outlook ?", sous: "Vous en avez plusieurs dans Outlook : choisissez celle des mails envoyés depuis le CRM.", classe: "moyenne",
      corps: `<div class="choix-signatures">${liste.map((s, i) => `<button class="choix-signature" data-i="${i}"><span class="cs-nom">${echap(s.nom)}${s.defaut
        ? ' <span class="pastille p-vert">par défaut dans Outlook</span>' : ""}</span><div class="cs-apercu">${s.html}</div></button>`).join("")}</div>` });
    let rendu = false;
    $$("[data-i]", f).forEach((b) => b.onclick = () => { rendu = true; f.fermer(); fin(liste[+b.dataset.i]); });
    const obs = new MutationObserver(() => { if (!f.isConnected) { obs.disconnect(); if (!rendu) fin(null); } });
    obs.observe($("#couche-modales"), { childList: true });
  });
}

/** « Garder cette signature et en faire celle par défaut ? » : oui → enregistrée ; non → seulement pour cette fois. */
function proposerSignature(html) {
  return new Promise((fin) => {
    const f = fenetre({ titre: "Garder cette signature ?", sous: "Elle sera mise en bas de tous vos mails envoyés depuis le CRM. Modifiable dans Paramètres.", classe: "moyenne",
      corps: `<div class="cs-apercu grand">${html}</div>`,
      pied: '<button class="btn fantome" data-a="non">Seulement pour ce mail</button><button class="btn principal" data-a="oui">Oui, en faire ma signature par défaut</button>' });
    let rendu = false;
    $('[data-a="non"]', f).onclick = () => { rendu = true; f.fermer(); fin(false); };
    $('[data-a="oui"]', f).onclick = async () => {
      rendu = true;
      try { S.signature = (await api("POST", "/api/signature", { html })).html; toast("Signature enregistrée par défaut."); } catch (e) { signalerErreur(e); }
      f.fermer(); fin(true);
    };
    const obs = new MutationObserver(() => { if (!f.isConnected) { obs.disconnect(); if (!rendu) fin(false); } });
    obs.observe($("#couche-modales"), { childList: true });
  });
}

/** Rédaction : réponse (en bas de la conversation) ou nouveau mail ; destinataires et lettres de mission en pastilles.
 *  o (facultatif) : mail préparé ailleurs { a, objet, html, lettres, url, apres, annuler }. */
async function composer(zone, p, fil, o = {}) {
  const lecture = $(".mx-lecture", zone);
  const contacts = (p.contacts || []).filter((c) => c.email);
  const dernier = fil?.mails[fil.mails.length - 1];
  const aDefaut = o.a || (fil ? [dernier.entrant ? dernier.de.adresse : (dernier.a[0] || {}).adresse].filter(Boolean) : contacts.slice(0, 1).map((c) => c.email));
  const adresses = [...new Set([...contacts.map((c) => c.email), ...aDefaut])];
  let lettresP = [];
  try { lettresP = (await api("GET", "/api/lettres")).lettres.filter((l) => l.dossier.ref === p.id && l.pdf); } catch { /* facultatif */ }
  const signature = await maSignature();
  const nomContact = (a) => { const c = contacts.find((x) => x.email === a); return c ? [c.prenom, c.nom].filter(Boolean).join(" ") || a : a; };
  const f = el(`<div class="mx-composer ${fil ? "" : "plein"}">
      ${fil ? "" : `<header class="mx-tete"><h3>Nouveau mail</h3><div class="mx-gens"><span>${echap(p.denomination)}</span></div></header>`}
      <div class="mx-champs">
        <div class="mx-ligne"><span>À</span><div class="mx-pastilles">${adresses.map((a) => `<button class="mx-pastille ${aDefaut.includes(a) ? "on" : ""}" data-dest="${echap(a)}" title="${echap(a)}">${echap(nomContact(a))}</button>`).join("")}</div></div>
        ${fil ? "" : `<div class="mx-ligne"><span>Objet</span><input name="objet" placeholder="Objet du mail" value="${echap(o.objet || "")}"></div>`}
      </div>
      <div class="mx-editeur"></div>
      ${lettresP.length ? `<div class="mx-joindre"><span>${icone("livrables")} Joindre</span>${lettresP.map((l) => `<button class="mx-pastille ${(o.lettres || []).includes(l.id) ? "on" : ""}" data-lettre="${l.id}">${echap(l.libelle)}.pdf</button>`).join("")}</div>` : ""}
      <footer class="mx-pied">${o.url || p._avec ? "" : '<button class="btn fantome petit" data-a="relance">Texte de relance</button>'}<span class="espace"></span>
        <button class="btn fantome petit" data-a="annuler">Annuler</button>
        <button class="btn principal" data-a="envoyer">Envoyer <kbd>Ctrl ↵</kbd></button></footer></div>`);
  if (fil) { $(".mx-reponse", lecture).replaceWith(f); f.animate([{ opacity: 0, transform: "translateY(10px)" }, { opacity: 1, transform: "none" }], { duration: 220, easing: "cubic-bezier(.33,1,.68,1)" }); }
  else { lecture.innerHTML = ""; lecture.append(f); }
  const ed = editeurTexte(`${o.html || "<p><br></p>"}<p><br></p>${signature ? `<div class="signature-crm">${signature}</div>` : ""}`,
    fil ? `Votre réponse à ${correspondant(fil)}…` : "Votre message…");
  $(".mx-editeur", f).append(ed.el);
  const zoneTexte = ed.zone;
  (fil || o.objet ? zoneTexte : $('[name="objet"]', f)).focus();
  $$(".mx-pastille", f).forEach((b) => b.onclick = () => b.classList.toggle("on"));
  $('[data-a="annuler"]', f).onclick = () => (o.annuler ? o.annuler() : fil ? lireFil(zone, p, fil) : ongletEchanges(zone, p));
  $('[data-a="relance"]', f)?.addEventListener("click", async () => {
    const m = await api("GET", `/api/prospects/${p.id}/mail-relance`);
    ed.debut(enParagraphes(m.texte)); const ob = $('[name="objet"]', f); if (ob && !ob.value) ob.value = m.objet; zoneTexte.focus();
  });
  const envoyer = async () => {
    const b = $('[data-a="envoyer"]', f);
    const corpsEnvoi = { a: $$("[data-dest].on", f).map((x) => x.dataset.dest), objet: $('[name="objet"]', f)?.value || "",
      html: ed.html(), texte: ed.texte(), lettres: $$("[data-lettre].on", f).map((x) => x.dataset.lettre), repondre_a: dernier?.id, avec: p._avec || "" };
    if (!corpsEnvoi.a.length) return toast("Choisissez au moins un destinataire.", { erreur: true });
    if (!corpsEnvoi.texte) return zoneTexte.focus();
    try {
      b.disabled = true; b.textContent = "Envoi…";
      const r = await api("POST", o.url || `/api/prospects/${p.id}/mails`, corpsEnvoi);
      if (o.apres) { toast("Mail envoyé."); return o.apres(r); }
      await charger();
      toast(r.prochaine ? `Mail envoyé · prochaine relance le ${dateFr(r.prochaine, true)}.` : "Mail envoyé.");
      if (S.page === "pipeline") remplirColonnes();
      const fiche = $(".fenetre[data-fiche]");
      ongletEchanges(zone, p, true, fil?.id);
      if (fiche) remplirFiche(fiche);  // onglet « Ancien cabinet » : étapes à jour (lettre envoyée)
    } catch (e) {
      b.disabled = false; b.innerHTML = "Envoyer <kbd>Ctrl ↵</kbd>";
      toast(e.champs ? Object.values(e.champs)[0] : e.message, { erreur: true });
    }
  };
  $('[data-a="envoyer"]', f).onclick = envoyer;
  f.addEventListener("keydown", (e) => { if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); envoyer(); } });
}

// ---------- Annuler / rétablir ----------
async function annuler() { await defaire("/api/annuler"); }
async function retablir() { await defaire("/api/retablir"); }
async function defaire(url) {
  try {
    const r = await api("POST", url);
    await charger(); S.clients = null;
    toast(r.libelle);
    rafraichirPage();
    if (S.page === "clients") { await chargerClients(); rendre(false); }
  } catch (e) { signalerErreur(e); }
}
addEventListener("keydown", (e) => {
  if (!S.moi || !(e.ctrlKey || e.metaKey) || e.target.closest("input, textarea, select, [contenteditable]") || e.target.isContentEditable) return;  // dans un texte : Ctrl+Z du texte
  if (e.key.toLowerCase() === "z" && !e.shiftKey) { e.preventDefault(); annuler(); }
  if (e.key.toLowerCase() === "y" || (e.key.toLowerCase() === "z" && e.shiftKey)) { e.preventDefault(); retablir(); }
});
addEventListener("resize", () => { if (S.moi) majRail(); });

// ---------- Nouveaux mails des prospects : pastille sur la carte, compteur sur Pipeline, message à l'arrivée ----------
const TITRE = document.title;
function majBadgeRail() {
  const b = $('.rail button[data-page="pipeline"]');
  if (!b) return;
  const n = Object.values(S.nonLus).reduce((t, x) => t + x.non_lus, 0);
  let pastille = $(".rail-compte", b);
  if (!pastille) { pastille = el('<span class="rail-compte"></span>'); b.append(pastille); }
  pastille.textContent = n > 99 ? "99+" : n;
  pastille.classList.toggle("cache", !n);
  document.title = n ? `(${n}) ${TITRE}` : TITRE;
}

let verificationEnCours = null;
async function verifierMessages(frais = false) {
  if (!S.moi || !S.etat) return;
  if (verificationEnCours) return verificationEnCours;
  verificationEnCours = (async () => {
    let r;
    try { r = await api("GET", "/api/outlook/nouveaux" + (frais ? "?frais=1" : "")); } catch { return; }
    if (!r.connecte) { S.nonLus = {}; S.mailsVus = new Set(); majBadgeRail(); return; }
    const avant = JSON.stringify(S.nonLus);
    S.nonLus = r.prospects || {};
    const premiere = S.mailsVus === null;
    const vus = S.mailsVus || new Set();
    const arrivees = [];
    for (const [pid, x] of Object.entries(S.nonLus)) for (const id of x.ids) if (!vus.has(id)) { vus.add(id); arrivees.push(pid); }
    S.mailsVus = vus;
    if (!premiere && arrivees.length) {
      const pid = arrivees[arrivees.length - 1], x = S.nonLus[pid];
      const p = S.etat.prospects.find((q) => q.id === pid);
      const texte = `Nouveau message de ${x.de}${p ? ` · ${p.denomination}` : ""} : « ${x.objet} »`;
      toast(texte, { action: "Ouvrir", auClic: () => { if (S.page !== "pipeline") aller("pipeline"); ouvrirFiche(pid, "echanges"); }, duree: 9000 });
      if (document.hidden && "Notification" in window && Notification.permission === "granted") {
        try { const n = new Notification("CRM Cabinet", { body: texte, icon: "logo.svg", tag: "crm-" + pid }); n.onclick = () => { focus(); ouvrirFiche(pid, "echanges"); n.close(); }; } catch { /* sans notification */ }
      }
    }
    majBadgeRail();
    $$(".fenetre[data-fiche]").forEach(majCompteFiche);
    if (JSON.stringify(S.nonLus) !== avant && S.page === "pipeline" && !S.geste) remplirColonnes();
  })();
  try { await verificationEnCours; } finally { verificationEnCours = null; }
}
setInterval(() => { if (!document.hidden || ("Notification" in window && Notification.permission === "granted")) verifierMessages(); }, 45000);
document.addEventListener("visibilitychange", () => { if (!document.hidden) verifierMessages(); });

// ---------- Mises à jour d'un collègue : relecture discrète toutes les 8 secondes ----------
setInterval(async () => {
  if (!S.moi || !S.etat || S.geste || document.hidden) return;
  try {
    const { version } = await api("GET", "/api/version");
    if (version === S.etat.version) return;
    await charger(); S.clients = null;
    if (S.page === "pipeline") remplirColonnes();  // la fiche ouverte n'est pas redessinée : une saisie en cours n'est jamais perdue
    majAnnuler();
  } catch { /* hors ligne un instant : on réessaiera */ }
}, 8000);

// ---------- Démarrage ----------
async function demarrer() {
  const demandee = location.hash.slice(1);  // /#clients : page ouverte au démarrage (liens, captures de la démo)
  if (PAGES.some(([code]) => code === demandee)) { S.page = demandee; history.replaceState(null, "", "/"); }
  await charger();
  construireCoque();
  majRail();
  rendre(true);
  majAnnuler();
  if (RETOUR_MICROSOFT && !demarrer.retourFait) { demarrer.retourFait = true; terminerRetourMicrosoft(); }
  if (RETOUR_CONNEXION) {
    const r = RETOUR_CONNEXION;
    RETOUR_CONNEXION = null;
    if (r === "lie") toast("Compte Microsoft lié : vous pourrez vous connecter avec Microsoft.");
    else toast(MESSAGES_MICROSOFT[r] || MESSAGES_MICROSOFT.erreur, { erreur: true, duree: 9000 });
    aller("parametres");
  }
  verifierMessages();
}
const RETOUR_MICROSOFT = (() => {  // retour de la page Microsoft sur le CRM : gardé, puis l'adresse est nettoyée
  const q = new URLSearchParams(location.search);
  if (!q.get("state") || !(q.get("code") || q.get("error"))) return null;
  const adresse = location.href;
  history.replaceState(null, "", "/");
  return adresse;
})();

async function terminerRetourMicrosoft() {
  try {
    const r = await api("POST", "/api/outlook/connexion/retour", { adresse: RETOUR_MICROSOFT });
    toast(`Boîte connectée : ${r.adresse}`);
  } catch (e) { if (e.message !== "401") toast(e.message, { erreur: true, duree: 9000 }); }
  aller("parametres");
}

(async () => {
  try { S.moi = await api("GET", "/api/moi"); } catch (e) { if (e.message !== "401") pageConnexion(); return; }
  await demarrer();
})();
