FROM php:8.2-apache

# Instala SQLite3 e dependências necessárias
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo_sqlite

# Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# Copia o código fonte para o container
COPY src/ /var/www/html/

# Define as permissões para a pasta do servidor e do banco
RUN mkdir -p /var/www/db && \
    chown -R www-data:www-data /var/www/html /var/www/db && \
    chmod -R 775 /var/www/html /var/www/db

EXPOSE 80
