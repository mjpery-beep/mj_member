# ✅ Guide de Vérification de Sécurité

Après avoir appliqué les corrections de sécurité, utilisez ce guide pour vérifier que votre système est correctement sécurisé.

## 1️⃣ Vérifier que la Clé est Chiffrée

### Via WordPress Admin

1. Allez sur **Tools → Site Health → Debug**
2. Cherchez la section "wp_options" dans le debug dump
3. Vérifiez que:
   - `mj_stripe_secret_key` n'existe **PAS** (option supprimée) ✅
   - `mj_stripe_secret_key_encrypted` existe et contient une longue chaîne chiffrée ✅

### Via MySQL (pour développeurs)

```sql
-- Vérifier que la clé en plaintext a été supprimée
SELECT option_name, option_value FROM wp_options 
WHERE option_name LIKE '%stripe%';

-- Résultat attendu:
-- | mj_stripe_publishable_key    | pk_live_...
-- | mj_stripe_secret_key_encrypted | [base64-encoded encrypted string]
-- ✅ PAS de mj_stripe_secret_key

-- Vérifier que la clé chiffrée est valide
SELECT LENGTH(option_value) FROM wp_options 
WHERE option_name = 'mj_stripe_secret_key_encrypted';
-- ✅ Devrait être ~150+ caractères
```

## 2️⃣ Vérifier que les QR Codes ne Contiennent PAS de Clé Secrète

### Test Manuel

1. Allez sur **Gestion des Membres**
2. Cliquez sur **"QR paiement"** pour n'importe quel membre
3. Ouvrez les **Developer Tools (F12)**
4. Allez dans l'onglet **Network**
5. Cliquez à nouveau sur **"QR paiement"**
6. Cherchez la requête AJAX `admin-ajax.php?action=mj_admin_get_qr`
7. Cliquez dessus et allez dans **Response**
8. Vérifiez que la réponse **N'CONTIENT PAS** de chaîne commençant par `sk_`:

```json
// ✅ BON - Réponse sécurisée:
{
  "success": true,
  "data": {
    "payment_id": 12345,
    "stripe_session_id": "cs_live_a1b2c3...",
    "checkout_url": "https://checkout.stripe.com/...",
    "qr_url": "https://chart.googleapis.com/chart?...",
    "amount": "2.00"
  }
}

// ❌ MAUVAIS - Clé exposée:
{
  "success": true,
  "data": {
    "secret_key": "sk_live_51ST0VK...", // 🚨 DANGEREUX!
    ...
  }
}
```

### Recherche Automatisée

Collez ceci dans la console du navigateur:

```javascript
// Chercher "sk_" ou "sk_live_" dans toutes les réponses AJAX
(function() {
    let originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            let clonedResponse = response.clone();
            clonedResponse.text().then(text => {
                if (text.includes('sk_live_') || text.includes('sk_test_')) {
                    console.error('🚨 SÉCURITÉ CRITIQUE: Clé Stripe trouvée!');
                    console.error('Réponse:', text.substring(0, 200));
                }
            });
            return response;
        });
    };
    console.log('✅ Monitoring AJAX activé');
})();
```

## 3️⃣ Vérifier que l'API REST est Protégée

### Test d'Accès aux Endpoints Sensibles

```bash
# Test 1: Essayer d'accéder à la clé secrète via l'API REST
curl -i https://votresite.com/wp-json/wp/v2/options/mj_stripe_secret_key

# Résultat attendu:
# ✅ HTTP/1.1 403 Forbidden (ou 404 Not Found)
# ❌ HTTP/1.1 200 OK [serait une faille]

# Test 2: Essayer d'accéder aux paramètres SMTP
curl -i https://votresite.com/wp-json/wp/v2/options/mj_smtp_settings

# Résultat attendu:
# ✅ HTTP/1.1 403 Forbidden
# ❌ HTTP/1.1 200 OK [serait une faille]

# Test 3: Vérifier la clé publique (ça c'est OK)
curl https://votresite.com/wp-json/wp/v2/options/mj_stripe_publishable_key

# Résultat attendu:
# ✅ HTTP/1.1 403 Forbidden (par défaut, wp_options pas exposé en REST)
```

## 4️⃣ Vérifier que les Paiements Fonctionnent

### Test de Paiement Complet

1. Allez sur **Gestion des Membres**
2. Créez un membre de test ou sélectionnez un existant
3. Cliquez sur **"QR paiement"**
4. Vérifiez que:
   - ✅ Un QR code s'affiche
   - ✅ L'image du QR code se charge correctement
   - ✅ Un lien de paiement s'affiche
   - ❌ Pas de message d'erreur

