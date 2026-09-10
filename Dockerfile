FROM php:8.2-cli
RUN docker-php-ext-install mysqli pdo_mysql opcache \
 && { echo 'opcache.enable_cli=1'; echo 'opcache.memory_consumption=128'; echo 'opcache.max_accelerated_files=10000'; echo 'opcache.validate_timestamps=1'; echo 'opcache.revalidate_freq=60'; } > /usr/local/etc/php/conf.d/opcache.ini
WORKDIR /srv/app
COPY . .
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} router.php"]
