# 43. Intégration WhatsApp pour notifications

## Objectif
Envoyer certaines notifications importantes via WhatsApp Business API en complément des emails et notifications push.

## Contexte
Le fichier `WHATSAPP_SETUP.md` existe déjà à la racine du plugin, indiquant que l'intégration est envisagée. Le système de notifications (`MjNotificationManager`) supporte déjà plusieurs canaux (in-app, push) ; WhatsApp serait un canal supplémentaire.

## Fonctionnalités
- [ ] Envoi de messages WhatsApp via l'API Cloud (Meta Business)
- [ ] Templates de messages pré-approuvés : rappel événement, confirmation inscription, rappel paiement
- [ ] Opt-in du membre pour recevoir les WhatsApp (numéro vérifié + consentement)
- [ ] Préférences de canal par membre (email, push, WhatsApp) dans les réglages de notification
- [ ] Logs d'envoi avec statut delivery (sent, delivered, read, failed)
- [ ] Page admin pour gérer les templates et voir les stats d'envoi

## Architecture
- Classe `includes/classes/WhatsAppClient.php` utilisant l'API Cloud Meta
- Token + Phone Number ID stockés dans `Config` (via `wp-config.php`)
- Extension de `MjNotificationManager` pour le canal WhatsApp
- Préférences stockées dans `MjMembers` ou table dédiée

## Tâches techniques
- [ ] Classe `WhatsAppClient` avec méthode `sendTemplate()`
- [ ] Configuration admin (token, phone number ID, templates)
- [ ] Migration : champ opt-in WhatsApp dans les membres
- [ ] Extension du `MjNotificationManager` pour dispatcher vers WhatsApp
- [ ] Widget front pour gérer ses préférences de notification (existant `notification-preferences` à étendre)
- [ ] Tests avec compte sandbox Meta
