# Plan d'optimisation des performances

Date de l'analyse : 2026-09-08  
Page de référence : `http://localhost:8080/`  
Périmètre : page d'accueil publique et assets front du plugin MJ Member.

## 1. Mesures observées

Les mesures ont été réalisées sur l'environnement local Docker. Elles donnent une direction fiable, mais doivent être confirmées en production avec un test sans cache puis avec cache.

| Indicateur | Valeur observée |
| --- | ---: |
| TTFB navigateur | environ 2,28 s |
| TTFB avec `curl` | environ 1,21 s |
| DOM interactif | environ 2,95 s |
| DOMContentLoaded | environ 3,34 s |
| Chargement complet | environ 3,82 s |
| Taille HTML brute | environ 1,0 Mo |
| Taille du DOM généré | environ 1,3 Mo |
| Images chargées | 30 |
| Poids transféré par les images | environ 820 KiB |
| Vidéos présentes | 4 |
| Scripts détectés | 56 |
| Feuilles de style détectées | 11 |

Constats complémentaires :

- La réponse HTML ne contient pas d'en-tête `Content-Encoding` visible.
- Les assets testés possèdent `ETag` et `Last-Modified`, mais pas de politique `Cache-Control` longue durée.
- La page charge des ressources lourdes de WordPress et d'Elementor comme `media-views`, `plupload`, Swiper et l'éditeur d'occurrences.
- Le script `mj-member-testimonials` fait environ 33 KiB encodés dans le navigateur et son fichier source est beaucoup plus volumineux en brut.
- Les quatre vidéos utilisent `preload="metadata"`, ce qui déclenche tout de même des requêtes pour leurs métadonnées.

## 2. Priorités

### Priorité P0 : compression HTTP

Activer Brotli ou gzip pour les réponses HTML, CSS, JavaScript, JSON et SVG.

Objectif : réduire immédiatement le poids de la réponse HTML d'environ 1 Mo et accélérer le transfert sur mobile ou réseau lent.

À vérifier côté serveur :

- `Content-Encoding: br` ou `Content-Encoding: gzip` ;
- présence de `Vary: Accept-Encoding` ;
- absence de double compression ;
- compression appliquée aussi aux réponses AJAX JSON.

### Priorité P0 : cache des assets statiques

Ajouter une durée de cache longue pour les fichiers versionnés :

```http
Cache-Control: public, max-age=31536000, immutable
```

Cette règle concerne les fichiers CSS, JavaScript, polices, images et médias dont l'URL change lorsqu'ils sont modifiés.

Pour les pages HTML publiques, utiliser une durée plus courte ou un cache de page contrôlé afin d'éviter de servir du contenu obsolète.

### Priorité P1 : chargement conditionnel des packages

Le gestionnaire d'assets est centralisé dans `includes/core/AssetsManager.php`. Les packages doivent être enregistrés globalement, mais chargés uniquement si le widget correspondant est présent dans la page.

À contrôler sur la page d'accueil :

- `media-views` et `plupload` ;
- `registration-manager/occurrence-editor.js` ;
- `events-calendar.js` ;
- les dépendances Swiper ;
- les styles et scripts réservés aux comptes connectés ou à l'administration.

Pour chaque package, vérifier que le template Elementor appelle `AssetsManager::requirePackage()` uniquement lorsqu'il rend effectivement le composant.

Ne pas désactiver globalement une dépendance sans vérifier les widgets Elementor qui l'utilisent.

### Priorité P1 : images responsives

Pour les images situées sous la ligne de flottaison :

- ajouter `loading="lazy"` ;
- ajouter `decoding="async"` ;
- définir `width` et `height` ;
- générer et servir des formats WebP ou AVIF ;
- demander une taille WordPress adaptée à la taille affichée, pas l'original ;
- utiliser `srcset` et `sizes`.

L'image principale visible immédiatement doit rester prioritaire et ne doit pas être lazy-loadée si elle constitue le contenu principal de la page.

### Priorité P1 : vidéos de témoignages

Les vidéos présentes dans `includes/templates/front/event-page/partials/testimonials.html.twig` doivent être chargées à la demande :

1. afficher une image poster légère ;
2. ne créer ou renseigner la source vidéo qu'au clic ;
3. conserver `preload="none"` par défaut ;
4. fournir un poster avec un ratio et des dimensions stables ;
5. éviter de charger les métadonnées de plusieurs vidéos invisibles.

