# Guide de Développement - Module Animateur Preact

## Installation et Configuration

### Prérequis
- Node.js 18+
- npm (ou yarn)
- Environnement WordPress de développement

### Installation initiale
```bash
npm install
```

### Commandes de développement

#### Mode développement (watch)
```bash
npm run dev
```
Lance Vite en mode développement avec rechargement à chaud.

#### Build de production
```bash
npm run build
```
Crée un build optimisé dans `js/dist/`

#### Prévisualisation du build
```bash
npm run preview
```

## Structure du Projet

```
src/animateur/
├── main.jsx                      # Point d'entrée
├── components/                    # Composants Preact
│   ├── Dashboard.jsx             # Composant principal
│   ├── EventCarousel.jsx         # Carousel d'événements
│   ├── OccurrenceAgenda.jsx      # Agenda des occurrences
│   ├── ParticipantsTable.jsx     # Tableau des participants
│   ├── SmsBlock.jsx              # Bloc d'envoi SMS
│   ├── MemberPickerModal.jsx     # Modal d'ajout de membres
│   └── QuickMemberModal.jsx      # Modal de création rapide
├── hooks/                         # Hooks personnalisés
│   └── useDashboardState.js      # Gestion d'état du dashboard
└── utils/                         # Utilitaires
    └── helpers.js                 # Fonctions d'aide
```

## Développement de Nouveaux Composants

### Structure d'un composant

```jsx
import { h } from 'preact';
import { useState, useEffect } from 'preact/hooks';

export function MyComponent({ prop1, prop2, onAction }) {
  const [state, setState] = useState(initialValue);

  useEffect(() => {
    // Code d'effet de bord
  }, [dependencies]);

  const handleClick = () => {
    // Logique de traitement
    onAction(data);
  };

  return (
    <div class="my-component">
      <button onClick={handleClick}>
        Action
      </button>
    </div>
  );
}
```

### Utilisation des hooks

#### useState
```jsx
const [value, setValue] = useState(initialValue);
```

#### useEffect
```jsx
useEffect(() => {
  // Code à exécuter
  return () => {
    // Nettoyage (optionnel)
  };
}, [dependencies]);
```

#### useMemo
```jsx
const memoizedValue = useMemo(() => {
  return expensiveComputation(a, b);
}, [a, b]);
```

## Communication avec WordPress

### Appels AJAX

Utilisez la fonction `wpAjax` pour communiquer avec le backend:

```jsx
import { wpAjax } from '../utils/helpers';

// Dans un composant ou une fonction
const handleAction = async () => {
  try {
    const data = await wpAjax('action_name', {
      param1: value1,
      param2: value2
    });
    
    // Traiter la réponse
    console.log(data);
  } catch (error) {
    // Gérer l'erreur
    console.error(error.message);
  }
};
```

### Actions disponibles

- `mj_member_animateur_get_event` - Récupérer un événement
- `mj_member_animateur_save_attendance` - Enregistrer la présence
- `mj_member_animateur_send_sms` - Envoyer un SMS
- `mj_member_animateur_toggle_cash_payment` - Basculer le paiement
- `mj_member_animateur_search_members` - Rechercher des membres
- `mj_member_animateur_quick_create_member` - Créer un membre rapidement
- `mj_member_animateur_add_members` - Ajouter des membres à un événement
- `mj_member_animateur_remove_registration` - Supprimer une inscription

## Gestion d'État

### Hook useDashboardState

Le hook principal pour gérer l'état du dashboard:

```jsx
const {
  state,              // État actuel { eventId, occurrence, filter }
  events,             // Liste des événements
  allEvents,          // Tous les événements disponibles
  currentEvent,       // Événement sélectionné
  currentOccurrence,  // Occurrence sélectionnée
  setEventId,         // Changer l'événement
  setOccurrence,      // Changer l'occurrence
  updateEventSnapshot // Mettre à jour un événement
} = useDashboardState(config);
```

### État local vs état global

- **État local** (useState): Pour l'UI d'un composant spécifique
- **État global** (useDashboardState): Pour les données partagées entre composants

## Styles et CSS

Les classes CSS suivent la convention BEM:

```css
.mj-animateur-dashboard           /* Bloc */
.mj-animateur-dashboard__element  /* Élément */
.mj-animateur-dashboard--modifier /* Modificateur */
.is-active                         /* État */
```

