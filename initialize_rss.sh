#!/bin/bash

# Script d'initialisation du système RSS pour Meyo.github

# Définition des couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Initialisation du système RSS pour Meyo.github ===${NC}"
echo ""

# Création des répertoires nécessaires
echo -e "${GREEN}1. Création des répertoires data et logs...${NC}"
mkdir -p rss_parser/data
mkdir -p rss_parser/logs
chmod 755 -R rss_parser/
chmod 777 rss_parser/data
chmod 777 rss_parser/logs

# Vérification des permissions
echo -e "${GREEN}2. Vérification des permissions...${NC}"
ls -la rss_parser/

# Exécution du script de mise à jour
echo -e "${GREEN}3. Première exécution du parsing RSS...${NC}"
/usr/bin/php rss_parser/update.php

# Vérification que les données ont été créées
echo -e "${GREEN}4. Vérification des fichiers créés...${NC}"
ls -la rss_parser/data/

echo ""
echo -e "${GREEN}=== Initialisation terminée ===${NC}"
echo "Le système RSS est maintenant configuré et fonctionnel."
echo "Pour tester l'API, visitez: http://meyo.github.io/rss_parser/api/"
echo ""
echo -e "${YELLOW}N'oubliez pas de configurer DNS pour rendre votre site accessible depuis l'extérieur :${NC}"
echo "IP du serveur : $(hostname -I | awk '{print $1}')" 