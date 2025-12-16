# Archives TODO MJ Member

## 1. Composant Elementor Connexion
- [x] Corriger le widget Elementor de connexion pour que le popup s'affiche correctement (vérifier JS et dépendances chargées).
- [x] Ajouter des options de configuration visuelle supplémentaires (couleurs, typographie, coins arrondis, ombres) directement depuis Elementor.
- [x] Quand ont est connecté le bouton donne accès a un popup avec 
       - Mes inscriptions stages
       - Mes données personnelles
       - Mes inscriptions Evenements
       - Déconnection
- [x] Dans la popup mon compte ajoute comme titre bienvenu [nom du membre]
- [x] Ajouter dans les réglages la sélection d'un avatar par défaut pour les membres sans photo.
- [x] Dans la popup mon compte afficher la photo du membre avec fallback sur l'avatar configuré.
- [x] Ajoute dans les options Elementor l'icone qui apparait sur le bouton
- [x] Ajoute sur le popup login le lien vers l'inscription 
        - Dans la configuration il faut choisir la page d'inscription
- [x] Afficher le nombre de messages non lus dans une bulle sur le bouton du widget de connexion

## 2. Page "Mon Compte" Elementor
- [x] Créer template/composant Elementor "Mes informations" utilisable sur la page mon compte
    - [x] Permettre au membre de modifier ses informations personnelles et sa photo.
    - [x] Créer template/composant Elementor  qui Afficher le statut de la cotisation (dernier paiement, montant, rappel si dû). Si la cotisation a expirée on peut créer le liens vers le stripe. 
    - [x] Quand je suis tuteur je dois payer pour mes enfants (afficher la cotisation des enfants dans le template/composant Elementor du statut cotisation)
    - [x] Créer template/composant Elementor pour Lister les événements / stages auxquels le membre est inscrit.
- [x] Ajouter un widget Elementor affichant une colonne avec les liens "Mon compte".
- [x] Chaque lien de mon compte peux être modifier dans le panel de control du componant (les liens pointes vers une pages)
- [x] Ajoute un current_page pour savoir quel lien est actif dans le menu "Mon compte"
- [x] Retirer le popup sur le bouton "Mon compte" de le widget login. Mettre un lien direct vers la page "Mon compte"
- [x] La taille du texte des liens dans le menu "Mon compte" doit être plus petit.
- [x] Le titre est "Mon espace" et nom "Mon espace MJ"
- [x] Mes données personnelles : Page mon-compte
- [x] Mes photos
- [x] Mes inscription : Page inscriptions
- [x] Animateurs : Page animateurs
- [x] le lien de déconnection ne doit pas être modifié dans le panel elementor. (logout wordpress par defaut)
- [x] La configuration des liens se fait dans le settings du module et plus dans le panel elementor du widget.
- [x] Retirer le lien animateur
- [x] Ajouter un lien Gestion Evenements Uniquement pour les membres qui sont animateurs => page evenements.
- [x] Ajouter un lien Gestion des membres Uniquement pour les membres qui sont animateurs

## 3. Paiements Stripe
- [x] Rediriger l'utilisateur vers `https://www.mj-pery.be/inscit` apres un paiement Stripe reussi.
    - [x] Ajouter option dans les settings pour personnaliser l'URL de redirection (après paiement).
    - [x] Tester le flux en mode test et production.
- [x] Harmoniser les URLs de retour en cas d'annulation (page informative dediee).
- [x] Journaliser les evenements Stripe (success, cancel, webhook) pour faciliter le support.
- [x] Je veux un widget elementor success après paiement qui affiche un message personnalisé et le récapitulatif de la transaction (montant, date, etc). 
    -> https://www.mj-pery.be/inscrit/?stripe_success=1&session_id=cs_test_a16EvEP0ZQlKQfeCC7FIh0XlSZlHD9FvaL5ADEmwm6cth7Bkju8KJLUmhG&mj_event_id=27&mj_registration_id=23
    Si mj_event_id sont présents dans l'url on affiche le récapitulatif de la transaction (Nom de l'event).
    si c'est une cotisation on affiche le récapitulatif de la cotisation.
- [x] Dans l'admin je ne vois pas dans l'historique des paiements les paiements effectués via stripe pour les événements. Je veux voir dans l'historique des paiements les paiements effectués via stripe pour les événements.
    - [x] Ajouter un filtre pour voir uniquement les paiements d'événements.
- [x] Quand on paie pour une ou plusieurs occurrences le montant doit être multiplé par le nombre d'occurrences sélectionnées. On doit voir dans l'admin/animateur-widget le détail du paiement avec le nombre d'occurrences payées.
- [ ] Au moment de payer on doit pouvoir choisir entre un paiement en ligne (stripe) ou un paiement en espèce (à un animateur) - dans l'email de confirmation d'inscription que le membre recois il y a le lien de paiement.

## 4. Module Événements / Stages
- [x] Creer une table `wp_mj_events` via une migration schema.
    - Champs: titre, statut (actif, brouillon, passe), type (stage, soiree, sortie), cover (attachment id), description, age_min (defaut 12), age_max (defaut 26), date_debut, date_fin, date_fin_inscription (defaut 14 jours avant), prix, created_at, updated_at.
    - [x] Ajouter des index sur `statut` et `date_debut`.
