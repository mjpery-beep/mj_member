# 41. Widget calendrier unifié

## Objectif
Créer un widget Elementor affichant un calendrier mensuel interactif regroupant événements, fermetures MJ, congés et tâches dans une vue unifiée.

## Contexte
Le widget `events-calendar` existe pour les événements seuls. Un calendrier unifié donnerait une vue globale de l'activité MJ aux animateurs et coordinateurs.

## Fonctionnalités
- [ ] Vue mois avec navigation (mois précédent/suivant, retour à aujourd'hui)
- [ ] Événements MJ (couleur par type ou lieu)
- [ ] Fermetures MJ (jours barrés ou colorés distinctement)
- [ ] Congés approuvés des animateurs (discret, visible seulement par les gestionnaires)
- [ ] Tâches avec deadline (petits indicateurs sur les jours concernés)
- [ ] Clic sur un jour → panneau latéral avec le détail
- [ ] Filtres par catégorie (événements / fermetures / congés / tâches)
- [ ] Vue semaine optionnelle
- [ ] Données mock pour preview Elementor

## Tables concernées
`MjEvents`, `MjEventOccurrences`, `MjEventClosures`, `MjLeaveRequests`, `MjTodos`

## Tâches techniques
- [ ] Endpoint AJAX front agrégeant les données des 4 sources pour un mois donné
- [ ] Composant Preact `UnifiedCalendar` avec sous-composants (MonthGrid, DayCell, DetailPanel)
- [ ] Template Elementor + classe widget
- [ ] CSS BEM `css/unified-calendar.css`
- [ ] Gestion des capabilities (congés visibles seulement si gestionnaire)
