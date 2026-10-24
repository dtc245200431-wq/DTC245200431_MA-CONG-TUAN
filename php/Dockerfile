FROM php:8.3-apache
RUN docker-php-ext-install mbstring pdo pdo_mysql && a2enmod rewrite
WORKDIR /var/www/html
COPY app/ /var/www/html/
EXPOSE 80
