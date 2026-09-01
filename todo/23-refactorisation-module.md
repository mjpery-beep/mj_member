# 23. Refactorisation du module MJ Member

## 23.1 Chargement et architecture des classes
- [x] Déplacer le dossier `core/` dans `includes/core/` et mettre à jour les chargements associés.
- [x] Remplacer la liste de `require` dans `mj-member.php` par un autoloader PSR-4 (Composer ou `spl_autoload_register`) afin de réduire les dépendances globales et préparer une future séparation par domaines.
- [x] Introduire des namespaces pour les classes `includes/classes/` et `core/` pour clarifier les responsabilités et permettre les imports explicites.
- [x] Extraire la définition des constantes (`MJ_MEMBER_*`) dans une classe Config centralisée afin de limiter la pollution de l’espace global et de faciliter les environnements multiples.

## 23.2 Modularisation du back-office
- [x] Convertir les fonctions globales d’administration (`mj_members_page`, `mj_events_page`, etc.) en classes dédiées (ex: `Admin\MembersPage`) avec un point d’entrée unique pour les hooks `add_menu_page` / `add_submenu_page`.
	- [x] Séparer les traitements d’action (`mj_member_handle_members_actions`, `mj_member_handle_locations_actions`) dans des services orientés cas d’usage pour réduire la taille de `mj-member.php` et permettre des tests unitaires ciblés.
- [x] Centraliser la logique de vérification des capacités et des nonces dans un helper unique (`Admin\RequestGuard`) et l’utiliser dans tous les handlers admin.

## 23.3 Rationalisation des classes CRUD
- [x] Uniformiser l’API des classes CRUD (`includes/classes/crud/`) via `CrudRepositoryInterface` afin de couvrir `get_all`, `count`, `create`, `update`, `delete` avec gestion systématique des `WP_Error`.
- [x] Extraire les constructions SQL répétitives dans `CrudQueryBuilder` pour factoriser les filtres et clauses `prepare` utilisés par `MjMembers`, `MjEvents` et `MjEventRegistrations`.
- [x] Renommer `MjMembers_CRUD` et `MjEvents_CRUD` en classes namespacées `Mj\Member\Classes\Crud\MjMembers` et `Mj\Member\Classes\Crud\MjEvents`, puis supprimer les alias `_CRUD` restants.
- [x] Remplacer les tableaux associatifs bruts par des objets valeur (`Value\MemberData`, `Value\EventData`) afin de documenter les attributs attendus et sécuriser les accès.

## 23.4 Cohésion front-office
- [x] Introduire un gestionnaire central des assets (classe `Core\AssetsManager`) exposant `requirePackage()` pour déclarer scripts/styles côté admin et front.
- [x] Documenter et isoler la configuration `data-config` partagée entre `includes/templates/elementor/animateur_account.php` et `js/animateur-account.js` via une fonction PHP unique qui sérialise les données.
- [x] Mutualiser les helpers JS (`escapeHtml`, `flagSummaryAssignments`, `toInt`) dans un bundle (`js/utils.js` + `window.MjMemberUtils`) afin de réduire les duplications entre les scripts historiques.
- [x] Migrer l’ensemble des templates/fronts restants vers `AssetsManager::requirePackage()` en remplacement des enqueues manuels (`wp_enqueue_*`).

## 23.5 Observabilité et qualité
- [x] Mettre en place un canal de logs dédié (wrapper sur `error_log` ou monolog) pour tracer les opérations sensibles (import CSV, paiements Stripe) et faciliter la supervision.
- [x] Ajouter un socle de tests automatisés (PHPUnit) autour des helpers `MjTools` et des classes CRUD, rendu possible grâce à la modularisation proposée ci-dessus.
- [x] Fournir une documentation interne (fichier `docs/architecture.md`) décrivant les nouveaux modules, conventions de nommage et points d’extension.

## 23.6 Migration des usages legacy
- [x] Remplacer les occurrences restantes de `MjMembers_CRUD` / `MjEvents_CRUD` par les nouvelles classes namespacées dans les templates, widgets et handlers AJAX.
- [x] Adapter `MjEventRegistrations`, `MjEventLocations` et les autres dépôts au duo `CrudRepositoryInterface` + DTO pour aligner toute la couche persistance.
- [x] Étendre le bundle `MjMemberUtils` aux modules front manquants (calendar, events widget) et supprimer les helpers dupliqués dans `js/`.
