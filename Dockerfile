# Use official PHP image with Apache
FROM php:8.2-apache

# Install any additional PHP extensions if needed
# RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy all application files to Apache's document root
COPY . /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Enable Apache mod_rewrite (useful for clean URLs)
RUN a2enmod rewrite

# Apache runs on port 80 by default, but Render requires using $PORT
# We'll configure Apache to listen on the PORT environment variable
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Expose the port
EXPOSE ${PORT}

# Start Apache in the foreground
CMD ["apache2-foreground"]
