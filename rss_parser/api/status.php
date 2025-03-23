<?php
// Inclure la configuration
require_once __DIR__ . '/../config.php';

// Activer le CORS pour autoriser les requêtes depuis GitHub Pages
header("Access-Control-Allow-Origin: " . GITHUB_PAGES_URL);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gestion des requêtes OPTIONS (pré-flight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclure la classe RSSParser
require_once __DIR__ . '/../RSSParser.php';

// Vérifier si les fichiers nécessaires existent
$dataPath = DATA_DIR . '/articles.json';
$lastUpdatePath = DATA_DIR . '/last_update.txt';
$feedsPath = DATA_DIR . '/feeds.json';

$status = [
    "status" => "ok",
    "timestamp" => time(),
    "datetime" => date('Y-m-d H:i:s'),
    "files" => [
        "articles_json" => [
            "exists" => file_exists($dataPath),
            "size" => file_exists($dataPath) ? filesize($dataPath) : 0,
            "last_modified" => file_exists($dataPath) ? date('Y-m-d H:i:s', filemtime($dataPath)) : null
        ],
        "last_update_txt" => [
            "exists" => file_exists($lastUpdatePath),
            "last_update" => file_exists($lastUpdatePath) ? date('Y-m-d H:i:s', (int)file_get_contents($lastUpdatePath)) : null,
            "time_diff" => file_exists($lastUpdatePath) ? (time() - (int)file_get_contents($lastUpdatePath)) : null
        ],
        "feeds_json" => [
            "exists" => file_exists($feedsPath),
            "size" => file_exists($feedsPath) ? filesize($feedsPath) : 0
        ]
    ]
];

// Vérifier si tous les fichiers existent
if (!$status["files"]["articles_json"]["exists"] || 
    !$status["files"]["last_update_txt"]["exists"] || 
    !$status["files"]["feeds_json"]["exists"]) {
    $status["status"] = "warning";
    $status["message"] = "Certains fichiers nécessaires n'existent pas";
}

// Vérifier si la dernière mise à jour est trop ancienne (plus de 2 heures)
if (file_exists($lastUpdatePath)) {
    $lastUpdate = (int)file_get_contents($lastUpdatePath);
    $timeDiff = time() - $lastUpdate;
    
    if ($timeDiff > 7200) { // 2 heures
        $status["status"] = "warning";
        $status["message"] = "La dernière mise à jour date de plus de 2 heures";
    }
}

// Vérifier le nombre d'articles
if (file_exists($dataPath)) {
    $articles = json_decode(file_get_contents($dataPath), true);
    $status["articles_count"] = is_array($articles) ? count($articles) : 0;
    
    if ($status["articles_count"] === 0) {
        $status["status"] = "warning";
        $status["message"] = "Aucun article n'a été récupéré";
    }
}

// Vérifier l'espace disque disponible
$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskUsed = $diskTotal - $diskFree;
$percentUsed = round(($diskUsed / $diskTotal) * 100, 2);

$status["disk"] = [
    "free" => $diskFree,
    "total" => $diskTotal,
    "used" => $diskUsed,
    "percent_used" => $percentUsed
];

// Si l'espace disque est presque plein (plus de 90%), ajouter un avertissement
if ($percentUsed > 90) {
    $status["status"] = "warning";
    $status["message"] = "L'espace disque est presque plein ($percentUsed%)";
}

// Ajouter des informations sur l'API
$status["api"] = [
    "version" => "1.0",
    "github_pages_url" => GITHUB_PAGES_URL,
    "cache_update_interval" => CACHE_UPDATE_INTERVAL,
    "server_info" => [
        "php_version" => PHP_VERSION,
        "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
    ]
];

// Envoyer la réponse
echo json_encode($status, JSON_PRETTY_PRINT);

?> 