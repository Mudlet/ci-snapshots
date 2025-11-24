#!/bin/bash
set -e

echo "Setting up Mudlet CI Snapshots development environment..."

# Create necessary directories
mkdir -p /workspace/files /workspace/tmp

# Set proper permissions (only if possible)
if command -v sudo >/dev/null 2>&1; then
    sudo chown -R www-data:www-data /workspace/files /workspace/tmp || echo "Warning: chown via sudo failed."
    sudo chmod -R 775 /workspace/files /workspace/tmp || echo "Warning: chmod via sudo failed."
else
    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data /workspace/files /workspace/tmp || echo "Warning: chown failed."
        chmod -R 775 /workspace/files /workspace/tmp || echo "Warning: chmod failed."
    else
        echo "Notice: Skipping permission adjustments (no sudo and not root)."
    fi
fi

# Create config.php from example if it doesn't exist
if [ ! -f /workspace/config.php ]; then
    echo "Creating config.php from example..."
    cp /workspace/config.example.php /workspace/config.php
    
    # Update database configuration
    sed -i "s/define('DB_HOST', '');/define('DB_HOST', 'db');/" /workspace/config.php
    sed -i "s/define('DB_NAME', '');/define('DB_NAME', 'ci_snapshots');/" /workspace/config.php
    sed -i "s/define('DB_USER', '');/define('DB_USER', 'snapshots');/" /workspace/config.php
    sed -i "s/define('DB_PASS', '');/define('DB_PASS', 'snapshots123');/" /workspace/config.php
    
    # Update site URL for local development
    sed -i "s|define('SITE_URL', 'https://make.mudlet.org/snapshots/');|define('SITE_URL', 'http://localhost:8080/');|" /workspace/config.php
    
    echo "config.php created and configured for development."
fi

# Create ip_list from example if it doesn't exist
if [ ! -f /workspace/ip_list ]; then
    echo "Creating ip_list from example..."
    cp /workspace/ip_list.example /workspace/ip_list
    echo "ip_list created."
fi

# Add local development IPs to ip_list if not already present
if ! grep -q "127.0.0.1" /workspace/ip_list; then
    echo "Adding local development IPs to ip_list..."
    cat >> /workspace/ip_list << 'IPLIST'

# Local Development IPs
127.0.0.1	1	# Localhost
::1	1	# IPv6 localhost
172.18.0.0/16	1	# Docker network range
IPLIST
    echo "Local development IPs added to ip_list."
fi

# Create .htaccess from example if it doesn't exist and example exists
if [ ! -f /workspace/.htaccess ]; then
    if [ -f /workspace/.htaccess.example ]; then
        echo "Creating .htaccess from example..."
        cp /workspace/.htaccess.example /workspace/.htaccess
        # Update RewriteBase for root document
        sed -i 's|RewriteBase /snapshots/|RewriteBase /|' /workspace/.htaccess
        # Update all rewrite rules to point to root
        sed -i 's|/snapshots/|/|g' /workspace/.htaccess
        echo ".htaccess created and configured for development."
    else
        echo "Notice: .htaccess.example not found; skipping .htaccess creation."
    fi
fi

# Configure Apache for PUT requests and RewriteMap
echo "Configuring Apache for PUT requests and RewriteMap..."

# Enable required Apache modules
if command -v a2enmod >/dev/null 2>&1; then
    a2enmod actions 2>/dev/null || echo "actions module already enabled or unavailable"
    a2enmod rewrite 2>/dev/null || echo "rewrite module already enabled or unavailable"
fi

# Update Apache VirtualHost configuration
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    echo "Updating Apache VirtualHost configuration..."
    cat > /etc/apache2/sites-enabled/000-default.conf << 'APACHECONF'
<VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot /workspace

        # RewriteMap for IP allowlist
        RewriteMap allowed "txt:/workspace/ip_list"

        # Handle PUT requests
        Script PUT /workspace/put.php

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined

        <Directory /workspace>
            Options -Indexes +FollowSymLinks
            AllowOverride All
            Require all granted
            
            # Enable PUT method in directory context
            <Limit GET POST HEAD PUT OPTIONS>
                Require all granted
            </Limit>
            <LimitExcept GET POST HEAD PUT OPTIONS>
                Require all denied
            </LimitExcept>
        </Directory>
</VirtualHost>
APACHECONF
    
    # Test Apache configuration
    if apachectl configtest 2>/dev/null; then
        echo "Apache configuration is valid."
        # Reload Apache to apply changes
        apachectl graceful 2>/dev/null || service apache2 reload 2>/dev/null || echo "Warning: Could not reload Apache."
        echo "Apache reloaded with new configuration."
    else
        echo "Warning: Apache configuration test failed. Please check manually."
    fi
else
    echo "Notice: Apache VirtualHost configuration not found at expected location."
fi

# Wait for MySQL to be ready
if command -v mysql >/dev/null 2>&1; then
    echo "Waiting for MySQL to be ready..."
    max_attempts=30
    attempt=0
    until mysql -e "SELECT 1" &> /dev/null || [ $attempt -eq $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "Waiting for MySQL... (attempt $attempt/$max_attempts)"
        sleep 2
    done
    if [ $attempt -eq $max_attempts ]; then
        echo "Warning: Could not connect to MySQL after $max_attempts attempts."
        echo "Database initialization will happen when you first access the application."
    else
        echo "MySQL is ready!"
    fi
else
    echo "mysql client not installed; skipping database readiness check."
fi

echo ""
echo "=========================================="
echo "Setup complete!"
echo "=========================================="
echo ""
echo "Development server will be available at:"
echo "  http://localhost:8080"
echo ""
echo "Database credentials:"
echo "  Host: db"
echo "  Database: ci_snapshots"
echo "  User: snapshots"
echo "  Password: snapshots123"
echo ""
echo "Apache Configuration:"
echo "  - PUT method enabled"
echo "  - RewriteMap configured for IP allowlist"
echo "  - Local IPs (127.0.0.1, ::1, Docker network) added to ip_list"
echo ""
echo "To initialize the database, visit:"
echo "  http://localhost:8080/index.php"
echo ""
echo "To test file uploads:"
echo "  curl --upload-file ./file.zip http://localhost:80/"
echo ""
echo "The application will automatically create required tables on first access."
echo ""
