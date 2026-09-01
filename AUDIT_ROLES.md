# Audit des rôles MJ Member

Date de l'audit : 17 décembre 2025

## 1. Définition des constantes de rôle

Les constantes sont définies dans `includes/classes/crud/MjMembers.php` (lignes 20-24) :

```php
const ROLE_JEUNE = 'jeune';
const ROLE_ANIMATEUR = 'animateur';
const ROLE_COORDINATEUR = 'coordinateur';
const ROLE_BENEVOLE = 'benevole';
const ROLE_TUTEUR = 'tuteur';
```

**Labels associés** (lignes 109-114) :
```php
self::ROLE_JEUNE => 'Jeune',
self::ROLE_TUTEUR => 'Tuteur',
self::ROLE_ANIMATEUR => 'Animateur',
self::ROLE_COORDINATEUR => 'Coordinateur',
self::ROLE_BENEVOLE => 'Bénévole',
```

---

## 2. Fichiers utilisant les rôles

### 2.1 Fichiers utilisant les CONSTANTES (✅ Bonne pratique)

| Fichier | Constantes utilisées | Usage |
|---------|---------------------|-------|
| [includes/classes/crud/MjMembers.php](includes/classes/crud/MjMembers.php) | Toutes | Définition, validation, normalisation |
| [includes/classes/crud/MjEventRegistrations.php](includes/classes/crud/MjEventRegistrations.php#L1142) | `ROLE_TUTEUR` | Vérification inscription tuteur |
| [includes/classes/crud/MjIdeas.php](includes/classes/crud/MjIdeas.php#L406-L407) | `ROLE_ANIMATEUR`, `ROLE_COORDINATEUR` | Permissions suppression idées |
| [includes/classes/crud/MjTodos.php](includes/classes/crud/MjTodos.php#L26-L28) | `ROLE_ANIMATEUR`, `ROLE_COORDINATEUR`, `ROLE_BENEVOLE` | Rôles autorisés todos |
| [includes/classes/table/MjMembers_List_Table.php](includes/classes/table/MjMembers_List_Table.php) | Toutes | Affichage labels, filtres switch/case |
| [includes/classes/MjMail.php](includes/classes/MjMail.php#L668) | `ROLE_TUTEUR` | Logique d'envoi email |
| [includes/classes/MjAccountLinks.php](includes/classes/MjAccountLinks.php#L241-L329) | Toutes | Détermination visibilité liens |
| [includes/contact_messages.php](includes/contact_messages.php#L203-L258) | `ROLE_COORDINATEUR`, `ROLE_ANIMATEUR` | Récupération destinataires |
| [includes/events_public.php](includes/events_public.php#L65-L74) | `ROLE_COORDINATEUR`, `ROLE_ANIMATEUR`, `ROLE_BENEVOLE` | Permissions événements internes |
| [includes/forms/form_member.php](includes/forms/form_member.php) | `ROLE_JEUNE`, `ROLE_TUTEUR` | Validation formulaire membre |
| [includes/forms/form_event.php](includes/forms/form_event.php#L523) | `ROLE_ANIMATEUR` | Filtre liste animateurs |
| [includes/import_members.php](includes/import_members.php#L1360-L1379) | Toutes | Mapping import CSV |
| [includes/shortcode_inscription.php](includes/shortcode_inscription.php#L281-L321) | `ROLE_JEUNE`, `ROLE_TUTEUR` | Création membre inscription |
| [includes/inline_edit_member.php](includes/inline_edit_member.php#L37-L68) | `ROLE_JEUNE` | Validation email selon rôle |
| [includes/dashboard.php](includes/dashboard.php#L481) | `ROLE_ANIMATEUR` | Statistiques animateurs actifs |
| [includes/member_accounts.php](includes/member_accounts.php#L319) | `ROLE_TUTEUR` | Permission gestion enfants |
| [includes/send_emails.php](includes/send_emails.php#L255) | `ROLE_TUTEUR` | CC tuteur dans emails |
| [includes/idea_box.php](includes/idea_box.php#L286-L287) | `ROLE_ANIMATEUR`, `ROLE_COORDINATEUR` | Permissions suppression |
| [includes/event_photos.php](includes/event_photos.php#L64-L65) | `ROLE_ANIMATEUR`, `ROLE_COORDINATEUR` | Rôles staff photos |
| [includes/templates/elementor/animateur_account.php](includes/templates/elementor/animateur_account.php#L216) | `ROLE_ANIMATEUR`, `ROLE_JEUNE`, `ROLE_TUTEUR` | Vérification accès, default role |
| [includes/elementor/class-mj-member-contact-messages-widget.php](includes/elementor/class-mj-member-contact-messages-widget.php#L525-L526) | `ROLE_ANIMATEUR`, `ROLE_COORDINATEUR` | Cibles messages |
| [includes/classes/front/EventFormController.php](includes/classes/front/EventFormController.php#L33) | `ROLE_ANIMATEUR` | Liste animateurs formulaire |
| [includes/core/ajax/admin/members.php](includes/core/ajax/admin/members.php#L288-L332) | `ROLE_JEUNE` | Validation inline edit |
| [tests/Crud/MjMembersAnonymizeTest.php](tests/Crud/MjMembersAnonymizeTest.php) | `ROLE_JEUNE` | Tests unitaires |

### 2.2 Fichiers utilisant des CHAÎNES EN DUR (⚠️ Incohérences)

#### 2.2.1 `capabilities.php` - Strings en dur pour rôles WordPress

| Ligne | Code | Commentaire |
|-------|------|-------------|
| 18 | `array('administrator', 'animateur', 'coordinateur')` | Rôles WP, pas MJ |
| 50 | `array('administrator', 'animateur', 'coordinateur')` | Rôles WP hours |
| 73 | `array('administrator', 'animateur', 'coordinateur')` | Rôles WP todos |
| 96 | `array('administrator', 'animateur')` | Rôles WP documents |
| 142 | `array('benevole')` | Legacy hours roles |
| 163 | `array('benevole')` | Legacy todos roles |

**Analyse** : Ces chaînes correspondent aux **rôles WordPress** (non aux rôles MJ Member). C'est intentionnel car `get_role()` attend des slugs WP. CEPENDANT, il y a un couplage implicite avec les valeurs des constantes MjMembers.

#### 2.2.2 `schema.php` - DDL et migrations

| Ligne | Code | Problème |
|-------|------|----------|
| 798 | `DEFAULT 'jeune'` | DDL SQL |
| 921 | `'role' => 'jeune'` | Seed data |
| 943 | `role = 'tuteur'` | Requête SQL |
| 2366 | `role = 'benevole'` | Migration |
| 2367 | `role = 'jeune' WHERE role = 'benevole'` | Migration |
| 2492 | `DEFAULT 'jeune'` | DDL table création |
| 2603 | `'role' => 'tuteur'` | Seed data |
| 2625 | `'role' => 'jeune'` | Seed data |

**Analyse** : Les strings en dur dans le DDL SQL sont acceptables (contrainte technique). Les migrations doivent rester stables.

#### 2.2.3 `event_photos.php` - Fallback avant constantes

| Ligne | Code | Problème |
|-------|------|----------|
| 60-61 | `$animateur_role = 'animateur'; $coordinateur_role = 'coordinateur';` | ⚠️ Fallback inutile |
| 981-983 | `array('animateur', 'coordinateur')` | ⚠️ Strings en dur dans filtre |

**Recommandation** : Utiliser directement les constantes dans le filtre.

#### 2.2.4 `includes/classes/View/EventPage/EventPageModel.php` - Strings en dur

| Ligne | Code | Problème |
|-------|------|----------|
| 845 | `in_array($role, array('animateur', 'coordinateur'), true)` | ⚠️ Devrait utiliser constantes |

#### 2.2.5 `js/elementor/idea-box.js` - JavaScript côté client

| Ligne | Code | Problème |
|-------|------|----------|
| 204 | `role === 'animateur' \|\| role === 'coordinateur'` | ⚠️ Strings en dur JS |

**Recommandation** : Ces valeurs devraient être passées via `wp_localize_script`.

#### 2.2.6 `includes/templates/elementor/idea_box.php` - Données factices

| Ligne | Code | Usage |
|-------|------|-------|
| 32, 49, 85 | `'role' => 'benevole'`, `'role' => 'animateur'` | Données preview Elementor |

**Analyse** : Acceptable pour les données factices (preview), mais pourrait utiliser constantes.

#### 2.2.7 `includes/forms/form_member.php` - JavaScript inline

| Lignes | Code | Problème |
|--------|------|----------|
| 679, 685, 688, 693, 702, 709, 737, 747, 765, 773 | `role === 'jeune'` | ⚠️ JS inline avec strings |

**Recommandation** : Externaliser le JS et passer les constantes via data-attributes ou localize.

#### 2.2.8 `includes/elementor/class-mj-member-contact-form-widget.php`

| Ligne | Code | Problème |
|-------|------|----------|
| 658, 713 | `'role' => 'animateur'`, `'role' => 'tuteur'` | Données preview/fallback |

#### 2.2.9 `includes/settings.php`

| Ligne | Code | Problème |
|-------|------|----------|
| 740 | `$link_config['visibility'] === 'animateur'` | ⚠️ Comparaison string |

#### 2.2.10 `includes/classes/View/EventSingle/PreviewContextFactory.php`

| Lignes | Code | Usage |
|--------|------|-------|
| 209-210, 227-228 | `'type' => 'jeune', 'role' => 'jeune'` | Données preview |

---

## 3. Constantes de cible contact (différent des rôles membres)

La classe `MjContactMessages` définit ses propres constantes pour les cibles :

```php
const TARGET_ANIMATEUR = 'animateur';
const TARGET_COORDINATEUR = 'coordinateur';
const TARGET_ALL = 'all';
```

Ces constantes sont correctement utilisées dans :
- [includes/contact_messages.php](includes/contact_messages.php)
- [includes/classes/MjAccountLinks.php](includes/classes/MjAccountLinks.php)
- [includes/elementor/class-mj-member-contact-messages-widget.php](includes/elementor/class-mj-member-contact-messages-widget.php)
- [includes/elementor/class-mj-member-contact-form-widget.php](includes/elementor/class-mj-member-contact-form-widget.php)

---

## 4. Fonctions de vérification de permissions

### 4.1 `mj_member_user_can_view_internal_events()`
**Fichier** : [includes/events_public.php#L54](includes/events_public.php#L54)

**Rôles autorisés** : `coordinateur`, `animateur`, `benevole`

**Implémentation** : ✅ Utilise les constantes avec fallback.

### 4.2 Vérifications via `Config::capability()`
**Fichier** : [includes/core/Config.php](includes/core/Config.php)

Les capacités WordPress sont définies :
- `MJ_MEMBER_CAPABILITY` = `mj_manage_members`
- `MJ_MEMBER_CONTACT_CAPABILITY` = `mj_manage_contact_messages`
- `MJ_MEMBER_HOURS_CAPABILITY` = `mj_member_log_hours`
- `MJ_MEMBER_TODOS_CAPABILITY` = `mj_member_manage_todos`
- `MJ_MEMBER_DOCUMENTS_CAPABILITY` = `mj_member_manage_documents`

Ces capacités sont assignées aux rôles WordPress via [capabilities.php](includes/core/capabilities.php).

---

## 5. Résumé des incohérences

### 5.1 Critique (à corriger)

| Fichier | Problème | Recommandation |
|---------|----------|----------------|
| [EventPageModel.php#L845](includes/classes/View/EventPage/EventPageModel.php#L845) | `array('animateur', 'coordinateur')` en dur | Utiliser `MjMembers::ROLE_*` |
| [event_photos.php#L981-983](includes/event_photos.php#L981-L983) | Strings en dur dans `apply_filters` | Utiliser constantes |

### 5.2 Moyenne (amélioration recommandée)

| Fichier | Problème | Recommandation |
|---------|----------|----------------|
| [form_member.php](includes/forms/form_member.php) (JS inline) | `role === 'jeune'` répété | Externaliser JS, passer constantes |
| [idea-box.js#L204](js/elementor/idea-box.js#L204) | Strings en dur côté client | Passer via `wp_localize_script` |
| [settings.php#L740](includes/settings.php#L740) | `'animateur'` en dur | Utiliser constante |

### 5.3 Faible (acceptable mais améliorable)

| Fichier | Problème | Statut |
|---------|----------|--------|
| [capabilities.php](includes/core/capabilities.php) | Rôles WP en dur | OK - Rôles WordPress, pas MJ |
| [schema.php](includes/core/schema.php) | DDL/Migrations | OK - Contrainte technique |
| Templates preview | Données factices | OK - Contexte de preview |

---

## 6. Recommandations de refactorisation

### 6.1 Court terme (priorité haute)

1. **Corriger `EventPageModel.php`** :
   ```php
   // Avant
   $isAnimateur = in_array($role, array('animateur', 'coordinateur'), true);
   
   // Après
   $isAnimateur = in_array($role, array(MjMembers::ROLE_ANIMATEUR, MjMembers::ROLE_COORDINATEUR), true);
   ```

2. **Corriger `event_photos.php`** (ligne 981) :
   ```php
   // Avant
   $auto_approve_roles = apply_filters('mj_member_event_photo_auto_approve_roles', array('animateur', 'coordinateur'));
   
   // Après
   $auto_approve_roles = apply_filters(
       'mj_member_event_photo_auto_approve_roles',
       array(MjMembers::ROLE_ANIMATEUR, MjMembers::ROLE_COORDINATEUR)
   );
   ```

### 6.2 Moyen terme (priorité moyenne)

3. **Externaliser le JavaScript de `form_member.php`** :
   - Créer `js/admin/form-member.js`
   - Passer les constantes via `wp_localize_script` :
   ```php
   wp_localize_script('mj-form-member', 'mjFormMember', array(
       'roles' => array(
           'jeune' => MjMembers::ROLE_JEUNE,
           'tuteur' => MjMembers::ROLE_TUTEUR,
       ),
   ));
   ```

4. **Améliorer `idea-box.js`** :
   - Passer les rôles privilégiés depuis PHP :
   ```php
   wp_localize_script('mj-idea-box', 'mjIdeaBoxConfig', array(
       'privilegedRoles' => array(MjMembers::ROLE_ANIMATEUR, MjMembers::ROLE_COORDINATEUR),
   ));
   ```

### 6.3 Long terme (amélioration architecturale)

5. **Créer une classe `Roles` centralisée** :
   ```php
   namespace Mj\Member\Core;
   
   final class Roles {
       public const JEUNE = 'jeune';
       public const ANIMATEUR = 'animateur';
       public const COORDINATEUR = 'coordinateur';
       public const BENEVOLE = 'benevole';
       public const TUTEUR = 'tuteur';
       
       public static function all(): array { ... }
       public static function staff(): array { ... }
       public static function labels(): array { ... }
       public static function wpRoles(): array { ... }
   }
   ```

6. **Centraliser les permissions par rôle** :
   ```php
   final class RolePermissions {
       public static function canViewInternalEvents(string $role): bool { ... }
       public static function canDeleteIdeas(string $role): bool { ... }
       public static function canAutoApprovePhotos(string $role): bool { ... }
   }
   ```

---

## 7. Matrice de compatibilité rôles MJ ↔ rôles WordPress

| Rôle MJ Member | Rôle WordPress correspondant | Capacités WP |
|----------------|------------------------------|--------------|
| coordinateur | coordinateur | `mj_manage_members`, `mj_member_log_hours`, `mj_member_manage_todos` |
| animateur | animateur | `mj_manage_members`, `mj_member_log_hours`, `mj_member_manage_todos`, `mj_member_manage_documents` |
| benevole | benevole | (legacy) `mj_member_log_hours`, `mj_member_manage_todos` |
| tuteur | subscriber | Aucune capacité MJ spécifique |
| jeune | subscriber | Aucune capacité MJ spécifique |

---

## 8. Conclusion

L'utilisation des constantes de rôle est **globalement cohérente** dans le plugin MJ Member. Les principales constantes (`ROLE_JEUNE`, `ROLE_ANIMATEUR`, etc.) sont bien définies et utilisées dans la majorité des fichiers PHP.

**Points positifs** :
- Constantes centralisées dans `MjMembers`
- Labels associés aux constantes
- Utilisation cohérente dans les classes CRUD
- Fallbacks avec vérification `class_exists`

**Points à améliorer** :
- Quelques strings en dur subsistent (notamment côté JS et dans certains filtres)
- Le JavaScript inline dans `form_member.php` devrait être externalisé
- Une classe `Roles` dédiée simplifierait la maintenance

**Effort estimé** :
- Corrections critiques : ~2h
- Améliorations moyennes : ~4h
- Refactorisation architecturale : ~8h
