"""Génère les modèles FICTIFS de lettres de mission (modeles/lettres-de-mission/*.docx), sans dépendance.

Les modèles réels d'un cabinet ne sont pas publiés : ceux-ci reprennent seulement la mécanique attendue par src/lettres.php.
Chaque donnée du client est une zone surlignée en jaune entre crochets ; chaque paragraphe qui en contient est reconnu par
une phrase qui lui est propre (lettres_regles). Les dix premiers paragraphes forment l'en-tête du cabinet, repris tel quel
par la lettre confraternelle.
    python outils/generer_modeles.py
"""
import os
import re
import zipfile
from xml.sax.saxutils import escape

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SORTIE = os.path.join(RACINE, "modeles", "lettres-de-mission")
T6 = "\t" * 6

CT = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>"""
RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""
DOC_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""
STYLES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:color w:val="222222"/><w:sz w:val="20"/><w:lang w:val="fr-FR"/></w:rPr></w:rPrDefault>
<w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
</w:styles>"""


def run(texte, gras=False, couleur=None, taille=None, jaune=False):
    rpr = ("<w:b/>" if gras else "") + (f'<w:color w:val="{couleur}"/>' if couleur else "") \
        + (f'<w:sz w:val="{taille}"/>' if taille else "") + ('<w:highlight w:val="yellow"/>' if jaune else "")
    corps = ""
    for morceau in re.split(r"(\t)", texte):
        if morceau == "\t":
            corps += "<w:tab/>"
        elif morceau:
            corps += f'<w:t xml:space="preserve">{escape(morceau)}</w:t>'
    return f"<w:r>{f'<w:rPr>{rpr}</w:rPr>' if rpr else ''}{corps}</w:r>"


def para(texte="", gras=False, couleur=None, taille=None, centre=False, puce=False, trait=False, mince=False, colonnes=False):
    """Paragraphe : chaque zone [..] devient un morceau surligné en jaune, le reste du texte garde la mise en forme."""
    ppr = ('<w:tabs><w:tab w:val="left" w:pos="5103"/></w:tabs>' if colonnes else "") + ('<w:spacing w:after="0" w:line="60" w:lineRule="exact"/>' if mince else "") + ('<w:pBdr><w:bottom w:val="single" w:sz="4" w:space="6" w:color="BBBBBB"/></w:pBdr>' if trait else "") \
        + ('<w:jc w:val="center"/>' if centre else "") + ('<w:ind w:left="360" w:hanging="180"/>' if puce else "")
    runs = "".join(run(m, gras, couleur, taille, jaune=m.startswith("[") and m.endswith("]"))
                   for m in re.split(r"(\[[^\[\]]{1,120}\])", ("• " if puce else "") + texte) if m)
    return f"<w:p>{f'<w:pPr>{ppr}</w:pPr>' if ppr else ''}{runs}</w:p>"


ENTETE = [  # 10 paragraphes : papier à en-tête du cabinet (fictif), noir et gris, sans couleur
    para("CABINET EXEMPLE", gras=True, taille=26),
    para("Expertise comptable et conseil", couleur="666666"),
    para("10 rue de l’Exemple, 75000 Paris · contact@cabinet-exemple.fr · 01 00 00 00 00", couleur="666666", taille=18),
    para("Cabinet fictif de démonstration, sans existence réelle", couleur="999999", taille=16, trait=True),
    para(mince=True), para(mince=True), para(mince=True), para(mince=True), para(mince=True), para(""),
]


def destinataire(societe, ei):
    l = []
    if societe:
        l.append(para(T6 + "[FORME] [DÉNOMINATION]", gras=True))
    if ei:
        l.append(para(T6 + "EI [PRÉNOM NOM]", gras=True))
    return l + [
        para(T6 + "A l’attention de [Madame / Monsieur] [Prénom NOM]"),
        para(T6 + "[Adresse],"),
        para(T6 + "[Code postal] [Ville]"),
        para(""),
        para(T6 + "Fait à [Ville du cabinet], le [date de la lettre]"),
        para(""),
    ]


