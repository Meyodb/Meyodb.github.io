<?php

// Inclure la classe RSSParser
require_once __DIR__ . '/RSSParser.php';

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Créer un fichier de log
$logFile = __DIR__ . '/logs/update_' . date('Y-m-d') . '.log';

// Créer le répertoire de logs s'il n'existe pas
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Fonction pour écrire dans le fichier de log
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    echo "[$timestamp] $message" . PHP_EOL;
}

try {
    writeLog("Démarrage de la mise à jour des flux RSS");
    
    // Créer une instance du parser RSS
    $parser = new RSSParser();
    
    // Récupérer les articles
    $startTime = microtime(true);
    $articles = $parser->fetchAllFeeds();
    $endTime = microtime(true);
    
    $duration = round($endTime - $startTime, 2);
    
    // Écrire les informations de mise à jour dans le log
    writeLog("Mise à jour terminée en $duration secondes");
    writeLog("Nombre d'articles récupérés: " . count($articles));
    
    // Enregistrer le timestamp de dernière mise à jour
    file_put_contents(__DIR__ . '/data/last_update.txt', time());
    
    writeLog("Mise à jour terminée avec succès");
} catch (Exception $e) {
    writeLog("Erreur lors de la mise à jour: " . $e->getMessage());
}

?> 