## Bonnes Pratiques

### 1. Composants purs
```jsx
// ✅ Bon
export function MyComponent({ data }) {
  return <div>{data.name}</div>;
}

// ❌ Mauvais (mutation externe)
let globalVar;
export function MyComponent({ data }) {
  globalVar = data.name; // Side effect
  return <div>{data.name}</div>;
}
```

### 2. Gestion des événements
```jsx
// ✅ Bon
<button onClick={() => handleClick(id)}>Click</button>

// ❌ Mauvais (appel immédiat)
<button onClick={handleClick(id)}>Click</button>
```

### 3. Conditions de rendu
```jsx
// ✅ Bon
{condition && <Component />}
{condition ? <ComponentA /> : <ComponentB />}

// ❌ Mauvais (rendu de false/undefined)
{condition.toString()}
```

### 4. Listes et clés
```jsx
// ✅ Bon
{items.map(item => (
  <Item key={item.id} data={item} />
))}

// ❌ Mauvais (index comme clé)
{items.map((item, index) => (
  <Item key={index} data={item} />
))}
```

### 5. Sécurité XSS
```jsx
// ✅ Bon - Preact échappe automatiquement
<div>{userInput}</div>

// ⚠️ Attention - dangerouslySetInnerHTML
<div dangerouslySetInnerHTML={{ __html: sanitizedHTML }} />
```

## Debugging

### React DevTools
Compatible avec Preact. Installer l'extension de navigateur.

### Console logging
```jsx
useEffect(() => {
  console.log('Component mounted');
  console.log('Props:', props);
  console.log('State:', state);
}, []);
```

### Performance profiling
```jsx
import { h, options } from 'preact';

// Activer le profiling
options.debounceRendering = requestIdleCallback;
```

## Tests

### Structure de test (à venir)
```jsx
import { h } from 'preact';
import { render } from '@testing-library/preact';
import { MyComponent } from './MyComponent';

describe('MyComponent', () => {
  it('should render', () => {
    const { getByText } = render(
      <MyComponent title="Test" />
    );
    expect(getByText('Test')).toBeTruthy();
  });
});
```

## Optimisations

### 1. Mémoïsation
```jsx
const expensiveValue = useMemo(() => {
  return computeExpensiveValue(a, b);
}, [a, b]);
```

### 2. Callbacks mémoïsés
```jsx
const handleClick = useCallback(() => {
  doSomething(a, b);
}, [a, b]);
```

### 3. Lazy loading
```jsx
import { lazy, Suspense } from 'preact/compat';

const HeavyComponent = lazy(() => import('./HeavyComponent'));

<Suspense fallback={<div>Loading...</div>}>
  <HeavyComponent />
</Suspense>
```

## Migration depuis jQuery

### Équivalences

| jQuery | Preact |
|--------|--------|
| `$('#id')` | `ref.current` ou state |
| `$('.class')` | Composants avec props |
| `$.ajax()` | `wpAjax()` ou `fetch()` |
| `$(document).ready()` | `useEffect(() => {}, [])` |
| `.on('click')` | `onClick={}` |
| `.addClass()` | Conditional class names |
| `.show()/.hide()` | Conditional rendering |

### Exemple de migration

#### Avant (jQuery)
```javascript
$('.button').on('click', function() {
  var data = $(this).data('value');
  $.ajax({
    url: '/api',
    data: { value: data },
    success: function(response) {
      $('.result').text(response.message);
    }
  });
});
```

#### Après (Preact)
```jsx
function MyComponent() {
  const [result, setResult] = useState('');
  
  const handleClick = async (value) => {
    try {
      const data = await wpAjax('my_action', { value });
      setResult(data.message);
    } catch (error) {
      console.error(error);
    }
  };
  
  return (
    <div>
      <button onClick={() => handleClick('test')}>
        Click
      </button>
      <div class="result">{result}</div>
    </div>
  );
}
```

## Ressources

- [Documentation Preact](https://preactjs.com/)
- [Hooks Preact](https://preactjs.com/guide/v10/hooks/)
- [Vite Documentation](https://vitejs.dev/)
- [MDN Web Docs](https://developer.mozilla.org/)

## Support et Questions

Pour toute question ou problème, consulter:
1. Ce guide de développement
2. La documentation Preact
3. Les commentaires dans le code source
4. L'équipe de développement
