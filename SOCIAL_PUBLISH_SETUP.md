# Configuration - Publication via n8n

Ce guide explique comment configurer la publication d'événements via un webhook n8n dans MJ Member.

## Accès à la configuration

1. Allez dans **Administration WordPress** > **MJ Member** > **Paramètres**.
2. Ouvrez l'onglet **📣 Publier sur les réseaux**.
3. Activez la publication via n8n.
4. Renseignez l'URL du webhook et le secret partagé.

---

## Paramètres attendus

| Paramètre | Description |
|-----------|-------------|
| **Activer la publication via n8n** | Active l'envoi du webhook |
| **Webhook URL n8n** | URL du nœud Webhook n8n |
| **Secret partagé** | Secret utilisé pour signer la requête |

---

## Payload envoyé par MJ Member

Le plugin envoie un `POST` JSON avec les en-têtes suivants :

- `X-MJ-Timestamp`
- `X-MJ-Signature` au format `sha256=<hash>`

La signature est calculée avec :

```text
hash_hmac('sha256', timestamp + "\n" + rawBody, secret)
```

Le corps JSON contient :

```json
{
  "source": "mj-member",
  "action": "publish_event",
  "message": "Texte de publication",
  "eventUrl": "https://...",
  "site": {
    "name": "Nom du site",
    "url": "https://..."
  },
  "event": {
    "id": 123,
    "title": "Titre de l'événement",
    "description": "Description",
    "date_start": "2026-03-22 10:00:00",
    "date_end": "2026-03-22 12:00:00",
    "event_page_url": "https://...",
    "front_url": "https://...",
    "location": "Lieu"
  },
  "requestedBy": {
    "userId": 45,
    "displayName": "Nom utilisateur"
  },
  "requestedAt": "2026-03-22T12:00:00+00:00"
}
```

---

## Workflow n8n à importer - Facebook, Instagram & WhatsApp

Ce workflow supporte la publication simultanée sur **Facebook**, **Instagram** et **WhatsApp (groupe)**.

### Étapes d'installation

1. Créez un nouveau workflow vide dans n8n.
2. Cliquez sur le menu n8n et sélectionnez **Import from URL** ou **Import from Code**.
3. Collez le JSON ci-dessous.
4. Configurez les variables d'environnement (voir section **Configuration des plateformes**).
5. Testez avec un événement.

> **💡 Conseil** : Gardez le code ci-dessous ouvert dans un onglet et testez en temps réel dans le gestionnaire MJ Member.

### Code n8n à importer

<details open>
<summary style="cursor: pointer; font-weight: 600; color: #1e293b;">📋 Cliquer pour voir/masquer le code JSON</summary>

