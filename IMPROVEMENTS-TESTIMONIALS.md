# Améliorations de l'onglet Témoignages

## Résumé des modifications

Les améliorations suivantes ont été apportées au widget gestionnaire d'inscriptions, section Témoignages de la fiche d'un membre:

### 1. ✅ Édition du contenu du témoignage
**Endpoint AJAX:** `mj_regmgr_edit_testimonial_content`
**Paramètres:**
- `testimonialId` (int): ID du témoignage
- `content` (string): Nouveau contenu (min 10 caractères)

**Réponse:** Message de succès avec le contenu mis à jour

### 2. ✅ Récupération des commentaires
Les commentaires sont maintenant chargés avec les détails du membre:
- ID du commentaire
- ID et nom du membre auteur
- Contenu du commentaire
- Date de création

**Fichier source:** `includes/core/ajax/admin/registration-manager.php`, handlers liés aux témoignages (chargement des commentaires)

### 3. ✅ Gestion des commentaires

#### Ajout de commentaire
**Endpoint AJAX:** `mj_regmgr_add_testimonial_comment`
**Paramètres:**
- `testimonialId` (int): ID du témoignage
- `content` (string): Contenu du commentaire (min 2 caractères)

**Réponse:** Objet commentaire avec détails

#### Édition de commentaire
**Endpoint AJAX:** `mj_regmgr_edit_testimonial_comment`
**Paramètres:**
- `commentId` (int): ID du commentaire
- `content` (string): Nouveau contenu

**Permissions:** Auteur du commentaire ou coordinateur

#### Suppression de commentaire
**Endpoint AJAX:** `mj_regmgr_delete_testimonial_comment`
**Paramètres:**
- `commentId` (int): ID du commentaire

**Permissions:** Auteur du commentaire ou coordinateur

### 4. ✅ Récupération des réactions
Les réactions sont maintenant chargées avec:
- Type de réaction (like, love, haha, wow, sad, angry)
- Emoji associé
- Label localisé
- Nombre de réactions

