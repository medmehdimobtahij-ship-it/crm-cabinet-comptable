<?php
/**
 * Lettres de mission : les modèles du cabinet (modeles/lettres-de-mission) remplis à partir du formulaire du CRM.
 * Sans bibliothèque : un .docx est un zip, le texte est dans word/document.xml. Les modèles publiés ici sont fictifs
 * (outils/generer_modeles.py) ; un cabinet y dépose les siens en gardant les mêmes phrases repères.
 *
 * Dans les modèles, chaque donnée du client est une zone surlignée en jaune entre crochets. Chaque paragraphe qui en
 * contient est reconnu par une phrase qui lui est propre (lettres_regles) ; ses zones sont remplacées dans l'ordre et le
 * surlignage retiré. Valeur null : la zone reste en jaune. Ligne facultative non retenue : le paragraphe est supprimé.
 * Le PDF et le rangement dans SharePoint (Lettres de mission › client) passent par Microsoft : voir sharepoint.php.
 */
declare(strict_types=1);

const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const XML_NS = 'http://www.w3.org/XML/1998/namespace';

// code => [libellé, public, types de dossier, fichier sans partenaire, fichier avec partenaire (services à la personne)]
const MODELES_LETTRE = [
    'PRESENTATION' => ['Présentation des comptes', 'PRO', ['SOCIETE'], 'PRO - 1 - Présentation des comptes.docx', null],
    'ETABLISSEMENT' => ['Établissement des comptes', 'PRO', ['SOCIETE'], 'PRO - 2 - Établissement des comptes.docx', null],
    'TVA' => ['Assistance aux déclarations fiscales (TVA)', 'PRO', ['SOCIETE', 'EI'], 'PRO - 3 - Assistance aux déclarations de TVA.docx', null],
    'BNC' => ['BNC (déclaration 2035)', 'PRO', ['EI'], 'PRO - 4 - BNC (déclaration 2035).docx', null],
    'BIC' => ['BIC (déclaration 2031)', 'PRO', ['EI'], 'PRO - 5 - BIC au réel (entreprise individuelle).docx', null],
    'LMNP' => ['LMNP (location meublée)', 'PRO', ['EI'], 'PRO - 6 - LMNP (location meublée).docx', null],
    'IR' => ['Déclaration de revenus (IR)', 'PERSO', ['PARTICULIER'], 'PERSO - IR (impôt sur le revenu) - sans partenaire.docx',
        'PERSO - IR (impôt sur le revenu) - avec partenaire.docx'],
    'FONCIER' => ['Revenus fonciers (2044)', 'PERSO', ['PARTICULIER'], 'PERSO - Revenus fonciers (2044) - sans partenaire.docx',
        'PERSO - Revenus fonciers (2044) - avec partenaire.docx'],
    'IFI' => ['IFI (impôt sur la fortune immobilière)', 'PERSO', ['PARTICULIER'], 'PERSO - IFI (impôt sur la fortune immobilière) - sans partenaire.docx',
        'PERSO - IFI (impôt sur la fortune immobilière) - avec partenaire.docx'],
];
const AVEC_ANNEXE2 = ['PRESENTATION', 'ETABLISSEMENT', 'TVA', 'BNC', 'BIC'];
const MISSION_VERS_MODELE = [
    'Présentation des comptes' => 'PRESENTATION', 'Établissement des comptes' => 'ETABLISSEMENT',
    'Présentation des comptes (avec assurance)' => 'PRESENTATION', 'Établissement des comptes (sans assurance)' => 'ETABLISSEMENT',
    'Assistance aux déclarations fiscales (TVA)' => 'TVA', 'BNC (déclaration 2035)' => 'BNC', 'BIC au réel' => 'BIC', 'BIC (déclaration 2031)' => 'BIC',
    'LMNP (location meublée)' => 'LMNP', 'Déclaration de revenus (IR)' => 'IR', 'Revenus fonciers (2044)' => 'FONCIER', 'IFI' => 'IFI',
    'LMNP' => 'LMNP', 'BNC' => 'BNC', 'BNC (2035)' => 'BNC', 'Déclaration de revenus' => 'IR', 'Revenus fonciers' => 'FONCIER',
    'Comptabilité / Fiscalité' => 'PRESENTATION',
];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const ZONE = '/\[[^\[\]]{1,120}\]/u';
const HONORAIRES = ['Forfait mensuel de', 'Forfait d’établissement des comptes annuels de', 'Etablissement des comptes annuels de',
    'Dépôt des comptes annuels', 'par déclaration de TVA', 'Création du statut LMNP', 'Forfait d’établissement de la liasse fiscale',
    'liasse fiscale [AAAA] et suivants', 'pour la déclaration de revenus', 'par bien immobilier déclaré', 'Déclaration IFI',
    '[Option] Déclaration rectificative IFI'];
const UNITES = ['mois' => 'par mois', 'trimestre' => 'par trimestre', 'an' => 'par an', 'forfait' => 'forfait',
    'declaration' => 'par déclaration', 'bien' => 'par bien', 'bulletin' => 'par bulletin'];
const FORFAIT_UNITE = ['mois' => 'mensuel', 'trimestre' => 'trimestriel', 'an' => 'annuel'];

function lettres_dossier(): string
{
    return chemin_data('lettres');
}

function date_longue(?string $iso): string
{
    $d = lire_date($iso) ?? aujourdhui();
    [$a, $m, $j] = array_map('intval', explode('-', $d));
    return ($j > 1 ? (string)$j : '1er') . ' ' . MOIS[$m - 1] . " $a";
}

function date_courte(?string $iso): string
{
    $d = lire_date($iso);
    return $d ? jjmmaaaa($d) : '';
}

function nombre_saisi($v): ?float
{
    $t = str_replace([' ', "\u{202f}", "\u{a0}", ','], ['', '', '', '.'], trim((string)($v ?? '')));
    return ($t !== '' && is_numeric($t)) ? (float)$t : null;
}

/** « 1200 » -> « 1 200 » ; « 99,5 » -> « 99,50 » ; vide -> null (la zone reste à compléter). */
function montant($v): ?string
{
    $x = nombre_saisi($v);
    if ($x === null) {
        return null;
    }
    return abs($x - round($x)) < 0.005 ? number_format(round($x), 0, ',', "\u{202f}") : number_format($x, 2, ',', "\u{202f}");
}

function texte_ou_null($v): ?string
{
    $t = trim((string)($v ?? ''));
    return $t === '' ? null : $t;
}

