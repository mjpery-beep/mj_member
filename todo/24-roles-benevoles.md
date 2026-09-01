# 24. Rôles des bénévoles

Objectif : identifier et structurer les missions bénévoles potentielles afin de préparer les écrans, workflows et besoins de formation côté MJ Member.

## Contexte
- un jeunes peut être bénévole (ex: aide lors d'un événement) sans pour autant être membre (adhérent payant)
- un animateur peut être bénévole (ex: encadrer un stage) sans pour autant être membre (adhérent payant)

# 24.1 Actions à envisager
- [x] Retirer le rôle "Bénévole" dans le système de gestion des rôles classique du module MJ Member.
- [x] Permettre d'assigner le rôle bénévole comme une information complémentaire au profil utilisateur (une case à cocher dans l'admin MJ Member).
- [x] Ajouter dans le table Member dans le panel identité, ajoute un Label "Bénévole" (modifiable en ajax)
 
## Aspects spécifiques aux rôles Bénévoles Jeunes
- [ ] Les bénévoles Jeunes ont accès à la page "Gestion des événements" dans l'admin WP
    - [ ] Accès en lecture seule ou avec droits limités (il ne voit que les events assignés à eux, pas de suppression)
- [ ] Les bénévoles Jeunes peuvent gérer les inscriptions aux événements (validation, refus) – (assignés à eux)
- [ ] Les bénévoles Jeunes peuvent publier des photos d'événements (sans modération)
- [x] Un jeune peut être bénévole (ex: aide lors d'un événement) sans pour autant être membre (adhérent payant)

## Aspects spécifiques aux rôles Bénévoles Animateurs
- [ ] Les bénévoles Animateurs ont accès à la page "Gestion des événements" dans l'admin WP
    - [ ] Accès en lecture/écriture aux événements assignés à eux
- [ ] Les bénévoles Animateurs peuvent gérer les inscriptions aux événements (validation, refus) – (assignés à eux)
- [ ] Les bénévoles Animateurs peuvent publier des photos d'événements (sans modération)