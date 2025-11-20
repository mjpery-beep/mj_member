# MJ Member - Plugin WordPress

## Description
MJ Member est un module WordPress conçu pour centraliser la gestion des adhérents d'une Maison de Jeunes. Il offre une interface claire dans l'administration WordPress afin de suivre facilement les jeunes, leurs tuteurs, les animateurs et les bénévoles.

## Pourquoi c'est utile pour une Maison de Jeunes
- Centralise les dossiers des jeunes, tuteurs et bénévoles dans un seul tableau de bord.
- Simplifie le suivi des paiements, adhésions annuelles et statuts d'activité.
- Accélère l'onboarding des nouveaux membres grâce à un formulaire guidé et cohérent entre équipes.
- Améliore la communication interne : chaque animateur accède aux informations utiles selon ses permissions WordPress.
- Protège les données personnelles grâce aux sécurités natives de WordPress renforcées par le plugin.

## Fonctionnalités principales

✅ **Gestion des membres**
- Ajouter, consulter, modifier ou retirer un membre en quelques clics
- Visualiser la liste complète, page par page si besoin, avec une recherche intégrée
- Effectuer des actions individuelles ou groupées selon vos besoins

✅ **Fiches complètes**
- Nom, prénom, coordonnées et date de naissance
- Statut actif ou inactif, date d'inscription et dernier paiement
- Rattachement à un type de membre : jeune, tuteur, animateur ou bénévole

✅ **Interface pensée pour l'équipe**
- Formulaire clair avec des sections faciles à parcourir
- Filtres simples pour retrouver un jeune ou un tuteur
- Apparence alignée avec WordPress pour limiter l'apprentissage nécessaire

## Installation

1. Copiez le dossier `mj-member` dans `/wp-content/plugins/`
2. Activez le plugin depuis le menu **Extensions** de WordPress
3. Une fois activé, le menu **Membres** apparaît automatiquement dans votre tableau de bord

## Utilisation

### Menu admin
- Cliquez sur **Membres** dans le menu WordPress pour afficher la liste
- Utilisez le bouton **Ajouter** pour enregistrer un nouvel adhérent
- Sélectionnez **Modifier** ou **Supprimer** directement depuis la liste selon vos actions

## Paiements Stripe en mode test

- Le plugin est livré avec Stripe configuré en mode test pour éviter tout encaissement réel.
- Pour simuler un paiement, utilisez la carte bancaire fictive suivante : `4242 4242 4242 4242`, choisissez n'importe quelle date d'expiration future et un CVC à trois chiffres (ex. `123`).
- Stripe demande parfois un code postal ; vous pouvez saisir `10000`.
- Les paiements testés apparaissent dans le tableau de bord Stripe en mode test et aident à vérifier les inscriptions sans risque.

## Protection des données

- Les formulaires sont sécurisés pour éviter les modifications non autorisées
- Les informations sensibles sont vérifiées avant d'être enregistrées
- Seuls les utilisateurs disposant des droits WordPress adaptés peuvent gérer les données

## Développement futur

- [ ] Suivi des activités : planifier les ateliers et relier chaque jeune à ses participations
- [ ] Rappels automatiques : emails ou SMS pour les renouvellements d'adhésion et les paiements à venir
- [ ] Rapports prêts à l'emploi : exports pour les subventions et les bilans associatifs
- [ ] Pointage sur place : scan de carte ou QR code pour enregistrer les présences des jeunes

## Support

Pour toute question ou problème, veuillez contacter l'équipe de développement.

## Licence

Tous droits réservés © 2025
