# 🚨 TL;DR - Version Ultra Rapide (30 secondes)

## Le Problème
Votre clé secrète Stripe (`sk_live_51ST0VK...`) était visible dans les QR codes. 
  **C'est critique - quelqu'un pouvait pirater votre compte Stripe.**

## La Solution
On a chiffré la clé, filtré les réponses web, et bloqué l'API REST. Votre clé n'est maintenant jamais exposée.

## Ce Que Vous Devez Faire MAINTENANT (15 minutes)
1. Allez sur **dashboard.stripe.com** et supprimez l'ancienne clé `sk_live_51ST0VK...`
2. Générez une nouvelle clé
3. Collez-la dans **WordPress → MJ Péry → Configuration → Clé secrète Stripe**
4. Cliquez **Enregistrer**
5. Exécutez **https://votresite.com/migrate-stripe-keys.php** pour chiffrer la clé

**C'est tout!** Votre Stripe est maintenant sécurisé.

---

## Plus de Détails?
- **Quick Start:** `ACTION_NOW.md`
- **Comprendre le problème:** `SECURITY_FIX.md`
- **Vérifier que c'est sécurisé:** `SECURITY_VERIFICATION.md`

---

**Status:** ✅ FIXÉ | **Temps Action:** ~15min | **Priorité:** 🚨 CRITIQUE
