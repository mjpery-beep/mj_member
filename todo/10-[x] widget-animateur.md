# 10. Composant Elementor pour les animateurs

- [x] Créer un composant Elementor "Liste des membres" pour "Mon compte". Qui permet a l'animateur de voir qui participe a ces évenements et de relever leur présence (une boite permet de communiquer par sms a l'ensemble des participants). 
    - [x] Anticiper les inscriptions événements récurrents. (prévoir une relation avec une date de l'événement, ou plusieurs dates)
    - [x] Permettre de marquer les membres qui sont effectivement présents lors de l'évenement.
    - [x] Permettre de voir les membres par évenement et de les contacter (sms).
            - Message type : rappels
    - [x] Permettre de filtrer par événement, par date.
- [x] Ajouter des options dans le pannel des Element de config du widget (ex: filtre, choix des couleurs, affichage des boutons,... )
- [x] Ajouter un bouton pour que l'animateur voir tout les évenements (et pas uniquement ceux qui lui sont attribué)
- [x] L'occurence selectionnée est soit celle d'aujourd'hui soit la prochaine. Ajoute un agenda pour voir les occurances. 
- [x] Dans la liste des participants : Afficher le nom du membre. Supprimer la colone contact. La colone présence n'est pas assez visuel. Le changement dois se faire avec ajax. 
- [x] Dans la liste des participants : On peux envoyé un message individuellement, ou a tout le groupe. 
- [x] Si l'evenement est payent, l'animateur peux voir si le jeune a payer soit dire accepeter l'argent en liquide et cliquer sur 'A payé' pour l'indiquer au systeme
- [x] Dans la liste des participants : La selection des Evenements n'est pas intuitive. Je veux une liste avec des vignettes (cover de l'évenemnt ), un lien vers l'évenement et des détails sous forme plusieur petite box sur une ligne. Il y a des flèches gauche droite pour afficher d'autre évenement.  Quand on clique sur l'évenement la liste des participants apparaits. 
- [x] Permettre la création rapide d'un membre depuis le tableau animateur (popup).
- [x] Ajout un boutou pour affiche le lien de paiement (si prix >0) doit se faire via stripe (comme pour la cotisation)
- [x] Quand un animateur inscrit un jeune il n'y a pas besoin d'envoyé un mail "Nouvelle inscription: " il y a un email uniquement quand un jeune s'inscirt sur le site.