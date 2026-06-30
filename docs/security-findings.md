# Audit des failles de sécurité MJ Member

_Date : 2026-02-07_

Ce document recense les failles de sécurité **connues** et les points de vigilance identifiés lors d'un passage rapide dans le code. Il ne remplace pas un audit complet, mais fournit un état des lieux clair pour répondre aux demandes de revue sécurité.

## Failles confirmées

Aucune faille de sécurité confirmée n'a été détectée dans le code applicatif lors de cette revue.

## Points de vigilance

1. **CSRF sur l’édition des icônes de menu**  
   Le hook `mj_member_account_menu_icon_save` lit `$_POST['mj_member_menu_icon_id']` sans vérifier de nonce dédié (`includes/account_menu_icons.php`). L’écran "Menus" de WordPress applique un nonce global, mais si `wp_update_nav_menu_item` est déclenché hors de l’UI d’administration, il faudra ajouter un `check_admin_referer()` pour éviter un scénario CSRF.

2. **Fragments HTML rendus en brut**  
   Certains composants affichent des fragments HTML générés côté serveur via `echo` ou `|raw` (ex. `schedule_component` dans `includes/templates/elementor/event_schedule.php` et `includes/templates/front/event-page/context.php`). Ces fragments doivent rester construits exclusivement à partir de valeurs échappées/sanitized dans `ScheduleDisplayHelper::render()` pour éviter un risque de XSS si des données utilisateur y sont injectées.

## Audit dépendances (composer)

Les dépendances directes ont été contrôlées via la base d’avis GitHub Security Advisory. Aucune vulnérabilité connue n’a été trouvée pour les versions suivantes :

| Dépendance | Version | Résultat |
| --- | --- | --- |
| psr/container | 2.0.2 | Aucun avis de sécurité connu |
| symfony/form | 6.4.30 | Aucun avis de sécurité connu |
| symfony/validator | 6.4.30 | Aucun avis de sécurité connu |
| symfony/http-foundation | 6.4.30 | Aucun avis de sécurité connu |
| symfony/options-resolver | 6.4.30 | Aucun avis de sécurité connu |
| twig/twig | 3.22.2 | Aucun avis de sécurité connu |
