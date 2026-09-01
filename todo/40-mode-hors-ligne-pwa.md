# 40. Mode hors-ligne & PWA

## Objectif
Transformer l'expérience front-end en Progressive Web App pour permettre un accès basique hors-ligne et une installation "app" sur mobile.

## Contexte
Le service worker pour le push (`sw-push.js`) existe déjà. L'étendre en vrai service worker PWA permettrait le caching des pages et une UX native sur mobile.

## Fonctionnalités
- [ ] Manifest `manifest.json` avec icônes, couleurs MJ Péry, nom de l'app
- [ ] Service worker étendu avec stratégie cache-first pour les assets statiques
- [ ] Page offline de secours (branding MJ + message "Pas de connexion")
- [ ] Cache des dernières données consultées (événements à venir, profil)
- [ ] Bouton "Installer l'app" dans le menu mobile
- [ ] Sync en arrière-plan pour les formulaires soumis hors-ligne (encodage heures, inscriptions)

## Architecture
- Étendre `sw-push.js` → `sw.js` complet
- Workbox (librairie Google) pour simplifier les stratégies de cache
- `AssetsManager` enregistre le manifest et le service worker
- Les pages Elementor critiques sont précachées

## Tâches techniques
- [ ] Créer `manifest.json` avec métadonnées MJ Péry
- [ ] Étendre le service worker (`js/sw.js`)
- [ ] Page offline (`offline.html`)
- [ ] Hook `wp_head` pour injecter le lien manifest + meta theme-color
- [ ] Bouton d'installation dans le widget menu mobile
- [ ] Tests sur mobile (Android Chrome, iOS Safari)
