# 33. Widget HourEncore

Widget Elementor permettant aux animateurs, coordinateurs et bénévoles d'encoder leurs heures de travail dans une vue hebdomadaire interactive.

## Spécifications
- Vue calendrier sur sept jours avec navigation par semaine et mise à jour Ajax.
- Manipulation directe des plages horaires (dimensionnement vertical et glisser-déposer) avec autocomplétion des tâches.
- Suggestion dynamique des tâches fréquentes et projets, création rapide de projets depuis l'interface.
- Calcul automatique du total d'heures par semaine et affichage des événements associés.
- Synchronisation avec le back-office (CRUD heures/projets) et gestion fine des capacités.

## Tâches
- [ ] Définir le schéma des données transportées côté Ajax (heures, projets, droits).
- [ ] Implémenter les endpoints Ajax (CRUD heures + projets) et sécuriser avec capabilities / nonces.
- [ ] Connecter la vue calendrier au backend pour la navigation semainière et la persistance des modifications.
- [ ] Ajouter le glisser-déposer et le redimensionnement des plages horaires côté JS (avec contraintes horaires).
- [ ] Alimenter les suggestions (tâches/projets) depuis la base et mettre en cache côté frontend.
- [ ] Synchroniser les événements de la semaine afin de les afficher dans la colonne contextuelle.
- [ ] Rédiger la documentation d'usage (guide animateurs + note technique).
