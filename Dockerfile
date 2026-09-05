FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Setup writable session directory
RUN mkdir -p /tmp/sessions && chmod 777 /tmp/sessions

# mod_php requires prefork: disable any other MPM, enable prefork only
# Disable ALL MPM modules first, then enable ONLY prefork
# This prevents "More than one MPM loaded" error
RUN a2dismod mpm_prefork mpm_event mpm_worker 2>/dev/null || true
# Remove ALL MPM module symlinks and configs completely
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-available/mpm_event.load /etc/apache2/mods-available/mpm_worker.load
# Now enable only prefork
RUN a2enmod mpm_prefork

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

# Configure dynamic port binding and start Apache
CMD ["sh", "-c", "sed -i \"s/Listen [0-9]*/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
