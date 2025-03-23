#!/bin/sh

# Script d'exécution des mises à jour RSS sur Render
# Ce script sera exécuté régulièrement pour mettre à jour les flux RSS

echo "Démarrage de la mise à jour RSS à $(date)"
cd /usr/share/nginx/html
php rss_parser/update.php
echo "Mise à jour terminée à $(date)" 