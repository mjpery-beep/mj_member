# 9. Widget Elementor Evenements - Ameliorations

## 9.1 Widget Carte des lieux
- [x] Le widget Carte des lieux (class-mj-member-locations-widget) doit permettre de filtrer par type de lieu le widget evenement liste (class-mj-member-events-widget).

## 9.2 Widget Calendrier – Améliorations
- [x] Gérer l’affichage des événements récurrents (hebdomadaire et mensuel) dans le widget calendrier.


## 9.3  Widget Calendrier Harmonisation avec la maquette « Agenda mensuel »
- [x] Reproduire l’entête calendrier (flèches de navigation, bouton « Aujourd’hui », libellé du mois) identique à la maquette fournie.
- [x] La largeur des colones du calendrier doit être égale, seule la hauter des colones peut varier en fonction du nombre d'événements. 
- [x] Le texte des événements doit être plus petit pour éviter la surcharge visuelle.
- [x] Les stages et autre évenement sur plusieurs jours doivent être affichés en bandeau horizontal continu sur les jours concernés.
- [x] Ajouter les jour de fermeture dans le widget calendrier - table closure (ex: MJ fermée le lundi) 
- [x] Permettre la sélection et le filtrage par type d’événement (case à coché) 
        Type : stage, sortie, soirée, atelier, fermeture   
- [x] Prévoir les variantes responsive (≤768px) : pile verticale des actions, navigation compacte, affichage des événements en liste déroulante.

## 9.4 Module Event admin + Widget Calendrier
- [x] Ajouter une palette pastel (color picker admin) sur chaque évenement. Les type d'évenemenet ont une couleur prédéfinie qui peux être surclassé pour définir la couleur de chaque événement et l’afficher dans le widget calendrier. 
- [x] Prévoir une synchronisation officielle des événements MJ vers un Google Agenda (configurable dans les settings du module) pour partager automatiquement le planning.
- [x] Le widget calendrier ne fonctionne pas sur tablette et sur mobile. Prévoir les variantes responsive (≤768px) : pile verticale des actions, navigation compacte, affichage des événements en liste déroulante.


## 9.5 Page event avec URL Événement Dédiée 
- [x] Fournir une URL propre par événement (`/date/slug`) menant vers une page dédiée affichant titre, dates, description, prix, lieu (avec carte), lien vers l'article, limites d’âge et bouton d’inscription. (si tu vois d'autre information a afficher n'hesite pas)
- [x] Aligner l’affichage des événements à occurrences multiples (widget liste + page dédiée) sur l’expérience animateur.
- [x] Forcer les widgets (agenda et liste) à ouvrir la page événement dédiée (`/date/slug`).
- [x] Réorganiser la page dédiée avec une section inscription (paiement), la liste des animateurs et les informations complètes du lieu.
- [x] Proposer la sélection des occurrences lors de l'inscription publique.
- [x] Permettre la gestion des occurrences inscrites (désinscription ciblée) et masquer le CTA d'inscription lorsqu'un créneau fixe est déjà confirmé.

