<?php
/**
 * Migration script to add event_id column to mj_member_notes table
 * Run this file once from WordPress admin or CLI
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Check admin permissions
if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

global $wpdb;
$table = $wpdb->prefix . 'mj_member_notes';

echo "<h1>Migration: Ajout de event_id à la table des notes</h1>";

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
if (!$table_exists) {
    echo "<p style='color:red;'>Erreur: La table {$table} n'existe pas.</p>";
    die();
}

echo "<p>Table trouvée: <strong>{$table}</strong></p>";

// Check if event_id column already exists
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'event_id'");

if (!empty($column_exists)) {
    echo "<p style='color:green;'>✓ La colonne 'event_id' existe déjà.</p>";
} else {
    echo "<p>Ajout de la colonne 'event_id'...</p>";
    
    // Add event_id column
    $result1 = $wpdb->query("ALTER TABLE {$table} ADD COLUMN event_id bigint(20) unsigned DEFAULT NULL AFTER author_id");
    
    if ($result1 === false) {
        echo "<p style='color:red;'>✗ Erreur lors de l'ajout de la colonne: " . $wpdb->last_error . "</p>";
    } else {
        echo "<p style='color:green;'>✓ Colonne 'event_id' ajoutée avec succès.</p>";
        
        // Add index
        $result2 = $wpdb->query("ALTER TABLE {$table} ADD KEY event_id (event_id)");
        
        if ($result2 === false) {
            echo "<p style='color:orange;'>⚠ Index non ajouté (peut déjà exister): " . $wpdb->last_error . "</p>";
        } else {
            echo "<p style='color:green;'>✓ Index ajouté sur 'event_id'.</p>";
        }
    }
}

// Show current table structure
echo "<h2>Structure actuelle de la table:</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . esc_html($column->Field) . "</td>";
    echo "<td>" . esc_html($column->Type) . "</td>";
    echo "<td>" . esc_html($column->Null) . "</td>";
    echo "<td>" . esc_html($column->Key) . "</td>";
    echo "<td>" . esc_html($column->Default ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>✓ Migration terminée</h2>";
echo "<p><strong>Note:</strong> Vous pouvez maintenant supprimer ce fichier (migrate-notes-event-id.php) pour des raisons de sécurité.</p>";
