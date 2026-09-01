# 28. Fermetures MJ par plage de dates

- [x] Mettre à jour le modèle de données des fermetures (table ou options) pour stocker une date de début et une date de fin.
- [x] Adapter les formulaires d'encodage/admin pour permettre la saisie d'une plage (sélecteurs de dates, validations cohérentes).
- [x] Ajuster la logique métier (vérification de disponibilité, blocage des réservations/événements) pour parcourir la plage complète.
- [x] Mettre à jour les affichages front/back afin de présenter clairement les fermetures étendues.
- [x] Prévoir les tests manuels : saisie de plages multi-jours, chevauchements, affichage dans les widgets et calculs liés.
	- Ajouter une fermeture sur plusieurs jours et vérifier son affichage complet dans l’admin, le widget calendrier et le flux Google Calendar.
	- Tenter d’enregistrer une fermeture qui chevauche une période existante et confirmer le blocage côté admin.
	- Saisir une fermeture d’un seul jour pour valider le comportement historique (affichage et export).
- [x] Ajouter la possibilité d'ajouter une photo pour chaque fermeture, avec affichage dans le widget calendrier et les notifications.