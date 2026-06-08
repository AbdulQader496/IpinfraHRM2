FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/uploads/profiles \
    /var/www/html/uploads/gallery \
    /var/www/html/uploads/documents \
    /var/www/html/uploads/employee_documents \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
