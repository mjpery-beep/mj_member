# 29. Assistant IA pour la description

Objectif : accompagner les animateurs dans la rédaction des descriptions d'entité en proposant un bouton d'assistance IA directement intégré au formulaire d'édition.

## Tâches fonctionnelles

- [ ] Ajouter un widget d'assistance IA dans le formulaire : bouton dans la section description ouvrant un prompt pour générer du contenu via ChatGPT, avec stockage sécurisé des tokens API.
- [ ] Étendre la section configuration du module pour documenter l'obtention des clés ChatGPT et permettre leur saisie + chiffrement côté base de données.

## Chantiers techniques

- [ ] Prévoir un hook PHP côté admin (`mj_member_render_event_form`) qui injecte le bouton et un conteneur `wp-editor-tools` pour conserver l'UX WordPress.
- [ ] Ajouter un module JS dédié (`js/admin/event-ai-helper.js`) chargé via `core/assets.php` uniquement sur l'écran d'édition d  (`mj_member_admin_enqueue_event_assets`).
- [ ] Implémenter un `wp.prompt` personnalisé (ou `wp.dialog`) qui recueille le contexte, la tonalité, la longueur souhaitée et déclenche un appel AJAX sécurisé.
- [ ] Créer un endpoint AJAX (`mj_member_admin_generate_event_description`) vérifiant la capacité `edit_mj_events`, un nonce dédié et la présence du token ChatGPT.
- [ ] Gérer l'appel à l'API OpenAI via `MjOpenAI_Client` (nouvelle classe dans `includes/classes/`) pour encapsuler la validation des paramètres, la construction des prompts et la journalisation des erreurs.
- [ ] Stocker les tokens via `update_option('mj_member_ai_secret', ...)` après chiffrement (`openssl_encrypt`) et ajouter une rotation possible.
- [ ] Documenter dans la configuration : lien vers https://platform.openai.com/, scope d'utilisation, bonnes pratiques RGPD (pas de données sensibles). Afficher un test de connexion.
- [ ] Prévoir des garde-fous UX : indicateur de chargement, limite de temps, message d'erreur contextualisé, insertion ou remplacement contrôlé dans l'éditeur TinyMCE.
- [ ] Anticiper l'extension du widget vers d'autres zones (ex. formulaires de stages ou communications) via un service réutilisable et un flag de configuration.
