# Système de Notifications MJ Member

Documentation des notifications in-app et email/SMS du plugin MJ Member.

---

## 📋 Notifications actuellement implémentées

| Type | Hook WordPress | Destinataire | Description |
|------|----------------|--------------|-------------|
| `event_registration_created` | `mj_member_event_registration_created` | Membre + Animateurs + Coordinateurs | Confirmation d'inscription à un événement |
| `event_registration_cancelled` | `mj_member_event_registration_cancelled` | Membre + Animateurs + Coordinateurs | Confirmation d'annulation d'inscription |
| `payment_completed` | `mj_member_event_registration_payment_confirmed` | Membre + Animateurs + Coordinateurs | Confirmation de paiement reçu |
| `member_created` | `mj_member_quick_member_created` | Nouveau membre + Animateurs + Coordinateurs | Bienvenue lors de la création d'un compte |
| `photo_uploaded` | `mj_member_event_photo_created` | Participants + Animateurs + Coordinateurs | Nouvelle photo partagée sur un événement |
| `member_profile_updated` | `mj_member_profile_updated` | Membre + Animateurs + Coordinateurs | Confirmation de mise à jour du profil |
| `idea_published` | `mj_member_idea_published` | Tous les membres + Animateurs + Coordinateurs | Nouvelle idée publiée dans la boîte à idées |
| `trophy_earned` | `mj_member_trophy_auto_assigned` | Membre + Animateurs + Coordinateurs | Trophée obtenu |
| `avatar_applied` | `mj_member_grimlins_avatar_applied` | Membre + Animateurs + Coordinateurs | Nouvel avatar personnalisé |

> **Note** : Les animateurs et coordinateurs reçoivent une notification pour chaque action des membres afin de suivre l'activité du site en temps réel.

### Détails des notifications existantes

#### 1. Inscription à un événement (`event_registration_created`)
- **Fichier** : `notification_listeners.php` ligne 189-284
- **Déclencheur** : Inscription depuis le calendrier ou le gestionnaire
- **Notifications** :
  - In-app au membre inscrit ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅
  - Email (si préférence activée) ✅
  - SMS (si préférence activée) ✅

#### 2. Annulation d'inscription (`event_registration_cancelled`)
- **Fichier** : `notification_listeners.php` ligne 289-347
- **Déclencheur** : Annulation par le membre ou un admin
- **Notifications** :
  - In-app au membre concerné ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅
  - Email (si préférence activée) ✅

#### 3. Paiement confirmé (`payment_completed`)
- **Fichier** : `notification_listeners.php` ligne 352-427
- **Déclencheur** : Paiement validé (Stripe webhook, admin, etc.)
- **Notifications** :
  - In-app au membre concerné ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅
  - Email (si préférence activée) ✅

#### 4. Création de membre (`member_created`)
- **Fichier** : `notification_listeners.php` ligne 432-501
- **Déclencheur** : Création via formulaire d'inscription ou admin
- **Notifications** :
  - In-app au nouveau membre ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅
  - Email de bienvenue (si préférence activée) ✅

#### 5. Photo partagée (`photo_uploaded`)
- **Fichier** : `notification_listeners.php` ligne 506-577
- **Déclencheur** : Upload d'une photo sur un événement
- **Notifications** :
  - In-app aux participants de l'événement ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅
  - Email (si préférence activée) ✅

#### 6. Profil mis à jour (`member_profile_updated`)
- **Fichier** : `notification_listeners.php` ligne 582-639
- **Déclencheur** : Modification du profil par le membre
- **Notifications** :
  - In-app au membre ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅

#### 7. Idée publiée (`idea_published`)
- **Fichier** : `notification_listeners.php` ligne 644-706
- **Déclencheur** : Publication d'une nouvelle idée
- **Notifications** :
  - In-app à tous les membres ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅

#### 8. Trophée obtenu (`trophy_earned`)
- **Fichier** : `notification_listeners.php`
- **Déclencheur** : Attribution automatique d'un trophée
- **Hook** : `mj_member_trophy_auto_assigned`
- **Notifications** :
  - In-app au membre concerné ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅

#### 9. Avatar personnalisé (`avatar_applied`)
- **Fichier** : `notification_listeners.php`
- **Déclencheur** : Application d'un avatar Grimlins
- **Hook** : `mj_member_grimlins_avatar_applied`
- **Notifications** :
  - In-app au membre concerné ✅
  - In-app aux animateurs ✅
  - In-app aux coordinateurs ✅

---

## 🔮 Notifications potentielles à implémenter

### Priorité haute ⭐⭐⭐

| Type suggéré | Hook existant/à créer | Destinataire | Description |
|--------------|----------------------|--------------|-------------|
| `event_reminder` | CRON + nouveau hook | Membres inscrits | Rappel J-1 ou J-2 avant un événement |
| `event_new_published` | À créer sur MjEvents::create | Tous les membres | Nouvel événement disponible au calendrier |
| `payment_reminder` | CRON + nouveau hook | Membres avec solde dû | Rappel de paiement en attente |
| ~~`trophy_earned`~~ | ~~`mj_member_trophy_auto_assigned`~~ | ~~Membre concerné~~ | ✅ **Implémenté** |
| `badge_earned` | À créer | Membre concerné | Badge débloqué |
| `level_up` | À créer | Membre concerné | Passage au niveau supérieur (coins) |

