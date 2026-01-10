# Contribuer au plugin MJ Member

Merci pour l'interet porte a MJ Member. Elles sont les etapes clefs pour participer efficacement au developpement du plugin.

## Avant de commencer
- Forkez le depot et clonez votre fork en local.
- Creez une branche par fonctionnalite ou correction (`feature/nom` ou `fix/bug`).
- Synchronisez regulièrement votre branche avec la branche principale.

## Installation locale
- Utilisez l'environnement Docker fourni (`docker-compose.yml`) pour lancer WordPress et la base MySQL correspondante.
- Activez le plugin MJ Member dans le tableau de bord WordPress.
- Importez des donnees de test via les scripts du dossier `scripts/` si necessaire.

## Directives de developpement
- Respectez les conventions WordPress : indentation quatre espaces, guillemets simples hors interpolation, `phpcs` compatible PSR-12.
- Ajoutez l'instruction `if (!defined('ABSPATH')) exit;` dans tout nouveau fichier charge.
- Preferez les classes CRUD existantes (dossier `includes/classes/`) aux requetes SQL directes.
- Toute nouvelle fonctionnalite doit etre enregistree dans `Bootstrap::MODULES` (fichier `mj-member.php`).
- Lors de l'ajout d'un widget Elementor, utilisez `includes/templates/elementor/<widget>.php` et `AssetsManager::requirePackage()` pour charger les assets.

## Qualite et verification
- Validez la syntaxe PHP (`php -l fichier.php`) et testez dans l'admin WordPress.
- Inspectez la console du navigateur pour assurer l'absence d'erreurs JS.
- Verifiez la compatibilite Elementor (mode preview) et les donnees factices si necessaire.
- Documentez les modifications impactant la base (`core/schema.php`) et incrementez `MJ_MEMBER_SCHEMA_VERSION` en cas de migration.

## Bonnes pratiques
- Respectez les capacites et roles definis dans `includes/core/capabilities.php`.
- Localisez les scripts avec `wp_localize_script` pour exposer nonces et libelles.
- Gardez les commentaires concis et utilitaires.

## Commits et Pull Requests
- Messages de commit a l'impératif, concis et explicites.
- Incluez captures ou etapes de test dans la Pull Request lorsque pertinent.
- Decrivez le contexte, la solution et les impacts potentiels.
- Liez la PR a un ticket ou a une issue si disponible.

## Support
Pour toute question, utilisez les issues ou discussions du depot. Pour signaler une vulnérabilité, consultez le document SECURITY.md.

Merci de contribuer a MJ Member !