```json
{
  "name": "MJ Member - Publish on Facebook, Instagram & WhatsApp",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "mj-social-publish",
        "responseMode": "responseNode",
        "options": {}
      },
      "id": "Webhook",
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [220, 260]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "secret",
              "name": "sharedSecret",
              "value": "CHANGE_ME_SHARED_SECRET",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "SetSecret",
      "name": "Set Shared Secret",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [440, 260]
    },
    {
      "parameters": {
        "jsCode": "const crypto = require('crypto');\nconst body = JSON.stringify($json.body || {});\nconst timestamp = $json.headers?.['x-mj-timestamp'] || '';\nconst signature = ($json.headers?.['x-mj-signature'] || '').replace('sha256=', '');\nconst secret = $json.sharedSecret || '';\nif (!timestamp || !signature || !secret) throw new Error('Missing signature headers or secret');\nconst expected = crypto.createHmac('sha256', secret).update(`${timestamp}\\n${body}`).digest('hex');\nif (expected !== signature) throw new Error('Invalid signature');\nreturn [{ json: { ...$json, verified: true, payload: $json.body || {} } }];"
      },
      "id": "VerifySignature",
      "name": "Verify Signature",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [680, 260]
    },
    {
      "parameters": {
        "jsCode": "const payload = $json.payload || {};\nconst eventTitle = payload.event?.title || 'Événement';\nconst eventDate = payload.event?.date_start || '';\nconst eventUrl = payload.event_page_url || '';\nconst location = payload.event?.location || '';\nconst message = payload.message || '';\nconst imageUrl = payload.imageUrl || '';\n\nconst facebookMessage = `📢 ${eventTitle}\\n📅 ${eventDate}\\n📍 ${location}\\n\\n${message}\\n\\n👉 En savoir plus : ${eventUrl}`;\nconst instagramCaption = `✨ ${eventTitle}\\n📅 ${eventDate}\\n📍 ${location}\\n\\n${message}\\n\\n#événement #activité`;\nconst whatsappMessage = `📢 *${eventTitle}*\\n📅 ${eventDate}\\n📍 ${location}\\n\\n${message}\\n\\n👉 Détails: ${eventUrl}`;\n\nreturn [{ json: { facebookMessage, instagramCaption, whatsappMessage, eventTitle, eventUrl, imageUrl } }];"
      },
      "id": "FormatMessages",
      "name": "Format Messages for Platforms",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [900, 180]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v18.0/{{$json.facebookPageId}}/feed",
        "authentication": "predefined",
        "sendBody": true,
        "bodyParameters": {
          "parameters": [
            {
              "name": "message",
              "value": "={{$json.facebookMessage}}"
            },
            {
              "name": "link",
              "value": "={{$json.eventUrl}}"
            },
            {
              "name": "access_token",
              "value": "={{$json.facebookToken}}"
            }
          ]
        }
      },
      "id": "PublishFacebook",
      "name": "Publish to Facebook",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [1100, 80]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.instagram.com/v18.0/{{$json.instagramBusinessAccountId}}/media",
        "authentication": "predefined",
        "sendBody": true,
        "bodyParameters": {
          "parameters": [
            {
              "name": "image_url",
              "value": "={{$json.imageUrl}}"
            },
            {
              "name": "caption",
              "value": "={{$json.instagramCaption}}"
            },
            {
              "name": "access_token",
              "value": "={{$json.instagramToken}}"
            }
          ]
        }
      },
      "id": "PublishInstagram",
      "name": "Publish to Instagram",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [1100, 200]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://api.whatsapp.com/send",
        "authentication": "predefined",
        "sendBody": true,
        "bodyParameters": {
          "parameters": [
            {
              "name": "phone",
              "value": "={{$json.whatsappGroupId}}"
            },
            {
              "name": "text",
              "value": "={{$json.whatsappMessage}}"
            },
            {
              "name": "token",
              "value": "={{$json.whatsappToken}}"
            }
          ]
        }
      },
      "id": "PublishWhatsApp",
      "name": "Publish to WhatsApp Group",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [1100, 320]
    },
    {
      "parameters": {
        "jsCode": "// Compile results from all platforms\nconst results = {\n  facebook: { success: true, timestamp: new Date().toISOString() },\n  instagram: { success: true, timestamp: new Date().toISOString() },\n  whatsapp: { success: true, timestamp: new Date().toISOString() }\n};\n\nreturn [{ json: { message: 'Publication envoyée sur tous les réseaux', results, publishedAt: new Date().toISOString() } }];"
      },
      "id": "AggregateResults",
      "name": "Aggregate Results",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [1320, 200]
    },
    {
      "parameters": {
        "respondWith": "json",
        "responseBody": "={{$json}}",
        "options": {
          "responseCode": 200
        }
      },
      "id": "Respond",
      "name": "Respond to Webhook",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1.1,
      "position": [1500, 200]
    }
  ],
  "connections": {
    "Webhook": {
      "main": [[{ "node": "Set Shared Secret", "type": "main", "index": 0 }]]
    },
    "Set Shared Secret": {
      "main": [[{ "node": "Verify Signature", "type": "main", "index": 0 }]]
    },
    "Verify Signature": {
      "main": [[{ "node": "Format Messages", "type": "main", "index": 0 }]]
    },
    "Format Messages": {
      "main": [
        [
          { "node": "Publish to Facebook", "type": "main", "index": 0 },
          { "node": "Publish to Instagram", "type": "main", "index": 0 },
          { "node": "Publish to WhatsApp Group", "type": "main", "index": 0 }
        ]
      ]
    },
    "Publish to Facebook": {
      "main": [[{ "node": "Aggregate Results", "type": "main", "index": 0 }]]
    },
    "Publish to Instagram": {
      "main": [[{ "node": "Aggregate Results", "type": "main", "index": 0 }]]
    },
    "Publish to WhatsApp Group": {
      "main": [[{ "node": "Aggregate Results", "type": "main", "index": 0 }]]
    },
    "Aggregate Results": {
      "main": [[{ "node": "Respond to Webhook", "type": "main", "index": 0 }]]
    }
  },
  "active": false,
  "settings": {}
}
```

</details>

---

## Configuration des plateformes

### 🔵 Facebook

**Points d'accès requis** :
- Page ID : ID numérique de votre page Facebook
- Access Token : Token d'accès long terme (60 jours minimum)

**Procédure** :
1. Allez sur [developers.facebook.com](https://developers.facebook.com)
2. Créez une application ou sélectionnez-en une existante
3. Allez dans **Paramètres** → **Basic** → Copiez l'**App ID** et l'**App Secret**
4. Allez dans **Outils** → **Graph API Explorer**
5. Sélectionnez votre page dans le menu déroulant
6. Demandez la permission `pages_read_engagement,pages_manage_posts`
7. Générez un **User Access Token**, puis convertissez-le en **Page Access Token**
8. Trouvez votre **Page ID** en consultant `https://graph.facebook.com/me/accounts?access_token=YOUR_TOKEN`

