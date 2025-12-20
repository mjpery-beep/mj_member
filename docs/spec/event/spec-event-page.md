## Page Event-single - Améliorations diverses
## Spécificités techniques
- Utilise un modèle MVC 
- Utilise les bundles Symfony
- Utilise Preact pour les rendu dynamique
- Utilise Twig pour les templates 
- Utilise au maximum les classe métier existante (CRUD, ... )


## Description du fonctionnement global des evénements
### Type d'événements
- Interne - événement non ouvert au public (réunion d'équipe, formation interne, etc.)
    Uniquement visible par les Animateurs, coordonnateurs et bénévoles.
### Article lié - couverture par défaut
- Permet de lier un contenu pédagogique a l'évenement. 
- Permet d'utiliser la photo de couverture de l'article comme image par défaut de l'événement.
- Lien "Voir le contenu pédagogique" sous la description de l'événement.
### Bénévoles et Animateurs
- Un evenement est lié a un ou plusieurs animateur ou bénévole 

### Inscription & accès
#### Autoriser les tuteurs.
- les tuteurs peuvent s'inscrire a l'événement a leur nom.
#### Participation libre 
- Lorsque cette option est cochée, il n'y a aucun systeme de réservation. 
  Les membres peuvent participer sans s'inscrire. 
#### Validation des inscriptions
- Les inscriptions peuvent être validées par un animateur ou bénévole.
- Un email de confirmation est envoyé a l'inscription.

### Planification
 - Date fixe (debut et fin le meme jour)  
    Une seule occurrence possible. (pas de selecteur de date sur la fiche event)
 - Plage de dates (plusieurs jours consecutifs)  
    Plusieurs occurrences possibles (en fonction de la gestion des occurences choisie).
 - Recurrence (hebdomadaire ou mensuelle)  
    Affiche le type d'occurence sur la fiche produit (ex: "Tous les lundis et vendredi de 18h a 20h")
    Plusieurs occurrences possibles (en fonction de la gestion des occurences choisie).
 - Série de dates personnalisées
    Affiche la liste des dates sur la fiche produit.
    Plusieurs occurrences possibles (en fonction de la gestion des occurences choisie).

### Gestion des occurrences
- Inscription a toutes les occurrences 
    Affiche la liste des occurences sur un calendrier (sans selection)
- Les membres choisissent les occurrences
    Affiche la liste des occurences sur un calendrier avec un selecteur (checkbox) pour chaque occurrence.

### Choix multiple d'occurence.
Affiche un calendrier avec une UI compatible mobile. Utilise Preact pour le rendu dynamique.
Quand on est déjà inscrit a certaines occurrences, on peux changer les occurences en cochant ou décochant les dates. Le bouton inscription devient "Mettre a jour mon inscription".

### Section Hero 
- Image de couverture (optionnelle)
- Titre de l'événement
- Type d'événement sous forme de label
- Dates de l'événement (format dynamique en fonction du mode de planification)

### Section side details 
- Dates de l'événement (format dynamique en fonction du mode de planification)
- Conditions d'accès (public, membres, tuteurs)
- Type d'inscription (obligatoire, libre, validation requise)

### Section Lieu 
- Nom du lieu
- Logo du lieu 
- Lien vers le site du lieux (optionnel)
- Adresse complète
- Carte interactive (Google Maps)

### Paiement d'un evenement
- Quand l'évenement est payant, affiche un résumé du paiement dans un popup lors de l'inscription.
- Sur ce popup on peux cliquer sur le lien de paiement Stripe. 
- Il y a un message qui dit : 
    Vous pouvez effectuér le paiement maintenant ou plus tards dans votre espace membre ou en main propre a un annimateur.

### Section Souvenir Partagés (Photo Gallery des membres)
- Galerie d'images uploadées par les membres
- Affichage en grille responsive
- Formulaire d'upload d'image avec description
- Modération des images par les animateurs coordinateurs et bénévoles

