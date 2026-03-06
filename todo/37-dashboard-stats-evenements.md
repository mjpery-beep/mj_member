# 37. Tableau de bord statistiques événements

## Objectif
Fournir aux gestionnaires un dashboard visuel avec les métriques clés des événements : fréquentation, taux de remplissage, tendances, membres les plus actifs.

## Contexte
Les données événements sont riches (inscriptions, présences, occurrences, photos, bénévoles) mais il n'y a pas de vue consolidée pour piloter l'activité.

## Fonctionnalités
- [ ] KPI cards : nombre d'événements (mois/année), total inscrits, taux de présence moyen, événement le plus populaire
- [ ] Graphique d'évolution mensuelle des inscriptions (line chart)
- [ ] Répartition par type/lieu d'événement (donut chart)
- [ ] Top 10 membres les plus actifs (nombre événements participés)
- [ ] Heatmap jour/heure montrant les créneaux les plus demandés
- [ ] Filtres par période, lieu, animateur
- [ ] Export des stats en CSV

## Tables concernées
`MjEvents`, `MjEventRegistrations`, `MjEventAttendance`, `MjEventOccurrences`, `MjEventLocations`, `MjEventAnimateurs`

## Tâches techniques
- [ ] Endpoints AJAX admin pour les agrégations SQL (via `CrudQueryBuilder`)
- [ ] Page admin ou section dans le dashboard existant
- [ ] Composants Preact : KpiCards, LineChart, DonutChart, HeatmapGrid, MemberRanking
- [ ] Librairie de charts légère (Chart.js ou uPlot)
- [ ] CSS admin dédié
