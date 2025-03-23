# Système de Parser RSS pour Portfolio

Ce système permet de récupérer et analyser automatiquement les flux RSS d'actualités Apple, puis de les stocker dans un fichier JSON pour les afficher sur votre portfolio.

## Fonctionnalités

- Récupération et analyse des flux RSS Apple (MacRumors, iMore, etc.)
- Catégorisation automatique des articles par thème (iOS, Hardware, Apps, Services)
- API REST pour accéder aux articles filtrés par catégorie
- Mise à jour automatique des articles via un cron job
- Détection des nouveaux articles et marquage avec un badge "Nouveau"
- Support multilingue (résumés traduits en français)

## Structure du projet

```
rss_parser/
├── api/
│   ├── index.php        # Point d'entrée de l'API
│   └── status.php       # Vérification du statut de l'API
├── data/                # Répertoire pour les données (créé automatiquement)
│   ├── articles.json    # Articles stockés au format JSON
│   ├── feeds.json       # Configuration des flux RSS
│   └── last_update.txt  # Horodatage de la dernière mise à jour
├── logs/                # Répertoire pour les logs (créé automatiquement)
├── RSSParser.php        # Classe principale pour l'analyse des flux RSS
├── update.php           # Script de mise à jour des flux RSS
├── cron_setup.sh        # Script pour configurer le cron job
└── README.md            # Ce fichier
```

## Prérequis

- PHP 7.4 ou supérieur
- Extensions PHP: JSON, SimpleXML, libxml
- Accès aux commandes cron (pour les mises à jour automatiques)
- Serveur web avec support PHP (Apache, Nginx, etc.)

## Installation

1. Placez le dossier `rss_parser` à la racine de votre site web.
2. Assurez-vous que les permissions sont correctement configurées:
   ```
   chmod 755 rss_parser/cron_setup.sh
   chmod 755 -R rss_parser/
   ```
3. Exécutez la première mise à jour manuellement:
   ```
   php rss_parser/update.php
   ```
4. Pour configurer les mises à jour automatiques, exécutez:
   ```
   ./rss_parser/cron_setup.sh
   ```
   et suivez les instructions.

## Utilisation de l'API

### Récupérer tous les articles

```
GET /rss_parser/api/
```

### Filtrer par catégorie

```
GET /rss_parser/api/?category=ios
GET /rss_parser/api/?category=hardware
GET /rss_parser/api/?category=apps
GET /rss_parser/api/?category=services
```

### Forcer une mise à jour

```
GET /rss_parser/api/?force_update=true
```

### Vérifier le statut

```
GET /rss_parser/api/status.php
```

## Intégration avec le portfolio

Pour intégrer les articles RSS dans votre portfolio, ajoutez le script JavaScript suivant:

```javascript
document.addEventListener('DOMContentLoaded', function() {
  // Référence aux éléments du DOM
  const articlesContainer = document.getElementById('articles-container');
  const template = document.getElementById('article-template');
  const filterButtons = document.querySelectorAll('[data-filter]');
  
  // Fonction pour récupérer les articles depuis l'API
  async function fetchArticles(category = 'tous') {
    try {
      const response = await fetch(`/rss_parser/api/?category=${category}`);
      if (!response.ok) {
        throw new Error('Erreur lors de la récupération des articles');
      }
      const data = await response.json();
      return data.articles || [];
    } catch (error) {
      console.error('Erreur:', error);
      return [];
    }
  }
  
  // Fonction pour afficher les articles
  async function renderArticles(category = 'tous') {
    // Vider le conteneur
    articlesContainer.innerHTML = '';
    
    // Récupérer les articles
    const articles = await fetchArticles(category);
    
    if (articles.length === 0) {
      const noArticlesMessage = document.createElement('div');
      noArticlesMessage.className = 'col-span-full text-center text-gray-500 dark:text-gray-400 py-8';
      noArticlesMessage.textContent = 'Aucun article ne correspond à cette catégorie.';
      articlesContainer.appendChild(noArticlesMessage);
      return;
    }
    
    // Afficher les articles
    articles.forEach(article => {
      // Cloner le template
      const articleElement = template.content.cloneNode(true);
      
      // Mettre à jour les données de l'article
      const card = articleElement.querySelector('.article-card');
      card.dataset.category = article.categories[0] || '';
      
      const dateElement = articleElement.querySelector('.date-placeholder');
      dateElement.textContent = article.date;
      
      const titleElement = articleElement.querySelector('.title-placeholder');
      titleElement.textContent = article.title;
      
      const descriptionElement = articleElement.querySelector('.description-placeholder');
      descriptionElement.textContent = article.description;
      
      const linkElement = articleElement.querySelector('.link-placeholder');
      linkElement.href = article.link;
      
      // Afficher le badge "Nouveau" si nécessaire
      const newBadge = articleElement.querySelector('.new-badge');
      if (article.isNew) {
        newBadge.classList.remove('hidden');
      }
      
      // Ajouter l'article au conteneur
      articlesContainer.appendChild(articleElement);
    });
  }
  
  // Gestionnaire d'événements pour les boutons de filtre
  filterButtons.forEach(button => {
    button.addEventListener('click', function() {
      // Mettre à jour l'apparence des boutons
      filterButtons.forEach(btn => {
        btn.classList.remove('bg-blue-500', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-800', 'dark:bg-gray-700', 'dark:text-white');
        btn.classList.remove('active-filter');
      });
      
      this.classList.remove('bg-gray-200', 'text-gray-800', 'dark:bg-gray-700', 'dark:text-white');
      this.classList.add('bg-blue-500', 'text-white', 'active-filter');
      
      // Filtrer les articles
      const filterCategory = this.dataset.filter;
      renderArticles(filterCategory);
    });
  });
  
  // Afficher tous les articles au chargement initial
  renderArticles();
});
```

## Personnalisation

### Ajouter ou modifier des flux RSS

Modifiez le fichier `data/feeds.json` pour ajouter ou supprimer des flux RSS. Le format est le suivant:

```json
[
  {
    "url": "https://example.com/feed.xml",
    "name": "Nom du flux",
    "category": "ios"
  }
]
```

### Personnaliser la catégorisation

Pour modifier les mots-clés utilisés pour la catégorisation automatique, modifiez la méthode `categorizeArticle()` dans le fichier `RSSParser.php`.

## Maintenance

Les logs sont stockés dans le répertoire `logs/`. Vérifiez-les régulièrement pour vous assurer que tout fonctionne correctement.

Pour vérifier le statut du système:

```
curl http://votre-site.com/rss_parser/api/status.php
```

## Licence

Ce projet est sous licence MIT. N'hésitez pas à le modifier selon vos besoins. 