### Priorité moyenne ⭐⭐

| Type suggéré | Hook existant/à créer | Destinataire | Description |
|--------------|----------------------|--------------|-------------|
| `attendance_recorded` | À créer sur MjEventAttendance | Membre concerné | Présence enregistrée à un événement |
| `hours_validated` | `mj_member_hours_after_create` | Membre concerné | Heures de bénévolat validées |
| `event_cancelled` | À créer | Membres inscrits | Événement annulé |
| `event_updated` | À créer | Membres inscrits | Modification importante d'un événement |
| `registration_waitlist` | À créer | Membre concerné | Placement sur liste d'attente |
| `registration_waitlist_promoted` | À créer | Membre concerné | Passage de liste d'attente à inscrit |
| ~~`grimlins_avatar_applied`~~ | ~~`mj_member_grimlins_avatar_applied`~~ | ~~Membre concerné~~ | ✅ **Implémenté** (renommé `avatar_applied`) |

### Priorité basse ⭐

| Type suggéré | Hook existant/à créer | Destinataire | Description |
|--------------|----------------------|--------------|-------------|
| `idea_voted` | À créer | Auteur de l'idée | Vote reçu sur une idée |
| `idea_commented` | À créer | Auteur de l'idée | Commentaire sur une idée |
| `birthday_reminder` | CRON | Membre | Joyeux anniversaire |
| `membership_expiring` | CRON | Membre | Adhésion expire bientôt |
| `membership_renewed` | À créer | Membre | Adhésion renouvelée |
| `data_retention_warning` | Avant `mj_member_data_retention_success` | Membre | Données seront supprimées (RGPD) |
| `photo_approved` | À créer | Photographe | Photo approuvée par modération |
| `child_added` | À créer | Parent | Enfant ajouté au compte famille |

---

## 🔧 Hooks WordPress disponibles (non exploités)

Ces hooks existent déjà dans le code mais n'ont pas encore de listener de notification :

| Hook | Fichier | Potentiel |
|------|---------|-----------|
| ~~`mj_member_trophy_auto_assigned`~~ | ~~MjTrophyService.php:141~~ | ✅ **Implémenté** |
| `mj_member_hours_after_create` | MjMemberHours.php:342 | 📊 Heures de bénévolat validées |
| ~~`mj_member_grimlins_avatar_applied`~~ | ~~photo_grimlins.php:615~~ | ✅ **Implémenté** |
| `mj_member_data_retention_success` | data_retention.php:86 | ⚠️ Données supprimées |
| `mj_member_sms_sent` | MjSms.php:248 | 📱 Log/confirmation SMS |

---

## 📊 Types définis mais non implémentés

Dans `MjNotificationTypes` mais sans listener actif :

| Constante | Valeur | Status |
|-----------|--------|--------|
| `EVENT_REMINDER` | `event_reminder` | ❌ Non implémenté (nécessite CRON) |
| `EVENT_NEW_PUBLISHED` | `event_new_published` | ❌ Non implémenté |
| `PAYMENT_REMINDER` | `payment_reminder` | ❌ Non implémenté (nécessite CRON) |
| `BADGE_EARNED` | `badge_earned` | ❌ Non implémenté (nécessite hook) |
| `LEVEL_UP` | `level_up` | ❌ Non implémenté (nécessite hook) |
| `ATTENDANCE_RECORDED` | `attendance_recorded` | ❌ Non implémenté (nécessite hook) |

---

## 🏗️ Architecture technique

### Fichiers principaux

```
includes/
├── notifications.php                 # Helpers publics
├── notification_listeners.php        # Listeners des hooks
├── classes/
│   ├── MjNotificationManager.php     # Service principal
│   └── crud/
│       ├── MjNotifications.php       # CRUD notifications
│       └── MjNotificationRecipients.php # CRUD destinataires
├── core/
│   └── ajax/front/
│       └── notification_bell_ajax.php # AJAX widget cloche
├── elementor/
│   └── class-mj-member-notification-bell-widget.php # Widget Elementor
└── templates/elementor/
    └── notification_bell.php         # Template HTML
```

### Tables de base de données

- `wp_mj_notifications` : Contenu des notifications
- `wp_mj_notification_recipients` : Liens notification ↔ membre/user/rôle

### Canaux de notification

| Canal | Implémenté | Préférences utilisateur |
|-------|------------|------------------------|
| In-app (widget cloche) | ✅ | Non (toujours actif) |
| Email | ✅ | ✅ Via MjMembers::getNotificationPreferences() |
| SMS | ✅ | ✅ Via MjMembers::getNotificationPreferences() |
| Push navigateur | ❌ | - |

---

## 📝 Notes d'implémentation

### Pour ajouter une nouvelle notification

1. **Ajouter la constante** dans `MjNotificationTypes`
2. **Créer le listener** dans `notification_listeners.php`
3. **S'assurer que le hook existe** (`do_action(...)`) au bon endroit
4. **Tester** avec le script `tmp/debug_notifications.php`

### Pour les notifications planifiées (rappels)

- Nécessite un système CRON WordPress (`wp_schedule_event`)
- Créer une fonction qui parcourt les événements/paiements à rappeler
- Envoyer les notifications via `mj_member_record_notification()`

---

*Dernière mise à jour : Février 2026*
