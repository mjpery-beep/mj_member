# ⚡ À FAIRE MAINTENANT - Étapes Critiques

## 🚨 URGENT: Faites ceci dans les 10 prochaines minutes

### Étape 1: Révoquer la Clé Compromise (2 minutes)

1. Allez sur **https://dashboard.stripe.com**
2. Cliquez sur **Developers** (en haut)
3. Cliquez sur **API Keys** (à gauche)
4. Trouvez votre clé: `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`
5. Cliquez sur le menu **⋯** (trois points)
6. Sélectionnez **Delete**
7. Confirmez

✅ **FAIT** - La clé compromise est maintenant INUTILISABLE

---

### Étape 2: Générer une Nouvelle Clé (2 minutes)

1. Toujours dans **Developers → API Keys**
2. Cliquez sur **Create secret key**
3. Donnez un nom: `MJ Péry - New 2025`
4. **Cliquez sur la clé pour la copier** (elle n'apparaîtra qu'UNE FOIS)
5. Gardez-la ouverte dans un nouvel onglet

✅ **FAIT** - Vous avez une nouvelle clé secrète

---

### Étape 3: Mettre à Jour WordPress (3 minutes)

1. Allez sur **votre site WordPress** → **wp-admin**
2. Cliquez sur **MJ Péry** (menu de gauche)
3. Cliquez sur **Configuration** (ou similaire)
4. Trouvez la section **💳 Stripe - Paiements en ligne**
5. Cherchez le champ **Clé secrète Stripe**
6. **Effacez l'ancienne clé** (le champ devrait être vide)
7. **Collez la nouvelle clé** (celle que vous avez copiée à l'étape 2)
8. Cliquez sur **💾 Enregistrer les paramètres**

✅ **FAIT** - Votre système utilise la nouvelle clé

---

### Étape 4: Vérifier que Ça Marche (3 minutes)

1. Allez sur **Gestion des Membres**
2. Sélectionnez un membre
3. Cliquez sur **QR paiement**
4. Vérifiez qu'un QR code s'affiche
5. Ouvrez la console du navigateur **(F12)**
6. Dans l'onglet **Console**, tapez:
   ```javascript
   console.log('test');
   ```
   (Juste pour vérifier que la console fonctionne)

7. Cherchez dans la réponse AJAX (onglet **Network**):
   - ✅ Devrait avoir: `payment_id`, `qr_url`, `checkout_url`
   - ❌ NE devrait PAS avoir: `sk_live_`, `secret_key`

✅ **FAIT** - Aucune clé secrète exposée!

---

## 📋 Après (aujourd'hui - demain)

### À Faire Aujourd'hui:

- [ ] Exécuter la migration des clés:
  ```
  https://votresite.com/migrate-stripe-keys.php
  ```
  
- [ ] Supprimer le fichier `migrate-stripe-keys.php` après migration
  
- [ ] Suivre la checklist de vérification:
  ```
  Lire le fichier: SECURITY_VERIFICATION.md
  ```

### À Faire Cette Semaine:

- [ ] Auditer les logs pour détections suspectes:
  ```bash
  grep -i "sk_live_\|error" /wp-content/debug.log | head -20
  ```

- [ ] Vérifier les transactions Stripe:
  - Allez sur https://dashboard.stripe.com
  - Cliquez sur **Payments**
  - Cherchez des transactions suspectes

- [ ] Sauvegarder votre site:
  ```bash
  # Faire une backup complète
  ```

### À Faire Ce Mois-Ci:

- [ ] Notifier les clients si nécessaire
- [ ] Mettre en place une rotation annuelle des clés Stripe
- [ ] Ajouter un monitoring pour détecter les leaks futurs

---

## 📞 En Cas de Problème

### Problème: "Clé non valide" ou "Stripe non configuré"

**Solution:**
1. Vérifiez que vous avez collé la BONNE clé (celle avec `sk_live_`)
2. Vérifiez qu'il n'y a pas d'espaces avant/après
3. Re-sauvegardez les paramètres
4. Videz le cache du navigateur (Ctrl+Maj+Suppr)

### Problème: QR Code n'Affiche Pas

**Solution:**
1. Ouvrez la console (F12)
2. Cherchez les erreurs rouge
3. Vérifiez `/wp-content/debug.log`
4. Assurez-vous que PHP 7.2+ et OpenSSL sont installés

### Problème: Paiement Échoue

**Solution:**
1. Vérifiez que c'est bien en mode **Live** (pas test)
2. Vérifiez que Stripe n'a pas bloqué quelque chose
3. Allez sur https://dashboard.stripe.com et vérifiez les logs Stripe

---

## 🎯 Résumé des Fichiers Importants

```
🔐 Sécurité (À LIRE):
├─ SECURITY_FIX.md             ← Actions immédiates
├─ SECURITY_SUMMARY.md         ← Vue d'ensemble
├─ SECURITY_CHANGELOG.md       ← Détails techniques
└─ SECURITY_VERIFICATION.md    ← Checklist de vérification

🔧 Utilitaires:
└─ migrate-stripe-keys.php     ← À exécuter puis supprimer

📁 Code Modifié:
├─ includes/classes/MjStripeConfig.php
├─ includes/classes/MjPayments.php
├─ includes/security.php
└─ mj-member.php
```

---

## ✅ Checklist Finale

- [ ] Clé compromise revoquée sur Stripe
- [ ] Nouvelle clé générée sur Stripe
- [ ] Nouvelle clé configurée dans WordPress
- [ ] QR code fonctionne sans montrer la clé
- [ ] Paiement de test fonctionne
- [ ] Migration des clés exécutée
- [ ] Fichier `migrate-stripe-keys.php` supprimé
- [ ] Logs vérifiés (pas de clé exposée)
- [ ] Transactions Stripe vérifiées
- [ ] Aucune activité suspecte détectée

---

**Temps estimé:** 15-20 minutes  
**Criticité:** 🚨 ÉLEVÉE  
**Deadline:** Aujourd'hui

💪 Vous pouvez le faire! C'est facile et ça ne prend que 15 minutes.
