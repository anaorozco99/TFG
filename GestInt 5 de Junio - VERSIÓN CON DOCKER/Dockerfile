FROM php:8.2-apache

# Extensiones necesarias para GestInt
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite para el .htaccess
RUN a2enmod rewrite headers

# Permisos para uploads
RUN mkdir -p /var/www/html/uploads/justificantes \
    && chmod -R 775 /var/www/html/uploads

# Configurar Apache para permitir .htaccess
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/gestint.conf \
    && a2enconf gestint

EXPOSE 80