/** [[phrase qui reconnaît le paragraphe, valeurs des zones dans l'ordre | null = supprimer le paragraphe]]. */
function lettres_regles(array $v, string $modele): array
{
    $societe = ($v['type'] ?? '') === 'SOCIETE';
    $civ_long = ['M' => 'Monsieur', 'MME' => 'Madame'][$v['civilite'] ?? ''] ?? null;
    $chere = ['M' => 'Cher Monsieur', 'MME' => 'Chère Madame'][$v['civilite'] ?? ''] ?? null;
    $prenom = trim((string)($v['prenom'] ?? ''));
    if ($prenom !== '' && mb_strtolower($prenom) === $prenom) {  // « abdelouahed » -> « Abdelouahed »
        $prenom = implode('-', array_map('majuscule_initiale', explode('-', $prenom)));
    }
    $qui = texte_ou_null(implode(' ', array_filter([$prenom, mb_strtoupper(trim((string)($v['nom'] ?? '')))])));
    $denomination = texte_ou_null(mb_strtoupper(trim((string)($v['denomination'] ?? ''))));
    $interlocuteur = texte_ou_null(implode(', ', array_filter([trim((string)($v['interlocuteur'] ?? '')), trim((string)($v['fonction'] ?? ''))])));
    $salaries = trim((string)($v['salaries'] ?? ''));
    $salaries = ctype_digit($salaries) ? ((int)$salaries === 0 ? 'Elle n’emploie aucun salarié'
        : 'Elle emploie ' . (int)$salaries . ' salarié' . ((int)$salaries > 1 ? 's' : '')) : null;
    $regime_tva = ['CA3' => 'réel normal (CA3)', 'CA12' => 'réel simplifié (CA12)'][$v['regime_tva'] ?? ''] ?? null;
    $periodicite = ($v['regime_tva'] ?? '') === 'CA3' ? ($v['periodicite'] ?? null) : (($v['regime_tva'] ?? '') === 'CA12' ? 'annuelle' : null);
    $declarations = ['mensuelle' => 'CA3 mensuelles', 'trimestrielle' => 'CA3 trimestrielles', 'annuelle' => 'CA12 annuelle'][$periodicite ?? ''] ?? null;
    $annee = texte_ou_null($v['annee'] ?? '');
    $ouvert = date_courte($v['exercice_ouvert'] ?? null);
    $r = [
        ['[FORME] [DÉNOMINATION]', [mb_strtoupper(trim((string)($v['forme'] ?? ''))), $denomination]],
        ['EI [PRÉNOM NOM]', [$societe ? $denomination : ($qui !== null ? mb_strtoupper($qui) : null)]],
        ['A l’attention de', [$civ_long, $qui]],
        ['[Adresse],', [texte_ou_null($v['adresse'] ?? '')]],
        ['[Code postal] [Ville]', [texte_ou_null($v['cp'] ?? ''), texte_ou_null(mb_strtoupper(trim((string)($v['ville'] ?? ''))))]],
        ['Fait à [Ville du cabinet], le', [CABINET_VILLE, date_longue($v['date_lettre'] ?? null)]],
        ['[Chère Madame / Cher Monsieur]', [$chere, $qui]],
        ['Votre société exerce une activité de', [texte_ou_null($v['activite'] ?? '')]],
        ['[Elle n’emploie aucun salarié', [$salaries]],
        ['Votre interlocuteur principal sera', [$interlocuteur]],
        ['Forfait mensuel de', [montant($v['forfait_mensuel'] ?? null)]],
        ['Forfait d’établissement des comptes annuels de', [montant($v['forfait_comptes'] ?? null)]],
        ['Etablissement des comptes annuels de', [montant($v['forfait_etablissement'] ?? null)]],
        ['Dépôt des comptes annuels', [montant($v['forfait_depot'] ?? null)]],
        ['la moitié des honoraires dus sur une année fiscale complète', [montant($v['minimum'] ?? null)]],
        ['sur une période de [nombre] mois', !empty($v['premier_exercice'])
            ? [texte_ou_null($v['premier_mois'] ?? ''), montant($v['premier_montant'] ?? null)] : null],
        ["Elle portera sur les comptes de l'exercice comptable", [($v['exercice'] ?? '') === 'CREATION'
            ? 'ouvert depuis la création de la société' : ($ouvert !== '' ? "ouvert le $ouvert" : null)]],
        ['Vous exercez une activité de [activité], sous la forme', [texte_ou_null($v['activite'] ?? ''),
            $societe ? 'd’une société' : 'd’une entreprise individuelle']],
        ['Vous relevez du régime de TVA', [$regime_tva]],
        ['télétransmission de vos déclarations de TVA', [$declarations]],
        ['par déclaration de TVA', [montant($v['forfait_tva'] ?? null), $periodicite]],
        ['déclarations déposées à compter du', [texte_ou_null(date_courte($v['date_debut'] ?? null))]],
        ['Vous exercez une activité libérale de', [texte_ou_null($v['profession'] ?? '')]],
        ['en entreprise individuelle, relevant des bénéfices industriels', [texte_ou_null($v['activite'] ?? ''),
            ['SIMPLIFIE' => 'réel simplifié', 'NORMAL' => 'réel normal'][$v['regime_bic'] ?? ''] ?? null]],
        ['Création du statut LMNP', !empty($v['creation_lmnp']) ? [montant($v['forfait_creation_lmnp'] ?? null)] : null],
        ['liasse fiscale [AAAA] et suivants', [($annee !== null && ctype_digit($annee)) ? (string)((int)$annee + 1) : null,
            montant($v['forfait_suivants'] ?? null)]],
        ['Forfait d’établissement de la liasse fiscale', [$annee, montant($v['forfait_liasse'] ?? null)]],
        ['Votre foyer fiscal se compose de', [texte_ou_null($v['composition'] ?? ''), texte_ou_null(implode(', ', $v['revenus'] ?? []))]],
        ['pour l’année fiscale', [$annee]],
        ['pour la déclaration de revenus', [montant($v['forfait_ir'] ?? null)]],
        ['par bien immobilier déclaré', [montant($v['forfait_bien'] ?? null)]],
        ['[Note interne : taux et plafonds à confirmer avec le partenaire.]', ['']],  // note interne du modèle : jamais dans la lettre
        ['Attestation de passage à domicile', [texte_ou_null($v['adresse_passage'] ?? ''), texte_ou_null(date_courte($v['date_passage'] ?? null))]],
        ['Cette assurance couvre', [texte_ou_null($v['couverture'] ?? '')]],
        ['médiateur de la consommation', [texte_ou_null($v['mediateur'] ?? '')]],
        // Bloc de signature du client (zone de texte Word : la version Python ne le voyait pas, il restait en jaune)
        ['[Gérant / Président]', [qualite_signataire($v)]],
        ['[Prénom NOM]', [$qui]],
    ];
    $ifi = [];
    foreach ($v['ifi'] ?? [] as $l) {
        if (!empty($l['retenue'])) {
            $ifi[(string)($l['annee'] ?? '')] = $l;
        }
    }
    foreach (['2023', '2024', '2025'] as $a) {
        $r[] = ["Déclaration IFI $a (déclaration rectificative)", isset($ifi["R$a"]) ? [montant($ifi["R$a"]['montant'] ?? null)] : null];
    }
    $c = $ifi['COURANTE'] ?? null;
    $r[] = ['Déclaration IFI [AAAA] :', $c ? [texte_ou_null($c['an'] ?? ''), montant($c['montant'] ?? null)] : null];
    $o = $ifi['OPTION'] ?? null;
    $r[] = ['[Option] Déclaration rectificative IFI', $o ? ['', texte_ou_null($o['an'] ?? ''), montant($o['montant'] ?? null)] : null];
    return $r;
}

