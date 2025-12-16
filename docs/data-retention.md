# Politique de conservation des données

Le module MJ Member applique par défaut une politique de conservation de **36 mois (1 095 jours)** pour les membres inactifs. Cette durée est configurable via le filtre `mj_member_data_retention_days`.

## Fonctionnement

1. À chaque chargement (`init`), le plugin planifie un évènement cron quotidien (`mj_member_purge_expired_members`).
2. Lors de l’exécution de cet évènement, le plugin sélectionne les membres inactifs dont la dernière cotisation (ou l’inscription) est antérieure au seuil défini.
3. Les comptes sélectionnés sont anonymisés (nom fictif, email supprimé, données personnelles effacées) et horodatés (`anonymized_at`).
4. Des hooks permettent de personnaliser ou de journaliser les opérations :
   - `mj_member_should_anonymize_member` pour filtrer les candidats.
   - `mj_member_data_retention_success` et `mj_member_data_retention_error` pour suivre les résultats.

## Configuration

- **Désactiver la purge** : retourner `0` via `mj_member_data_retention_days`.
- **Modifier la taille de lot** : utiliser `mj_member_data_retention_batch_size`.
- **Reporter la première exécution** : filtre `mj_member_data_retention_first_run`.

## Lancer la purge manuellement

```php
// Déclencher la vérification manuellement.
do_action('mj_member_purge_expired_members');
```

Cette documentation complète les exigences RGPD en tenant compte des membres inactifs et offre des points d’extension pour auditer ou adapter la politique de conservation.
