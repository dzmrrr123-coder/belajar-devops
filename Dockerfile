FROM php:8.2-apache

# mod_php requires prefork: wipe all MPM symlinks first, enable prefork only
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* /etc/apache2/mods-enabled/mpm_prefork.* && a2enmod mpm_prefork

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Setup writable session directory
RUN mkdir -p /tmp/sessions && chmod 777 /tmp/sessions

# Enable Apache mod_rewrite and configure ServerName
RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Default port fallback
ENV PORT=8080
EXPOSE 8080

# Bind Apache to Railway's $PORT at runtime, then start
CMD ["sh", "-c", "sed -i \"s/Listen [0-9]*/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
