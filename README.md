# MJ Member - Plugin WordPress

## Description
MJ Member centralise la gestion des membres, des événements et de la communication d'une Maison de Jeunes dans l'interface WordPress. Le module fournit des écrans administratifs cohérents, des widgets Elementor prêts à l'emploi et un suivi complet des paiements Stripe.

## Pourquoi MJ Member
- Regroupe les dossiers des jeunes, tuteurs, animateurs et bénévoles dans un même espace.
- Automatise l'encodage des heures, la gestion des cotisations et le suivi des présences.
- Renforce la collaboration grâce aux tâches partagées, à la messagerie interne et aux workflows de validation.
- Capte les idées des membres et valorise la participation via un portail front Elementor.
- Protège les données sensibles en s'appuyant sur les capacités WordPress et des contrôles dédiés.

## Fonctionnalités principales
- **Membres et profils** : formulaires rapides, imports CSV, statuts personnalisés, PDF d'adhésion et historique complet.
- **Événements et présences** : création d'activités, inscriptions en ligne, calendrier interactif, listes d'attente et affectation des animateurs.
- **Temps et planification** : encodage glisser-déposer des heures bénévoles, totaux multi-projets et exports prêts pour la comptabilité.
- **Collaboration interne** : gestionnaire de to-dos, boîte à idées avec votes, messagerie centralisée et notifications ciblées.
- **Paiements et documents** : intégration Stripe sécurisée, suivi des cotisations et participations, connecteur Google Drive pour les pièces jointes.
- **Interface front** : widgets Elementor configurables couvrant compte membre, événements, paiements, documents et expériences IA ludiques.

## Captures d'écran
- **Encodage des heures pour les animateurs/coordinateurs**  
  ![Encodage des heures pour les animateurs/coordinateurs](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202055.png)  
  _Suivi hebdomadaire des heures encodées avec totaux automatiques et filtres dédiés._
- **Gestionnaire de to-dos pour les animateurs/coordinateurs**  
  ![Gestionnaire de to-dos pour les animateurs/coordinateurs](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202107.png)  
  _Planifiez et suivez les actions clés de l'équipe en regroupant les priorités par projet._
- **Crée ton avatar grâce à l'IA**  
  ![Crée ton avatar grâce à l'IA](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202128.png)  
  _Générez en un clic un portrait ludique pour animer les profils membres._
- **Boîte à idées**  
  ![Boîte à idées](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202135.png)  
  _Recueillez et priorisez les propositions d'activités soumises par les jeunes._
- **Données individuelles et gestion de la cotisation avec paiement en ligne**  
  ![Données individuelles et gestion de la cotisation avec paiement en ligne](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202218.png)  
  _Retrouvez l'historique d'un membre, ses statuts et ses paiements Stripe au même endroit._
- **Gestionnaire centralisé des paiements (cotisation/participation événements)**  
  ![Gestionnaire centralisé des paiements (cotisation/participation événements)](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202230.png)  
  _Suivez les encaissements en temps réel et filtrez par type d'opération._
- **Gestionnaire des messages en interne**  
  ![Gestionnaire des messages en interne](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202259.png)  
  _Centralisez les échanges entrants pour ne manquer aucun message important._
- **Calendrier des événements dynamique**  
  ![Calendrier des événements dynamique](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202306.png)  
  _Visualisez l'ensemble des stages et fermetures avec navigation rapide par période._
- **Fiche d'un événement**  
  ![Fiche d'un événement](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20202316.png)  
  _Accédez aux détails pratiques, aux inscrits et aux animateurs associés en un coup d'œil._
- **Éditeur des événements pour les animateurs/coordinateurs**  
  ![Éditeur des événements pour les animateurs/coordinateurs](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20205602.png)  
  _Préparez vos séances avec des formulaires guidés et des validations intégrées._
- **Gestionnaires des membres pour les animateurs/coordinateurs**  
  ![Gestionnaires des membres pour les animateurs/coordinateurs](screen-capture/Capture%20d%27%C3%A9cran%202026-01-03%20205815.png)  
  _Parcourez la base adhérents avec filtres rapides et actions groupées._


## Installation
1. Copier le dossier mj-member dans wp-content/plugins.
2. Activer MJ Member depuis le menu Extensions.
3. Configurer les capacités et clés Stripe dans les réglages du plugin.

## Widgets Elementor
- Membres : connexion, inscription, tableau de bord, navigation compte, préférences et paiements.
- Événements : calendrier mensuel, liste des prochains événements, formulaires d'inscription, photos et lieux.
- Équipe : annuaire animateurs, compte animateur et encodage des heures.
- Collaboration : boîte à idées, to-dos, documents, messagerie de contact.
- Paiements : abonnement avec Stripe, confirmation post-paiement, expériences Photo/Galerie Grimlins.

## Utilisation quotidienne
- Menu Membres : ajouter, filtrer, exporter et gérer les statuts des adhérents.
- Menu Événements : planifier stages, suivre les présences, relancer les inscrits et éditer les fiches.
- Widget Heures : les animateurs encodent leurs prestations, les coordinateurs valident et exportent.
- Paiements : surveiller les transactions Stripe, gérer les échecs et éditer les reçus.
- Communication : centraliser les messages entrants, envoyer des campagnes ciblées et suivre les réponses.

## Paiements et intégrations
- Stripe : mode test pour valider les flux, bascule en production une fois les clés live renseignées.
- Google Drive : synchronisation des dossiers partagés avec contrôle fin des accès.
- OpenAI : génération d'avatars via Photo Grimlins pour dynamiser l'espace membre.

## Protection des données
- Vérifications de capacités WordPress avant chaque action sensible.
- Nonces et validation côté serveur sur les formulaires front.
- Nettoyage automatisé des fichiers temporaires et respect du RGPD.
- Journalisation des mises à jour critiques pour faciliter les audits.

## Développement futur
- Rappels automatisés pour renouvellements et paiements.
- Rapports financiers prêts pour les subventions.
- Pointage QR code sur site.
- Notifications SMS via passerelle dédiée.
- Application mobile pour animateurs et membres.

## Support

Pour toute question ou problème, veuillez contacter l'équipe de développement.

## Licence

Tous droits réservés © 2025
