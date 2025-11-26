# Spécification du module WordPress MJ Member

## Résumé
MJ Member est un plugin WordPress conçu pour centraliser la gestion des membres, des événements et des communications d'une Maison de Jeunes. Il expose des interfaces back-office dédiées, des composants Elementor pour le front-office et une intégration Stripe pour les paiements en ligne.

## Périmètre fonctionnel
### Gestion des membres
- CRUD complet dans l'administration (`mj_members_page`, `MjMembers_CRUD`).
- Rôles supportés : jeunes, tuteurs, animateurs, bénévoles.
- Gestion des tuteurs et rattachement de plusieurs jeunes via `guardian_id`.
- Suivi des statuts (actif/inactif), des consentements photo et des préférences de communication.

### Adhésions et paiements
- Paiements en ligne via Stripe (mode test par défaut) orchestrés par `MjPayments` et `MjStripeConfig`.
- Tokens de confirmation gérés par `mj_handle_payment_confirmation`.
- Historique des paiements et journalisation (`mj_payments`, `mj_payment_history`).

### Événements et inscriptions
- CRUD événements/stages (`MjEvents_CRUD`, interfaces `includes/forms/form_event.php` et `includes/table_events.php`).
- Gestion des lieux (`MjEventLocations`), animateurs associés (`MjEventAnimateurs`), capacités et récurrences.
- Inscriptions membres ou tuteurs (`MjEventRegistrations`, `MjEventAttendance`) avec files d'attente et suivi de présence.

### Communications
- Modèles d'e-mails personnalisables (`mj_email_templates`, `MjMail`).
- Envoi ciblé dans l'admin (`mj_send_emails_page`, scripts `js/admin-send-emails.js`).
- Préparations pour SMS (`MjSms`, colonnes `sms_opt_in`, `sms_content`).

### Interfaces front
- Shortcodes : `[mj_member_account]`, composants d'inscription et connexion (`includes/templates/elementor/login_component.php`, `includes/shortcode_inscription.php`).
- Widgets Elementor pour comptes animateurs, calendrier d'événements (`includes/elementor/`).
- Scripts front (`js/animateur-account.js`, `js/events-calendar.js`) synchronisés via `data-config` JSON.

## Architecture technique
- Fichier d'entrée `mj-member.php` : définit les constantes, charge les classes et enregistre les hooks.
- Dossier `core/` : capacités (`capabilities.php`), assets (`assets.php`), schéma SQL et migrations (`schema.php`), AJAX admin (`core/ajax/admin/`).
- Dossier `includes/` : logique métier (comptes, formulaires, sécurité, Elementor, import CSV, templates e-mail).
- Dossier `js/` et `css/` : scripts jQuery IIFE, styles BEM (`mj-animateur-dashboard__*`).
- Activation (`mj_install`) : création des tables, seed de données de démonstration et page `[mj_member_account]`.
- Mise à jour de schéma (`mj_member_run_schema_upgrade`) déclenchée sur `init` et `admin_init`.

## Modèle de données
- `wp_mj_members` : informations des membres, rattachements tuteurs, consentements, préférences de notification.
- `wp_mj_payments` : paiements Stripe, contexte (adhésion, événement), références externes.
- `wp_mj_payment_history` : traçabilité des paiements hors Stripe.
- `wp_mj_events` : métadonnées des événements (statut, période, type, capacité, récurrence, article WordPress associé).
- `wp_mj_event_registrations` : inscriptions membres/parents avec statut et notes.
- `wp_mj_event_locations` : catalogue des lieux et cartes.
- `wp_mj_event_animateurs` : table pivot événements ↔ animateurs.
- `wp_mj_event_closures` : jours de fermeture de la structure.
- `wp_mj_email_templates` / `wp_mj_email_logs` : modèles et journal des envois.

## Permissions et sécurité
- Capacité personnalisée `mj_manage_members` injectée dans les rôles via `core/capabilities.php`.
- Filtre REST `mj_protect_stripe_keys` pour bloquer l'accès aux clés Stripe.
- Vérifications systématiques des nonces et capacités dans les actions AJAX/admin.
- Gestion des uploads via WordPress Media (`mj_photo_upload_nonce`).

## Intégrations
- **Stripe** : configuration sécurisée (`MjStripeConfig`), hooks de protection, paiement par liens, confirmation par token.
- **Elementor** : widgets dans `includes/elementor/`, mode aperçu avec jeux de données factices pour éviter les erreurs d'édition.
- **WordPress AJAX** : actions centralisées dans `core/ajax/admin/*`, scripts localisés (`wp_localize_script`).
- **WP Cron** (prévu) : hooks disponibles pour rappels paiements/renouvellements.

## Flux critiques
1. **Onboarding membre** : création via interface admin → assignation tuteur → envoi du lien de paiement Stripe → confirmation → mise à jour `date_last_payement`.
2. **Gestion événement** : création d'un événement (récurrence, capacités) → publication front via widget ou page → inscriptions (membre/tuteur) → suivi présence (payload JSON) → rappels par e-mail.
3. **Communication massive** : sélection d'un template → préparation du message (HTML, SMS) → envoi AJAX avec logs (`mj_email_logs`).

## Extensibilité
- Utilisation de classes statiques CRUD pour encapsuler les requêtes SQL et validations.
- Hooks WordPress disponibles (`add_action`, `add_filter`) pour étendre formulaires, ajouter scripts ou compléter les e-mails.
- Module import CSV (`includes/import_members.php`) prêt pour injection de nouveaux mappings.
- Possibilité de décliner des composants Elementor supplémentaires en réutilisant la structure existante (`includes/elementor/templates/`).

## Déploiement et maintenance
- Vérifier `MJ_MEMBER_SCHEMA_VERSION` lors de toute évolution structurelle des tables.
- Tester les modifications PHP avec `php -l` et recharger les pages admin concernées.
- Purger les caches front (navigateur) après modification JS/CSS ; la version `filemtime` est gérée automatiquement.
- Configurer les clés Stripe test/production via la page de configuration `mj_settings`.
- Surveiller `wp-content/debug.log` (activé pour les administrateurs via `mj_enable_admin_debug`).

## Références rapides
- Point d'entrée : `wp-content/plugins/mj-member/mj-member.php`.
- Assets : `core/assets.php`, `css/styles.css`, `js/*`.
- Classes CRUD : `includes/classes/crud/` (membres, événements, inscriptions, lieux, présences).
- Widgets Elementor : `includes/elementor/*`.
- Shortcodes : `includes/shortcode_inscription.php`, `includes/templates/elementor/shortcode_member_account.php`.
- Sécurité : `includes/security.php`, filtres REST et nonces.
