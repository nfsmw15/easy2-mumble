FROM php:8.2-apache

# PHP-Erweiterungen
RUN apt-get update -qq && apt-get install -y -qq \
        libpng-dev libjpeg-dev libfreetype6-dev \
        default-mysql-client git curl unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache konfigurieren
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

WORKDIR /var/www/html

# Easy2-PHP8 (main-dashboard) klonen
RUN git clone --depth=1 --branch main-dashboard \
    https://github.com/nfsmw15/Easy2-PHP8.git /var/www/html \
    && cp -r /var/www/html/install/sql /docker-init/easy2-sql \
    && rm -rf /var/www/html/.git

# easy2-mumble Dateien einspielen
COPY system/classes/mumble.php       system/classes/mumble.php
COPY system/classes/mumble_agent.php system/classes/mumble_agent.php
COPY system/js/mumble-edit.js        system/js/mumble-edit.js
COPY templates/mumble/               templates/mumble/
COPY sql/                            /docker-init/sql/
RUN mkdir -p /docker-init/easy2-sql

# Snippets für classes.run.user.php und run.user.php
COPY user-snippets/                  /docker-init/snippets/

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Berechtigungen
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 0644 {} \; \
    && find /var/www/html -type d -exec chmod 0755 {} \;

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
