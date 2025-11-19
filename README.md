# MJ Member - Plugin WordPress

## Description
Module WordPress complet pour gérer les membres avec une interface CRUD (Create, Read, Update, Delete) dans l'administration WordPress.

## Caractéristiques

✅ **Gestion complète CRUD**
- Créer de nouveaux membres
- Consulter les membres (liste paginée)
- Éditer les informations des membres
- Supprimer des membres (action unique ou en masse)

✅ **Champs gérés**
- **Informations du membre**
  - Nom et prénom
  - Email
  - Téléphone
  - Date de naissance

- **Type de membre**
  - Jeune
  - Tuteur > peux gerer plusieurs membres.
  - Animateur 
  - Bénévole
- **Autres données**
  - Statut (Actif/Inactif)
  - Date d'inscription (automatique)
  - Date du dernier paiement

✅ **Interface utilisateur**
- Tableau d'affichage des membres avec pagination
- Filtrage et recherche
- Actions rapides (édition, suppression)
- Suppressions en masse
- Formulaire intuitif avec sections organisées
- Styles WordPress personnalisés

## Installation

1. Placez le dossier `mj-member` dans `/wp-content/plugins/`
2. Activez le plugin depuis l'administration WordPress
3. La table `wp_mj_members` sera créée automatiquement

## Utilisation

### Menu admin
- Allez dans **Membres** dans le menu WordPress
- Vous verrez la liste des membres
- Cliquez sur **Ajouter un membre** pour en créer un nouveau
- Cliquez sur **Éditer** pour modifier un membre
- Cliquez sur **Supprimer** pour suppprimer un membre

### Fonctionnalités

#### Ajouter un membre
1. Cliquez sur "Ajouter un membre"
2. Remplissez les informations obligatoires (*)
3. Complétez les champs optionnels si nécessaire
4. Cliquez sur "Enregistrer"

#### Éditer un membre
1. Depuis la liste, cliquez sur "Éditer"
2. Modifiez les informations souhaitées
3. Cliquez sur "Enregistrer"

#### Supprimer un membre
- Option 1 : Cliquez sur "Supprimer" dans la liste
- Option 2 : Cochez les cases des membres et sélectionnez "Supprimer" dans les actions groupées

## Structure du code

```
mj-member/
├── mj-member.php                 # Fichier principal du plugin
├── css/
│   └── styles.css                # Styles personnalisés
└── includes/
    ├── classes/
    │   ├── MjTools.php           # Classe utilitaire de base
    │   ├── MjMembers_CRUD.php     # Opérations CRUD sur la table
    │   ├── MjMembers_List_Table.php  # Tableau d'affichage
    │   └── MjMail.php            # Gestion des emails
    ├── form_member.php           # Formulaire d'ajout/édition
    └── table_members.php         # Affichage du tableau
```

## Classe MjMembers_CRUD

Méthodes disponibles :

```php
// Obtenir tous les membres
MjMembers_CRUD::getAll($limit, $offset);

// Compter les membres
MjMembers_CRUD::countAll();

// Obtenir un membre par ID
MjMembers_CRUD::getById($id);

// Créer un membre
MjMembers_CRUD::create($data);

// Mettre à jour un membre
MjMembers_CRUD::update($id, $data);

// Supprimer un membre
MjMembers_CRUD::delete($id);

// Chercher des membres
MjMembers_CRUD::search($query);

// Filtrer par statut
MjMembers_CRUD::getByStatus($status);
```

## Sécurité

- Vérification des nonces pour les formulaires
- Sanitization de tous les inputs
- Utilisation des requêtes préparées ($wpdb->prepare)
- Échappement des données en sortie
- Vérification des permissions WordPress

## Développement futur

- [ ] Import/Export CSV
- [ ] Envoi d'emails d'inscription avec le lien du paiement
- [ ] 

## Support

Pour toute question ou problème, veuillez contacter l'équipe de développement.

## Licence

Tous droits réservés © 2025