Pour un carrousel, seule la vidéo active doit être initialisée.

### Priorité P1 : cache de page et TTFB

Le TTFB varie entre environ 1,2 s et 2,3 s sur l'environnement local. Il faut profiler le rendu avant de modifier les requêtes.

Mesures recommandées :

- installer temporairement Query Monitor sur l'environnement de développement ;
- relever le nombre et la durée des requêtes SQL ;
- identifier les appels HTTP externes pendant le rendu ;
- mesurer le temps Elementor et les hooks WordPress les plus coûteux ;
- activer un object cache Redis si l'hébergement le permet ;
- activer un cache de page pour les visiteurs non connectés ;
- exclure les pages et endpoints personnalisés qui doivent rester dynamiques.

Les pages contenant des données personnalisées, des formulaires ou des informations membres ne doivent pas être mises en cache publiquement sans règle d'exclusion.

### Priorité P2 : JavaScript non critique

Les scripts qui ne participent pas au rendu initial doivent être différés ou chargés après l'affichage principal.

Cibles possibles :

- interactions secondaires des témoignages ;
- modules de modales ;
- éditeurs réservés aux gestionnaires ;
- composants de calendrier non visibles ;
- scripts de notifications ou d'abonnement push.

Les changements doivent préserver l'ordre des dépendances WordPress. Il faut éviter d'ajouter `defer` manuellement à un script dont un autre script dépend pendant le parsing de la page.

### Priorité P2 : réduire le HTML rendu

Le document HTML est particulièrement volumineux. Rechercher :

- données JSON répétées dans les attributs ou scripts inline ;
- listes de témoignages complètes rendues alors qu'une page de résultats suffit ;
- contenu masqué rendu plusieurs fois pour les variantes mobile et desktop ;
- markup Elementor inutilisé ;
- textes ou métadonnées chargés avant interaction.

Lorsque les données ne sont pas nécessaires au premier affichage, les charger via un endpoint AJAX paginé ou au moment de l'ouverture du composant.

## 3. Fichiers et zones à examiner

- `includes/core/AssetsManager.php` : enregistrement et chargement conditionnel des packages front.
- `includes/templates/front/event-page/partials/testimonials.html.twig` : vidéos et contenu des témoignages.
- `includes/templates/elementor/testimonials.php` : rendu Elementor des témoignages.
- `js/testimonials.js` : initialisation du carrousel et des vidéos.
- Configuration Apache ou du reverse proxy : compression et cache HTTP.
- Configuration WordPress/Elementor : génération CSS, cache de page et assets globaux.

## 4. Plan d'implémentation recommandé

1. Mesurer une page publique sans cache avec les métriques TTFB, LCP, INP et CLS.
2. Activer la compression HTTP et vérifier les en-têtes avec `curl -I`.
3. Ajouter les règles de cache des assets statiques.
4. Identifier les packages réellement utilisés par la page d'accueil.
5. Corriger le chargement conditionnel dans `AssetsManager` ou les templates concernés.
6. Optimiser les images et remplacer les vidéos initiales par des posters.
7. Profiler les requêtes SQL et le rendu Elementor.
8. Activer le cache de page pour les visiteurs anonymes.
9. Refaire les mesures sur desktop et mobile, avec cache froid et cache chaud.

## 5. Critères de validation

La première itération peut être considérée comme réussie si, sur une page publique représentative :

- le TTFB avec cache reste inférieur à 500 ms ;
- le TTFB sans cache est documenté et en baisse ;
- le HTML transféré est nettement inférieur à la mesure actuelle ;
- les scripts d'administration ou de gestionnaire ne sont plus chargés inutilement ;
- les vidéos invisibles ne déclenchent plus de requête initiale ;
- les images sous la ligne de flottaison sont lazy-loadées ;
- aucun décalage visuel important n'est introduit ;
- les widgets Elementor existants fonctionnent encore en mode connecté et déconnecté.

Commandes utiles pour contrôler les en-têtes :

```bash
curl -sSI http://localhost:8080/
curl -sSI http://localhost:8080/wp-content/plugins/mj-member/js/testimonials.js
```

Vérifications de syntaxe après modification du PHP ou du JavaScript :

```bash
php -l includes/core/AssetsManager.php
node --check js/testimonials.js
```

Pour les tests PHPUnit du plugin, utiliser le conteneur `web` conformément aux conventions du projet.