/** Qualité du signataire d'une société : saisie, reprise du répertoire, sinon déduite de la forme juridique. */
function qualite_signataire(array $v): ?string
{
    $q = trim((string)($v['qualite'] ?? ''));
    if ($q !== '') {
        return preg_match('/^pr[ée]sident/i', $q) ? 'Président' : (preg_match('/^g[ée]rant/i', $q) ? 'Gérant' : $q);
    }
    $forme = mb_strtoupper(trim((string)($v['forme'] ?? '')));
    if (in_array($forme, ['SAS', 'SASU', 'SA', 'SELAS', 'SELASU'], true)) {
        return 'Président';
    }
    return in_array($forme, ['SARL', 'EURL', 'SELARL', 'SELARLU', 'SCI', 'SNC'], true) ? 'Gérant' : null;
}

function taxe_du_modele(string $modele): string
{
    return in_array($modele, ['LMNP', 'IR', 'FONCIER', 'IFI'], true) ? 'TTC' : 'HT';
}

/** « Établissement de la paie : 25 Euros HT par bulletin (à partir de 2027), offert la première année ; » */
function phrase_prestation(array $l, string $taxe): ?string
{
    $m = montant($l['montant'] ?? null);
    $lib = trim((string)($l['libelle'] ?? ''));
    if ($lib === '' || $m === null) {
        return null;
    }
    $unite = ($l['unite'] ?? '') ?: 'forfait';
    $t = "$lib : $m Euros $taxe" . ($unite === 'forfait' ? '' : ' ' . (UNITES[$unite] ?? ''));
    if (trim((string)($l['precision'] ?? '')) !== '') {
        $t .= ' (' . trim((string)$l['precision']) . ')';
    }
    if (!empty($l['offert'])) {
        $t .= ', offert la première année';
    }
    return "$t ;";
}

// ---------- Le document Word (zip + XML) ----------
final class DocWord
{
    /** @var array<string, DOMDocument> parties XML modifiées (document, en-têtes, pieds de page) */
    public array $parties = [];

    public function __construct(private string $fichier)
    {
        $z = new ZipArchive();
        if ($z->open($fichier) !== true) {
            throw new RuntimeException('Modèle Word illisible.');
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            $nom = $z->getNameIndex($i);
            if ($nom === 'word/document.xml' || preg_match('#^word/(header|footer)\d*\.xml$#', $nom)) {
                $d = new DOMDocument();
                $d->preserveWhiteSpace = true;
                $d->loadXML($z->getFromName($nom), LIBXML_NONET | LIBXML_PARSEHUGE);
                $this->parties[$nom] = $d;
            }
        }
        $z->close();
    }

    public function document(): DOMDocument
    {
        return $this->parties['word/document.xml'];
    }

    /** Tous les paragraphes : corps (tableaux compris), puis en-têtes et pieds de page. */
    public function paragraphes(): array
    {
        $out = [];
        foreach ($this->parties as $d) {
            foreach ($d->getElementsByTagNameNS(W_NS, 'p') as $p) {
                $out[] = $p;
            }
        }
        return $out;
    }

    public static function runs(DOMElement $p): array
    {
        $out = [];
        foreach ($p->childNodes as $n) {
            if ($n instanceof DOMElement && $n->namespaceURI === W_NS && $n->localName === 'r') {
                $out[] = $n;
            }
        }
        return $out;
    }

    public static function texte_run(DOMElement $r): string
    {
        $t = '';
        foreach ($r->childNodes as $n) {
            if (!$n instanceof DOMElement || $n->namespaceURI !== W_NS) {
                continue;
            }
            $t .= match ($n->localName) { 't' => $n->textContent, 'tab' => "\t", 'br', 'cr' => "\n", default => '' };
        }
        return $t;
    }

    /** Texte du paragraphe (liens compris), sans celui des zones de texte qu'il contient (traitées à part). */
    public static function texte(DOMElement $p): string
    {
        $t = '';
        foreach ($p->getElementsByTagNameNS(W_NS, 'r') as $r) {
            $parent = $r->parentNode;
            while ($parent && !($parent instanceof DOMElement && $parent->localName === 'p' && $parent->namespaceURI === W_NS)) {
                $parent = $parent->parentNode;
            }
            if ($parent === $p) {
                $t .= self::texte_run($r);
            }
        }
        return $t;
    }

    public static function changer_texte_run(DOMElement $r, string $texte): void
    {
        foreach (iterator_to_array($r->childNodes) as $n) {
            if (!($n instanceof DOMElement && $n->localName === 'rPr')) {
                $r->removeChild($n);
            }
        }
        $d = $r->ownerDocument;
        foreach (preg_split('/(\t|\n)/', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) as $morceau) {
            if ($morceau === "\t") {
                $r->appendChild($d->createElementNS(W_NS, 'w:tab'));
            } elseif ($morceau === "\n") {
                $r->appendChild($d->createElementNS(W_NS, 'w:br'));
            } elseif ($morceau !== '') {
                $t = $d->createElementNS(W_NS, 'w:t');
                $t->setAttributeNS(XML_NS, 'xml:space', 'preserve');
                $t->appendChild($d->createTextNode($morceau));
                $r->appendChild($t);
            }
        }
    }

    public static function sans_surlignage(DOMElement $r): void
    {
        foreach ($r->getElementsByTagNameNS(W_NS, 'highlight') as $h) {
            $h->parentNode->removeChild($h);
            return;
        }
    }

    public function enregistrer(string $sortie): void
    {
        copy($this->fichier, $sortie);
        $z = new ZipArchive();
        $z->open($sortie);
        foreach ($this->parties as $nom => $d) {
            $z->addFromString($nom, $d->saveXML());
        }
        $z->close();
    }
}

