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
- Import CSV pour faciliter la migration de données existantes

✅ **Fiches complètes**
- Nom, prénom, coordonnées et date de naissance
- Statut actif ou inactif, date d'inscription et dernier paiement
- Rattachement à un type de membre : jeune, tuteur, animateur ou bénévole
- Génération de cartes d'adhérent en PDF pour les membres

✅ **Gestion des événements et stages**
- Création et gestion d'événements avec inscriptions en ligne
- Suivi des présences et gestion des animateurs assignés
- Calendrier interactif avec vue mensuelle et événements récurrents
- Gestion des lieux et des fermetures de la structure
- Partage de photos liées aux événements

✅ **Encodage des heures bénévoles**
- Widget calendrier hebdomadaire pour encoder les heures de travail
- Glisser-déposer pour ajuster facilement les plages horaires
- Suivi par projet et par animateur/bénévole
- Tableau de bord administratif avec statistiques et visualisations
- Totaux automatiques par jour, semaine et projet

✅ **Gestion des tâches (Todos)**
- Système de gestion de tâches pour l'équipe
- Assignation aux animateurs et bénévoles
- Suivi de l'avancement avec notes et médias
- Organisation par projets

✅ **Boîte à idées**
- Espace pour que les membres proposent des idées d'activités
- Système de vote pour prioriser les suggestions
- Interface accessible aux jeunes actifs

✅ **Gestion documentaire**
- Intégration Google Drive pour centraliser les documents
- Accès sécurisé selon les permissions
- Navigation et recherche dans les dossiers

✅ **Communications**
- Modèles d'e-mails personnalisables
- Envoi ciblé aux membres selon leur statut ou rôle
- Formulaire de contact avec gestion des messages
- Préférences de notification pour les membres

✅ **Interface pensée pour l'équipe**
- Formulaire clair avec des sections faciles à parcourir
- Filtres simples pour retrouver un jeune ou un tuteur
- Apparence alignée avec WordPress pour limiter l'apprentissage nécessaire
- Widgets Elementor pour personnaliser les pages front

## Installation

1. Copiez le dossier `mj-member` dans `/wp-content/plugins/`
2. Activez le plugin depuis le menu **Extensions** de WordPress
3. Une fois activé, le menu **Membres** apparaît automatiquement dans votre tableau de bord

## Widgets Elementor disponibles

Le plugin propose une riche collection de widgets Elementor pour personnaliser votre site :

### Widgets membres et comptes
- **Connexion** : formulaire de connexion personnalisable
- **Inscription** : formulaire d'inscription avec paiement Stripe
- **Mon Compte** : tableau de bord personnel pour les membres
- **Menu Compte** : menu de navigation pour les pages membres
- **Profil** : affichage et édition du profil membre
- **Préférences de notification** : gestion des préférences de communication

### Widgets événements
- **Calendrier événements** : calendrier interactif mensuel avec vue détaillée
- **Prochains événements** : liste des événements à venir (liste, grille ou slider)
- **Inscription événement** : formulaire d'inscription aux événements
- **Photos événement** : galerie de photos liées aux événements
- **Lieux** : liste des lieux disponibles pour les événements

### Widgets équipe
- **Animateurs** : annuaire des animateurs avec filtres
- **Compte animateur** : tableau de bord dédié aux animateurs
- **Encodage heures** : calendrier hebdomadaire pour encoder les heures de travail

### Widgets communication
- **Formulaire de contact** : formulaire de contact avec validation
- **Messages de contact** : gestion des messages reçus

### Widgets activités
- **Boîte à idées** : proposition et vote d'idées d'activités
- **Todos** : liste des tâches à accomplir
- **Documents** : accès aux documents partagés via Google Drive
- **Photo Grimlins** : transformation ludique de photos en avatars stylisés avec IA
- **Galerie Grimlins** : galerie des photos transformées

### Widgets paiements
- **Abonnement** : formulaire d'abonnement avec Stripe
- **Confirmation paiement** : page de confirmation après paiement

## Utilisation

### Menu admin
- Cliquez sur **Membres** dans le menu WordPress pour afficher la liste
- Utilisez le bouton **Ajouter** pour enregistrer un nouvel adhérent
- Sélectionnez **Modifier** ou **Supprimer** directement depuis la liste selon vos actions

### Gestion des événements
- Accédez à **Événements** pour créer et gérer vos stages et activités
- Assignez des animateurs, définissez les capacités et gérez les inscriptions
- Consultez les présences et exportez les listes de participants

### Encodage des heures
- Les animateurs et bénévoles peuvent encoder leurs heures via le widget dédié
- Visualisez les statistiques dans le tableau de bord administratif
- Exportez les données pour la comptabilité ou les rapports

## Paiements et abonnements

### Stripe en mode test
- Le plugin est livré avec Stripe configuré en mode test pour éviter tout encaissement réel
- Pour simuler un paiement, utilisez la carte bancaire fictive suivante : `4242 4242 4242 4242`
- Choisissez n'importe quelle date d'expiration future et un CVC à trois chiffres (ex. `123`)
- Stripe demande parfois un code postal ; vous pouvez saisir `10000`
- Les paiements testés apparaissent dans le tableau de bord Stripe en mode test

### Configuration
- Configurez vos clés API Stripe dans les paramètres du plugin
- Basculez entre mode test et production selon vos besoins
- Consultez le fichier `STRIPE_SETUP.md` pour plus de détails

## Intégrations

### Google Drive
- Intégration native pour la gestion documentaire
- Configurez vos identifiants Google Drive dans les paramètres
- Créez un dossier racine pour organiser vos documents
- Gérez les permissions d'accès selon les rôles

### OpenAI
- Fonctionnalité "Photo Grimlins" utilisant l'API OpenAI
- Transforme les photos des membres en avatars stylisés et amusants inspirés du film Gremlins
- Configurez votre clé API OpenAI dans les paramètres
- Mode ludique pour engager les jeunes et créer une ambiance conviviale

## Protection des données

- Les formulaires sont sécurisés pour éviter les modifications non autorisées
- Les informations sensibles sont vérifiées avant d'être enregistrées
- Seuls les utilisateurs disposant des droits WordPress adaptés peuvent gérer les données
- Politique de rétention des données conforme au RGPD
- Nettoyage automatique des fichiers temporaires (photos, uploads)

## Développement futur

- [ ] Rappels automatiques : emails ou SMS pour les renouvellements d'adhésion et les paiements à venir
- [ ] Rapports prêts à l'emploi : exports avancés pour les subventions et les bilans associatifs
- [ ] Pointage sur place : scan de carte ou QR code pour enregistrer les présences des jeunes
- [ ] Intégration SMS : envoi de notifications par SMS via Twilio ou équivalent
- [ ] Application mobile : accès mobile pour les membres et animateurs

## Support

Pour toute question ou problème, veuillez contacter l'équipe de développement.

## Licence

Tous droits réservés © 2025
