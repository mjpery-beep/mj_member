# 38. Système de rappels automatiques

## Objectif
Mettre en place un mécanisme de rappels automatiques envoyés par email et/ou notification push avant les événements, pour les échéances de paiement, et pour les tâches (todos) en retard.

## Contexte
Le système de notifications existe (`MjNotificationManager`, push via VAPID) mais les rappels sont manuels. Automatiser les rappels réduirait la charge admin et améliorerait la participation.

## Fonctionnalités
- [ ] Rappel événement J-2 et J-1 pour les inscrits (configurable)
- [ ] Rappel paiement expirant dans X jours (configurable via `MJ_MEMBER_PAYMENT_EXPIRATION_DAYS`)
- [ ] Rappel tâches non terminées depuis X jours pour le responsable
- [ ] Rappel congés en attente de validation pour les gestionnaires
- [ ] Page de configuration admin pour activer/désactiver chaque type de rappel et définir les délais
- [ ] Historique des rappels envoyés (pour éviter les doublons)

## Architecture
- WP-Cron job planifié quotidiennement (ou toutes les 6h)
- Classe `ReminderScheduler` qui collecte les rappels à envoyer
- Utilise `MjNotificationManager::record()` pour créer les notifications
- Utilise le système de push existant (`web_push.php`) pour les notifications push
- Templates email dédiés via le module `email_templates.php`

## Tâches techniques
- [ ] Classe `includes/classes/ReminderScheduler.php`
- [ ] Hook WP-Cron dans `Bootstrap::MODULES`
- [ ] Configuration admin (formulaire dans Settings)
- [ ] Templates email de rappel
- [ ] Table ou meta pour tracker les rappels déjà envoyés (anti-doublon)
- [ ] Tests manuels avec dates simulées
