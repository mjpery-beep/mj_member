# Widget Elementor : Annuaire Animateurs / Coordinateurs

## Objectif
Afficher dans Elementor la carte des animateurs et coordinateurs MJ avec leur photo de couverture, surnom, email cliquable et description courte.

## Pré-requis côté données
- Les membres doivent utiliser les rôles `animateur` ou `coordinateur` dans le module membres.
- Remplir les champs *Surnom*, *Description courte* et *Adresse e-mail* dans la fiche membre (`Membres > Modifier`).
- Ajouter une photo de profil (optionnel mais recommandé) via le bloc « Photo de profil » du formulaire membre.
- Vérifier que le statut du membre est **Actif** pour apparaître par défaut.

## Insertion dans Elementor
1. Éditer la page avec Elementor et rechercher "MJ – Team Directory" dans le panneau Widgets.
2. Glisser le widget à l’endroit souhaité.
3. Adapter les champs **Titre** et **Description** pour présenter l’équipe.
4. Dans l’onglet **Source** :
   - *Rôles affichés* : choisir Animateurs, Coordinateurs ou les deux.
   - *Nombre maximum* : laisser `0` pour tout afficher ou définir une limite.
   - *Trier par / Ordre* : sélectionner l’ordre d’affichage (nom, prénom, surnom, dates).
   - *Afficher uniquement les membres actifs* : désactiver pour montrer les profils archivés.
5. (Optionnel) Dans l’onglet **Style**, ajuster couleurs, marges et nombre de colonnes tablette/desktop.

## Comportement du widget
- Chaque carte affiche la cover (ou un dégradé si absente), le rôle, le nom complet, le surnom, la description courte et l’email `mailto`.
- Sur petits écrans, les cartes passent en pleine largeur et centrent l’avatar et les textes.
- En mode aperçu Elementor, des données fictives sont injectées si aucune fiche active n’est trouvée.

## Bonnes pratiques
- Garder les descriptions courtes (< 140 caractères) pour éviter les débordements.
- Vérifier que les adresses e-mail sont valides (sinon le lien ne s’affiche pas).
- Utiliser des photos carrées (512×512) pour un rendu homogène.
- Pour scinder l’équipe (ex. animateurs vs coordinateurs), dupliquer la section et filtrer par rôle dans chaque widget.
