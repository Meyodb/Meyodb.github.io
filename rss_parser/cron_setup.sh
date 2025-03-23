#!/bin/bash

# Script d'installation du cron job pour la mise à jour automatique des flux RSS

# Chemin absolu vers le répertoire du script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Chemin vers le script de mise à jour PHP
UPDATE_SCRIPT="$SCRIPT_DIR/update.php"

# Vérifier si le script PHP existe
if [ ! -f "$UPDATE_SCRIPT" ]; then
    echo "Erreur: Le script de mise à jour PHP n'existe pas: $UPDATE_SCRIPT"
    exit 1
fi

# Trouver le chemin vers PHP
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "Erreur: PHP n'est pas installé ou n'est pas dans le PATH"
    exit 1
fi

# Créer la commande cron
CRON_CMD="$PHP_PATH $UPDATE_SCRIPT > $SCRIPT_DIR/logs/cron_output.log 2>&1"

# Vérifier si le cron job existe déjà
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$UPDATE_SCRIPT")

if [ -n "$EXISTING_CRON" ]; then
    echo "Un cron job existe déjà pour ce script:"
    echo "$EXISTING_CRON"
    echo ""
    read -p "Voulez-vous le remplacer? (o/n): " REPLACE
    if [ "$REPLACE" != "o" ]; then
        echo "Installation annulée."
        exit 0
    fi
    
    # Supprimer l'ancien cron job
    crontab -l 2>/dev/null | grep -v -F "$UPDATE_SCRIPT" | crontab -
fi

# Demander la fréquence de mise à jour
echo "À quelle fréquence souhaitez-vous mettre à jour les flux RSS?"
echo "1) Toutes les heures"
echo "2) Toutes les 2 heures"
echo "3) Toutes les 4 heures"
echo "4) Toutes les 6 heures"
echo "5) Une fois par jour"
echo "6) Personnalisé"

read -p "Choisissez une option (1-6): " FREQUENCY

case $FREQUENCY in
    1) CRON_SCHEDULE="0 * * * *" ;;
    2) CRON_SCHEDULE="0 */2 * * *" ;;
    3) CRON_SCHEDULE="0 */4 * * *" ;;
    4) CRON_SCHEDULE="0 */6 * * *" ;;
    5) CRON_SCHEDULE="0 8 * * *" ;;
    6)
        echo "Entrez l'expression cron personnalisée (format: minute heure jour mois jour_semaine):"
        read -p "> " CRON_SCHEDULE
        ;;
    *)
        echo "Option invalide. Utilisation de la valeur par défaut (toutes les heures)."
        CRON_SCHEDULE="0 * * * *"
        ;;
esac

# Ajouter le nouveau cron job
(crontab -l 2>/dev/null; echo "$CRON_SCHEDULE $CRON_CMD") | crontab -

echo "Cron job installé avec succès!"
echo "Programme: $CRON_SCHEDULE"
echo "Commande: $CRON_CMD"
echo ""
echo "Pour vérifier l'installation, exécutez 'crontab -l'" 