def debut(titre, societe=False, ei=False):
    return ENTETE + [para(f"Lettre de mission – {titre}", gras=True, taille=24), para("")] \
        + destinataire(societe, ei) + [
        para("Objet : lettre de mission", gras=True),
        para(""),
        para("[Chère Madame / Cher Monsieur] [Prénom NOM],"),
        para("Vous avez manifesté le souhait de confier à notre cabinet la mission décrite ci-dessous. La présente lettre en précise "
             "la nature, l’étendue, les conditions d’exécution et les honoraires, conformément aux normes professionnelles."),
        para(""),
    ]


def fin(signataire_societe, annexe):
    l = [
        para("Votre interlocuteur principal sera [interlocuteur, fonction]."),
        para(""),
        para("Assurance et réclamations", gras=True),
        para("Notre responsabilité civile professionnelle est assurée. Cette assurance couvre [territoire couvert]."),
        para("En cas de litige, vous pouvez recourir gratuitement au médiateur de la consommation [nom du médiateur]."),
        para(""),
        para("Nous vous prions d’agréer l’expression de nos salutations distinguées."),
        para(""),
        para("Pour le cabinet,\tLe client (bon pour accord),", colonnes=True),
        para("L’expert-comptable\t[Gérant / Président]" if signataire_societe else "L’expert-comptable", colonnes=True),
        para("\t[Prénom NOM]", colonnes=True),
    ]
    if annexe:
        l += [para(""), para("Annexe 1 – Conditions générales : voir le site du cabinet (exemple)."),
              para("Annexe 2 – Tableau de répartition des travaux", gras=True)]
    return l


SOCIETE_CORPS = [
    para("Votre société exerce une activité de [activité]."),
    para("[Elle n’emploie aucun salarié / Elle emploie N salariés]."),
    para("Elle portera sur les comptes de l'exercice comptable [ouvert le JJ/MM/AAAA]."),
]


def premier_exercice():
    return [
        para("En cas de rupture avant le terme, la moitié des honoraires dus sur une année fiscale complète, soit [montant] Euros HT, reste due."),
        para("Premier exercice : forfait calculé sur une période de [nombre] mois, soit [montant] Euros HT."),
    ]


MODELES = {
    "PRO - 1 - Présentation des comptes.docx": debut("Présentation des comptes", societe=True) + SOCIETE_CORPS + [
        para("La mission de présentation des comptes vise à vous apporter une assurance modérée sur la cohérence et la vraisemblance des comptes."),
        para("Honoraires", gras=True),
        para("Forfait mensuel de [montant] Euros HT ;", puce=True),
        para("Forfait d’établissement des comptes annuels de [montant] Euros HT ;", puce=True),
        para("Dépôt des comptes annuels : [montant] Euros HT.", puce=True),
    ] + premier_exercice() + fin(True, True),
    "PRO - 2 - Établissement des comptes.docx": debut("Établissement des comptes", societe=True) + SOCIETE_CORPS + [
        para("La mission d’établissement des comptes consiste à produire les comptes annuels à partir des pièces que vous nous transmettez."),
        para("Honoraires", gras=True),
        para("Forfait mensuel de [montant] Euros HT ;", puce=True),
        para("Etablissement des comptes annuels de [montant] Euros HT ;", puce=True),
        para("Dépôt des comptes annuels : [montant] Euros HT.", puce=True),
    ] + premier_exercice() + fin(True, True),
    "PRO - 3 - Assistance aux déclarations de TVA.docx": debut("Assistance aux déclarations fiscales (TVA)", ei=True) + [
        para("Vous exercez une activité de [activité], sous la forme [d’une société / d’une entreprise individuelle]."),
        para("Vous relevez du régime de TVA [régime de TVA]."),
        para("Notre mission comprend la préparation et la télétransmission de vos déclarations de TVA [déclarations]."),
        para("Honoraires", gras=True),
        para("[montant] Euros HT par déclaration de TVA [périodicité].", puce=True),
        para("La mission s’applique aux déclarations déposées à compter du [JJ/MM/AAAA]."),
    ] + fin(False, True),
    "PRO - 4 - BNC (déclaration 2035).docx": debut("BNC (déclaration 2035)", ei=True) + [
        para("Vous exercez une activité libérale de [profession], relevant des bénéfices non commerciaux."),
        para("Honoraires", gras=True),
        para("Forfait mensuel de [montant] Euros HT ;", puce=True),
        para("Forfait d’établissement de la liasse fiscale [AAAA] : [montant] Euros HT.", puce=True),
    ] + fin(False, True),
    "PRO - 5 - BIC au réel (entreprise individuelle).docx": debut("BIC au réel", ei=True) + [
        para("Vous exercez une activité de [activité] en entreprise individuelle, relevant des bénéfices industriels et commerciaux, "
             "au régime [réel simplifié / réel normal]."),
        para("Honoraires", gras=True),
        para("Forfait mensuel de [montant] Euros HT ;", puce=True),
        para("Forfait d’établissement de la liasse fiscale [AAAA] : [montant] Euros HT.", puce=True),
    ] + fin(False, True),
    "PRO - 6 - LMNP (location meublée).docx": debut("LMNP (location meublée)", ei=True) + [
        para("Vous louez un ou plusieurs biens meublés et relevez du statut de loueur en meublé non professionnel (LMNP)."),
        para("Honoraires", gras=True),
        para("Création du statut LMNP : [montant] Euros TTC ;", puce=True),
        para("Forfait d’établissement de la liasse fiscale [AAAA] : [montant] Euros TTC ;", puce=True),
        para("Forfait annuel pour la liasse fiscale [AAAA] et suivants : [montant] Euros TTC.", puce=True),
    ] + fin(False, False),
}


