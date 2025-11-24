FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    gettext \
    locales \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Generate locales
RUN echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli zip intl gettext

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /workspace

# Create necessary directories with proper permissions
RUN mkdir -p /workspace/files /workspace/tmp \
    && chown -R www-data:www-data /workspace

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
