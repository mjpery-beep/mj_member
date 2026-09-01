# Widget Animateurs / Coordinateurs

## Objectif
Créer un widget Elementor affichant la liste des animateurs et coordinateurs avec leurs informations clés (cover, surname, email, description courte).

## Hypothèses
- Les animateurs et coordinateurs sont identifiés via un rôle WordPress ou un champ méta spécifique (à confirmer).
- Les images "cover" sont stockées via la médiathèque WordPress et associées aux membres.
- Les données sont déjà disponibles via `mj_member_get_animateur_events_data` ou une fonction similaire ; sinon, étendre le CRUD des membres.

## Tâches
- [x] Définir la source de données (requête CRUD ou nouvelle fonction) pour récupérer animateurs/coordinateurs et garantir la présence des champs nécessaires.
- [x] Vérifier/ajouter les métadonnées requises côté admin (upload cover, surnom, description courte) et mettre à jour la validation.
- [x] Implémenter le widget Elementor avec contrôles (filtre par rôle, tri, nombre d'items, ordre) et affichage cover/surnom/email/description.
- [x] Ajouter le template PHP dédié (dans `includes/templates/elementor/`) ainsi que les styles nécessaires.
- [x] Prévoir un mode "aperçu Elementor" avec données factices.
- [x] Rédiger la documentation d'utilisation dans la section support.

## Critères d'acceptation
- Widget disponible dans Elementor avec un aperçu fonctionnel.
- Chaque carte affiche cover, surname, email cliquable et description courte.
- Responsive mobile/desktop conforme aux styles MJ.
- Données sécurisées (échappement, vérifications de capacités).
- Documentation mise à jour.
