# 34. Refonte page admin heures

## Objectifs
- Créer une page statistique consolidée pour les heures encodées (vue équipe).
- Afficher les totaux d'heures par membre et par projet.
- Remplacer l'ancienne interface d'encodage admin par un tableau de bord Preact.

## Tâches
- [x] Ajouter les agrégations SQL (totaux par membre, par projet, par membre/projet).
- [x] Construire la configuration PHP du tableau de bord et le gabarit minimal.
- [x] Mettre en place le bundle Preact (script + style) et initialiser l'app côté admin.
- [x] Implémenter les composants (cartes KPI, donuts projets, donut par membre, tableau).
- [ ] Tests manuels (chargement admin, données sans heures, grands volumes).