/** Remplit le document ; retourne les zones restées à compléter (texte du paragraphe abrégé). */
function lettres_remplir(DocWord $doc, array $v, string $modele): array
{
    $regles = lettres_regles($v, $modele);
    $restantes = [];
    $derniere = null;  // dernière ligne d'honoraires gardée : les prestations complémentaires s'insèrent après
    foreach ($doc->paragraphes() as $p) {
        if (!$p->parentNode) {
            continue;
        }
        $brut = DocWord::texte($p);
        if (!preg_match(ZONE, $brut)) {
            continue;
        }
        $texte = str_replace(["\u{a0}", "\u{202f}"], ' ', $brut);  // les modèles mettent parfois des espaces insécables
        $regle = null;
        foreach ($regles as $rg) {
            if (str_contains($texte, $rg[0])) {
                $regle = $rg;
                break;
            }
        }
        if ($regle === null) {
            $restantes[] = mb_substr(trim($brut), 0, 90);
            continue;
        }
        if ($regle[1] === null) {  // ligne facultative non retenue
            $p->parentNode->removeChild($p);
            continue;
        }
        if (in_array($regle[0], HONORAIRES, true)) {
            $derniere = $p;
        }
        if ($regle[0] === 'Forfait mensuel de' && in_array($v['unite_tenue'] ?? '', ['trimestre', 'an'], true)) {
            foreach (DocWord::runs($p) as $r) {
                $t = DocWord::texte_run($r);
                if (str_contains($t, 'mensuel')) {
                    DocWord::changer_texte_run($r, preg_replace('/mensuel/', FORFAIT_UNITE[$v['unite_tenue']], $t, 1));
                    break;
                }
            }
        }
        $valeurs = $regle[1];
        $efface = in_array('', $regle[1], true);
        foreach (DocWord::runs($p) as $r) {
            $avant = DocWord::texte_run($r);
            if (!preg_match(ZONE, $avant)) {
                continue;
            }
            $apres = preg_replace_callback(ZONE, function ($m) use (&$valeurs) {
                if (!$valeurs) {
                    return $m[0];
                }
                $val = array_shift($valeurs);
                return $val === null ? $m[0] : $val;
            }, $avant);
            if ($efface) {  // zone effacée (mention interne, « [Option] ») : pas de double espace
                $apres = preg_replace('/ {2,}/', ' ', $apres);
                if (str_starts_with(ltrim($avant), '[')) {
                    $apres = ltrim($apres, ' ');
                }
            }
            DocWord::changer_texte_run($r, $apres);
            if (!preg_match(ZONE, $apres)) {
                DocWord::sans_surlignage($r);
            }
        }
        if (str_starts_with($regle[0], '[Option]')) {
            foreach (DocWord::runs($p) as $r) {
                $t = DocWord::texte_run($r);
                if ($t !== '') {
                    DocWord::changer_texte_run($r, ltrim($t));
                    break;
                }
            }
        }
        if (str_starts_with($regle[0], 'EI [') && ($v['type'] ?? '') === 'SOCIETE') {  // TVA d'une société : « SAS DUPONT »
            foreach (DocWord::runs($p) as $r) {
                $t = DocWord::texte_run($r);
                if (str_starts_with($t, 'EI ')) {
                    DocWord::changer_texte_run($r, ltrim(mb_strtoupper(trim((string)($v['forme'] ?? ''))) . ' ' . substr($t, 3)));
                }
            }
        }
        if (preg_match(ZONE, DocWord::texte($p))) {
            $restantes[] = mb_substr(trim(DocWord::texte($p)), 0, 90);
        }
    }
    $textes = array_values(array_filter(array_map(fn($l) => phrase_prestation($l, taxe_du_modele($modele)), $v['prestations'] ?? [])));
    if ($derniere !== null && $textes) {
        $textes[count($textes) - 1] = substr($textes[count($textes) - 1], 0, -2) . '.';  // la liste finit par un point…
        foreach (array_reverse(DocWord::runs($derniere)) as $r) {  // … et la ligne du modèle d'avant passe au « ; »
            $t = DocWord::texte_run($r);
            if (trim($t) !== '') {
                DocWord::changer_texte_run($r, preg_replace('/\s*\.\s*$/u', ' ;', $t));
                break;
            }
        }
        $apres = $derniere;
        foreach ($textes as $texte) {
            $nouvelle = $derniere->cloneNode(true);  // même mise en forme que les lignes du modèle (puce, retrait)
            $apres->parentNode->insertBefore($nouvelle, $apres->nextSibling);
            $apres = $nouvelle;
            $premier = true;
            foreach ($nouvelle->getElementsByTagNameNS(W_NS, 't') as $t) {
                $t->nodeValue = '';
                if ($premier) {
                    $t->appendChild($t->ownerDocument->createTextNode($texte));
                    $t->setAttributeNS(XML_NS, 'xml:space', 'preserve');
                    $premier = false;
                }
            }
            foreach (iterator_to_array($nouvelle->getElementsByTagNameNS(W_NS, 'highlight')) as $h) {
                $h->parentNode->removeChild($h);
            }
        }
    }
    return $restantes;
}

// ---------- Annexe 2 : tableau de répartition des travaux (41 lignes) ----------
const QUI = ['Cabinet', 'Client', 'Non défini'];
const TARIFS = ['Forfait comptable', "à l'acte", 'au temps passé', 'N/A', 'Non définie'];
const COULEURS = ['Cabinet' => ['D9F0D3', '262626'], 'Client' => ['7F7F7F', 'FFFFFF'], 'Non défini' => ['A6A6A6', 'FFFFFF'],
    'Forfait comptable' => ['D9F0D3', '262626'], "à l'acte" => ['FCE4D6', '262626'], 'au temps passé' => ['FCE4D6', '262626'],
    'N/A' => ['7F7F7F', 'FFFFFF'], 'Non définie' => ['A6A6A6', 'FFFFFF']];

