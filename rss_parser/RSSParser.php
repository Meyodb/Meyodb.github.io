<?php

/**
 * Classe RSSParser
 * 
 * Cette classe permet de récupérer et analyser les flux RSS d'actualités Apple,
 * puis de stocker les articles dans un fichier JSON.
 */
class RSSParser {
    // Chemins des fichiers
    private $dataFilePath;
    private $feedsFilePath;
    
    // Tableau des flux RSS à surveiller
    private $feeds = [
        [
            'url' => 'https://feeds.macrumors.com/MacRumors-All',
            'name' => 'MacRumors',
            'category' => 'all'
        ],
        [
            'url' => 'https://feeds.macrumors.com/MacRumors-iOS',
            'name' => 'MacRumors iOS',
            'category' => 'ios'
        ],
        [
            'url' => 'https://feeds.macrumors.com/MacRumors-Mac',
            'name' => 'MacRumors Mac',
            'category' => 'hardware'
        ],
        [
            'url' => 'https://www.imore.com/rss.xml',
            'name' => 'iMore',
            'category' => 'all'
        ]
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Définir les chemins des fichiers
        $this->dataFilePath = __DIR__ . '/data/articles.json';
        $this->feedsFilePath = __DIR__ . '/data/feeds.json';
        
        // Créer le répertoire de données s'il n'existe pas
        if (!file_exists(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
        
        // Créer le fichier des flux s'il n'existe pas
        if (!file_exists($this->feedsFilePath)) {
            file_put_contents($this->feedsFilePath, json_encode($this->feeds, JSON_PRETTY_PRINT));
        } else {
            // Charger la configuration des flux depuis le fichier
            $this->feeds = json_decode(file_get_contents($this->feedsFilePath), true);
        }
        
        // Créer le fichier d'articles s'il n'existe pas
        if (!file_exists($this->dataFilePath)) {
            file_put_contents($this->dataFilePath, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Fonction principale qui récupère et analyse tous les flux RSS
     */
    public function fetchAllFeeds() {
        $allArticles = [];
        
        // Récupérer les articles existants
        if (file_exists($this->dataFilePath)) {
            $allArticles = json_decode(file_get_contents($this->dataFilePath), true);
            if (!is_array($allArticles)) {
                $allArticles = [];
            }
        }
        
        // Assigner un horodatage pour identifier les nouveaux articles
        $timestamp = time();
        
        // Parcourir chaque flux RSS
        foreach ($this->feeds as $feed) {
            $articles = $this->fetchSingleFeed($feed['url'], $feed['category']);
            
            if ($articles) {
                foreach ($articles as $article) {
                    // Générer un ID unique pour chaque article
                    $id = md5($article['link']);
                    
                    // Vérifier si l'article existe déjà
                    $exists = false;
                    foreach ($allArticles as &$existingArticle) {
                        if ($existingArticle['id'] === $id) {
                            // Mettre à jour la catégorie si nécessaire
                            if (!in_array($article['category'], $existingArticle['categories'])) {
                                $existingArticle['categories'][] = $article['category'];
                            }
                            $exists = true;
                            break;
                        }
                    }
                    
                    // Ajouter l'article s'il n'existe pas
                    if (!$exists) {
                        $article['id'] = $id;
                        $article['isNew'] = true;
                        $article['timestamp'] = $timestamp;
                        $article['categories'] = [$article['category']];
                        unset($article['category']);
                        $allArticles[] = $article;
                    }
                }
            }
        }
        
        // Marquer les articles comme non nouveaux s'ils sont plus anciens que 2 jours
        $twoDaysAgo = time() - (2 * 24 * 60 * 60);
        foreach ($allArticles as &$article) {
            if (isset($article['timestamp']) && $article['timestamp'] < $twoDaysAgo) {
                $article['isNew'] = false;
            }
        }
        
        // Trier les articles par date (les plus récents d'abord)
        usort($allArticles, function($a, $b) {
            return strtotime($b['pubDate']) - strtotime($a['pubDate']);
        });
        
        // Limiter à 50 articles pour éviter que le fichier ne devienne trop volumineux
        $allArticles = array_slice($allArticles, 0, 50);
        
        // Sauvegarder les articles dans le fichier JSON
        file_put_contents($this->dataFilePath, json_encode($allArticles, JSON_PRETTY_PRINT));
        
        return $allArticles;
    }
    
    /**
     * Récupère et analyse un flux RSS spécifique
     */
    private function fetchSingleFeed($url, $category) {
        try {
            // Récupérer le contenu du flux RSS
            $content = @file_get_contents($url);
            if (!$content) {
                return null;
            }
            
            // Désactiver les erreurs libxml pour éviter les erreurs de parsing
            libxml_use_internal_errors(true);
            
            // Créer un objet SimpleXML à partir du contenu
            $xml = simplexml_load_string($content);
            if (!$xml) {
                return null;
            }
            
            $articles = [];
            
            // Analyser les articles du flux RSS
            foreach ($xml->channel->item as $item) {
                // Extraire les données de l'article
                $title = (string)$item->title;
                $link = (string)$item->link;
                $pubDate = (string)$item->pubDate;
                $description = $this->cleanDescription((string)$item->description);
                
                // Déterminer la catégorie
                $articleCategory = $this->categorizeArticle($title, $description, $category);
                
                // Formater la date
                $formattedDate = $this->formatDate($pubDate);
                
                // Ajouter l'article au tableau
                $articles[] = [
                    'title' => $title,
                    'link' => $link,
                    'pubDate' => $pubDate,
                    'date' => $formattedDate,
                    'description' => $description,
                    'category' => $articleCategory
                ];
            }
            
            return $articles;
        } catch (Exception $e) {
            // Enregistrer l'erreur
            error_log('Erreur lors de la récupération du flux RSS: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Nettoie la description de l'article
     */
    private function cleanDescription($description) {
        // Supprimer les balises HTML et les entités
        $description = strip_tags($description);
        
        // Limiter la longueur
        if (strlen($description) > 300) {
            $description = substr($description, 0, 297) . '...';
        }
        
        return $description;
    }
    
    /**
     * Formate la date de publication
     */
    private function formatDate($pubDate) {
        $timestamp = strtotime($pubDate);
        
        // Traduire les noms des mois en français
        $months = [
            'January' => 'Janvier',
            'February' => 'Février',
            'March' => 'Mars',
            'April' => 'Avril',
            'May' => 'Mai',
            'June' => 'Juin',
            'July' => 'Juillet',
            'August' => 'Août',
            'September' => 'Septembre',
            'October' => 'Octobre',
            'November' => 'Novembre',
            'December' => 'Décembre'
        ];
        
        $formattedDate = date('d F Y', $timestamp);
        
        foreach ($months as $en => $fr) {
            $formattedDate = str_replace($en, $fr, $formattedDate);
        }
        
        return $formattedDate;
    }
    
    /**
     * Catégorise un article en fonction de son contenu
     */
    private function categorizeArticle($title, $description, $defaultCategory) {
        // Mots-clés pour chaque catégorie
        $keywords = [
            'ios' => ['iOS', 'iPhone', 'iPad', 'iPadOS', 'Apple Watch', 'watchOS', 'Siri', 'App Store', 'SwiftUI'],
            'hardware' => ['Mac', 'MacBook', 'iMac', 'Mac mini', 'Mac Pro', 'MacBook Pro', 'MacBook Air', 'AirPods', 'AirTag', 'Vision Pro', 'Apple Silicon', 'M1', 'M2', 'M3', 'M4'],
            'apps' => ['App', 'application', 'logiciel', 'mise à jour', 'update', 'Safari', 'Mail', 'Photos', 'jeux', 'games', 'gaming'],
            'services' => ['Apple TV+', 'Apple Music', 'Apple Arcade', 'iCloud', 'Apple Pay', 'Apple Card', 'Apple One', 'abonnement', 'subscription']
        ];
        
        // Contenu combiné pour la recherche
        $content = $title . ' ' . $description;
        
        // Vérifier les correspondances de mots-clés
        $matchedCategories = [];
        $maxMatches = 0;
        $bestCategory = $defaultCategory;
        
        foreach ($keywords as $category => $categoryKeywords) {
            $matches = 0;
            foreach ($categoryKeywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    $matches++;
                }
            }
            
            if ($matches > 0) {
                $matchedCategories[$category] = $matches;
                if ($matches > $maxMatches) {
                    $maxMatches = $matches;
                    $bestCategory = $category;
                }
            }
        }
        
        // Si aucune correspondance, utiliser la catégorie par défaut
        return $bestCategory;
    }
    
    /**
     * Récupère tous les articles
     */
    public function getAllArticles() {
        if (file_exists($this->dataFilePath)) {
            return json_decode(file_get_contents($this->dataFilePath), true);
        }
        return [];
    }
    
    /**
     * Récupère les articles filtrés par catégorie
     */
    public function getArticlesByCategory($category) {
        $articles = $this->getAllArticles();
        
        if ($category === 'tous') {
            return $articles;
        }
        
        // Filtrer les articles par catégorie
        return array_filter($articles, function($article) use ($category) {
            return in_array($category, $article['categories']);
        });
    }
}

?> 