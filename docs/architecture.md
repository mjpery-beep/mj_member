# Architecture MJ Member

Ce document présente les blocs techniques clés du plugin MJ Member après la refonte 23.x. Il complète `spec-module-mj-member.md` avec une vision orientée code et extension.

## Découpage général
- **Point d'entrée** : `mj-member.php` initialise la configuration (`Config::bootstrap`), enregistre l'autoloader PSR-4 et lance `Bootstrap::init()` suivi du menu admin (`AdminMenu::boot()`).
- **Autoload** : `includes/core/Autoloader.php` gère les préfixes `Mj\Member\*`. Les classes historiques conservent des alias via `$legacyMap` pour éviter les ruptures.
- **Bootstrap** : `includes/Bootstrap.php` requiert les modules procéduraux listés dans `MODULES` (sécurité, templates, AJAX, etc.) en respectant `apply_filters('mj_member_bootstrap_modules', …)` pour permettre des ajouts.

## Strates fonctionnelles
| Couche | Rôle | Dossier principal |
| --- | --- | --- |
| Core | Config, capacités, assets, schéma, AJAX admin | `includes/core/`
| Domaine | Classes métier (CRUD, services, valeurs, paiements, Stripe) | `includes/classes/`
| Présentation back | Pages admin procédurales (PHP) + scripts | `includes/*.php`, `js/admin-*`
| Présentation front | Templates Elementor, shortcodes, scripts front | `includes/templates/`, `includes/elementor/`, `js/`
| Tests | Bootstrap + suites PHPUnit | `tests/`

## Persistance & DTO
- Pattern commun : chaque dépôt dans `includes/classes/crud/` implémente `CrudRepositoryInterface` et délègue les filtres SQL à `CrudQueryBuilder`.
- Les entités exposées côté appelant utilisent des objets valeur immuables (`includes/classes/value/*Data.php`). Exemple : `EventRegistrationData`, `EventLocationData`.
- Les méthodes `get`, `get_all`, `find` retournent désormais des DTO ; les consommateurs doivent accéder aux attributs via les getters magiques (`$dto->email`) ou `toArray()`.

## Gestion des assets
- `includes/core/assets.php` centralise les paquets scripts/styles via `AssetsManager::requirePackage($handle, $context)`.
- Chaque package déclare dépendances, version (filemtime) et localisations (`wp_localize_script`).
- Les nouveaux templates front doivent remplacer les `wp_enqueue_*` directs par `AssetsManager::requirePackage` afin de bénéficier du versioning automatique.

## Journalisation
- `includes/core/Logger.php` fournit un wrapper structuré (format JSON) avec canaux (`stripe`, `payments`, `import`, `security`, `app`).
- Les logs sont stockés dans `wp-content/uploads/mj-member/logs/` ; repli sur `error_log` en cas d'échec d'écriture.
- Exemples d'utilisation : `Logger::info('stripe.checkout.created', [...], 'stripe')`, `Logger::error('CSV import failed', [...], 'import')`.

## Tests automatisés
- `composer.json` expose `composer test` et charge PHPStan via PSR-4.
- `phpunit.xml.dist` et `tests/bootstrap.php` fournissent un environnement minimal (stubs WordPress) pour tester les helpers/dépôts sans WordPress complet.
- Suites de référence :
  - `tests/Helpers/MjToolsTest.php`
  - `tests/Crud/CrudQueryBuilderTest.php`

## Conventions de nommage
- **Namespaces** : `Mj\Member\<Couche>\<Nom>` reflète l'arborescence (`Classes\Crud`, `Core`, `Value`).
- **Fichiers** : PascalCase pour les classes (`MjEventRegistrations.php`), kebab-case pour les scripts (`admin-send-emails.js`), BEM pour le CSS (`mj-animateur-dashboard__header`).
- **Fonctions globales** : préfixées `mj_member_` et gardées uniquement pour compatibilité ; privilégier les classes.
- **Hooks** : utiliser `mj_member_` pour les filtres/actions personnalisés (`mj_member_bootstrap_modules`, `mj_member_logger_channel_file`).

## Points d'extension
- **Bootstrap** : étendre/modifier les modules chargés via le filtre `mj_member_bootstrap_modules`.
- **Assets** : définir de nouveaux paquets avec `AssetsManager::registerPackage()` et les activer dans les templates.
- **CRUD** : créer un dépôt en implémentant `CrudRepositoryInterface`, réutiliser `CrudQueryBuilder` et fournir un DTO dans `includes/classes/value/`.
- **Journalisation** : brancher un canal dédié via `add_filter('mj_member_logger_channel_file', ...)` ou utiliser des wrappers `Logger::log()`.
- **Tests** : ajouter des suites dans `tests/` en respectant la structure PSR-4 et en important les stubs nécessaires.

## Bonnes pratiques
- Toujours vérifier `php -l` avant commit et exécuter `composer test` si disponible.
- Utiliser les helpers (`sanitize_text_field`, `wp_parse_args`, `Logger`, DTOs) pour garder la cohérence.
- Documenter les nouveaux modules ou flux dans `docs/` et mettre à jour `todo/` pour assurer la traçabilité des refactors.
