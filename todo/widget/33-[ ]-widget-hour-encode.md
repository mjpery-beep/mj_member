# 33. Widget Hour Encode

## Spécifications
- Widget Elementor dédié à l'encodage hebdomadaire des heures des animateurs, bénévoles et coordinateurs.
- Vue calendrier sur 7 jours avec glisser-déposer vertical pour ajuster les plages horaires.
- Interaction 100 % AJAX pour créer, modifier, supprimer, dupliquer et déplacer les créneaux.
- Autocomplétion des tâches basée sur l'historique et les libellés existants en base.
- Gestion en direct des projets (labels ajoutés/supprimés à la volée) avec persistance immédiate.
- Affichage d'un total horaire hebdomadaire et d'une synthèse par jour/projet.
- Visualisation des événements MJ de la semaine pour contextualiser l'encodage.
- Respect des droits `Config::hoursCapability()` avec fallback informatif si l'utilisateur n'est pas autorisé.
- Compatibilité Elementor preview avec jeu de données factices.

## Objectifs
- Fournir un outil intuitif et rapide pour l'encodage des heures directement depuis une page Elementor.
- Réduire les allers-retours entre l'admin et le front pour une meilleure expérience utilisateur.

## Tâches
- [x] Définir l'API AJAX (endpoints création/édition/suppression) et valider les nonces/capacités.
- [x] Implémenter la récupération des tâches/projets existants via CRUD dédié ou requêtes optimisées.
- [x] Construire le calendrier hebdomadaire (structure, grille, drag & drop, resizing vertical).
- [x] Ajouter la création rapide d'une plage horaire via clic sur créneau vide + saisie directe.
- [ ] Connecter l'autocomplétion des tâches et projets (fetch + debounce + stockage local).
- [x] Afficher/actualiser le total d'heures hebdomadaire et les totaux quotidiens en direct.
- [x] Afficher les événements MJ associés à la semaine (couleur, lien, lieu) dans le panneau latéral.
- [x] Gérer la navigation semaine précédente/suivante avec rechargement AJAX et états de chargement.
- [x] Prévoir des tests manuels (droits, double encodage, collision de créneaux, ajout projet).
- [ ] Documenter le flux dans `docs/` et mettre à jour la roadmap si besoin.
- [x] Préparer un commit « Implement Hour Encode widget ».
