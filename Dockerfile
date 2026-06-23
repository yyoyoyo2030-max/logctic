FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required PHP extensions (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files to the Apache document root
COPY . /var/www/html/

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/uploads/

# Make entrypoint executable
RUN chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80

# Use entrypoint script to configure at runtime
CMD ["/var/www/html/docker-entrypoint.sh"]