**Dans n8n** :
- Créez une **Credentials** de type **HTTP Header Auth**
- Nœud : `Publish to Facebook`
- URL : `https://graph.facebook.com/v18.0/{FACEBOOK_PAGE_ID}/feed`
- Paramètres POST :
  - `message` : Texte du post
  - `link` : URL de l'événement
  - `access_token` : Votre Page Access Token

---

### 📷 Instagram

**Points d'accès requis** :
- Business Account ID : ID du compte Instagram Business lié à votre page Facebook
- Access Token : Le même token que Facebook

**Procédure** :
1. Votre compte Instagram doit être un **Instagram Business Account** (lié à votre Page Facebook)
2. Utilisez le même **Page Access Token** que Facebook
3. Trouvez votre **Instagram Business Account ID** avec :
   ```
   https://graph.instagram.com/me/instagram_business_accounts?access_token=YOUR_TOKEN
   ```

**Limitations Instagram** :
- ⚠️ Instagram n'autorise **pas** les posts via API pour les images. Vous devez utiliser le **Content Publishing API**
- Les images doivent être téléchargées d'abord, puis créées comme brouillons
- Alternative simple : utilisez **Zapier** ou mettez en place un **Content Calendar** manuellement

**Option recommandée pour Instagram** :
Remplacez le nœud `Publish to Instagram` par un nœud **Email** ou **Slack** qui envoie une notification avec l'image et la caption + lien de publication manuel. Exemple :

```
Envoi email à vous-même :
"Nouvelle publication Instagram à approuver :
Titre : {title}
Caption : {caption}
Image : {imageUrl}
Cliquer ici pour approuver : {admin_link}"
```

---

### 💬 WhatsApp (Groupe)

**Points d'accès requis** :
- Numéro WhatsApp Business ou Twilio
- Group ID ou numéro de téléphone du groupe
- API Token

**Deux options** :

