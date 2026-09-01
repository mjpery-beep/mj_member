# 22. Widget formulaire de contact

## Objectif
Mettre à disposition des animateurs et coordinateurs un widget Elementor permettant aux visiteurs d'envoyer des messages ciblés (animateur spécifique, coordinateur ou tous les destinataires).

## User stories
- En tant que visiteur, je peux choisir le destinataire du message (animateur assigné, coordinateur, tous) afin que ma demande soit transmise au bon interlocuteur.
- En tant que coordinateur, je consulte l'historique des messages depuis l'admin WordPress dans la section "Maison de jeune" > "Messages".
- En tant que coordinateur, je gère les tickets associés aux messages (statuts à valider, assignation, suivi).

## Fonctionnalités attendues
 - [x] **Widget Elementor**
   - Formulaire avec champs : nom, email, sujet, message, choix du destinataire (liste des animateurs + options "Coordinateur" et "Tous").
   - Gestion des états de soumission (succès, erreurs de validation).
   - Protection anti-spam basique (nonce, honeypot ou reCAPTCHA existant si disponible).
 - [x] **Persistance des messages**
   - Création d'une table personnalisée pour stocker les messages (id, auteur, email, destinataire, contenu, statut du ticket, timestamps).
   - Enregistrement systématique de chaque soumission.
 - [x] **Interface admin**
   - Nouveau sous-menu dans "Maison de jeune" intitulé "Messages" listant les entrées.
   - Vue liste avec filtres par statut, destinataire, date.
   - Fiche détaillée d'un message avec historique des changements de statut.
   - Actions rapides pour basculer l'état de lecture depuis la liste et la fiche du ticket.
 - [x] **Widget Elementor Messages**
   - Liste les messages récents associés aux tickets avec indicateur de non lecture.
   - Propose un filtre rapide (non lus uniquement, assignés à l'utilisateur courant).
   - Permet de marquer un message comme lu/non lu et d'envoyer une réponse directe.
   - Prévoit une compatibilité complète avec la prévisualisation Elementor via des données factices.
  - Supporte une configuration d'expéditeur via options ou constantes (`MJ_MEMBER_CONTACT_FROM_EMAIL`, `MJ_MEMBER_CONTACT_FROM_NAME`).
  - [x] **Liens compte**
    - Ajoute un lien "Messages" dans le widget Elementor des liens Mon Compte avec compteur de messages non lus.
 - [x] **Système de tickets**
   - Statuts proposés : `nouveau`, `en_cours`, `résolu`, `archivé`.
   - Possibilité d'assigner/mettre à jour le ticket dans l'admin.
   - Journal d'activité minimal (utilisateur, date, action) pour la traçabilité.

## Points techniques
- [x] Reposer sur la structure CRUD existante dans `includes/classes/crud/` pour la gestion des messages.
- [x] Ajouter les hooks nécessaires dans `core/assets.php` pour charger les scripts du widget.
- [x] Prévoir des capacités WordPress dédiées (ex: `mj_manage_contact_messages`).
- [x] Vérifier la compatibilité avec le mode prévisualisation Elementor (jeu de données factices si aucun message).

## Validation
- [x] Soumission du formulaire avec chaque option de destinataire.
- [x] Vérification de la création et de la mise à jour des tickets dans l'admin.
- [x] Contrôle de l'affichage du widget dans Elementor (mode prévisualisation inclus).
