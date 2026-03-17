# Optimisations de performance — MJ Member

## Implémenté

- [x] **Chargement conditionnel des modules** — 21 modules admin déplacés dans `MODULES_ADMIN`, chargés uniquement quand `is_admin()` est vrai (~36% de fichiers PHP en moins côté front)
- [x] **Optimisation des checks de schéma** — Early returns, hooks déplacés vers `admin_init`, fast path si version identique
- [x] **Index de base de données manquants** — Migration 2.77 ajoutée avec 9 index (5 priorité 1 + 4 priorité 2), schema version bumpée à 2.77.0
- [x] **Cache objet CRUD** — `wp_cache_get`/`wp_cache_set` ajoutés sur `MjMembers::getById()`, `MjEvents::find()`, `MjDynamicFields::getAll()` avec invalidation dans create/update/delete/reorder
- [x] **Recherche tokenisée** — Tokens limités à 4 max dans `CrudQueryBuilder`, colonnes réduites (5 vs 7) pour les recherches non-admin, `extended_search` ajouté pour l'admin
- [x] **Frontend & Assets** — Triple `mjPushSubscribe` dédupliqué via `localizePushSubscribe()`, stratégie `defer` ajoutée par défaut à tous les scripts front via `registerScript()`

## Audité — aucune action nécessaire

- [x] **Requêtes sans LIMIT** — Après audit : `send_emails.php` et `getGuardians()` alimentent des dropdowns (LIMIT non approprié), `getByStatus()` et `get_all_for_year()` ne sont pas appelés
- [x] **Hydratation des inscriptions** — `hydrate_registrations()` utilise déjà un batch `SELECT ... WHERE id IN (...)` pour les membres. Le mapping manuel est du O(n) CPU pur, négligeable
- [x] **Notification listeners** — 29 hooks × ~2µs = ~60µs, négligeable. Structure actuelle claire et bien organisée par domaine

## Pistes futures (non prioritaires)

- [ ] Bundler (esbuild/vite) pour les modules Preact (registration-manager = 10 scripts séparés)
- [ ] Self-héberger Preact au lieu du CDN unpkg.com (résilience)
- [ ] Minifier JS/CSS en production
- [ ] Ajouter un index FULLTEXT sur un champ `search_index` si le volume de membres dépasse 1000
- [ ] Cacher les préférences de notification par membre (appelées per-listener dans `notifications_listener.php`)
