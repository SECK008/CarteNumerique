<?php
/**
 * Configuration du système de cartes électroniques.
 * Modifiez ces valeurs selon votre entreprise.
 */

return [
    // --- Identité de l'entreprise ---
    'company_name'  => 'Teranga Group',            // Remplacez par le nom de votre entreprise
    'accent_color'  => '#C9A227',                  // Couleur d'accent (or), format hexadécimal
    'ink_color'     => '#1B1F2A',                   // Couleur du texte principal

    // Chemin vers votre vrai logo (PNG ou JPG, idéalement carré, fond transparent si possible).
    // Laissez une chaîne vide '' pour utiliser un repère générique généré automatiquement.
    'logo_path'     => '',                          // ex: 'assets/logo.png'

    // --- Hébergement ---
    // L'adresse de base où les pages de profil seront publiées.
    // Chaque employé aura une page à base_url/<ID>.html
    // Exemple GitHub Pages : 'https://votre-compte.github.io/cartes'
    // Exemple nom de domaine : 'https://carte.votreentreprise.com'
    'base_url'      => 'https://REMPLACEZ-MOI.github.io/cartes',

    // --- Fichiers et dossiers (chemins relatifs à la racine du projet) ---
    'data_file'       => 'data/employees.xlsx',
    'photos_dir'      => 'photos',
    'template_file'   => 'templates/profile_template.html',
    'card_template_file' => 'templates/card_template.html',
    'output_dir'      => 'output',

    // --- Format de la carte physique (CR80, format carte bancaire standard) ---
    'card_width_mm'  => 85.6,
    'card_height_mm' => 54.0,
];