#### Option A : WhatsApp Cloud API (officielle)
1. Créez un compte [WhatsApp Business](https://www.whatsapp.com/business/api/)
2. Validez votre numéro
3. Obtenez un **Permanent Access Token** avec les scopes :
   - `whatsapp_business_messaging`
   - `messages_and_connections_messaging`
4. Trouvez votre **Business Phone Number ID** dans les paramètres
5. Testez avec :
   ```
   POST https://graph.instagram.com/v18.0/{PHONE_NUMBER_ID}/messages
   {
     "messaging_product": "whatsapp",
     "to": "{GROUP_ID}",
     "type": "text",
     "text": { "body": "Votre message" }
   }
   ```

#### Option B : Twilio (plus simple)
1. Créez un compte [Twilio](https://www.twilio.com/)
2. Activez **WhatsApp Sandbox** (gratuit pendant 72h)
3. Envoyez `join ${code}` au numéro Twilio pour rejoindre
4. Utilisez votre **SID** et **Auth Token** pour l'API
5. Endpoint Twilio :
   ```
   POST https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages
   {
     "From": "whatsapp:+14155238886",
     "To": "whatsapp:+33XXXXXXXXX",
     "Body": "Votre message"
   }
   ```

**Dans n8n (Option A)** :
- Nœud : `Publish to WhatsApp Group`
- URL : `https://graph.instagram.com/v18.0/{PHONE_NUMBER_ID}/messages`
- Paramètres POST :
  - `messaging_product` : `"whatsapp"`
  - `to` : ID du groupe
  - `type` : `"text"`
  - `text.body` : Votre message

---

## Variables n8n à définir

Créez ces variables d'environnement dans **n8n Settings** → **Environment Variables** :

```bash
# Facebook
MJ_FACEBOOK_PAGE_ID="123456789"
MJ_FACEBOOK_TOKEN="EAAXxxx..."

# Instagram
MJ_INSTAGRAM_BUSINESS_ACCOUNT_ID="987654321"
MJ_INSTAGRAM_TOKEN="EAAXxxx..."

# WhatsApp
MJ_WHATSAPP_GROUP_ID="+33612345678" # ou ID du groupe
MJ_WHATSAPP_TOKEN="AC1234abc..."

# Secret partagé (très important !)
MJ_SHARED_SECRET="votre-secret-très-long-et-aléatoire"
```

Puis dans chaque nœud HTTP, référencez-les comme `$env.MJ_FACEBOOK_TOKEN`, etc.

---

## Contrat de réponse attendu

Le webhook doit répondre en HTTP 2xx avec un JSON de ce type :

```json
{
  "message": "Publication envoyée sur tous les réseaux",
  "results": {
    "facebook": { "success": true, "timestamp": "2026-03-23T12:00:00Z" },
    "instagram": { "success": true, "timestamp": "2026-03-23T12:00:00Z" },
    "whatsapp": { "success": true, "timestamp": "2026-03-23T12:00:00Z" }
  },
  "publishedAt": "2026-03-23T12:00:00Z"
}
```

Si une plateforme échoue, notifiez l'utilisateur dans la réponse :

```json
{
  "message": "Publication partiellement réussie",
  "results": {
    "facebook": { "success": true },
    "instagram": { "success": false, "error": "Image requise" },
    "whatsapp": { "success": true }
  }
}
```

---

## Vérification rapide

1. ✅ Importez le workflow n8n
2. ✅ Configurez les secrets et variables
3. ✅ Activez le workflow (`Active` = true)
4. ✅ Ouvrez un événement dans le widget **Gestionnaire**
5. ✅ Remplissez le message de publication
6. ✅ Cliquez sur **Publier via n8n**
7. ✅ Vérifiez l'exécution du workflow dans n8n
8. ✅ Confirmez la publication sur Facebook, Instagram et WhatsApp

---

## Dépannage

| Problème | Solution |
|----------|----------|
| **Signature invalide** | Vérifiez que le secret n8n correspond à celui en WordPress |
| **Token expiré** | Régénérez les tokens d'accès Facebook/Instagram/WhatsApp |
| **WhatsApp : " Group ID introuvable"** | Assurez-vous d'avoir rejoint le groupe via SMS/code |
| **Instagram : "Image requise"** | Instagram exige une image ; utilisez l'option Email alternative |
| **Pas de réponse du webhook** | Activez le workflow dans n8n et testez le nœud `Webhook` individuellement |
