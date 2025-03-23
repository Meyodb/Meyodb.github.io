/**
 * RSSViewer - Classe pour gérer l'affichage des flux RSS
 * 
 * Cette classe permet de récupérer, catégoriser et afficher des articles
 * à partir de différents flux RSS.
 */
class RSSViewer {
    /**
     * Initialise le visualiseur RSS
     * @param {Object} options - Options de configuration
     * @param {string} options.apiKey - Clé API pour le service de proxy RSS (optionnel)
     * @param {HTMLElement} options.container - Élément HTML où afficher les articles
     * @param {number} options.updateInterval - Intervalle de mise à jour en millisecondes (par défaut: 3600000 - 1 heure)
     * @param {number} options.maxArticles - Nombre maximum d'articles à afficher (par défaut: 20)
     */
    constructor(options = {}) {
        // Configuration par défaut
        this.apiKey = options.apiKey || '';
        this.container = options.container;
        this.updateInterval = options.updateInterval || 3600000;
        this.maxArticles = options.maxArticles || 20;
        
        // Service RSS2JSON comme proxy CORS
        this.proxyUrl = 'https://api.rss2json.com/v1/api.json?rss_url=';
        
        // État interne
        this.articles = [];
        this.categories = new Set(['tous']);
        this.selectedCategory = 'tous';
        this.isLoading = false;
        
        // Charger les articles depuis le stockage local
        this.loadFromLocalStorage();
        
        // Initialiser l'interface utilisateur
        this.initUI();
        
        // Récupérer les articles au chargement de la page
        this.fetchAllFeeds();
        
        // Configurer la mise à jour automatique
        if (this.updateInterval > 0) {
            setInterval(() => this.fetchAllFeeds(), this.updateInterval);
        }
    }
    
    /**
     * Initialise l'interface utilisateur
     */
    initUI() {
        if (!this.container) return;
        
        // Créer le conteneur des filtres
        const filterContainer = document.createElement('div');
        filterContainer.className = 'flex flex-wrap justify-center gap-3 mb-8';
        this.filterContainer = filterContainer;
        
        // Créer le conteneur des articles
        const articlesGrid = document.createElement('div');
        articlesGrid.className = 'articles-grid';
        this.articlesGrid = articlesGrid;
        
        // Créer le bouton de mise à jour
        const updateContainer = document.createElement('div');
        updateContainer.className = 'mt-6 text-center';
        
        const updateButton = document.createElement('button');
        updateButton.className = 'px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors';
        updateButton.textContent = 'Mettre à jour les articles';
        updateButton.addEventListener('click', () => this.fetchAllFeeds());
        
        // Créer l'indicateur de dernière mise à jour
        const lastUpdateInfo = document.createElement('div');
        lastUpdateInfo.className = 'text-sm text-gray-600 dark:text-gray-400 mt-2';
        this.lastUpdateInfo = lastUpdateInfo;
        
        // Assembler l'interface
        updateContainer.appendChild(updateButton);
        updateContainer.appendChild(lastUpdateInfo);
        
        this.container.appendChild(filterContainer);
        this.container.appendChild(articlesGrid);
        this.container.appendChild(updateContainer);
        
        // Mettre à jour les filtres
        this.updateFilters();
    }
    
    /**
     * Met à jour les boutons de filtre
     */
    updateFilters() {
        if (!this.filterContainer) return;
        
        // Vider les filtres existants
        this.filterContainer.innerHTML = '';
        
        // Créer les boutons de filtre pour chaque catégorie
        this.categories.forEach(category => {
            const button = document.createElement('button');
            button.className = `px-4 py-2 rounded-lg transition-colors ${
                category === this.selectedCategory 
                ? 'bg-blue-500 text-white' 
                : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-white'
            }`;
            button.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            button.addEventListener('click', () => {
                this.selectedCategory = category;
                this.updateFilters();
                this.renderArticles();
            });
            
            this.filterContainer.appendChild(button);
        });
    }
    
    /**
     * Récupère tous les flux RSS configurés
     */
    async fetchAllFeeds() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.updateLoadingState(true);
        
