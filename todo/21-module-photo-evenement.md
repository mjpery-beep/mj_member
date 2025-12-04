# Module photo pour les événements

## Contexte
- Les jeunes participants veulent partager leurs photos prises lors des événements.
- Le module doit s’inscrire dans le flux existant des événements/stages.

## Objectifs
- Autoriser les jeunes ayant participé à un événement à téléverser des photos.
- Associer chaque photo à l’événement concerné et au compte du participant.
- Prévoir la modération côté administrateur avant publication.

## Pistes / Questions
- Où stocker les fichiers (Bibliothèque WP ou dossier dédié) ?
    dans un dissoer dédié 
- Faut-il limiter le nombre de photos par participant ?
    Le nombre de photo est limité par evenement (3 photos par participant)
- Comment notifier l’équipe d’animation lorsqu’une photo est ajoutée ?
    Prévoir un widget de validation pour les animateurs. 

## Tâches proposées
- [x] Définir le modèle de données (table ou métadonnées) pour les galeries d’événements.
- [ ] Concevoir l’interface front (dans la page single event) pour envoyer des photos.
- [ ] Ajouter un écran d’administration pour modérer et publier les photos.
        -> widget de validation pour les animateurs
- [ ] Gérer les miniatures et l’optimisation des images.
- [ ] Vérifier les droits d’accès (participants uniquement) et les règles RGPD.
