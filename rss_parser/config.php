<?php
// Configuration de l'API RSS Parser

// URL du site hébergé sur GitHub Pages
define('GITHUB_PAGES_URL', 'https://meyodb.github.io');

// Intervalle de mise à jour du cache en secondes (30 minutes par défaut)
define('CACHE_UPDATE_INTERVAL', 1800);

// Chemin vers le répertoire de données
define('DATA_DIR', __DIR__ . '/data');

// Chemin vers le répertoire de logs
define('LOGS_DIR', __DIR__ . '/logs');

// Création des répertoires si nécessaires
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}
?> 