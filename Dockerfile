FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required PHP extensions (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files to the Apache document root
COPY . /var/www/html/

# If config.php doesn't exist, use the example config (for cloud deployments)
RUN if [ ! -f /var/www/html/config/config.php ]; then \
        cp /var/www/html/config/config.example.php /var/www/html/config/config.php; \
    fi

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Expose the port (Render provides the PORT environment variable)
EXPOSE 80

# Configure Apache to listen on the port provided by Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/uploads/
