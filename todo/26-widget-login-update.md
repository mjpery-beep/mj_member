# 26. Mise à jour widget login
## Objectif : Améliorer l'expérience utilisateur du widget de connexion en intégrant une boîte modale pour les liens « Mon compte » et le formulaire de connexion.
    - La boite modal doit descendre avec une animamtion css. Elle est collée au bouton "Mon compte" Ou "Se connecter"

## Points techniques
- Créer une classe métier commune pour récupérer les liens dynamiquement d'un widget a l'autre (class-mj-member-account-links-widget, class-mj-member-login-widget). La configuration de ces liens proviens de la configuration du module. 
- Assurer l'accessibilité (ARIA roles, focus management, navigation clavier) pour la boîte modale et les formulaires.   
- Externaliser le plus possible les css et js 

## Liste des opérations
- [x] Concevoir une boîte modale flottante déclenchée onclick|hover du bouton « Mon compte » du widget `class-mj-member-login-widget`, avec transitions CSS et styles cohérents.
- [x] Optenir les même liens que le widget `class-mj-member-account-link` dans cette boîte et prévoir la gestion responsive (mobile/desktop).
- [x] Intégrer le formulaire de connexion dans la même boîte lorsque l'utilisateur est déconnecté, avec validations et messages d'erreur inline
- [x] Gérer les états ouverts/fermés côté JS (clavier, clic extérieur, focus) tout en respectant les helpers 
- [x] Dans les panel elementor de class-mj-member-account-link permettre de choisir si le widget est visible en version tablette/mobile ou non.
- [x] Étendre les réglages de visibilité tablette/mobile à tous les widgets Elementor `class-mj-member-*`.