# 25. Encodage des heures animateurs

- [x] Concevoir une table personnalisée `mj_member_hours` (CRUD dédié) afin d'enregistrer les plages horaires avec membre, tâche, date, durée et commentaires.
- [x] Restreindre l'accès au module admin aux rôles `animateur`, `coordinateur` et `benevole` via des capacités spécifiques (`mj_member_log_hours`).
- [x] Créer une page d'administration avec formulaire ergonomique (saisie rapide au clavier, navigation par touches, validations instantanées).

- [x] Introduire des heures de début/fin sur chaque tâche, avec champs libres et suggestions de plages horaires récurrentes (ex. 08h30-12h00, 09h00-12h00, 13h00-15h00, 15h00-18h00, 18h00-21h00).
- [x] Afficher les encodages dans un calendrier mensuel dédié, synchronisé avec les filtres du membre courant et navigable par mois.
	- Vue mensuelle responsive avec navigation AJX et cache local par mois.
	- Affichage des créneaux (horaire + tâche) et temps total quotidien, mis à jour après chaque encodage.
- [x] Ajouter un calcul des heures par semaine et par personne, avec agrégation affichée dans le tableau de bord.
- [x] Définir les hooks de recalcul et les tests manuels à effectuer (droits, totaux hebdomadaires, cohérence des horaires projetés, UX de la saisie rapide).
	- Hooks WordPress disponibles : `mj_member_hours_after_create`, `mj_member_hours_after_update`, `mj_member_hours_after_delete`, `mj_member_hours_after_change` (payload unifié avec membre, date, durée, horaire).
	- Tests manuels recommandés : vérifier l'accès par rôle (animateur/coordinateur/benevole), confirmer la mise à jour des totaux hebdomadaires et du calendrier après encodage, contrôler la cohérence horaires/durée (début < fin, durée recalculée), évaluer la saisie rapide (presets, validation instantanée) et la navigation mensuelle.
- [ ] Ajuster l'autocomplétion des tâches et les données sauvegardées pour qu'elles exploitent les projets et les nouvelles plages horaires.
- [ ] Permettre d'encoder un projet associé (ou "Aucun projet") et lier chaque tâche à ce projet, avec suggestions alimentées par l'historique.