# 36. Widget Profil Gamification

## Objectif
Créer un widget Elementor affichant le profil gamification d'un membre : niveau actuel, barre de progression XP, coins accumulés, badges obtenus et trophées débloqués.

## Contexte
Le système de gamification (XP, coins, badges, trophées, niveaux) est en place côté CRUD mais il n'y a pas encore de widget front dédié pour que le membre visualise sa progression de manière engageante.

## Fonctionnalités
- [ ] Carte de niveau avec barre de progression XP (niveau actuel → prochain niveau)
- [ ] Compteur de coins avec historique récent (gains/dépenses)
- [ ] Grille de badges avec badges verrouillés grisés et tooltip des critères restants
- [ ] Vitrine de trophées avec animation de déverrouillage
- [ ] Classement anonymisé (position du membre parmi les autres, sans révéler les noms)
- [ ] Mode compact (pour sidebar) et mode pleine page
- [ ] Données mock pour `is_elementor_preview()`
- [ ] Animations Preact légères (confetti au nouveau badge, shake au level-up)

## Tables concernées
`MjMemberXp`, `MjMemberCoins`, `MjLevels`, `MjBadges`, `MjMemberBadges`, `MjBadgeCriteria`, `MjMemberBadgeCriteria`, `MjTrophies`, `MjMemberTrophies`

## Tâches techniques
- [ ] Endpoint AJAX front pour récupérer le profil gamification complet d'un membre
- [ ] Composant Preact `GamificationProfile` avec sous-composants (LevelCard, BadgeGrid, TrophyCase, CoinCounter)
- [ ] Template Elementor `includes/templates/elementor/gamification_profile.php`
- [ ] Classe widget `class-mj-member-gamification-profile-widget.php`
- [ ] CSS BEM `css/gamification-profile.css`
- [ ] Enregistrement dans `AssetsManager::registerFrontAssets()`
