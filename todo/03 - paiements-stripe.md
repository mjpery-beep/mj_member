# 3. Paiements Stripe

- [x] Rediriger l'utilisateur vers `https://www.mj-pery.be/inscit` apres un paiement Stripe reussi.
    - [x] Ajouter option dans les settings pour personnaliser l'URL de redirection (après paiement).
    - [x] Tester le flux en mode test et production.
- [x] Harmoniser les URLs de retour en cas d'annulation (page informative dediee).
- [x] Journaliser les evenements Stripe (success, cancel, webhook) pour faciliter le support.
- [x] Je veux un widget elementor success après paiement qui affiche un message personnalisé et le récapitulatif de la transaction (montant, date, etc). 
    -> https://www.mj-pery.be/inscrit/?stripe_success=1&session_id=cs_test_a16EvEP0ZQlKQfeCC7FIh0XlSZlHD9FvaL5ADEmwm6cth7Bkju8KJLUmhG&mj_event_id=27&mj_registration_id=23
    Si mj_event_id sont présents dans l'url on affiche le récapitulatif de la transaction (Nom de l'event).
    si c'est une cotisation on affiche le récapitulatif de la cotisation.
- [x] Dans l'admin je ne vois pas dans l'historique des paiements les paiements effectués via stripe pour les événements. Je veux voir dans l'historique des paiements les paiements effectués via stripe pour les événements.
    - [x] Ajouter un filtre pour voir uniquement les paiements d'événements.
- [x] Quand on paie pour une ou plusieurs occurrences le montant doit être multiplé par le nombre d'occurrences sélectionnées. On doit voir dans l'admin/animateur-widget le détail du paiement avec le nombre d'occurrences payées.
- [ ] Au moment de payer on doit pouvoir choisir entre un paiement en ligne (stripe) ou un paiement en espèce (à un animateur) - dans l'email de confirmation d'inscription que le membre recois il y a le lien de paiement.