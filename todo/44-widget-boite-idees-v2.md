# 44. Widget boîte à idées améliorée (v2)

## Objectif
Faire évoluer la boîte à idées existante vers une plateforme collaborative avec votes pondérés, statuts de réalisation, commentaires et intégration gamification.

## Contexte
Le module `idea_box.php` avec `MjIdeas` et `MjIdeaVotes` existe. Le widget actuel permet la soumission et le vote. Cette v2 enrichit l'expérience.

## Fonctionnalités
- [ ] Statuts d'une idée : Soumise → En discussion → Approuvée → En cours → Réalisée → Rejetée
- [ ] Timeline de progression visible par les membres (style kanban ou stepper)
- [ ] Commentaires sur les idées (fil de discussion)
- [ ] Catégories / tags pour trier les idées (activités, locaux, matériel, ambiance…)
- [ ] Recherche et filtres (par statut, catégorie, popularité, date)
- [ ] Notification aux auteurs quand le statut de leur idée change
- [ ] Récompenses gamification :
  - XP pour soumettre une idée
  - Coins bonus si l'idée est approuvée
  - Badge "Inventeur" après 5 idées approuvées
- [ ] Modération admin : changer le statut, masquer une idée, laisser une réponse officielle
- [ ] Données mock pour le preview Elementor

## Tables concernées
`MjIdeas` (ajouter champs status, category), `MjIdeaVotes`, `MjIdeaComments` (nouvelle table)

## Tâches techniques
- [ ] Migration : champs status + category sur `MjIdeas`, nouvelle table `MjIdeaComments`
- [ ] CRUD `MjIdeaComments`
- [ ] Refonte du composant Preact (IdeaList, IdeaCard, IdeaDetail, CommentThread)
- [ ] Endpoints AJAX front pour commentaires et changement de statut (admin)
- [ ] Listeners de notifications pour changement de statut
- [ ] Intégration gamification (actions, critères badges)
- [ ] CSS BEM mis à jour