function repartition_modele(): array
{
    $C = 'Cabinet'; $F = 'Forfait comptable'; $A = "à l'acte"; $T = 'au temps passé';
    return [
        ['Comptabilité', 'Transmission des justificatifs', 'Client', 'N/A'], ['Comptabilité', 'Conservation des justificatifs', 'Client', 'N/A'],
        ['Comptabilité', 'Inventaire de caisse, état de caisse', 'Client', 'N/A'], ['Comptabilité', 'Inventaire, valorisation, état des stocks', 'Client', 'N/A'],
        ['Comptabilité', 'Tenue des journaux (AC, VT, BQ, OD …)', $C, $F], ['Comptabilité', 'Saisie des écritures d’inventaires', $C, $F],
        ['Comptabilité', 'Edition des états de synthèse (BG, GL …)', $C, $F], ['Comptabilité', 'Edition du fichier FEC', $C, $F],
        ['Comptabilité', 'Préparation de l’annexe légale', $C, $F], ['Fiscalité', 'Établissement de la liasse fiscale', $C, $F],
        ['Fiscalité', 'Déclaration de résultat (liasse fiscale)', $C, $F], ['Fiscalité', 'Déclarations de TVA (CA3, CA3T, CA12)', $C, $F],
        ['Fiscalité', 'Guichet unique de TVA', $C, $F], ['Fiscalité', 'Demande de remboursement de TVA', $C, $A],  // faite par le cabinet, hors forfait
        ['Fiscalité', 'Déclarations IS (2571, 2572, 2573)', $C, $F],
        ['Fiscalité', 'Déclarations CVAE (1329)', $C, $F], ['Fiscalité', 'Déclarations RCM (2777)', $C, $F],
        ['Fiscalité', 'Déclarations IFU (2561)', $C, $F], ['Fiscalité', 'Déclarations CFE', $C, $F],
        ['Fiscalité', 'Déclarations Taxe foncière', 'Non défini', 'Non définie'], ['Fiscalité', 'Déclarations DAS2', $C, $F],
        ['Fiscalité', 'Déclarations Récapitulatif TVA (DEB/DES)', $C, $F], ['Fiscalité', 'Déclarations Enquête statistique EMEBI', 'Non défini', 'Non définie'],
        ['Fiscalité', 'Déclarations TVTS', $C, $F], ['Fiscalité', 'Déclarations de la Taxe sur les salaires', 'Non défini', 'Non définie'],
        ['Gestion TNS', 'Calcul de la provision théorique TNS', $C, $F], ['Gestion TNS', 'Edition du récapitulatif IR (DRITI)', $C, $F],
        ['Juridique', 'Création de la société', $C, $A], ['Juridique', 'Établissement de l’AG d’affection du résultat', $C, $A],
        ['Juridique', 'Dépôt des comptes INPI', $C, $A], ['Juridique', 'Déclaration des bénéficiaires effectifs', $C, $A],
        ['Social', 'Établissement des contrats de travail', 'Client', 'N/A'], ['Social', 'Établissement des bulletins de paie', 'Non défini', $A],
        ['Social', 'Établissement des déclarations sociales (DSN)', 'Non défini', $A], ['Autres missions', 'Prévisionnel ou Business Plan', 'Non défini', $T],
        ['Autres missions', 'Situations comptables', 'Non défini', $T], ['Autres missions', 'Tableau de bord', 'Non défini', $T],
        ['Autres missions', 'Attestations', 'Non défini', $T], ['Autres missions', 'Examen de conformité fiscale', 'Non défini', $T],
        ['Autres missions', 'Refonte des processus administratifs', 'Non défini', $T],
        ['Autres missions', 'Assistance en cas de contrôle fiscal / social', 'Non défini', $T],
    ];
}

/** Tableau du modèle, ajusté au dossier : paie au cabinet si une prestation de paie est prévue ; création seulement en création. */
function repartition_par_defaut(array $v): array
{
    $lignes = repartition_modele();
    $textes = mb_strtolower(implode(' ', array_map(fn($l) => (string)($l['libelle'] ?? ''), $v['prestations'] ?? [])));
    foreach ($lignes as &$l) {
        if ((str_contains($textes, 'paie') || str_contains($textes, 'bulletin'))
            && in_array($l[1], ['Établissement des bulletins de paie', 'Établissement des déclarations sociales (DSN)'], true)) {
            $l[2] = 'Cabinet';
        }
        if (empty($v['en_creation']) && $l[1] === 'Création de la société') {
            $l[2] = 'Non défini';
            $l[3] = 'Non définie';
        }
    }
    unset($l);
    return $lignes;
}

