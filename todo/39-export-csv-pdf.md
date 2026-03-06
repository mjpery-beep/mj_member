# 39. Export CSV / PDF des données

## Objectif
Permettre aux gestionnaires d'exporter les données membres, événements, heures, paiements et présences en CSV et PDF directement depuis l'interface admin.

## Contexte
L'import CSV existe (`import_members.php`) mais l'export est absent. Les gestionnaires doivent manipuler les données via phpMyAdmin ou des requêtes manuelles.

## Fonctionnalités
- [ ] Export CSV des membres (filtré par statut, cotisation, période d'inscription)
- [ ] Export CSV des inscriptions à un événement
- [ ] Export CSV des heures encodées (par membre, projet, période)
- [ ] Export CSV des paiements (par statut, période)
- [ ] Export PDF liste de présence vierge pour un événement (nom + signature)
- [ ] Export PDF récapitulatif mensuel des heures par membre
- [ ] Export PDF carte de membre individuelle (existant partiel dans `cards_pdf_admin.php`)
- [ ] Boutons d'export intégrés dans chaque page admin concernée

## Architecture
- Endpoints AJAX admin dédiés (un par type d'export)
- Classe utilitaire `includes/classes/ExportManager.php` centralisant la génération
- CSV : `fputcsv` avec BOM UTF-8 pour compatibilité Excel
- PDF : librairie TCPDF ou FPDF (déjà en usage pour les cartes ?)
- Headers HTTP pour forcer le download

## Tâches techniques
- [ ] Classe `ExportManager` avec méthodes par domaine
- [ ] Endpoints AJAX dans `includes/core/ajax/admin/exports.php`
- [ ] Boutons UI dans les pages admin existantes
- [ ] Gestion des capabilities (`Config::capability()`)
- [ ] Templates PDF pour chaque type de document
