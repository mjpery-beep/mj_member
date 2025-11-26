# 20. Partage de photos lie aux evenements

## 20.1 Collecte des photos
- [ ] Autoriser les jeunes marques "present" sur un evenement a uploader des photos depuis leur espace (widget photo dans "Mon compte").
- [ ] Limiter le poids et les formats des fichiers (JPEG/PNG/HEIC) avec un feedback clair en cas de rejet.
- [ ] Associer chaque upload a l'inscription (`event_id`, `member_id`, `registration_id`) pour tracer l'auteur et la date d'ajout.

## 20.2 Moderation et validation
- [ ] Configurer un flux de moderation (animateur ou admin) avant publication : file d'attente, statut (en_attente, publie, refuse).
- [ ] Envoyer une notification a l'equipe responsable lorsqu'une photo est soumise.
- [ ] Permettre la suppression ou le masquage rapide d'une photo deja publiee.

## 20.3 Affichage et experience utilisateur
- [ ] Afficher les photos valides sur la fiche evenement (grille responsive ou carrousel) avec metadonnees minimales (auteur, date d'ajout).
- [ ] Offrir un telechargement ou partage securise aux membres autorises.
- [ ] Ajouter des indicateurs de progression (upload en cours, en attente de validation) cote jeune.

## 20.4 Conformite et stockage
- [ ] Stocker les photos dans la mediathque WordPress avec un repertoire dedie et des droits restreints.
- [ ] Recueillir et journaliser le consentement du jeune et/ou du tuteur pour la diffusion des photos.
- [ ] Prevoir une politique de retention (suppression automatique au bout de X mois ou sur demande).

## 20.5 Widget « Mon compte »
- [ ] Creer un widget Elementor "Galerie photos evenements" accessible depuis la page "Mon compte".
- [ ] Y afficher la liste des evenements passes auxquels le jeune etait present avec acces rapide a l'upload et a la galerie correspondante.
- [ ] Respecter les capacites WordPress pour limiter l'acces aux seuls comptes autorises et gerer les retours d'erreur.
