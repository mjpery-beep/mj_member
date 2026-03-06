# 45. Audit trail & historique des actions admin

## Objectif
Tracer toutes les actions administratives (modifications membres, paiements, événements, droits) dans un journal consultable pour la transparence et le débogage.

## Contexte
Il n'y a pas de système d'audit centralisé. Les modifications sont faites via les endpoints AJAX admin sans historique exploitable. En cas de litige ou de bug, il est impossible de reconstituer qui a fait quoi.

## Fonctionnalités
- [ ] Log automatique de chaque action admin : création, modification, suppression (membres, événements, paiements, heures, tâches…)
- [ ] Informations tracées : user_id, action, entité concernée, ancien/nouveau JSON, timestamp, IP
- [ ] Page admin "Journal d'activité" avec liste paginée et filtres (par utilisateur, par entité, par période)
- [ ] Recherche full-text dans les entrées
- [ ] Politique de rétention (purge automatique après X jours, configurable)
- [ ] Export CSV du journal
- [ ] Notification optionnelle aux super-admins pour les actions sensibles (suppression membre, changement de rôle)

## Architecture
- Nouvelle table `mj_member_audit_log` (id, user_id, action, entity_type, entity_id, old_data JSON, new_data JSON, ip, created_at)
- CRUD `MjAuditLog`
- Trait ou helper `AuditableTrait` à inclure dans les handlers AJAX
- Hook `do_action('mj_member_audit', ...)` appelé dans chaque handler
- WP-Cron pour la purge automatique

## Tâches techniques
- [ ] Migration schema `mj_member_audit_log`
- [ ] CRUD `MjAuditLog`
- [ ] Helper/Trait `Auditable` pour injection dans les handlers existants
- [ ] Page admin avec liste, filtres, recherche (Preact ou PHP classique)
- [ ] Hook `mj_member_audit` + listener
- [ ] Politique de rétention + cron de purge
- [ ] Export CSV
- [ ] Intégration progressive dans les handlers AJAX existants
