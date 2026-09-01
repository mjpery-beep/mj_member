# Widget Prochains Evenements

## Objectif
Concevoir un widget Elementor affichant la liste des prochains evenements (cover, titre, date, lieu, type, prix (si existe)) 
Faire plusieurs propositions de mise en page (liste, grille, slider) avec options de personnalisation.

## Hypotheses
- Les evenements sont accessibles via les classes CRUD existantes (`MjEvents_CRUD`, `MjEventRegistrations`), incluant les metadonnees necessaires (date de debut/fin, lieu, type).
- Le widget doit respecter le mode Apercu Elementor avec un jeu de donnees factices.
- Les styles globaux `css/styles.css` peuvent etre reutilises; de nouveaux styles peuvent etre ajoutes si besoin.

## Taches
1. Verifier la fonction de recuperation des evenements futurs et ajouter un filtre dedie si necessaire.
2. Ajouter les controles Elementor (nombre d evenements, tri, categorie/type, affichage du bouton voir plus).
3. Creer le template PHP dans `includes/templates/elementor/` et implementer le markup BEM avec echappement des donnees.
4. Mettre a jour les scripts/styles front si une interaction (slider, tabs) est requise.
5. Ajouter un mode demo pour l apercu Elementor.
6. Documenter l utilisation du widget dans la section support.

## Criteres d acceptation
- Widget disponible dans Elementor avec options de filtrage funcitonnelles.
- Chaque carte evenement affiche titre, date formatee, lieu et type.
- Respect des standards MJ (BEM, echappement, securite AJAX si necessaire).
- Documentation et capture ecran mises a jour.
