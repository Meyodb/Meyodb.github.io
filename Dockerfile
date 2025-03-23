FROM nginx:alpine

COPY . /usr/share/nginx/html

# Configuration pour PHP
RUN apk add --no-cache php8 php8-fpm php8-json php8-xml php8-curl php8-mbstring php8-phar php8-openssl

# Copier la configuration Nginx personnalisée
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Exposer le port 80
EXPOSE 80

# Commande pour démarrer PHP-FPM et Nginx
CMD sh -c "php-fpm8 -D && nginx -g 'daemon off;'" 