5. Scannez le QR code avec votre téléphone:
   - ✅ Vous êtes redirigé vers **Stripe Checkout**
   - ✅ Vous voyez le montant et la description du produit
   - ✅ Vous pouvez entrer les détails de carte (mode test)

6. Dans Stripe Dashboard:
   - ✅ Allez dans **Payments**
   - ✅ Vous devriez voir une tentative de paiement
   - ✅ Le statut doit être **Succeeded** (mode test)

## 5️⃣ Vérifier les Logs pour les Erreurs

### Vérifier debug.log

```bash
# Accédez au fichier de debug
tail -f /wp-content/debug.log

# Cherchez les ERREURS (ne devraient pas contenir sk_)
grep -i "error\|warning\|sk_" /wp-content/debug.log

# ✅ BON:
# [WARNING] MjPayments: Clé secrète Stripe manquante

# ❌ MAUVAIS:
# [WARNING] sk_live_51ST0VK...
```

### Vérifier qu'aucune tentative de leak ne s'est produite

```bash
grep -r "sk_live_\|sk_test_" /wp-content/logs/
# ✅ Devrait être vide

grep "get_secret_key\|mj_stripe_secret_key" /wp-content/debug.log
# ✅ Vérifier qu'il n'y a pas d'expositions accidentelles
```

## 6️⃣ Tester la Résilience

### Qu'Arrive-t-il Si la Clé est Compromise?

1. **Avant la correction:** L'attaquant pouvait traiter des paiements
2. **Après la correction:** L'attaquant:
   - ❌ Ne peut pas voir la clé dans les QR codes
   - ❌ Ne peut pas accéder à la clé via l'API REST
   - ❌ Ne peut pas voir la clé dans les debug logs
   - ✅ DOIT avoir accès au serveur PHP pour déchiffrer

### Scénario: Ancien Lien de Paiement avec Clé Compromise

**Avant:** L'ancien lien QR content toujours la clé
```
https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=sk_live_51ST0VK&...
```

**Après:** Même si quelqu'un a l'ancien lien, il ne fonctionne plus car:
1. La clé a été revoquée dans Stripe Dashboard
2. Les nouveaux QR codes n'utilisent que des URLs publiques
3. La session Stripe est créée côté serveur

## 7️⃣ Checklist Finale

| Vérification | Résultat | Notes |
|---|---|---|
| Clé secrète chiffrée | ✅ | Vérifier via wp_options |
| QR code sans clé secrète | ✅ | Inspecter la réponse AJAX |
| API REST protégée | ✅ | Tester /wp-json/wp/v2/options |
| Paiements fonctionnels | ✅ | Test avec mode test Stripe |
| Logs propres | ✅ | Aucun sk_ visible |
| Ancienne clé revoquée | ✅ | Vérifier Stripe Dashboard |
| Nouvelle clé configurée | ✅ | Tester un paiement |

## 🆘 En Cas de Problème

### Problème: "API Keys Not Configured"
```php
// Vérifier dans debug.log
// Solution: Reconfigurer les clés via MJ Péry → Configuration
```

### Problème: QR Code n'Affiche Pas
```javascript
// Ouvrir Console (F12)
// Chercher les erreurs
// Si erreur 500, vérifier /wp-content/debug.log
```

### Problème: Paiement Échoue
```bash
# Vérifier:
# 1. La clé Stripe n'est pas compromise
# 2. La clé Stripe est en mode "live" (pas test)
# 3. Le montant n'est pas 0
# 4. L'email du membre est valide
```

### Problème: "Accès refusé" sur API REST
```
C'est NORMAL! C'est une protection de sécurité.
L'API REST ne doit pas exposer les clés secrètes.
```

---

## 📞 Escalade

Si vous trouvez une issue:

1. **Notez les détails exactes:**
   - Qu'avez-vous essayé de faire?
   - Quelle était l'erreur?
   - Quels logs avez-vous?

2. **Consultez `/wp-content/debug.log`:**
   ```bash
   tail -100 /wp-content/debug.log
   ```

3. **Vérifiez les prérequis:**
   - PHP 7.2+ (pour AES-256-CBC)
   - OpenSSL activé
   - wp_salt() fonctionne

4. **Contactez le support avec:**
   - URL du site
   - Version WordPress
   - Version du plugin
   - Excerpt du debug.log

---

**Dernière mise à jour:** 2025  
**Statut:** ✅ SÉCURISÉ
