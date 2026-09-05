FROM php:8.2-apache

# IMMEDIATELY remove all default MPM modules before anything else
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* /etc/apache2/mods-enabled/mpm_prefork.*

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Setup writable session directory
RUN mkdir -p /tmp/sessions && chmod 777 /tmp/sessions

# Enable only mpm_prefork
RUN a2enmod mpm_prefork

# Enable Apache mod_rewrite
RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 8080

# Start Apache (container already listens 8080 by default)
CMD ["apache2-foreground"]
