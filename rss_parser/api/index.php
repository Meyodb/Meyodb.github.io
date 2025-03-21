<?php

// Activer le CORS pour autoriser les requêtes depuis le domaine du portfolio
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclure la classe RSSParser
require_once __DIR__ . '/../RSSParser.php';

// Fonction pour envoyer une réponse JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Gérer seulement les requêtes GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(["error" => "Méthode non autorisée"], 405);
}

// Créer une instance du parser RSS
$parser = new RSSParser();

// Récupérer le paramètre de catégorie depuis l'URL
$category = isset($_GET['category']) ? $_GET['category'] : 'tous';

// Vérifier si la catégorie est valide
$validCategories = ['tous', 'ios', 'hardware', 'apps', 'services'];
if (!in_array($category, $validCategories)) {
    sendResponse(["error" => "Catégorie invalide"], 400);
}

try {
    // Vérifier si nous devons forcer une mise à jour des flux
    $forceUpdate = isset($_GET['force_update']) && $_GET['force_update'] === 'true';
    
    if ($forceUpdate) {
        // Mise à jour forcée des flux
        $articles = $parser->fetchAllFeeds();
    } else {
        // Vérifier quand a eu lieu la dernière mise à jour
        $lastUpdateFile = __DIR__ . '/../data/last_update.txt';
        $currentTime = time();
        $lastUpdateTime = file_exists($lastUpdateFile) ? (int)file_get_contents($lastUpdateFile) : 0;
        $timeDiff = $currentTime - $lastUpdateTime;
        
        // Si la dernière mise à jour date de plus de 30 minutes, mettre à jour les flux
        if ($timeDiff > 1800) {
            $articles = $parser->fetchAllFeeds();
            file_put_contents($lastUpdateFile, $currentTime);
        } else {
            // Sinon, récupérer les articles existants
            $articles = $parser->getAllArticles();
        }
    }
    
    // Filtrer les articles par catégorie
    if ($category !== 'tous') {
        $articles = $parser->getArticlesByCategory($category);
    }
    
    // Ajouter des informations sur la réponse
    $response = [
        "status" => "success",
        "count" => count($articles),
        "category" => $category,
        "last_update" => date('Y-m-d H:i:s', file_exists($lastUpdateFile) ? (int)file_get_contents($lastUpdateFile) : time()),
        "articles" => $articles
    ];
    
    // Envoyer la réponse
    sendResponse($response);
    
} catch (Exception $e) {
    // En cas d'erreur, envoyer un message d'erreur
    sendResponse(["error" => "Erreur lors de la récupération des articles: " . $e->getMessage()], 500);
}

?> 