# Tests manuels — Liste des événements (AJAX)

## Pré-requis
- Site de recette ou environnement local contenant le plugin `mj-member` activé et à jour.
- Compte administrateur WordPress avec l'accès au menu `MJ Events`.
- Navigateur Chrome/Firefox avec les DevTools accessibles (onglets Réseau et Performance).
- Jeu d'événements couvrant plusieurs statuts (`actif`, `brouillon`, `passé`) et types (`stage`, `soirée`, `sortie`) avec des lieux variés.
- Un événement disposant d'inscriptions complètes afin de valider les statistiques.

## Données de test suggérées
- Créer au moins 35 événements pour forcer la pagination (≥ 2 pages à 20 éléments / page).
- Prévoir au moins un événement par statut et par type pour vérifier les filtres croisés.
- Associer des images de couverture et des articles liés pour valider l'affichage des vignettes.
- Pré-enregistrer quelques participants pour tester les compteurs d'inscriptions et les actions liées.

## Scénarios principaux

### Chargement initial & fallback
1. Charger `wp-admin/admin.php?page=mj_events` avec JavaScript activé : vérifier l'affichage immédiat de la première page, l'indicateur de chargement AJAX et l'absence d'erreurs console.
2. Désactiver JavaScript via DevTools (ou utiliser une extension bloqueur de scripts) puis recharger la page : confirmer que la table se charge en mode dégradé (soumission complète du formulaire de filtrage, pagination classique) et que les actions principales restent accessibles.

### Navigation & pagination
1. Avec JavaScript activé, utiliser la pagination pour passer de la page 1 à la page 2.
2. Observer dans l'onglet Réseau l'appel AJAX `mj_member_fetch_events` : vérifier le statut HTTP 200, le temps de réponse (< 400 ms en local) et la présence des paramètres `paged`, `orderby`, `order`.
3. Confirmer que le compteur total et les flèches de pagination reflètent correctement la nouvelle page sans rechargement complet.
4. Tester la navigation inverse (page précédente) et l'accessibilité clavier des boutons.

### Filtres & recherche
1. Appliquer successivement chaque filtre (`Statut`, `Type`) et une recherche textuelle ; valider que l'URL et les champs du formulaire sont mis à jour après l'appel AJAX.
2. Combiner un filtre de statut et un terme de recherche : vérifier que seuls les événements correspondants s'affichent et que la pagination se réinitialise à la page 1.
3. Effacer les filtres via le bouton de réinitialisation (ou en vidant les champs) et vérifier que la liste complète revient.

### Tri des colonnes
1. Cliquer sur les entêtes triables (`Titre`, `Début`, `Fin`, `Lieu`, `Modifié`).
2. Contrôler que la direction de tri alterne (`ASC`/`DESC`) et que l'appel AJAX inclut `orderby` et `order` cohérents.
3. Confirmer que la mise en évidence visuelle de la colonne triée correspond à l'ordre appliqué.

### Actions rapides & lot
1. Sélectionner plusieurs événements puis appliquer chaque action en masse (`Marquer comme actif`, `Archiver`, `Dupliquer`, `Supprimer`).
2. Vérifier la présence du nonce `mj_member_events_bulk` dans la requête POST et la mise à jour du tableau sans rechargement complet.
3. Pour les actions destructives, confirmer l'affichage des confirmations attendues et la remontée d'éventuels messages d'erreur (`lastDeleteErrors`).
4. Tester les actions de ligne (`Éditer`, `Dupliquer`) : le clic doit soit ouvrir la page d'édition, soit déclencher l'action AJAX correspondante avec message de succès.

### Édition inline du statut
1. Cliquer sur le badge de statut (`Actif`, `Brouillon`, `Passé`).
2. Modifier la valeur depuis la fenêtre modale ou le sélecteur inline.
3. Vérifier que la requête AJAX utilise `mj_member_update_event_status` et qu'en cas d'échec un message est affiché.
4. Rafraîchir la page pour s'assurer que la modification persiste.

### Statistiques d'inscriptions
1. Ouvrir la ligne d'un événement avec des inscriptions : contrôler que la colonne `Inscriptions` affiche les totaux attendus.
2. Si un événement est complet, vérifier que le badge visuel change (ex. couleur ou label) et qu'aucune erreur PHP n'est logguée.

### Performances & robustesse
1. Surveiller l'onglet Performance du navigateur pendant une session de filtrage intensif (≥ 10 actions successives) : vérifier l'absence de fuites mémoire et une consommation CPU stable.
2. Observer la taille des réponses JSON : elles doivent rester sous 100 Ko ; sinon noter des pistes d'optimisation (réduction des champs inutiles).
3. Simuler un échec réseau (profil Réseau "Offline"): vérifier le message d'erreur utilisateur et la possibilité de relancer la requête.

### Accessibilité & clavier
1. Naviguer dans la table uniquement au clavier (Tab/Shift+Tab) : vérifier la focalisation sur les contrôles de pagination, filtres et boutons d'actions.
2. Vérifier que les annonces ARIA (si présentes) décrivent correctement les changements de page ou de filtre.

## Critères de validation
- Aucun message d'erreur PHP ou JavaScript dans les logs pendant toute la session de test.
- Les requêtes AJAX retournent un code HTTP 200 et respectent les nonces/capacités WordPress.
- Les actions critiques restent utilisables sans JavaScript (rechargement complet fonctionnel).
- Les filtres, tri, pagination et actions rapides se comportent comme sur `MjMembers_List_Table`, garantissant une expérience homogène.
- Les performances restituées sont adaptées à l'usage (latence faible, aucune régression notable).
