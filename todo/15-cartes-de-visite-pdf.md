# 15. Prévoir la génération d'un PDF avec des carte de visite des membres

- [x] Créer une fonctionnalité pour générer un PDF contenant des cartes de visite pour les membres.
    - [x] Chaque carte doit inclure: nom, prénom, rôle (membre/tuteur/animateur), date d'adhésion
    - [x] Permettre la sélection de membres spécifiques ou de groupes (ex: tous les animateurs) pour la génération du PDF.
    - [x] Format des cartes: standard (85x55mm) avec une mise en page professionnelle.
    - [x] Ajouter des options de personnalisation (couleurs, logo MJ, police).
    - [x] Intégrer un bouton dans l'admin MJ Member pour lancer la génération et le téléchargement du PDF.
- [x] Ajoute la possiblité d'uploader un background image dans les settings (Onglet Cartes de visite) qui viendra décorer la carte de membre. 
- [x] Ajouter un QR code unique sur chaque carte qui pointe vers une url a clé unique pour soit
        => si pas de wp_user_id lui proposer de créer un password et de valider les informations restante au compte qu'on possède deja
        => si wp_user_id renvoyer vers la page Login 

