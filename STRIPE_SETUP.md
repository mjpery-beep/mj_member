# 💳 Intégration Stripe - Guide d'Installation

## Vue d'ensemble

Le plugin MJ Member supporte maintenant **Stripe** pour générer les QR codes de paiement directement depuis les sessions de paiement Stripe. Cela permet aux membres de scaner un QR code avec leur téléphone pour payer directement.

## 🚀 Configuration

### Étape 1: Créer un compte Stripe

1. Accédez à [stripe.com](https://stripe.com)
2. Créez un compte gratuit
3. Complétez la vérification de l'identité
4. Une fois vérifié, allez à votre **Tableau de bord**

### Étape 2: Récupérer vos clés API

1. Cliquez sur **Paramètres** → **Clés API**
2. Vous trouverez deux clés:
   - **Clé publique (pk_...)** - À partager
   - **Clé secrète (sk_...)** - À garder secrète ⚠️

### Étape 3: Configurer le plugin

1. Allez dans **Membres** → **Paramètres**
2. Trouvez la section **"💳 Stripe - Paiements en ligne"**
3. Collez vos clés:
   - Clé publique Stripe
   - Clé secrète Stripe
4. Cliquez sur **Enregistrer les paramètres**

### Étape 4: Tester

1. Allez dans la liste des membres
2. Cliquez sur le bouton **"QR paiement"** pour un membre
3. Un QR code sera généré et affiché
4. Scannez-le avec un téléphone pour tester le lien Stripe
5. Vérifiez la redirection finale vers la page configurée dans les paramètres Stripe du plugin

## ✅ Scénarios de test recommandés

### Mode test Stripe

1. Activez le mode test dans **Membres → Paramètres → Stripe**.
2. Générez un paiement depuis une fiche membre (utilisez un montant symbolique, par exemple 2 €).
3. Sur la page Stripe Checkout, utilisez la carte de test `4242 4242 4242 4242`, date future, CVC `123`.
4. Finalisez le paiement et confirmez que la redirection mène bien vers `https://www.mj-pery.be/inscit` (ou l'URL définie dans les paramètres).
5. Vérifiez dans Stripe (mode test) que la session de paiement apparaît en statut **succeeded**.
6. Répétez avec une annulation (bouton « Annuler et retourner ») pour vérifier l'URL d'annulation.

### Mode production Stripe

1. Désactivez le mode test et vérifiez que les clés **LIVE** sont actives.
2. Utilisez un petit montant et un moyen de paiement réel (carte ou Apple Pay) pour tester.
3. Confirmez la redirection post-paiement vers la page de confirmation souhaitée.
4. Vérifiez dans le tableau de bord Stripe que le paiement apparaît en statut **succeeded** et que l'email du payeur correspond bien.
5. Annulez un paiement depuis l'étape Stripe pour valider la redirection d'annulation.
6. Notez les références Stripe (Payment Intent, Checkout Session) pour suivi dans l'interface MJ Member.

> Conseil : réalisez au moins un test complet avant chaque ouverture d'inscription importante afin de confirmer que les clés Stripe n'ont pas expiré et que les redirections sont toujours valides.

## 🗒️ Journal Stripe

- Les retours `stripe_success` et `stripe_cancel` sont automatiquement journalisés dans `wp-content/uploads/mj-member/stripe-events.log` pour faciliter le support.
- Les webhooks `checkout.session.completed` et `payment_intent.succeeded` alimentent également ce journal avec l'identifiant Stripe et l'ID membre quand ils sont disponibles.
- En cas d'erreur de signature ou de secret manquant, le fichier de log indiquera le motif pour accélérer le diagnostic.

## 📱 Fonctionnement

### Avant (système simple):
- ❌ QR code généré avec Google Chart API
- ❌ Lien de confirmation personnalisé
- ❌ Pas de intégration paiement réelle

### Après (avec Stripe):
- ✅ QR code généré avec session Stripe réelle
- ✅ Lien pointant directement vers Stripe Checkout
- ✅ Paiements traités sécurisés par Stripe
- ✅ Redirection automatique après paiement

## 🔒 Sécurité

- ⚠️ **Ne jamais** partager votre clé secrète (sk_...)
- ✅ La clé publique (pk_...) peut être partagée
- ✅ Toutes les données de paiement sont chiffrées
- ✅ Stripe gère la conformité PCI DSS

## 📋 Variables disponibles

Dans le fichier `settings.php`, vous pouvez personnaliser:

- **Montant par défaut**: Actuellement 2.00 € (modifiable dans `MjPayments::create_payment_record()`)
- **Description du produit**: "Cotisation annuelle MJ Péry"
- **Email de succès**: Redirige après le paiement

## 🛠️ Suivi du statut de paiement

- Le fichier `stripe-webhook.php` vérifie la signature Stripe, met à jour la base MJ Member puis écrit un log de chaque événement reçu.
- Les confirmations `checkout.session.completed` déclenchent la mise à jour des paiements et l'envoi des emails (si configuré).
- Le log `stripe-events.log` est la source de vérité pour suivre les succès, annulations et erreurs côté Stripe.

## 📚 Ressources

- [Documentation Stripe API](https://stripe.com/docs/api)
- [Stripe Checkout](https://stripe.com/docs/payments/checkout)
- [Stripe PHP SDK](https://github.com/stripe/stripe-php)

## ❌ Dépannage

### "Stripe n'est pas configuré"
→ Vérifiez que vos clés sont correctement entrées dans les paramètres

### "Erreur lors de la génération du QR"
→ Vérifiez que votre clé secrète Stripe est valide
→ Assurez-vous que curl est activé sur votre serveur

### "Le QR code ne redirige pas vers Stripe"
→ Vérifiez la clé publique
→ Assurez-vous que l'URL du site est correcte

## 🎯 Prochaines étapes recommandées

1. Implémenter les webhooks Stripe pour confirmations automatiques
2. Ajouter des emails de confirmation après paiement
3. Afficher l'historique des paiements dans l'admin
4. Ajouter des rappels automatiques pour paiements non effectués

---

**Dernière mise à jour**: February 2026
**Support Stripe**: Contactez support@stripe.com
