# MJ Member Roadmap

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


## 2. Page "Mon Compte" Elementor
- [x] Créer template/composant Elementor "Mes informations" utilisable sur la page mon compte
    - [x] Permettre au membre de modifier ses informations personnelles et sa photo.
    - [x] Créer template/composant Elementor  qui Afficher le statut de la cotisation (dernier paiement, montant, rappel si dû). Si la cotisation a expirée on peut créer le liens vers le stripe. 
    - [x] Quand je suis tuteur je dois payer pour mes enfants (afficher la cotisation des enfants dans le template/composant Elementor du statut cotisation)
    - [x] Créer template/composant Elementor pour Lister les événements / stages auxquels le membre est inscrit.

## 3. Paiements Stripe
- [x] Rediriger l'utilisateur vers `https://www.mj-pery.be/inscit` apres un paiement Stripe reussi.
    - [x] Ajouter option dans les settings pour personnaliser l'URL de redirection (après paiement).
    - [x] Tester le flux en mode test et production.
- [x] Harmoniser les URLs de retour en cas d'annulation (page informative dediee).
- [x] Journaliser les evenements Stripe (success, cancel, webhook) pour faciliter le support.

## 4. Module Evenements / Stages
- [x] Creer une table `wp_mj_events` via une migration schema.
    - Champs: titre, statut (actif, brouillon, passe), type (stage, soiree, sortie), cover (attachment id), description, age_min (defaut 12), age_max (defaut 26), date_debut, date_fin, date_fin_inscription (defaut 14 jours avant), prix, created_at, updated_at.
    - [x] Ajouter des index sur `statut` et `date_debut`.
- [x] Implementer `MjEvents_List_Table` pour l'administration.
    - [x] Filtres par statut et type, recherche rapide.
    - [x] Actions groupees (activer, archiver, dupliquer).
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


## 5. Composants Elementor Evenements
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

## 6. Tableau de bord administrateur
- [x] Creer une page "Tableau de bord" dans le menu MJ Member.
    - [x] Bloc "Presentation de la MJ" editable via `wp_options`.
    - [x] Indicateurs clefs: membres actifs, paiements recents (30 jours), animateurs actifs.
    - [x] Graphique (barres ou ligne) sur les inscriptions/paiements mensuels.
- [x] Ajouter un widget WordPress Dashboard affichant un resume des stats.

## 7. Envoie de sms
- [x] Le membre peux décider si il veux recevoir des sms ainsi que des newsletters. 
- [x] Le module d'envoie d'email permet aussi d'envoyé des sms. 
- [x] Les templates mails comporte aussi un champs avec le sms (texte plus concis)

## 8. Import CSV membres
- [x] Créer une interface d'import CSV dans l'admin MJ Member.
    - [x] Permettre le mapping des colonnes CSV aux champs membres.
    - [x] Gérer les doublons basés sur l'email (mettre à jour ou ignorer).
    - [x] Afficher un rapport détaillé après import (nombre importés, mis à jour, erreurs).

## 9. Widget Elementor Evenements - Ameliorations
- [x] Le widget Carte des lieux (class-mj-member-locations-widget) doit permettre de filtrer par type de lieu le widget evenement liste (class-mj-member-events-widget).
- [ ] Le widget agenda (class-mj-member-events-calendar-widget) doit permettre de filtrer le widget evenement liste (class-mj-member-events-widget).

## 10. Composant Elementor pour les animateurs
- [ ] Créer un composant Elementor "Liste des membres" pour "Mon compte". Qui permet a l'animateur de voir qui participe a ces évenements et de relever leur présence (une boite permet de communiquer par sms a l'ensemble des participants). 
    - [ ] Permettre de marquer les membres qui sont effectivement présents lors de l'évenement.
    - [ ] Permettre de voir les membres par évenement et de les contacter (sms).
            - Message type : rappels
    - [ ] Permettre de filtrer par événement, par date.

## 11. Dans l'édition d'un evénement dans l'admin
- [ ] permettre de choisir soit :
        - une date fixe (avec plage horaire)
        - plage de date (ex: du 10 au 15 aout)
        - soit une récurrence (tout les vendredi, chaque 1er samedi du mois, mardi une semaine sur deux)
- [ ] créer une base de donnée avec les jours de fermeture de la maison de jeunes pour bloquer les évenements recurrents.
- [ ] Ajouter atelier dans le type d'événement possible.
- [ ] Ajouter un système de gestion des places limitées par évenement avec notification quand le seuil est atteint.
- [ ] Le paiement pour un évenement (si prix >0) doit se faire via stripe (comme pour la cotisation)
- [ ] Ajoute un système de liste d'attente si l'évenement est complet.


## 12.Dans le module de membres
### 12.1 Formulaire d'édition d'un membre dans l'admin

### 12.2 Tableau des membres dans l'admin
    - [x] Met l'info (cotisation, Dispense de cotisation), consentement image, date de creation, date de mise a jour dans la meme cellule.
    - [x] Met un titre sur les cellules du détail 
    - [x] Dans la colonne accès et actions, le bouton Détail doit changer de nom en "WP user".
    - [ ] dans la colonne Détail on ne sais pas editer la date d'anniversaire (inline sur l'age).
    
    
Un même tuteur peut être lié à plusieurs jeunes (frères et sœurs, par exemple).

## 13. Notifications et emails
- [x] Logger les envois d'emails dans une table dédiée afin de faciliter le support.
- [ ] Prévoir un panel dans l'admin pour voir les log d'email

## 14. Prévoir la génération d'un PDF avec des carte de visite des membres
- [ ] Créer une fonctionnalité pour générer un PDF contenant des cartes de visite pour les membres.
    - [ ] Chaque carte doit inclure: nom, prénom, rôle (membre/tuteur/animateur), date d'adhésion
    - [ ] Permettre la sélection de membres spécifiques ou de groupes (ex: tous les animateurs) pour la génération du PDF.
    - [ ] Format des cartes: standard (85x55mm) avec une mise en page professionnelle.
    - [ ] Ajouter des options de personnalisation (couleurs, logo MJ, police).
    - [ ] Intégrer un bouton dans l'admin MJ Member pour lancer la génération et le téléchargement du PDF.


## 15. Qualite et tests
- [ ] Rediger une checklist QA couvrant: paiement Stripe, connexion modal, inscription a un stage, import CSV.

## 16. Securite et conformite
- [ ] Vérifier les capacités et nonces sur toutes les nouvelles pages admin (import, tableau de bord, etc.).
- [ ] Documenter et appliquer une politique de conservation des données membres (RGPD).
- [ ] Mettre en place des tests unitaires pour les hooks critiques (connexion, paiement, inscription).


## 17. Documentation et support
- [ ] Compléter `README.md` avec les captures d'écran clés et la procédure d'installation Stripe.
- [ ] Rédiger une doc utilisateur pour l'équipe MJ (PDF ou Notion) couvrant les scénarios principaux.
- [ ] Ajouter un guide contributeur expliquant conventions de code, tests et nomenclature des fichiers.
- [ ] Préparer une FAQ interne avec les problèmes fréquents et les procédures de résolution.