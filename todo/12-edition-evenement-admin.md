# 12. Dans l'édition d'un evénement dans l'admin

- [x] permettre de choisir soit :
        - une date fixe (avec plage horaire)
        - plage de date (ex: du 10 au 15 aout)
        - soit une récurrence (tout les vendredi, chaque 1er samedi du mois, mardi une semaine sur deux)
- [x] Corriger la migration pour ajouter `schedule_mode` et `schedule_payload` aux tables existantes.
- [x] Erreur lors de l'enregistrement d'un évenement avec une récurrence : 
    Il semble que le schema de la table `wp_mj_mj_events` n'a pas été mis à jour correctement pour inclure les nouveaux champs `schedule_mode` et `schedule_payload`. Veuillez vérifier que la migration de la base de données a été exécutée avec succès et que les colonnes ont bien été ajoutées.
- [x] Ajouter atelier dans le type d'événement possible.
- [x] Ajouter un système de gestion des places limitées par évenement avec notification quand le seuil est atteint.
- [x] Ajout un boutou pour affiche le lien de paiement (si prix >0) doit se faire via stripe (comme pour la cotisation)
- [x] Aligner le formulaire d'édition avec la logique stage/atelier du widget animateur :
    - reprendre un sélecteur d'occurrence clair (stage = toutes, atelier = une seule occurrence)
    - persister le scope choisi dans `schedule_payload` et l'exploiter côté validation admin
    - faire apparaitre dans l'aperçu admin les conditions et compteurs comme sur le widget

