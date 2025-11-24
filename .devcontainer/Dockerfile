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
    ca-certificates \
    gnupg \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# Install Docker CLI (compatible version)
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli=5:28.1.* docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*

# Set Docker API version to be compatible with host
ENV DOCKER_API_VERSION=1.43

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

# Create MySQL client configuration to skip SSL
RUN mkdir -p /etc/mysql/conf.d && \
    echo "[client]" > /etc/mysql/conf.d/client.cnf && \
    echo "skip-ssl" >> /etc/mysql/conf.d/client.cnf && \
    echo "host=db" >> /etc/mysql/conf.d/client.cnf && \
    echo "user=snapshots" >> /etc/mysql/conf.d/client.cnf && \
    echo "password=snapshots123" >> /etc/mysql/conf.d/client.cnf && \
    echo "database=ci_snapshots" >> /etc/mysql/conf.d/client.cnf

# Create necessary directories with proper permissions
RUN mkdir -p /workspace/files /workspace/tmp \
    && chown -R www-data:www-data /workspace

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