function xml_echap(string $t): string
{
    return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Cellule du tableau : largeur, bordures, fond, texte (Calibri Light), fusion verticale éventuelle. */
function cellule_xml(int $largeur, string $texte, array $bords, ?string $fond = null, string $couleur = '262626', bool $centre = false,
    int $taille = 9, ?string $fusion = null, string $vertical = 'center'): string
{
    $b = '';
    foreach (['top', 'left', 'bottom', 'right'] as $cote) {
        $v = $bords[$cote] ?? null;
        $b .= $v === null ? "<w:$cote w:val=\"nil\"/>" : "<w:$cote w:val=\"single\" w:color=\"{$v[0]}\" w:sz=\"{$v[1]}\" w:space=\"0\"/>";
    }
    $tc = "<w:tcW w:w=\"$largeur\" w:type=\"dxa\"/>" . ($fusion ? "<w:vMerge" . ($fusion === 'debut' ? ' w:val="restart"' : '') . '/>' : '')
        . "<w:tcBorders>$b</w:tcBorders>" . ($fond ? "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"$fond\"/>" : '') . "<w:vAlign w:val=\"$vertical\"/>";
    $run = $texte === '' ? '' : '<w:r><w:rPr><w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light" w:eastAsia="Calibri Light" w:cs="Calibri Light"/>'
        . "<w:color w:val=\"$couleur\"/><w:sz w:val=\"" . ($taille * 2) . '"/></w:rPr><w:t xml:space="preserve">' . xml_echap($texte) . '</w:t></w:r>';
    return "<w:tc><w:tcPr>$tc</w:tcPr><w:p><w:pPr><w:spacing w:before=\"0\" w:after=\"0\" w:line=\"240\" w:lineRule=\"auto\"/>"
        . ($centre ? '<w:jc w:val="center"/>' : '') . "</w:pPr>$run</w:p></w:tc>";
}

/** Ajoute le tableau Type | Missions | Qui s'en charge ? | Tarification à la fin de la lettre (modèles qui ont l'annexe 2). */
function ajouter_annexe2(DocWord $doc, array $lignes): bool
{
    $a_annexe = false;
    foreach ($doc->paragraphes() as $p) {
        if (str_starts_with(trim(DocWord::texte($p)), 'Annexe 2')) {
            $a_annexe = true;
            break;
        }
    }
    if (!$lignes || !$a_annexe) {
        return false;
    }
    $L = [1474, 3742, 2041, 2041];  // 2,6 / 6,6 / 3,6 / 3,6 cm
    $G = ['BFBFBF', 6]; $GC = ['E3E3E3', 4]; $B = ['FFFFFF', 12];
    $xml = '<w:tbl xmlns:w="' . W_NS . '"><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="fixed"/>'
        . '<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/></w:tblPr><w:tblGrid>';
    foreach ($L as $l) {
        $xml .= "<w:gridCol w:w=\"$l\"/>";
    }
    $xml .= '</w:tblGrid><w:tr><w:trPr><w:trHeight w:val="283" w:hRule="atLeast"/></w:trPr>';
    foreach (['Type', 'Missions', 'Qui s’en charge ?', 'Tarification'] as $i => $titre) {
        $xml .= cellule_xml($L[$i], $titre, ['top' => $G, 'bottom' => $G, 'left' => $i === 0 ? $G : null], null, '262626', $i >= 2, 10);
    }
    $xml .= '</w:tr>';
    $groupes = [];
    foreach ($lignes as $l) {
        [$type, $mission, $qui, $tarif] = array_values($l);
        if (!$groupes || $groupes[count($groupes) - 1][0] !== $type) {
            $groupes[] = [$type, []];
        }
        $groupes[count($groupes) - 1][1][] = [$mission, $qui, $tarif];
    }
    foreach ($groupes as [$type, $lg]) {
        $n = count($lg);
        foreach ($lg as $k => [$mission, $qui, $tarif]) {
            $dernier = $k === $n - 1;
            $fusion = $n > 1 ? ($k === 0 ? 'debut' : 'suite') : null;
            $xml .= '<w:tr><w:trPr><w:trHeight w:val="232" w:hRule="atLeast"/></w:trPr>'
                . cellule_xml($L[0], $k === 0 ? $type : '', ['left' => $G, 'bottom' => $G, 'top' => $G], null, '262626', false, 9, $fusion, 'top')
                . cellule_xml($L[1], $mission, ['bottom' => $dernier ? $G : $GC]);
            foreach ([[$L[2], $qui], [$L[3], $tarif]] as [$larg, $val]) {
                [$fond, $coul] = COULEURS[$val] ?? ['FFFFFF', '262626'];
                $xml .= cellule_xml($larg, $val, ['top' => $B, 'bottom' => $dernier ? $G : $B, 'left' => $B, 'right' => $B], $fond, $coul, true);
            }
            $xml .= '</w:tr>';
        }
    }
    $xml .= '</w:tbl>';
    $d = $doc->document();
    $frag = new DOMDocument();
    $frag->loadXML($xml);
    $tbl = $d->importNode($frag->documentElement, true);
    $corps = $d->getElementsByTagNameNS(W_NS, 'body')->item(0);
    $sect = null;
    foreach ($corps->childNodes as $n) {
        if ($n instanceof DOMElement && $n->localName === 'sectPr') {
            $sect = $n;
        }
    }
    $corps->insertBefore($tbl, $sect);
    $corps->insertBefore($d->createElementNS(W_NS, 'w:p'), $sect);  // Word exige un paragraphe après un tableau en fin de section
    return true;
}

// ---------- Index des lettres, génération ----------
function lettres_index(): array
{
    $index = lire_json(lettres_dossier() . '/index.json', []);
    foreach ($index as &$e) {  // intitulé actuel (sans « avec / sans assurance »)
        if (isset(MODELES_LETTRE[$e['modele'] ?? ''])) {
            $e['libelle'] = MODELES_LETTRE[$e['modele']][0];
        }
    }
    unset($e);
    return $index;
}

function lettres_ecrire_index(array $index): void
{
    ecrire_json(lettres_dossier() . '/index.json', array_values($index));
}

function lettre_trouver(?string $id): ?array
{
    foreach (lettres_index() as $e) {
        if ($e['id'] === $id) {
            return $e;
        }
    }
    return null;
}

function lettre_chemin(?string $id, string $ext = 'docx'): ?string
{
    if (!preg_match('/^[0-9a-f]{32}$/', (string)$id)) {
        return null;
    }
    $p = lettres_dossier() . "/$id.$ext";
    return is_file($p) ? $p : null;
}

function nom_fichier_lettre(array $v, string $modele): string
{
    $qui = ($v['type'] ?? '') === 'SOCIETE' ? (string)($v['denomination'] ?? '')
        : trim(mb_strtoupper((string)($v['nom'] ?? '')) . ' ' . ($v['prenom'] ?? ''));
    $qui = trim($qui) ?: 'CLIENT';
    $propre = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', 'Lettre de mission - ' . MODELES_LETTRE[$modele][0] . " - $qui"));
    return preg_replace('/\s{2,}/', ' ', $propre) . '.docx';
}

/** Nom du dossier du client dans SharePoint › Lettres de mission (« ATELIER DUPAIN », « ANNE LEMOINE », « LMNP DURAND »). */
function nom_dossier_client(array $v): string
{
    if (($v['type'] ?? '') === 'SOCIETE' && trim((string)($v['denomination'] ?? '')) !== '') {
        return sans_accents_majuscules((string)$v['denomination']);
    }
    $personne = trim(trim((string)($v['prenom'] ?? '')) . ' ' . trim((string)($v['nom'] ?? '')));
    if (($v['modele'] ?? '') === 'LMNP') {
        return sans_accents_majuscules('LMNP ' . (trim((string)($v['nom'] ?? '')) ?: $personne));
    }
    return sans_accents_majuscules($personne ?: (string)($v['denomination'] ?? '') ?: 'CLIENT');
}

/** Crée (ou recrée, si $id) la lettre Word. Le PDF vient ensuite de Microsoft (sharepoint.php, POST /api/lettres/<id>/pdf). */
function lettres_generer(array $v, string $auteur, ?string $id = null): array
{
    $modele = $v['modele'] ?? '';
    if (!isset(MODELES_LETTRE[$modele])) {
        throw new ErreurSaisie(['modele' => 'Choisir une lettre de mission.']);
    }
    [$libelle, , , $sans, $avec] = MODELES_LETTRE[$modele];
    $doc = new DocWord(MODELES_DIR . '/' . (!empty($v['partenaire']) && $avec ? $avec : $sans));
    $restantes = lettres_remplir($doc, $v, $modele);
    ajouter_annexe2($doc, is_array($v['repartition'] ?? null) ? $v['repartition'] : repartition_par_defaut($v));
    $index = lettres_index();
    $ancienne = $id ? lettre_trouver($id) : null;
    $ident = $ancienne['id'] ?? uuid_hex();
    if (!is_dir(lettres_dossier())) {
        mkdir(lettres_dossier(), 0770, true);
    }
    $doc->enregistrer(lettres_dossier() . "/$ident.docx");
    @unlink(lettres_dossier() . "/$ident.pdf");  // l'ancien PDF ne correspond plus : il est refait par Microsoft (POST /api/lettres/<id>/pdf)
    $entree = ['id' => $ident, 'modele' => $modele, 'libelle' => $libelle, 'partenaire' => !empty($v['partenaire']) && $avec, 'fichier' => nom_fichier_lettre($v, $modele),
        'dossier' => ['source' => $v['source'] ?? '', 'ref' => $v['ref'] ?? '',
            'nom' => trim((string)(($v['denomination'] ?? '') ?: trim(($v['prenom'] ?? '') . ' ' . ($v['nom'] ?? ''))))],
        'dossier_onedrive' => nom_dossier_client($v),
        'valeurs' => $v, 'restantes' => $restantes, 'pdf' => false, 'classement' => null, 'le' => maintenant_minutes(), 'par' => $auteur,
        'cree_le' => $ancienne['cree_le'] ?? maintenant_minutes()];
    $index = array_values(array_filter($index, fn($e) => $e['id'] !== $ident));
    $index[] = $entree;
    lettres_ecrire_index($index);
    return $entree;
}

// ---------- Lettre confraternelle (reprise du dossier d'un confrère) ----------
/** Texte de la lettre confraternelle, générique : le cabinet l'ajuste dans Word au besoin. */
function texte_confraternelle(array $p, string $signataire): array
{
    $a = $p['ancien'] ?? [];
    $expert = trim(implode(' ', array_filter([LIBELLES_CIVILITE[$a['civilite'] ?? ''] ?? '', $a['prenom'] ?? '', mb_strtoupper((string)($a['nom'] ?? ''))],
        fn($x) => $x !== '' && $x !== '–')));
    $appel = match ($a['civilite'] ?? '') { 'MME' => 'Chère Consœur', 'M' => 'Cher Confrère', default => 'Chère Consœur, Cher Confrère' };
    $client = trim(($p['forme'] ?? '') . ' ' . $p['denomination']);
    $missions = array_map(fn($m) => mb_strtolower(preg_replace('/\s*\(.*\)$/', '', $m)), $p['missions'] ?? []);
    return [
        'destinataire' => array_values(array_filter([($p['cabinet_precedent'] ?? '') ?: ($a['cabinet'] ?? ''), $expert ? "A l’attention de $expert" : '',
            $a['adresse'] ?? '', trim(($a['cp'] ?? '') . ' ' . ($a['ville'] ?? ''))])),
        'date' => 'Fait à ' . CABINET_VILLE . ', le ' . date_longue(aujourdhui()),
        'objet' => "Objet : reprise du dossier $client" . (($p['siren'] ?? '') ? " (SIREN {$p['siren']})" : ''),
        'corps' => [
            "$appel,",
            "$client nous a fait part de son souhait de confier à notre cabinet " . ($missions ? 'les missions suivantes : ' . implode(', ', $missions)
                : 'son accompagnement comptable et fiscal') . ', que vous assuriez jusqu’à présent.',
            'Conformément aux règles de confraternité de notre profession, nous tenions à vous en informer avant d’accepter cette mission. '
                . 'Nous vous serions reconnaissants de bien vouloir nous faire savoir si vous avez connaissance d’éléments qui s’opposeraient '
                . 'à notre intervention, et si des honoraires vous restent dus par ce client.',
            'Afin d’assurer la continuité du dossier, nous vous remercions par avance de bien vouloir nous transmettre, avec l’accord de notre client '
                . 'commun, les éléments nécessaires à la reprise : derniers comptes annuels et liasse fiscale, balance et grand livre, fichier des '
                . 'écritures comptables (FEC) du dernier exercice, déclarations fiscales en cours, ainsi que tout document utile au suivi du dossier.',
            'Nous restons à votre disposition pour organiser cette transition dans les meilleures conditions.',
            "Nous vous prions d’agréer, $appel, l’expression de nos sentiments confraternels.",
        ],
        'signature' => ['Pour ' . mb_strtoupper(CABINET_NOM), $signataire],
    ];
}

/** Lettre Word sur le papier du cabinet (en-tête repris du modèle de lettre de mission), avec le texte de texte_confraternelle(). */
function confraternelle_docx(array $t, string $sortie): void
{
    $doc = new DocWord(MODELES_DIR . '/' . MODELES_LETTRE['PRESENTATION'][3]);
    $d = $doc->document();
    $corps = $d->getElementsByTagNameNS(W_NS, 'body')->item(0);
    $enfants = array_values(array_filter(iterator_to_array($corps->childNodes), fn($n) => $n instanceof DOMElement));
    $modele = function (string $debut) use ($enfants): DOMElement {
        foreach ($enfants as $n) {
            if ($n->localName === 'p' && str_starts_with(ltrim(DocWord::texte($n), "\t"), $debut)) {
                return $n->cloneNode(true);
            }
        }
        throw new RuntimeException("Modèle de lettre inattendu ($debut).");
    };
    $droite = $modele('A l');      // bloc destinataire, décalé à droite par des tabulations
    $texte = $modele('Vous avez manifesté');
    $vide = $enfants[2]->cloneNode(true);
    foreach (array_slice($enfants, 10) as $n) {  // on garde l'en-tête du cabinet (début du modèle) et la mise en page finale
        if ($n->localName !== 'sectPr') {
            $corps->removeChild($n);
        }
    }
    $fin = $corps->lastChild;
    $ajouter = function (DOMElement $m, string $ligne, bool $gras = false) use ($corps, $fin, $d): void {
        $p = $m->cloneNode(true);
        $runs = DocWord::runs($p);
        foreach ($p->childNodes as $n) {  // signets, corrections : seul le premier morceau de texte reste
            if ($n instanceof DOMElement && $n->localName !== 'pPr' && $n !== ($runs[0] ?? null)) {
                $suppr[] = $n;
            }
        }
        foreach ($suppr ?? [] as $n) {
            $p->removeChild($n);
        }
        $r = $runs[0] ?? $p->appendChild($d->createElementNS(W_NS, 'w:r'));
        DocWord::changer_texte_run($r, $ligne);
        DocWord::sans_surlignage($r);
        if ($gras) {
            $rpr = $r->getElementsByTagNameNS(W_NS, 'rPr')->item(0) ?? $r->insertBefore($d->createElementNS(W_NS, 'w:rPr'), $r->firstChild);
            $rpr->appendChild($d->createElementNS(W_NS, 'w:b'));
        }
        $corps->insertBefore($p, $fin);
    };
    foreach ($t['destinataire'] as $i => $l) {
        $ajouter($droite, str_repeat("\t", 6) . $l, $i === 0);
    }
    $ajouter($vide, '');
    $ajouter($droite, str_repeat("\t", 6) . $t['date']);
    $ajouter($vide, '');
    $ajouter($texte, $t['objet'], true);
    $ajouter($vide, '');
    foreach ($t['corps'] as $l) {
        $ajouter($texte, $l);
        $ajouter($vide, '');
    }
    $ajouter($vide, '');
    foreach ($t['signature'] as $i => $l) {
        $ajouter($droite, str_repeat("\t", 6) . $l, $i === 1);
    }
    $doc->enregistrer($sortie);
}

/** Crée (ou recrée) la lettre confraternelle du prospect : rangée avec les lettres (Livrables), PDF par Microsoft ensuite. */
function confraternelle_generer(array $p, string $auteur): array
{
    $index = lettres_index();
    $ancienne = lettre_trouver($p['confraternelle']['lettre'] ?? null);
    $ident = $ancienne['id'] ?? uuid_hex();
    if (!is_dir(lettres_dossier())) {
        mkdir(lettres_dossier(), 0770, true);
    }
    confraternelle_docx(texte_confraternelle($p, $auteur), lettres_dossier() . "/$ident.docx");
    @unlink(lettres_dossier() . "/$ident.pdf");
    $v = prefill_prospect($p);
    $nom = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', "Lettre confraternelle - {$p['denomination']}"));
    $entree = ['id' => $ident, 'modele' => 'CONFRATERNELLE', 'libelle' => 'Lettre confraternelle', 'partenaire' => false, 'fichier' => "$nom.docx",
        'dossier' => ['source' => 'prospect', 'ref' => $p['id'], 'nom' => $p['denomination']], 'dossier_onedrive' => nom_dossier_client($v),
        'valeurs' => ['email' => $p['ancien']['email'] ?? ''], 'restantes' => [], 'pdf' => false, 'classement' => null,
        'le' => maintenant_minutes(), 'par' => $auteur, 'cree_le' => $ancienne['cree_le'] ?? maintenant_minutes()];
    $index = array_values(array_filter($index, fn($e) => $e['id'] !== $ident));
    $index[] = $entree;
    lettres_ecrire_index($index);
    return $entree;
}

function lettres_supprimer(string $id): void
{
    foreach (['docx', 'pdf'] as $ext) {
        if ($p = lettre_chemin($id, $ext)) {
            unlink($p);
        }
    }
    lettres_ecrire_index(array_filter(lettres_index(), fn($e) => $e['id'] !== $id));
}

// ---------- Pré-remplissage : tout ce que le CRM sait déjà du dossier ----------
function prefill_prospect(array $p): array
{
    $contact = null;
    foreach ($p['contacts'] ?? [] as $c) {
        if (in_array($c['role'] ?? '', ['REPRESENTANT_LEGAL', 'DECLARANT'], true)) {
            $contact = $c;
            break;
        }
    }
    $contact ??= $p['contacts'][0] ?? [];
    $t = type_prospect($p);
    $v = ['source' => 'prospect', 'ref' => $p['id'], 'type' => $t, 'denomination' => $t === 'SOCIETE' ? ($p['denomination'] ?? '') : '',
        'forme' => ($p['forme'] ?? '') ?: ($p['forme_envisagee'] ?? ''), 'siren' => $p['siren'] ?? '', 'civilite' => $contact['civilite'] ?? '',
        'prenom' => $contact['prenom'] ?? '', 'nom' => $contact['nom'] ?? '', 'email' => $contact['email'] ?? '',
        'responsable' => $p['responsable'] ?? '', 'nb_personnes' => $p['nb_personnes'] ?? '', 'nb_biens' => $p['nb_biens'] ?? '',
        'partenaire' => !empty($p['partenaire']), 'regime' => $p['regime'] ?? '', 'en_creation' => !empty($p['en_creation']), 'modeles_suggeres' => []];
    foreach (['adresse', 'cp', 'ville'] as $k) {  // siège repris du registre à la création
        if (($p[$k] ?? '') !== '') {
            $v[$k] = $p[$k];
        }
    }
    if (($contact['fonction'] ?? '') !== '') {
        $v['qualite'] = $contact['fonction'];
    }

    if ($t === 'PARTICULIER' && $v['prenom'] === '' && $v['nom'] === '') {
        $v['nom'] = $p['denomination'] ?? '';
    }
    foreach ($p['missions'] ?? [] as $m) {
        $code = MISSION_VERS_MODELE[$m] ?? null;
        if ($code && !in_array($code, $v['modeles_suggeres'], true) && in_array($v['type'], MODELES_LETTRE[$code][2], true)) {
            $v['modeles_suggeres'][] = $code;
        }
    }
    if (($p['regime'] ?? '') === 'LMNP' && !in_array('LMNP', $v['modeles_suggeres'], true)) {
        $v['modeles_suggeres'][] = 'LMNP';
    }
    foreach ($p['honoraires']['lignes'] ?? [] as $l) {  // gain potentiel saisi : repris comme forfait mensuel
        if (($l['unite'] ?? '') === 'mois' && empty($l['offert'])) {
            $m = (float)$l['montant'];
            $v['forfait_mensuel'] = floor($m) == $m ? (string)(int)$m : (string)$m;
            break;
        }
    }
    return $v;
}

function prefill_client(array $c): array
{
    $parts = explode(' ', (string)($c['contact'] ?? ''), 2);
    $t = ['SOCIETE' => 'SOCIETE', 'EI' => 'EI', 'LMNP' => 'EI', 'PARTICULIER' => 'PARTICULIER'][$c['type'] ?? ''] ?? 'SOCIETE';
    return ['source' => 'client', 'ref' => $c['id'], 'type' => $t, 'denomination' => $t === 'SOCIETE' ? ($c['nom'] ?? '') : '',
        'forme' => $c['forme'] ?? '', 'siren' => $c['siren'] ?? '', 'civilite' => '', 'prenom' => $parts[0] ?? '', 'nom' => $parts[1] ?? '',
        'email' => $c['email'] ?? '', 'adresse' => mb_convert_case((string)($c['adresse'] ?? ''), MB_CASE_TITLE), 'cp' => $c['cp'] ?? '',
        'ville' => $c['ville'] ?? '', 'activite' => mb_strtolower((string)($c['activite'] ?? '')), 'responsable' => $c['collaborateur'] ?? '',
        'qualite' => $c['qualite'] ?? '', 'regime' => ($c['type'] ?? '') === 'LMNP' ? 'LMNP' : '',
        'modeles_suggeres' => ($c['type'] ?? '') === 'LMNP' ? ['LMNP'] : []];
}

/** Adresse du siège, forme et dirigeant, depuis le répertoire officiel (SIREN). */
function prefill_repertoire(string $siren): array
{
    $e = entreprises_fiche($siren);
    if (!$e) {
        return [];
    }
    $s = $e['siege'] ?? [];
    $voie = implode(' ', array_filter([$s['numero_voie'] ?? '', $s['indice_repetition'] ?? '', $s['type_voie'] ?? '', $s['libelle_voie'] ?? '']));
    $d = $e['dirigeants'][0] ?? [];
    return ['adresse' => mb_convert_case($voie, MB_CASE_TITLE), 'cp' => $s['code_postal'] ?? '', 'ville' => $s['libelle_commune'] ?? '',
        'forme' => FORMES_JURIDIQUES[(string)($e['nature_juridique'] ?? '')] ?? '', 'qualite' => $d['qualite'] ?? '',
        'dirigeant_prenom' => mb_convert_case(explode(' ', (string)($d['prenoms'] ?? ''))[0], MB_CASE_TITLE), 'dirigeant_nom' => $d['nom'] ?? ''];
}
