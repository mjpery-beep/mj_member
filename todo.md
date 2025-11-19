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
    - [ ] Quand je suis tuteur je dois payer pour mes enfants (afficher la cotisation des enfants dans le template/composant Elementor du statut cotisation)
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
- [ ] Ajouter un lien vers un membre (Animateur en charge de l'event) Lors d'une inscription prévenir le membre qui est en charge de l'event
- [ ] Corrigé le bug d'échappement dans la description détaillée de l'évenement. (exemple : C\'est cool)
- [ ] Associer un lieux a l'évènement 
- [ ] Faire un gestionnaire de lieux pour éviter de les ré-encodé.
        - géolocalisation, utilise google map qui affiche le lieux en fonction de l'adresse
        - cover image du lieu (logo)
        - Préencode les lieux de base : La bibi, La Mj Péry, La citadelle, ...


## 5. Composant Elementor "Liste des stages"
- [ ] Ajouter un widget Elementor pour afficher les evenements actifs.
    - [ ] Parametres: type(s) d'evenement, nombre max, tri (date debut DESC), affichage grille ou liste.
    - [ ] Utiliser la cover comme visuel, fallback si absent.
- [ ] Bouton "S'inscrire" conditionnel.
    - [ ] Cache automatiquement si la date de fin d'inscription est depassee.
    - [ ] Si utilisateur connecte: ouvrir un panneau permettant de choisir un jeune associe ou soi-meme.
    - [ ] Si non connecte: afficher le composant modal de connexion.
- [ ] Assurer la compatibilite responsive et l'utilisation des couleurs globales Elementor.

## 6. Tableau de bord administrateur
- [ ] Creer une page "Tableau de bord" dans le menu MJ Member.
    - [ ] Bloc "Presentation de la MJ" editable via `wp_options`.
    - [ ] Indicateurs clefs: membres actifs, paiements recents (30 jours), animateurs actifs.
    - [ ] Graphique (barres ou ligne) sur les inscriptions/paiements mensuels.
- [ ] Ajouter un widget WordPress Dashboard affichant un resume des stats.

## 8. Import CSV des membres
- [ ] Ajouter une page d'outil "Import CSV".
    - [ ] Upload securise avec nonce et verification de type MIME.
    - [ ] Mapping des colonnes CSV -> champs (email, prenom, nom, role, date_naissance, adresse, etc.).
    - [ ] Mode simulation (dry-run) pour afficher un rapport avant import definitif.
    - [ ] Rapport final: lignes importees, ignorees, erreurs detaillees.
- [ ] Option pour creer/associer des comptes WordPress lors de l'import (choix login auto ou fourni).
- [ ] Documentation utilisateur sur le format attendu.

## 9. Dans la page admin des membres
- [ ] Ajouter un bouton "Recu 2 EURO" qui va automatiquement mettre à jour la date de paiement. 
        - [ ] Il faut concerver l'utilisateur (Nom de Animateur) qui a cliquer sur se bouton et voir l'information apparaitre dans l'historique des paiements 

## 10. Qualite et tests
- [ ] Ecrire des jeux de donnees de test pour les evenements et l'import CSV.
- [ ] Tester les upgrades schema sur un clone de site (verifier absence de colonnes manquantes).
- [ ] Rediger une checklist QA couvrant: paiement Stripe, connexion modal, inscription a un stage, import CSV.

## 11. Notifications et emails
- [ ] Logger les envois d'emails dans une table dédiée afin de faciliter le support.


## 13. Securite et conformite
- [ ] Vérifier les capacités et nonces sur toutes les nouvelles pages admin (import, tableau de bord, etc.).
- [ ] Documenter et appliquer une politique de conservation des données membres (RGPD).
- [ ] Mettre en place des tests unitaires pour les hooks critiques (connexion, paiement, inscription).

## 14. Documentation et support
- [ ] Compléter `README.md` avec les captures d'écran clés et la procédure d'installation Stripe.
- [ ] Rédiger une doc utilisateur pour l'équipe MJ (PDF ou Notion) couvrant les scénarios principaux.
- [ ] Ajouter un guide contributeur expliquant conventions de code, tests et nomenclature des fichiers.
- [ ] Préparer une FAQ interne avec les problèmes fréquents et les procédures de résolution.