        try {
            // Liste des flux RSS à récupérer
            const feeds = [
                { url: "https://www.macrumors.com/macrumors.xml", category: "apple" },
                { url: "https://www.theverge.com/apple/rss/index.xml", category: "apple" },
                { url: "https://9to5mac.com/feed/", category: "apple" },
                { url: "https://appleinsider.com/rss/news/", category: "apple" },
                { url: "https://www.theverge.com/rss/index.xml", category: "tech" },
                { url: "https://techcrunch.com/feed/", category: "tech" },
                { url: "https://www.wired.com/feed/rss", category: "tech" },
                { url: "https://feeds.arstechnica.com/arstechnica/index", category: "tech" }
            ];
            
            // Récupérer tous les flux en parallèle
            const promises = feeds.map(feed => this.fetchFeed(feed.url, feed.category));
            const results = await Promise.allSettled(promises);
            
            // Traiter les résultats
            let newArticles = [];
            results.forEach(result => {
                if (result.status === 'fulfilled') {
                    newArticles = [...newArticles, ...result.value];
                }
            });
            
            // Trier les articles par date (du plus récent au plus ancien)
            newArticles.sort((a, b) => new Date(b.pubDate) - new Date(a.pubDate));
            
            // Limiter le nombre d'articles
            newArticles = newArticles.slice(0, this.maxArticles);
            
            // Mettre à jour les articles
            this.articles = newArticles;
            
            // Sauvegarder dans le stockage local
            this.saveToLocalStorage();
            
            // Mettre à jour l'interface utilisateur
            this.updateUI();
        } catch (error) {
            console.error('Erreur lors de la récupération des flux RSS:', error);
        } finally {
            this.isLoading = false;
            this.updateLoadingState(false);
        }
    }
    
    /**
     * Récupère un flux RSS
     * @param {string} url - URL du flux RSS
     * @param {string} defaultCategory - Catégorie par défaut pour les articles de ce flux
     * @returns {Promise<Array>} - Articles traités
     */
    async fetchFeed(url, defaultCategory) {
        try {
            // Construire l'URL du proxy
            let proxyUrl = this.proxyUrl + encodeURIComponent(url);
            if (this.apiKey) {
                proxyUrl += `&api_key=${this.apiKey}`;
            }
            
            // Récupérer le flux RSS
            const response = await fetch(proxyUrl);
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Vérifier si le flux a été récupéré avec succès
            if (data.status !== 'ok') {
                throw new Error(data.message || 'Erreur inconnue');
            }
            
            // Traiter les articles
            return data.items.map(item => this.processArticle(item, defaultCategory));
        } catch (error) {
            console.error(`Erreur lors de la récupération du flux ${url}:`, error);
            return [];
        }
    }
    
    /**
     * Traite un article pour le standardiser
     * @param {Object} item - Article brut
     * @param {string} defaultCategory - Catégorie par défaut
     * @returns {Object} - Article traité
     */
    processArticle(item, defaultCategory) {
        // Extraire les catégories de l'article
        let categories = [...(item.categories || [])];
        
        // Ajouter la catégorie par défaut si elle n'existe pas
        if (!categories.includes(defaultCategory)) {
            categories.push(defaultCategory);
        }
        
        // Analyser le contenu de l'article pour détecter des mots-clés
        const content = (item.content || item.description || '').toLowerCase();
        const title = (item.title || '').toLowerCase();
        
        // Mots-clés pour catégoriser les articles
        const keywords = {
            'ios': ['ios', 'iphone', 'ipad', 'ipados', 'siri', 'app store'],
            'macos': ['macos', 'mac', 'macbook', 'imac', 'mac studio', 'mac mini', 'mac pro'],
            'hardware': ['hardware', 'apple silicon', 'puce m', 'puce a', 'processeur', 'écran', 'vision pro', 'airpods'],
            'développement': ['développement', 'swift', 'swiftui', 'xcode', 'api', 'sdk', 'wwdc', 'développeur'],
            'intelligence artificielle': ['ia', 'intelligence artificielle', 'ai', 'machine learning', 'ml', 'apple intelligence'],
            'sécurité': ['sécurité', 'confidentialité', 'privacy', 'chiffrement', 'end-to-end']
        };
        
        // Détecter les catégories en fonction des mots-clés
        for (const [category, words] of Object.entries(keywords)) {
            if (words.some(word => title.includes(word) || content.includes(word))) {
                categories.push(category);
                
                // Ajouter la catégorie à l'ensemble des catégories disponibles
                this.categories.add(category);
            }
        }
        
        // Ajouter toutes les catégories à l'ensemble
        categories.forEach(category => this.categories.add(category.toLowerCase()));
        
        // Standardiser l'article
        return {
            id: item.guid || item.link,
            title: item.title,
            link: item.link,
            description: item.description || this.extractDescription(item.content),
            pubDate: item.pubDate,
            categories: categories.map(c => c.toLowerCase()),
            thumbnail: item.thumbnail || this.extractThumbnail(item.content),
            source: item.author || new URL(item.link).hostname
        };
    }
    
    /**
     * Extrait une description à partir du contenu HTML
     * @param {string} content - Contenu HTML
     * @returns {string} - Description extraite
     */
    extractDescription(content) {
        if (!content) return '';
        
        // Créer un élément temporaire pour manipuler le HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = content;
        
        // Supprimer les scripts et les styles
        const scripts = tempDiv.querySelectorAll('script, style');
        scripts.forEach(el => el.remove());
        
        // Retourner le texte brut
        let text = tempDiv.textContent || tempDiv.innerText || '';
        
        // Limiter la taille
        return text.trim().substring(0, 200) + (text.length > 200 ? '...' : '');
    }
    
    /**
     * Extrait une image miniature à partir du contenu HTML
     * @param {string} content - Contenu HTML
     * @returns {string} - URL de l'image
     */
    extractThumbnail(content) {
        if (!content) return '';
        
        // Créer un élément temporaire pour manipuler le HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = content;
        
        // Rechercher la première image
        const img = tempDiv.querySelector('img');
        return img ? img.src : '';
    }
    
    /**
     * Met à jour l'interface utilisateur
     */
    updateUI() {
        this.updateFilters();
        this.renderArticles();
        this.updateLastUpdateInfo();
    }
    
    /**
     * Affiche les articles filtrés
     */
    renderArticles() {
        if (!this.articlesGrid) return;
        
        // Vider la grille
        this.articlesGrid.innerHTML = '';
        
        // Filtrer les articles par catégorie
        const filteredArticles = this.selectedCategory === 'tous' 
            ? this.articles 
            : this.articles.filter(article => 
                article.categories.includes(this.selectedCategory)
            );
        
        // Afficher un message si aucun article n'a été trouvé
        if (filteredArticles.length === 0) {
            const noArticlesMessage = document.createElement('div');
            noArticlesMessage.className = 'col-span-full text-center text-gray-500 dark:text-gray-400 py-8';
            noArticlesMessage.textContent = 'Aucun article ne correspond à cette catégorie.';
            this.articlesGrid.appendChild(noArticlesMessage);
            return;
        }
        
        // Créer une carte pour chaque article
        filteredArticles.forEach(article => {
            const card = document.createElement('div');
            card.className = 'article-card';
            
            // Formater la date
            const pubDate = new Date(article.pubDate);
            const formattedDate = pubDate.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            
            // Vérifier si l'article est récent (moins de 24 heures)
            const isNew = (Date.now() - pubDate.getTime()) < 24 * 60 * 60 * 1000;
            
            // Construire le HTML de la carte
            card.innerHTML = `
                <div class="p-6">
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-gray-500 text-sm dark:text-gray-400">${formattedDate}</p>
                        ${isNew ? '<span class="px-2 py-1 text-xs rounded-full bg-green-500 text-white">Nouveau</span>' : ''}
                    </div>
                    <h4 class="font-bold text-lg mb-3 dark:text-white">${article.title}</h4>
                    <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3">${article.description}</p>
                    <a href="${article.link}" target="_blank" class="text-blue-500 hover:underline inline-flex items-center">
                        Lire l'article
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </a>
                </div>
            `;
            
            // Ajouter la carte à la grille
            this.articlesGrid.appendChild(card);
        });
    }
    
    /**
     * Met à jour l'info de dernière mise à jour
     */
    updateLastUpdateInfo() {
        if (!this.lastUpdateInfo) return;
        
        const now = new Date();
        const formattedDate = now.toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        this.lastUpdateInfo.textContent = `Dernière mise à jour : ${formattedDate}`;
    }
    
    /**
     * Met à jour l'état de chargement
     * @param {boolean} isLoading - État de chargement
     */
    updateLoadingState(isLoading) {
        if (!this.lastUpdateInfo) return;
        
        if (isLoading) {
            this.lastUpdateInfo.textContent = 'Chargement des articles...';
        } else {
            this.updateLastUpdateInfo();
        }
    }
    
    /**
     * Sauvegarde les articles dans le stockage local
     */
    saveToLocalStorage() {
        try {
            const data = {
                articles: this.articles,
                categories: Array.from(this.categories),
                lastUpdate: new Date().toISOString()
            };
            
            localStorage.setItem('rss-viewer-data', JSON.stringify(data));
        } catch (error) {
            console.error('Erreur lors de la sauvegarde dans le stockage local:', error);
        }
    }
    
    /**
     * Charge les articles depuis le stockage local
     */
    loadFromLocalStorage() {
        try {
            const data = localStorage.getItem('rss-viewer-data');
            if (data) {
                const parsedData = JSON.parse(data);
                
                this.articles = parsedData.articles || [];
                this.categories = new Set(parsedData.categories || ['tous']);
                
                // Vérifier si les données sont trop anciennes (plus de 24 heures)
                const lastUpdate = new Date(parsedData.lastUpdate);
                const isDataOld = (Date.now() - lastUpdate.getTime()) > 24 * 60 * 60 * 1000;
                
                if (isDataOld) {
                    // Les données sont trop anciennes, on va les rafraîchir
                    this.fetchAllFeeds();
                } else {
                    // Les données sont assez récentes, on les utilise
                    this.updateUI();
                }
            }
        } catch (error) {
            console.error('Erreur lors du chargement depuis le stockage local:', error);
        }
    }
} 