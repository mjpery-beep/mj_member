# Intégration WhatsApp - Guide d'installation

## ✅ Ce qui a été implémenté

### 1. **Classe MjWhatsapp** (`includes/classes/MjWhatsapp.php`)
- ✅ Gestion complète de l'envoi WhatsApp via Twilio
- ✅ Support des placeholders (member_first_name, etc.)
- ✅ Validation des numéros de téléphone
- ✅ Vérification du consentement WhatsApp
- ✅ Mode test intégré

### 2. **UI d'envoi massif** (`includes/send_emails.php`)
- ✅ Checkbox WhatsApp dans les canaux d'envoi
- ✅ Fieldset dédié pour les messages WhatsApp
- ✅ Support des templates WhatsApp
- ✅ Notice de mode test WhatsApp

### 3. **Contrôleurs AJAX** (`includes/core/ajax/admin/emails.php`)
- ✅ Validation du contenu WhatsApp
- ✅ Récupération des templates WhatsApp
- ✅ Envoi en masse avec WhatsApp
- ✅ Envoi individuel avec WhatsApp
- ✅ Retour de prévisualisations

## 🔧 Configuration requise

### 1. **Credentials Twilio WhatsApp**
Dans les **Settings du plugin**, vous devez have:
- ✅ `mj_sms_provider` = "twilio"
- ✅ `mj_sms_twilio_sid` = votre Account SID
- ✅ `mj_sms_twilio_token` = votre Auth Token
- ✅ `mj_sms_twilio_from` = votre numéro WhatsApp Business (format: +1234567890)

Pour WhatsApp Business avec Twilio :
1. Se connecter à Twilio Console
2. Activer WhatsApp Sandbox ou vérifier un numéro
3. Copier le numéro `whatsapp:` (ex: whatsapp:+1234567890)

### 2. **Champ de consentement** (déjà présent)
Le champ `whatsapp_opt_in` existe déjà dans la table `mj_members`.

### 3. **Champ de template** (optionnel)
Si vos templates ont un champ `whatsapp_content`, il sera utilisé automatiquement.

## 📝 Utilisation

### Page d'envoi massif
1. Allez sur **MJ Member** → **Envoyer des emails**
2. Cochez **WhatsApp** dans les canaux d'envoi
3. Remplissez le **Message WhatsApp** (max 4096 caractères)
4. Sélectionnez les destinataires
5. Cliquez **Envoyer** (ou Envoyer en mode test)

### Points importants
- Seul les membres avec `whatsapp_opt_in = 1` reçoivent les messages
- Les placeholders supportés: `{{member_first_name}}`, `{{member_last_name}}`, `{{site_name}}`, `{{today}}`
- Limite Twilio: ~160 caractères par SMS, mais WhatsApp permet 4096
- Format du numéro: le plugin normalise les numéros automatiquement

## 🧪 Mode test

Activez dans les **Settings**:
- `mj_whatsapp_test_mode` = 1

Les messages WhatsApp seront simulés sans être envoyés réellement.

## 🐛 Dépannage

### "Service WhatsApp indisponible"
→ Vérifiez que `class_exists('MjWhatsapp')` retourne true
→ Vérifiez les logs WordPress

### "Membre a refusé ce canal"
→ Vérifiez que `whatsapp_opt_in = 1` pour le membre
→ Allez dans le profil membre pour activer le consentement

### "Aucun numéro de téléphone valide"
→ Le numéro de téléphone du membre est vide ou mal formaté
→ Accepte: +1234567890, +32123456789, etc.

## 📱 Hooks disponibles

```php
// Avant envoi (avec chance de modifier)
apply_filters('mj_member_whatsapp_send', null, $phones, $message, $member, $context, $result);

// Après succès
do_action('mj_member_whatsapp_sent', $phones, $message, $member, $context, $result);

// Mode simulé
do_action('mj_member_whatsapp_simulated', $phones, $message, $member, $context, $result);
```

## 🔐 Sécurité

- ✅ Vérification des nonces AJAX
- ✅ Vérification des capabilités admin
- ✅ Sanitization des inputs
- ✅ Les credentials Twilio sont sécurisés
- ✅ Respect du consentement RGPD

## 📋 TODO (optionnel)

Pour pousser plus loin:
1. Ajouter une colonne `whatsapp_content` aux templates
2. Implémenter le logging des WhatsApp envoyés
3. Ajouter des webhooks de confirmation Twilio
4. Intégrer les fichiers/images WhatsApp
5. Supporter plusieurs numéros WhatsApp par membre

## 📞 Support

Pour les problèmes Twilio:
- Logs: Twilio Console
- Documentation: https://www.twilio.com/docs/whatsapp/api

Pour les problèmes du plugin:
- Vérifiez les logs WordPress: `wp-content/debug.log`
- Vérifiez les routes AJAX: admin-ajax.php?action=mj_member_send_single_email
