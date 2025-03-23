#!/bin/bash

# Script de configuration pour le serveur PHP hébergeant l'API RSS

# Codes couleur pour un affichage plus clair
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Fonction pour afficher des messages d'erreur et quitter
error_exit() {
    echo -e "${RED}Erreur: $1${NC}" >&2
    exit 1
}

# Vérification que le script est exécuté en tant que root ou avec sudo
if [ "$EUID" -ne 0 ]; then
    error_exit "Ce script doit être exécuté en tant que root ou avec sudo."
fi

echo -e "${GREEN}=== Configuration du serveur PHP pour l'API RSS ===${NC}"

# 1. Vérification que PHP est installé
echo -e "${GREEN}1. Vérification de l'installation de PHP...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${YELLOW}PHP n'est pas installé. Installation en cours...${NC}"
    apt-get update && apt-get install -y php php-xml php-curl
else
    echo "PHP est déjà installé."
fi

# 2. Vérification des modules PHP nécessaires
echo -e "${GREEN}2. Vérification des modules PHP...${NC}"
required_modules=("xml" "curl" "json")
for module in "${required_modules[@]}"; do
    if ! php -m | grep -q "$module"; then
        echo -e "${YELLOW}Installation du module PHP $module...${NC}"
        apt-get install -y php-$module
    else
        echo "Module PHP $module déjà installé."
    fi
done

# 3. Créer un virtual host pour l'API RSS
echo -e "${GREEN}3. Configuration du virtual host pour l'API...${NC}"

# Détecter le serveur web utilisé
if command -v apache2 &> /dev/null; then
    # Configuration pour Apache
    echo "Apache détecté, création du virtual host..."
    
    # Créer le fichier de configuration
    cat > /etc/apache2/sites-available/rss-api.conf << EOF
<VirtualHost *:80>
    ServerName api.meyo.github.io
    DocumentRoot $(pwd)/rss_parser
    
    <Directory $(pwd)/rss_parser>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Protection supplémentaire pour les répertoires sensibles
        <DirectoryMatch "^$(pwd)/rss_parser/(data|logs)">
            Require all denied
        </DirectoryMatch>
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/rss-api-error.log
    CustomLog \${APACHE_LOG_DIR}/rss-api-access.log combined
</VirtualHost>
EOF
    
    # Activer le module rewrite
    a2enmod rewrite headers
    
    # Activer le site
    a2ensite rss-api.conf
    
    # Redémarrer Apache
    systemctl restart apache2
    
elif command -v nginx &> /dev/null; then
    # Configuration pour Nginx
    echo "Nginx détecté, création du virtual host..."
    
    # Créer le fichier de configuration
    cat > /etc/nginx/sites-available/rss-api << EOF
server {
    listen 80;
    server_name api.meyo.github.io;
    root $(pwd)/rss_parser;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~ ^/(data|logs)/ {
        deny all;
    }
    
    # Configuration CORS
    add_header Access-Control-Allow-Origin "https://meyodb.github.io";
    add_header Access-Control-Allow-Methods "GET, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With";
}
EOF
    
    # Créer un lien symbolique
    ln -s /etc/nginx/sites-available/rss-api /etc/nginx/sites-enabled/
    
    # Redémarrer Nginx
    systemctl restart nginx
    
else
    echo -e "${YELLOW}Aucun serveur web (Apache/Nginx) n'a été détecté.${NC}"
    echo "Vous devrez configurer manuellement votre serveur web pour l'API RSS."
fi

# 4. Configuration du cron job pour la mise à jour automatique
echo -e "${GREEN}4. Configuration du cron job...${NC}"

# Créer un fichier temporaire pour le cron
TEMP_CRON=$(mktemp)

# Lire le crontab actuel
crontab -l > "$TEMP_CRON" 2>/dev/null || echo "" > "$TEMP_CRON"

# Vérifier si l'entrée cron existe déjà
if ! grep -q "rss_parser/update.php" "$TEMP_CRON"; then
    # Ajouter une tâche cron pour exécuter update.php toutes les 30 minutes
    echo "*/30 * * * * php $(pwd)/rss_parser/update.php >> $(pwd)/rss_parser/logs/cron.log 2>&1" >> "$TEMP_CRON"
    
    # Installer le nouveau crontab
    crontab "$TEMP_CRON"
    echo "Cron job configuré pour s'exécuter toutes les 30 minutes."
else
    echo "Le cron job existe déjà."
fi

# Supprimer le fichier temporaire
rm "$TEMP_CRON"

# 5. Initialisation du système RSS
echo -e "${GREEN}5. Initialisation du système RSS...${NC}"

# S'assurer que les répertoires de données et de logs existent et ont les bonnes permissions
mkdir -p rss_parser/data
mkdir -p rss_parser/logs
chmod -R 755 rss_parser/
chmod 777 rss_parser/data
chmod 777 rss_parser/logs

# Exécuter la première mise à jour des flux RSS
php rss_parser/update.php

echo -e "${GREEN}=== Configuration terminée ===${NC}"
echo -e "L'API RSS est maintenant configurée et accessible à l'URL suivante:"
echo -e "${YELLOW}http://api.meyo.github.io${NC}"
echo ""
echo -e "Pour tester l'API, visitez:"
echo -e "${YELLOW}http://api.meyo.github.io/api/${NC}"
echo ""
echo -e "Pour modifier la configuration GitHub Pages, éditez le fichier rss_parser/config.php"
echo ""
echo -e "${GREEN}N'oubliez pas de modifier votre fichier index.html pour pointer vers le nouveau serveur API !${NC}" 