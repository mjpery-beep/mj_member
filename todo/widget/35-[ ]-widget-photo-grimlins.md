# Widget Photo Grimlins

## Objectif
Créer un widget Elementor permettant à un membre duploader une photo, denvoyer limage à lAPI OpenAI (ChatGPT) pour générer une version "grimlins" stylisée, puis dafficher le rendu à lécran.

## Portée
- Front-office via un widget Elementor dédié.
- Upload contrôlé (taille, format) et stockage temporaire local.
- Appel API OpenAI (Images) avec prompt spécifique "grimlins" et anonymisation des métadonnées.
- Ajoute les API keys dans la configuration MJ Member.
- Affichage du résultat et option de téléchargement.

## À faire
- [x] **Spécifications** : détailler les limites (poids max, formats autorisés), le prompt, les limites de responsabilité (avatar fun, pas de garantie).
- [x] **Back PHP** :
  - [x] Rédiger le module `includes/photo_grimlins.php` avec hooks, contrôle d'accès et nonce.
  - [x] Gérer l'upload (WordPress media handle, vérifications MIME, nettoyage des fichiers temporaires).
  - [x] Implémenter un endpoint AJAX sécurisé (`core/ajax/...`) qui appelle `OpenAI` avec les credentials stockés dans `Config`.
- [x] **Front JS** :
  - [x] Créer `js/elementor/photo-grimlins.js` (module ES6) :
    - [x] Gestion du formulaire (drag & drop, aperçu).
    - [x] Appel fetch AJAX (progress, abort controller).
    - [x] Affichage du résultat, fallback erreur, bouton download.
- [x] **Caméra** : permettre la capture directe depuis la caméra de l’appareil (input `capture`, bouton dédié, gestion des erreurs côté JS).
- [x] **Template Elementor** :
  - [x] Ajouter `includes/templates/elementor/photo_grimlins.php` avec données factices en mode preview.
  - [x] Consommer `AssetsManager::requirePackage('photo-grimlins')`.
- [x] **Styles** : gérer BEM dans `css/widget-photo-grimlins.css` (loader, dropzone, résultat).
- [x] **Configuration** : stocker la clé OpenAI dans `Config` (option + formulaire admin si nécessaire) ou utiliser variable denvironnement.
- [ ] **Sécurité & RGPD** : documenter la suppression automatique des originaux, mentionner que limage générée peut être utilisée uniquement dans le cadre MJ.
- [ ] **Docs** : mettre à jour README / doc interne (utilisation, dépendances, quotas).
- [ ] **Validation** : tester en environnement de staging, vérifier la compatibilité Elementor preview.