- [x] Implementer `MjEvents_List_Table` pour l'administration.
    - [x] Filtres par statut et type, recherche rapide.
    - [x] Actions groupees (activer, archiver, dupliquer, supprimer).
- [x] Interface d'edition d'un evenement.
    - [x] Uploader une cover, definir les dates, valider les limites d'age et d'inscription.
    - [x] Permettre un champ "description detaillee" compatible blocks.
- [x] Associer les membres aux evenements (table `wp_mj_event_registrations`).
    - Champs: event_id, member_id, guardian_id, statut (en_attente, valide, annule), notes, created_at.
    - [x] Empêcher l'inscription si la date limite est depassee ou si l'age est hors plage.
    - [x] Envoyer une notification email au tuteur et a l'equipe MJ lors d'une inscription.
- [x] Ajouter un lien vers un membre (Animateur en charge de l'event) Lors d'une inscription prévenir le membre qui est en charge de l'event
    -> la liste des lieux utilise maintenant le fallback de la table legacy et l'animateur recoit un mail avec le lien admin.
- [x] Corrigé le bug d'échappement dans la description détaillée de l'évenement. (exemple : C\'est cool)
- [x] Associer un lieux a l'évènement 
- [x] Faire un gestionnaire de lieux pour éviter de les ré-encodé.
        - [x] géolocalisation, utilise google map qui affiche le lieux en fonction de l'adresse
        - [x] cover image du lieu (logo)
        - [x] Préencode les lieux de base : La bibi, La Mj Péry, La citadelle, ...

## 5. Composants Elementor Événements
- [x] Ajouter un widget Elementor pour lister les evenements actifs (vue liste).
    - [x] Parametres: type(s) d'evenement, type (stage, soirée, sortie), nombre max, tri (date debut DESC), affichage grille ou liste.
    - [x] Utiliser la cover comme visuel, fallback si absent.
    - [x] Propose différents layout d'affichage (des évènements)
- [x] Bouton "S'inscrire" conditionnel integre au widget liste.
    - [x] Cache automatiquement si la date de fin d'inscription est depassee.
    - [x] Si utilisateur connecte: ouvrir un panneau permettant de choisir un jeune associe ou soi-meme.
    - [x] Si non connecte: afficher le composant modal de connexion.
    - [x] Si un membre s'est déjà inscrit affiche le dans la partie "Qui participera ?"
    - [x] Ajoute un champs de texte "note" pour que la personne s'inscrive communique. 
    - [x] Ajoute un bouton pour se désinscrire (fait un genre de bouton on/off pour chaque membre)
            (La note Message pour l’équipe (optionnel) concerne chaque membre)
    - [x] Ajoute un carte interactive (google map) pour montrer ou se trouve l'évenement. 
    - [x] ajoute la description du lieu et l'image en petit
    - [x] quand on est Animateur on peut voir les inscrit 
    - [x] Ajouter un champ "Parent autoriser" sur l'event et permetre l'inscription du tuteur sur cette condition.
    - [x] Ajouter dans les paramètres du composant Elementor "widget event liste" plus d'option sur l'apparence (bouton, titre, tarifs (on/off/si 0 off), layout,...).
- [x] Ajouter un widget Elementor "Calendrier des evenements" affichant les evenements par date.
    - [x] Navigation mensuelle et mise en avant du prochain evenement.
    - [x] Synchroniser les filtres (type, statut) avec le widget liste.
    - [x] Ajouter sur le calendrier la photo de l'event en tout petit. 
    - [x] Ajouter dans les paramètres du composant Elementor Calendrier plus d'option sur l'apparence.
    - [x] Ajouter sur le calendrier "onclick event > modal box > avec tout les détails (description, titre,maps, s'inscrire)"
    - [x] Retirer l'option Mettre en avant le prochain évènement
    - [x] Mettre dans une autre couleur la date d'aujourd'hui.
- [x] Ajouter un widget Elementor "Lieux MJ" sous forme de carte google maps listant les lieux partenaires.
    - [x] Afficher la cover en tout petit sur le marker google maps quand on click on vois le détailt : cover (légèrement plus grand), adresse, infos pratiques.
    - [x] Options d'affichage: grille, slider ou carte interactive.
- [x] Assurer la compatibilite responsive et l'utilisation des couleurs globales Elementor.
- [x] Supprimer la popup sur le click de l'event et ajouter le lien vers la page dédiée de l'évenement.

### Ajustements Widget Liste des Événements
- [x] Récupère la couleur de l'event pour l'afficher dans la "Carte" 
- [x] Supprime google maps et met juste le logo du lieu avec l'adresse en dessous.
- [x] Supprime le bouton d'inscription
- [x] Mets le prix dans un style spécial (uniquement si supérieur a zero)
- [x] Soigne la mise ne page de la carte de l'event (alignement, espace, typo)
- [x] Reconfigure le panel style du widget liste des évenements pour ajouter plus d'option (ex: couleur de fond (blanc), couleur du texte, taille des images, ... )
- [x] Permettre de choisir le nombre d'événements par ligne en mode grille (2, 3, 4)
- [x] Permettre de filtrer en fonctione de article associé. (Liste des articles wordpress lié a un évenement)
- [x] Supprime "Détail de l'evenement"
- [x] Afficher un résumé sur les évenements occurences de type "Tous les lundis de 16h00 à 21h00"
- [x] Quand je clique sur la vignette ca ouvre la page dédiée de l'évenement
- [x] Le bande "Soirée", "Stage" doit être affiché par dessus de la cover de l'évenement (coin supérieur droite)
- [x] Supprime la liste d'occurences dans la carte de l'évenement
- [x] Le modules n'affiche pas les évenements avec occurence (1 évenement avec plusieurs dates)
- [x] Le widget list n'affiche pas les évenements avec occurence (1 évenement avec plusieurs dates)
- [x] sur le widget list Supprime la liste des Participants inscrits
- [x] sur le widget list permet dans le panel de config elementor de n'afficher qu'un seule éléments en mode wide (1 évenement par ligne avec image plus grande
- [x] Remplace la phrase "Prochaine occurrence le ..." par "Prochaines dates : ..." (n'affiche pas l'heure)
- [x] Fait une petite mise en page pour les heure d'ouverte
- [x] retire l'information "Inscriptions cloturées"

## 6. Tableau de bord administrateur
- [x] Creer une page "Tableau de bord" dans le menu MJ Member.
    - [x] Bloc "Presentation de la MJ" editable via `wp_options`.
    - [x] Indicateurs clefs: membres actifs, paiements recents (30 jours), animateurs actifs.
    - [x] Graphique (barres ou ligne) sur les inscriptions/paiements mensuels.
- [x] Ajouter un widget WordPress Dashboard affichant un resume des stats.
- [x] Ajouter un système d'onglets dans la page de réglages afin de séparer les thématiques principales.
- [x] Dans le tableau de bord, ajouter un résumé des prochaines échéances de cotisation des membres.
- [x] Dans le tableau de bord, ajouter un résumé des événements à venir avec le nombre d'inscriptions.
- [x] Dans le tableau de bord, ajouter statistiques des membres
- [x] Dans le tableau de bord, ajouter statistiques des événements (inscriptions, annulations, liste des événements complets).
- [x] Dans le tableau de bord, supprime la "présentation de la mj"
- [x] Ajouter un widget affichant les 5 derniers membres inscrits avec leur statut (actif/inactif) et date d'inscription.
- [x] Met les "Événements à venir" dans un panel séparé, le panel doit être plus large, le tableau plus estéthique et lisible.

## 7. Envoi de SMS
- [x] Le membre peux décider si il veux recevoir des sms ainsi que des newsletters. 
- [x] Le module d'envoie d'email permet aussi d'envoyé des sms. 
- [x] Les templates mails comporte aussi un champs avec le sms (texte plus concis)
- [x] Ajoute twilio comme service d'envoi de SMS par defaut (ajoute la gestion des clefs api dans les settings du module)

## 8. Import CSV membres
- [x] Créer une interface d'import CSV dans l'admin MJ Member.
    - [x] Permettre le mapping des colonnes CSV aux champs membres.
    - [x] Gérer les doublons basés sur l'email (mettre à jour ou ignorer).
    - [x] Afficher un rapport détaillé après import (nombre importés, mis à jour, erreurs).

## 9. Widget Elementor Événements – Améliorations
### 9.1 Widget Carte des lieux
- [x] Le widget Carte des lieux (class-mj-member-locations-widget) doit permettre de filtrer par type de lieu le widget evenement liste (class-mj-member-events-widget).

### 9.2 Widget Calendrier – Améliorations
- [x] Gérer l’affichage des événements récurrents (hebdomadaire et mensuel) dans le widget calendrier.

### 9.3 Widget Calendrier – Harmonisation « Agenda mensuel »
- [x] Reproduire l’entête calendrier (flèches de navigation, bouton « Aujourd’hui », libellé du mois) identique à la maquette fournie.
- [x] La largeur des colones du calendrier doit être égale, seule la hauter des colones peut varier en fonction du nombre d'événements. 
- [x] Le texte des événements doit être plus petit pour éviter la surcharge visuelle.
- [x] Les stages et autre évenement sur plusieurs jours doivent être affichés en bandeau horizontal continu sur les jours concernés.
- [x] Ajouter les jour de fermeture dans le widget calendrier - table closure (ex: MJ fermée le lundi) 
- [x] Permettre la sélection et le filtrage par type d’événement (case à coché) 
        Type : stage, sortie, soirée, atelier, fermeture   
- [x] Prévoir les variantes responsive (≤768px) : pile verticale des actions, navigation compacte, affichage des événements en liste déroulante.

### 9.4 Module Event admin + Widget Calendrier
- [x] Ajouter une palette pastel (color picker admin) sur chaque évenement. Les type d'évenemenet ont une couleur prédéfinie qui peux être surclassé pour définir la couleur de chaque événement et l’afficher dans le widget calendrier. 
- [x] Prévoir une synchronisation officielle des événements MJ vers un Google Agenda (configurable dans les settings du module) pour partager automatiquement le planning.
- [x] Le widget calendrier ne fonctionne pas sur tablette et sur mobile. Prévoir les variantes responsive (≤768px) : pile verticale des actions, navigation compacte, affichage des événements en liste déroulante.

### 9.5 Page événement dédiée
- [x] Fournir une URL propre par événement (`/date/slug`) menant vers une page dédiée affichant titre, dates, description, prix, lieu (avec carte), lien vers l'article, limites d’âge et bouton d’inscription. (si tu vois d'autre information a afficher n'hesite pas)
- [x] Aligner l’affichage des événements à occurrences multiples (widget liste + page dédiée) sur l’expérience animateur.
- [x] Forcer les widgets (agenda et liste) à ouvrir la page événement dédiée (`/date/slug`).
- [x] Réorganiser la page dédiée avec une section inscription (paiement), la liste des animateurs et les informations complètes du lieu.
- [x] Proposer la sélection des occurrences lors de l'inscription publique.
- [x] Permettre la gestion des occurrences inscrites (désinscription ciblée) et masquer le CTA d'inscription lorsqu'un créneau fixe est déjà confirmé.

## 10. Composant Elementor pour les animateurs
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
- [x] Remplacer les carrousels des événements et occurrences par des listes repliables avec bouton de réaffichage.

## 11. Gestionnaire des emails et SMS
- [x] je veux que tout les email systeme (inscription évenement, paiement, rappel cotisation, ...) soit géré par le systeme de template du module. (cherche les envoie d'email qui ne passe par le gestionnaire du module des mail)
- [x] Lors de la sauvegearde d'un template email/sms il y un problème d'échappement des apostrophes (')
- [x] Prévoir un dans les paramètre du compte "widget profile" ou l'utilsateur choisise quelle sms ou mail il veux être notifié.
    > inscription évenement, paiement, rappel cotisation, rappel evenement, info nouveau evenement
- [x] Je veux que le service de SMS (le moins chère et avec l'api la plus simple) soit configuré dans les settings du module

## 12. Édition d'un événement (admin)
- [x] permettre de choisir soit :
        - une date fixe (avec plage horaire)
        - plage de date (ex: du 10 au 15 aout)
        - soit une récurrence (tout les vendredi, chaque 1er samedi du mois, mardi une semaine sur deux)
- [x] Corriger la migration pour ajouter `schedule_mode` et `schedule_payload` aux tables existantes.
- [x] Erreur lors de l'enregistrement d'un évenement avec une récurrence : 
    Il semble que le schema de la table `wp_mj_mj_events` n'a pas été mis à jour correctement pour inclure les nouveaux champs `schedule_mode` et `schedule_payload`. Veuillez vérifier que la migration de la base de données a été exécutée avec succès et que les colonnes ont bien été ajoutées.
- [x] Ajouter atelier dans le type d'événement possible.
- [x] Ajouter un système de gestion des places limitées par évenement avec notification quand le seuil est atteint.
- [x] Ajout un boutou pour affiche le lien de paiement (si prix >0) doit se faire via stripe (comme pour la cotisation)
- [x] Aligner le formulaire d'édition avec la logique stage/atelier du widget animateur :
    - reprendre un sélecteur d'occurrence clair (stage = toutes, atelier = une seule occurrence)
    - persister le scope choisi dans `schedule_payload` et l'exploiter côté validation admin
    - faire apparaitre dans l'aperçu admin les conditions et compteurs comme sur le widget
- [ ] Ajouter un widget d'assistance IA dans le formulaire : bouton dans la section description ouvrant un prompt pour générer du contenu via ChatGPT, avec stockage sécurisé des tokens API.
- [ ] Étendre la section configuration du module pour documenter l'obtention des clés ChatGPT et permettre leur saisie + chiffrement côté base de données.

## 13. Module Membres
### 13.1 Formulaire d'édition d'un membre (admin)
- [x] Champ « Surnom » et consentement WhatsApp dans le formulaire admin (07/12/2025)

### 13.2 Tableau des membres (admin)
- [x] Met l'info (cotisation, Dispense de cotisation), consentement image, date de creation, date de mise a jour dans la meme cellule.
- [x] Met un titre sur les cellules du détail 
- [x] Dans la colonne accès et actions, le bouton Détail doit changer de nom en "WP user".
- [x] dans la colonne Détail on ne sais pas editer la date d'anniversaire (inline sur l'age).

## 15. Cartes de visite PDF
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

## 18. Sécurité et conformité
- [x] Vérifier les capacités et nonces sur toutes les nouvelles pages admin (import, tableau de bord, etc.).
- [x] Documenter et appliquer une politique de conservation des données membres (RGPD).
- [x] Mettre en place des tests unitaires pour les hooks critiques (connexion, paiement, inscription).

## 19. Documentation et support
- [ ] Compléter `README.md` avec les captures d'écran clés et la procédure d'installation Stripe.
- [ ] Rédiger une doc utilisateur pour l'équipe MJ (PDF ou Notion) couvrant les scénarios principaux.
- [ ] Ajouter un guide contributeur expliquant conventions de code, tests et nomenclature des fichiers.
- [ ] Préparer une FAQ interne avec les problèmes fréquents et les procédures de résolution.

## 20. Widget « Mes Réservations » (jeunes)
- [x] Settings "Liens « Mon compte »", Mes inscriptions affiche : uniquement réservé aux jeunes membres.
- [x] Limiter l'affichage « Mes réservations » sur la page événement aux participations du membre connecté.
- [x] Rafraîchir en direct le panneau « Mes réservations » après une inscription ou une annulation.
- [x] Permettre l’annulation d’une réservation directement depuis le panneau.
- [ ] Affiche un qr code de paiement pour les réservations en attente de paiement.
- [ ] Intégrer une section « Actions à réaliser » qui consolide les réservations comportant un paiement en attente ou nécessitant une question à l’animateur, en listant l’action attendue et le canal recommandé (mail, téléphone, formulaire interne).
- [ ] Harmoniser le style graphique avec celui du widget photo (typographies, couleurs d’accent, bordures) en factorisant les classes CSS communes et en assurant la compatibilité avec le thème sombre.
- [ ] Ajouter des filtres dynamiques (evenement passés / future, statut de réservation, type d’activité) pour faciliter la recherche et la gestion des réservations.

## 21. Module photo pour les événements
### Contexte
- Les jeunes participants veulent partager leurs photos prises lors des événements.
- Le module doit s’inscrire dans le flux existant des événements/stages.

### Objectifs
- Autoriser les jeunes ayant participé à un événement à téléverser des photos.
- Associer chaque photo à l’événement concerné et au compte du participant.
- Prévoir la modération côté administrateur avant publication.

### Pistes / Questions
- Où stocker les fichiers (Bibliothèque WP ou dossier dédié) ?
    dans un dissoer dédié 
- Faut-il limiter le nombre de photos par participant ?
    Le nombre de photo est limité par evenement (3 photos par participant)
- Comment notifier l’équipe d’animation lorsqu’une photo est ajoutée ?
    Prévoir un widget de validation pour les animateurs. 

### Tâches proposées
- [x] Définir le modèle de données (table ou métadonnées) pour les galeries d’événements.
- [x] Concevoir l’interface front dans une widget on peut ajouter des images associé a un éveneemnt auquel on a participé
- [x] Ajouter un écran d’administration pour modérer et publier les photos.
        -> widget de validation pour les animateurs
- [x] Sur la page Event je veux voir une gallerie de photos 
- [x] Gérer les miniatures et l’optimisation des images.
- [x] Ajoute les règles RGPD lors de l'upload de photo.

## 22. Widget formulaire de contact
### Objectif
Mettre à disposition des animateurs et coordinateurs un widget Elementor permettant aux visiteurs d'envoyer des messages ciblés (animateur spécifique, coordinateur ou tous les destinataires).

### User stories
- En tant que visiteur, je peux choisir le destinataire du message (animateur assigné, coordinateur, tous) afin que ma demande soit transmise au bon interlocuteur.
- En tant que coordinateur, je consulte l'historique des messages depuis l'admin WordPress dans la section "Maison de jeune" > "Messages".
- En tant que coordinateur, je gère les tickets associés aux messages (statuts à valider, assignation, suivi).

### Fonctionnalités attendues
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

### Points techniques
- [x] Reposer sur la structure CRUD existante dans `includes/classes/crud/` pour la gestion des messages.
- [x] Ajouter les hooks nécessaires dans `core/assets.php` pour charger les scripts du widget.
- [x] Prévoir des capacités WordPress dédiées (ex: `mj_manage_contact_messages`).
- [x] Vérifier la compatibilité avec le mode prévisualisation Elementor (jeu de données factices si aucun message).

### Validation
- [x] Soumission du formulaire avec chaque option de destinataire.
- [x] Vérification de la création et de la mise à jour des tickets dans l'admin.
- [x] Contrôle de l'affichage du widget dans Elementor (mode prévisualisation inclus).

## 23. Refactorisation du module MJ Member
### 23.1 Chargement et architecture des classes
- [x] Déplacer le dossier `core/` dans `includes/core/` et mettre à jour les chargements associés.
- [x] Remplacer la liste de `require` dans `mj-member.php` par un autoloader PSR-4 (Composer ou `spl_autoload_register`) afin de réduire les dépendances globales et préparer une future séparation par domaines.
- [x] Introduire des namespaces pour les classes `includes/classes/` et `core/` pour clarifier les responsabilités et permettre les imports explicites.
- [x] Extraire la définition des constantes (`MJ_MEMBER_*`) dans une classe Config centralisée afin de limiter la pollution de l’espace global et de faciliter les environnements multiples.

### 23.2 Modularisation du back-office
- [x] Convertir les fonctions globales d’administration (`mj_members_page`, `mj_events_page`, etc.) en classes dédiées (ex: `Admin\MembersPage`) avec un point d’entrée unique pour les hooks `add_menu_page` / `add_submenu_page`.
	- [x] Séparer les traitements d’action (`mj_member_handle_members_actions`, `mj_member_handle_locations_actions`) dans des services orientés cas d’usage pour réduire la taille de `mj-member.php` et permettre des tests unitaires ciblés.
- [x] Centraliser la logique de vérification des capacités et des nonces dans un helper unique (`Admin\RequestGuard`) et l’utiliser dans tous les handlers admin.

### 23.3 Rationalisation des classes CRUD
- [x] Uniformiser l’API des classes CRUD (`includes/classes/crud/`) via `CrudRepositoryInterface` afin de couvrir `get_all`, `count`, `create`, `update`, `delete` avec gestion systématique des `WP_Error`.
- [x] Extraire les constructions SQL répétitives dans `CrudQueryBuilder` pour factoriser les filtres et clauses `prepare` utilisés par `MjMembers`, `MjEvents` et `MjEventRegistrations`.
- [x] Renommer `MjMembers_CRUD` et `MjEvents_CRUD` en classes namespacées `Mj\Member\Classes\Crud\MjMembers` et `Mj\Member\Classes\Crud\MjEvents`, puis supprimer les alias `_CRUD` restants.
- [x] Remplacer les tableaux associatifs bruts par des objets valeur (`Value\MemberData`, `Value\EventData`) afin de documenter les attributs attendus et sécuriser les accès.

### 23.4 Cohésion front-office
- [x] Introduire un gestionnaire central des assets (classe `Core\AssetsManager`) exposant `requirePackage()` pour déclarer scripts/styles côté admin et front.
- [x] Documenter et isoler la configuration `data-config` partagée entre `includes/templates/elementor/animateur_account.php` et `js/animateur-account.js` via une fonction PHP unique qui sérialise les données.
- [x] Mutualiser les helpers JS (`escapeHtml`, `flagSummaryAssignments`, `toInt`) dans un bundle (`js/utils.js` + `window.MjMemberUtils`) afin de réduire les duplications entre les scripts historiques.
- [x] Migrer l’ensemble des templates/fronts restants vers `AssetsManager::requirePackage()` en remplacement des enqueues manuels (`wp_enqueue_*`).

### 23.5 Observabilité et qualité
- [x] Mettre en place un canal de logs dédié (wrapper sur `error_log` ou monolog) pour tracer les opérations sensibles (import CSV, paiements Stripe) et faciliter la supervision.
- [x] Ajouter un socle de tests automatisés (PHPUnit) autour des helpers `MjTools` et des classes CRUD, rendu possible grâce à la modularisation proposée ci-dessus.
- [x] Fournir une documentation interne (fichier `docs/architecture.md`) décrivant les nouveaux modules, conventions de nommage et points d’extension.

### 23.6 Migration des usages legacy
- [x] Remplacer les occurrences restantes de `MjMembers_CRUD` / `MjEvents_CRUD` par les nouvelles classes namespacées dans les templates, widgets et handlers AJAX.
- [x] Adapter `MjEventRegistrations`, `MjEventLocations` et les autres dépôts au duo `CrudRepositoryInterface` + DTO pour aligner toute la couche persistance.
- [x] Étendre le bundle `MjMemberUtils` aux modules front manquants (calendar, events widget) et supprimer les helpers dupliqués dans `js/`.

## 24. Rôles des bénévoles
### Contexte
- un jeunes peut être bénévole (ex: aide lors d'un événement) sans pour autant être membre (adhérent payant)
- un animateur peut être bénévole (ex: encadrer un stage) sans pour autant être membre (adhérent payant)

### Actions à envisager
- [x] Retirer le rôle "Bénévole" dans le système de gestion des rôles classique du module MJ Member.
- [x] Permettre d'assigner le rôle bénévole comme une information complémentaire au profil utilisateur (une case à cocher dans l'admin MJ Member).
- [x] Ajouter dans le table Member dans le panel identité, ajoute un Label "Bénévole" (modifiable en ajax)
 
### Rôles bénévoles jeunes
- [ ] Les bénévoles Jeunes ont accès à la page "Gestion des événements" dans l'admin WP
    - [ ] Accès en lecture seule ou avec droits limités (il ne voit que les events assignés à eux, pas de suppression)
- [ ] Les bénévoles Jeunes peuvent gérer les inscriptions aux événements (validation, refus) – (assignés à eux)
- [ ] Les bénévoles Jeunes peuvent publier des photos d'événements (sans modération)
- [x] Un jeune peut être bénévole (ex: aide lors d'un événement) sans pour autant être membre (adhérent payant)

### Rôles bénévoles animateurs
- [ ] Les bénévoles Animateurs ont accès à la page "Gestion des événements" dans l'admin WP
    - [ ] Accès en lecture/écriture aux événements assignés à eux
- [ ] Les bénévoles Animateurs peuvent gérer les inscriptions aux événements (validation, refus) – (assignés à eux)
- [ ] Les bénévoles Animateurs peuvent publier des photos d'événements (sans modération)

## 25. Encodage des heures animateurs
- [x] Concevoir une table personnalisée `mj_member_hours` (CRUD dédié) afin d'enregistrer les plages horaires avec membre, tâche, date, durée et commentaires.
- [x] Restreindre l'accès au module admin aux rôles `animateur`, `coordinateur` et `benevole` via des capacités spécifiques (`mj_member_log_hours`).
- [x] Créer une page d'administration avec formulaire ergonomique (saisie rapide au clavier, navigation par touches, validations instantanées).
- [x] Introduire des heures de début/fin sur chaque tâche, avec champs libres et suggestions de plages horaires récurrentes (ex. 08h30-12h00, 09h00-12h00, 13h00-15h00, 15h00-18h00, 18h00-21h00).
- [x] Afficher les encodages dans un calendrier mensuel dédié, synchronisé avec les filtres du membre courant et navigable par mois.
	- Vue mensuelle responsive avec navigation AJX et cache local par mois.
	- Affichage des créneaux (horaire + tâche) et temps total quotidien, mis à jour après chaque encodage.
- [x] Ajouter un calcul des heures par semaine et par personne, avec agrégation affichée dans le tableau de bord.
- [x] Définir les hooks de recalcul et les tests manuels à effectuer (droits, totaux hebdomadaires, cohérence des horaires projetés, UX de la saisie rapide).
	- Hooks WordPress disponibles : `mj_member_hours_after_create`, `mj_member_hours_after_update`, `mj_member_hours_after_delete`, `mj_member_hours_after_change` (payload unifié avec membre, date, durée, horaire).
	- Tests manuels recommandés : vérifier l'accès par rôle (animateur/coordinateur/benevole), confirmer la mise à jour des totaux hebdomadaires et du calendrier après encodage, contrôler la cohérence horaires/durée (début < fin, durée recalculée), évaluer la saisie rapide (presets, validation instantanée) et la navigation mensuelle.
- [ ] Ajuster l'autocomplétion des tâches et les données sauvegardées pour qu'elles exploitent les projets et les nouvelles plages horaires.
- [ ] Permettre d'encoder un projet associé (ou "Aucun projet") et lier chaque tâche à ce projet, avec suggestions alimentées par l'historique.

## 26. Mise à jour du widget login
### Objectif
Améliorer l'expérience utilisateur du widget de connexion en intégrant une boîte modale pour les liens « Mon compte » et le formulaire de connexion.
    - La boite modal doit descendre avec une animamtion css. Elle est collée au bouton "Mon compte" Ou "Se connecter"

### Points techniques
- Créer une classe métier commune pour récupérer les liens dynamiquement d'un widget a l'autre (class-mj-member-account-links-widget, class-mj-member-login-widget). La configuration de ces liens proviens de la configuration du module. 
- Assurer l'accessibilité (ARIA roles, focus management, navigation clavier) pour la boîte modale et les formulaires.   
- Externaliser le plus possible les css et js 

### Liste des opérations
- [x] Concevoir une boîte modale flottante déclenchée onclick|hover du bouton « Mon compte » du widget `class-mj-member-login-widget`, avec transitions CSS et styles cohérents.
- [x] Optenir les même liens que le widget `class-mj-member-account-link` dans cette boîte et prévoir la gestion responsive (mobile/desktop).
- [x] Intégrer le formulaire de connexion dans la même boîte lorsque l'utilisateur est déconnecté, avec validations et messages d'erreur inline
- [x] Gérer les états ouverts/fermés côté JS (clavier, clic extérieur, focus) tout en respectant les helpers 
- [x] Dans les panel elementor de class-mj-member-account-link permettre de choisir si le widget est visible en version tablette/mobile ou non.
- [x] Étendre les réglages de visibilité tablette/mobile à tous les widgets Elementor `class-mj-member-*`.

## 27. Aligner `MjEvents_List_Table` sur `MjMembers_List_Table`
- [x] Analyser les comportements AJAX et le style appliqués à `MjMembers_List_Table` (fichiers PHP, JS, CSS associés).
- [x] Adapter `MjEvents_List_Table` pour utiliser le même flux AJAX (filtrage, pagination dynamique, recherche, chargement asynchrone).
- [x] Harmoniser le markup HTML et les classes CSS afin de réutiliser les styles existants ou mutualiser les styles spécifiques.
- [x] Implémenter les callbacks AJAX nécessaires (côté PHP) pour les actions propres aux événements, en respectant les capacités et nonces.
- [x] Mettre à jour les scripts JS pour gérer l'initialisation de la table événements avec le même pattern que la table membres.

## 28. Fermetures MJ par plage de dates
- [x] Mettre à jour le modèle de données des fermetures (table ou options) pour stocker une date de début et une date de fin.
- [x] Adapter les formulaires d'encodage/admin pour permettre la saisie d'une plage (sélecteurs de dates, validations cohérentes).
- [x] Ajuster la logique métier (vérification de disponibilité, blocage des réservations/événements) pour parcourir la plage complète.
- [x] Mettre à jour les affichages front/back afin de présenter clairement les fermetures étendues.
- [x] Prévoir les tests manuels : saisie de plages multi-jours, chevauchements, affichage dans les widgets et calculs liés.
    - Ajouter une fermeture sur plusieurs jours et vérifier son affichage complet dans l'admin, le widget calendrier et le flux Google Calendar.
    - Tenter d'enregistrer une fermeture qui chevauche une période existante et confirmer le blocage côté admin.
    - Saisir une fermeture d'un seul jour pour valider le comportement historique (affichage et export).
- [x] Ajouter la possibilité d'ajouter une photo pour chaque fermeture, avec affichage dans le widget calendrier et les notifications.

## 29. Assistant IA pour la description
Objectif : accompagner les animateurs dans la rédaction des descriptions d'entité en proposant un bouton d'assistance IA directement intégré au formulaire d'édition.

### Tâches fonctionnelles
- [ ] Ajouter un widget d'assistance IA dans le formulaire : bouton dans la section description ouvrant un prompt pour générer du contenu via ChatGPT, avec stockage sécurisé des tokens API.
- [ ] Étendre la section configuration du module pour documenter l'obtention des clés ChatGPT et permettre leur saisie + chiffrement côté base de données.

### Chantiers techniques
- [ ] Prévoir un hook PHP côté admin (`mj_member_render_event_form`) qui injecte le bouton et un conteneur `wp-editor-tools` pour conserver l'UX WordPress.
- [ ] Ajouter un module JS dédié (`js/admin/event-ai-helper.js`) chargé via `core/assets.php` uniquement sur l'écran d'édition (`mj_member_admin_enqueue_event_assets`).
- [ ] Implémenter un `wp.prompt` personnalisé (ou `wp.dialog`) qui recueille le contexte, la tonalité, la longueur souhaitée et déclenche un appel AJAX sécurisé.
- [ ] Créer un endpoint AJAX (`mj_member_admin_generate_event_description`) vérifiant la capacité `edit_mj_events`, un nonce dédié et la présence du token ChatGPT.
- [ ] Gérer l'appel à l'API OpenAI via `MjOpenAI_Client` (nouvelle classe dans `includes/classes/`) pour encapsuler la validation des paramètres, la construction des prompts et la journalisation des erreurs.
- [ ] Stocker les tokens via `update_option('mj_member_ai_secret', ...)` après chiffrement (`openssl_encrypt`) et ajouter une rotation possible.
- [ ] Documenter dans la configuration : lien vers https://platform.openai.com/, scope d'utilisation, bonnes pratiques RGPD (pas de données sensibles). Afficher un test de connexion.
- [ ] Prévoir des garde-fous UX : indicateur de chargement, limite de temps, message d'erreur contextualisé, insertion ou remplacement contrôlé dans l'éditeur TinyMCE.
- [ ] Anticiper l'extension du widget vers d'autres zones (ex. formulaires de stages ou communications) via un service réutilisable et un flag de configuration.

## 30. Widget menu mobile
- [x] Créer un widget Elementor dédié au menu smartphone réutilisant le style du modal « Mon compte ».
- [x] Permettre la sélection d'un menu WordPress dans le panneau de configuration du widget.
- [x] Offrir des options de visibilité responsive (mobile/tablette) adaptées au contexte smartphone.
- [x] Harmoniser le rendu front avec les assets existants et prévoir des données factices pour l'aperçu Elementor.
- [x] Ajouter une disposition desktop classique avec la gestion des sous-menus hiérarchiques.
- [x] Afficher simultanément les versions mobile (modal) et desktop sans choix manuel de disposition.
- [x] Permettre de replier/déplier les sous-menus côté mobile et tablette.
- [x] Implémenter les sous menus (foldable) pour modal (mobile et tablette).

## 31. Widget animateurs / coordinateurs

### Objectif
Créer un widget Elementor affichant la liste des animateurs et coordinateurs avec leurs informations clés (cover, surname, email, description courte).

### Hypothèses
- Les animateurs et coordinateurs sont identifiés via un rôle WordPress ou un champ méta spécifique (à confirmer).
- Les images "cover" sont stockées via la médiathèque WordPress et associées aux membres.
- Les données sont déjà disponibles via `mj_member_get_animateur_events_data` ou une fonction similaire ; sinon, étendre le CRUD des membres.

### Tâches
- [x] Définir la source de données (requête CRUD ou nouvelle fonction) pour récupérer animateurs/coordinateurs et garantir la présence des champs nécessaires.
- [x] Vérifier/ajouter les métadonnées requises côté admin (upload cover, surnom, description courte) et mettre à jour la validation.
- [x] Implémenter le widget Elementor avec contrôles (filtre par rôle, tri, nombre d'items, ordre) et affichage cover/surnom/email/description.
- [x] Ajouter le template PHP dédié (dans `includes/templates/elementor/`) ainsi que les styles nécessaires.
- [x] Prévoir un mode "aperçu Elementor" avec données factices.
- [x] Rédiger la documentation d'utilisation dans la section support.

### Critères d'acceptation
- Widget disponible dans Elementor avec un aperçu fonctionnel.
- Chaque carte affiche cover, surname, email cliquable et description courte.
- Responsive mobile/desktop conforme aux styles MJ.
- Données sécurisées (échappement, vérifications de capacités).
- Documentation mise à jour.

## 32. Widget prochains événements

### Objectif
Concevoir un widget Elementor affichant la liste des prochains événements (cover, titre, date, lieu, type, prix le cas échéant) avec plusieurs propositions de mise en page (liste, grille, slider) et des options de personnalisation.

### Hypothèses
- Les événements sont accessibles via les classes CRUD existantes (`MjEvents_CRUD`, `MjEventRegistrations`), incluant les métadonnées nécessaires (date de début/fin, lieu, type).
- Le widget doit respecter le mode aperçu Elementor avec un jeu de données factices.
- Les styles globaux `css/styles.css` peuvent être réutilisés ; de nouveaux styles peuvent être ajoutés si besoin.

### Tâches
1. Vérifier la fonction de récupération des événements futurs et ajouter un filtre dédié si nécessaire.
2. Ajouter les contrôles Elementor (nombre d'événements, tri, catégorie/type, affichage du bouton « voir plus »).
3. Créer le template PHP dans `includes/templates/elementor/` et implémenter le markup BEM avec échappement des données.
4. Mettre à jour les scripts/styles front si une interaction (slider, onglets) est requise.
5. Ajouter un mode démo pour l'aperçu Elementor.
6. Documenter l'utilisation du widget dans la section support.

### Critères d'acceptation
- Widget disponible dans Elementor avec options de filtrage fonctionnelles.
- Chaque carte événement affiche titre, date formatée, lieu et type.
- Respect des standards MJ (BEM, échappement, sécurité AJAX si nécessaire).
- Documentation et capture d'écran mises à jour.