def perso(titre, corps, partenaire):
    extra = [para("Attestation de passage à domicile : [adresse du passage], le [JJ/MM/AAAA]."),
             para("[Note interne : taux et plafonds à confirmer avec le partenaire.]")] if partenaire else []
    return debut(titre) + [
        para("Votre foyer fiscal se compose de [composition du foyer] et perçoit [revenus]."),
        para("Notre mission porte sur vos obligations déclaratives pour l’année fiscale [AAAA]."),
    ] + corps + extra + fin(False, False)


PERSO = {
    "IR (impôt sur le revenu)": [para("Honoraires", gras=True),
                                 para("Forfait pour la déclaration de revenus : [montant] Euros TTC.", puce=True)],
    "Revenus fonciers (2044)": [para("Honoraires", gras=True),
                                para("Forfait pour la déclaration de revenus : [montant] Euros TTC ;", puce=True),
                                para("Supplément de [montant] Euros TTC par bien immobilier déclaré.", puce=True)],
    "IFI (impôt sur la fortune immobilière)": [para("Honoraires", gras=True)] + [
        para(f"Déclaration IFI {a} (déclaration rectificative) : [montant] Euros TTC ;", puce=True) for a in (2023, 2024, 2025)] + [
        para("Déclaration IFI [AAAA] : [montant] Euros TTC ;", puce=True),
        para("[Option] Déclaration rectificative IFI [AAAA] : [montant] Euros TTC.", puce=True)],
}
for nom, corps in PERSO.items():
    MODELES[f"PERSO - {nom} - sans partenaire.docx"] = perso(nom, corps, False)
    MODELES[f"PERSO - {nom} - avec partenaire.docx"] = perso(nom, corps, True)


def ecrire(nom, paragraphes):
    doc = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
           + "".join(paragraphes)
           + '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" '
             'w:header="567" w:footer="567" w:gutter="0"/></w:sectPr></w:body></w:document>')
    with zipfile.ZipFile(os.path.join(SORTIE, nom), "w", zipfile.ZIP_DEFLATED) as z:
        for chemin, contenu in (("[Content_Types].xml", CT), ("_rels/.rels", RELS), ("word/_rels/document.xml.rels", DOC_RELS),
                                ("word/styles.xml", STYLES), ("word/document.xml", doc)):
            z.writestr(chemin, contenu)


if __name__ == "__main__":
    os.makedirs(SORTIE, exist_ok=True)
    for nom, paragraphes in MODELES.items():
        ecrire(nom, paragraphes)
        print("écrit :", nom)
