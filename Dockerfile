FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Setup writable session directory
RUN mkdir -p /tmp/sessions && chmod 777 /tmp/sessions

# Enable Apache mod_rewrite and configure ServerName
RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy entrypoint script that handles MPM module conflicts at runtime
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Default port fallback
ENV PORT=8080
EXPOSE 8080

# Start Apache via entrypoint script that resolves MPM conflicts at runtime
CMD ["/usr/local/bin/entrypoint.sh"]
