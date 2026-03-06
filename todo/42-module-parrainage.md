# 42. Module parrainage entre membres

## Objectif
Permettre aux membres expérimentés de parrainer les nouveaux arrivants pour faciliter leur intégration à la MJ, avec un suivi et des récompenses gamifiées.

## Contexte
Le système de gamification (badges, XP, coins) est en place. Le parrainage s'y intègrerait naturellement en récompensant l'entraide et le suivi des nouveaux.

## Fonctionnalités
- [ ] Un membre peut proposer de devenir parrain (volontariat)
- [ ] Attribution automatique ou manuelle d'un parrain aux nouveaux inscrits
- [ ] Dashboard parrain : voir ses filleuls, leur progression, leurs premiers événements
- [ ] Dashboard filleul : voir son parrain, le contacter
- [ ] Récompenses gamifiées :
  - Badge "Parrain" après le premier parrainage
  - XP bonus quand le filleul participe à son premier événement
  - Trophée au bout de 3 parrainages réussis
- [ ] Notification au parrain quand le filleul s'inscrit ou participe
- [ ] Page admin pour superviser les paires et les dissolver si besoin

## Architecture
- Nouvelle table `mj_member_sponsorships` (sponsor_id, sponsored_id, status, created_at)
- CRUD `MjSponsorships`
- Listener de notifications pour les événements filleul
- Intégration avec `MjMemberActions` pour les récompenses

## Tâches techniques
- [ ] Migration schema + CRUD `MjSponsorships`
- [ ] Page admin de gestion
- [ ] Widget front "Mon parrainage" (Preact)
- [ ] Listeners de notifications
- [ ] Critères de badges/trophées associés