**Types de réactions supportés:**
- 👍 like (J'aime)
- ❤️ love (J'adore)
- 😂 haha (Haha)
- 😮 wow (Wouah)
- 😢 sad (Triste)
- 😠 angry (Grrr)

**Fichier source:** `includes/core/ajax/admin/registration-manager.php` ligne 5569-5582

#### Ajouter une réaction
**Endpoint AJAX:** `mj_regmgr_add_testimonial_reaction`
**Paramètres:**
- `testimonialId` (int): ID du témoignage
- `reactionType` (string): Type de réaction validé

**Comportement:** Toggle - la même réaction ajoute ou supprime

#### Supprimer une réaction
**Endpoint AJAX:** `mj_regmgr_remove_testimonial_reaction`
**Paramètres:**
- `testimonialId` (int): ID du témoignage
- `reactionType` (string): Type de réaction

### 5. ✅ Gestion des liens sociaux

#### Ajouter/Mettre à jour un lien
**Endpoint AJAX:** `mj_regmgr_update_social_link`
**Paramètres:**
- `testimonialId` (int): ID du témoignage
- `action` (string): 'add' ou autre (supprime le lien)
- `url` (string): URL du lien social
- `title` (string): Titre du lien (optionnel)
- `preview` (string): Aperçu du lien (optionnel)

**Exemple d'utilisation:**
```javascript
// Ajouter un lien
{
  testimonialId: 123,
  action: 'add',
  url: 'https://facebook.com/...',
  title: 'Partagé sur Facebook',
  preview: 'Contenu du lien...'
}

// Supprimer un lien
{
  testimonialId: 123,
  action: 'remove'
}
```

### 6. ✅ Modification du flag "Mettre en vedette"

**Endpoint existant:** `mj_regmgr_toggle_testimonial_featured`
Cet endpoint était déjà implémenté et fonctionne correctement.

---

## Structure des données

Les données des témoignages chargées pour chaque membre incluent maintenant:

```javascript
{
  id: 1,
  content: "Contenu du témoignage...",
  status: "approved|pending|rejected",
  featured: true|false,
  rejection_reason: "Optionnel si rejected",
  photos: [
    { thumb: "URL", url: "URL_full" }
  ],
  video: {
    url: "URL_video",
    poster: "URL_poster"
  },
  linkPreview: {
    url: "URL_social",
    title: "Titre",
    preview: "Aperçu"
  },
  comments: [
    {
      id: 1,
      memberId: 5,
      memberName: "Jean Dupont",
      content: "Excellent témoignage!",
      createdAt: "2024-01-15 10:30:00"
    }
  ],
  reactions: [
    {
      type: "like",
      emoji: "👍",
      label: "J'aime",
      count: 3
    }
  ],
  created_at: "2024-01-15 10:00:00"
}
```

---

## Prérequis pour l'affichage frontend

Pour afficher complètement ces fonctionnalités dans l'UI, il faut améliorer le composant Preact dans `js/registration-manager/members.js`:

1. Ajouter des états React pour l'édition du contenu
2. Créer un composant pour afficher/éditer les commentaires
3. Créer un composant pour afficher/ajouter les réactions
4. Améliorer l'affichage du lien avec aperçu
5. Connecter les services JS aux nouveaux endpoints AJAX

### Services JS à ajouter dans `js/registration-manager/services.js`:

```javascript
editTestimonialContent: function(testimonialId, content) {
  return post('mj_regmgr_edit_testimonial_content', {
    testimonialId: testimonialId,
    content: content,
  });
},

addTestimonialComment: function(testimonialId, content) {
  return post('mj_regmgr_add_testimonial_comment', {
    testimonialId: testimonialId,
    content: content,
  });
},

editTestimonialComment: function(commentId, content) {
  return post('mj_regmgr_edit_testimonial_comment', {
    commentId: commentId,
    content: content,
  });
},

deleteTestimonialComment: function(commentId) {
  return post('mj_regmgr_delete_testimonial_comment', {
    commentId: commentId,
  });
},

addTestimonialReaction: function(testimonialId, reactionType) {
  return post('mj_regmgr_add_testimonial_reaction', {
    testimonialId: testimonialId,
    reactionType: reactionType,
  });
},

removeTestimonialReaction: function(testimonialId, reactionType) {
  return post('mj_regmgr_remove_testimonial_reaction', {
    testimonialId: testimonialId,
    reactionType: reactionType,
  });
},

updateSocialLink: function(testimonialId, action, url, title, preview) {
  return post('mj_regmgr_update_social_link', {
    testimonialId: testimonialId,
    action: action,
    url: url,
    title: title,
    preview: preview,
  });
}
```

---

## Fichiers modifiés

1. **includes/core/ajax/admin/registration-manager.php**
   - Ajout des imports pour MjTestimonialComments et MjTestimonialReactions
   - Enrichissement des données testimonials dans `mj_regmgr_get_member_details()`
   - Ajout de 7 nouveaux endpoints AJAX

2. **Pas de modifications attendues dans:**
   - js/registration-manager/members.js (compatible, données prêtes à être affichées)
   - includes/templates/elementor/registration_manager.php (données prêtes)

---

## Permissions

Toutes les actions respectent les permissions:
- **Coordinateur**: Peut éditer le contenu, gérer les liens, modérer les commentaires
- **Auteur du commentaire**: Peut éditer/supprimer son propre commentaire
- **Tout membre connecté**: Peut ajouter des commentaires et réactions

---

## Notes d'implémentation

1. Les commentaires et réactions sont stockés dans les tables:
   - `wp_mj_testimonial_comments`
   - `wp_mj_testimonial_reactions`

2. Le système des réactions utilise des types prédéfinis (like, love, haha, etc.) plutôt que des emojis libres

3. La gestion des liens sociaux stocke:
   - URL du lien
   - Titre (pour affichage)
   - Aperçu (description/preview)

4. Toutes les requêtes incluent une vérification de nonce pour la sécurité
