# 4. Module Evenements / Stages

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
