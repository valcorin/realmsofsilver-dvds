#!/bin/bash

# Fix file permissions for Realms of Silver DVDs deployment
# Run this script as root or with sudo

echo "Setting up file permissions for DVD collection app..."

# Set directory permissions (755 = read/execute for all, write for owner)
echo "Setting directory permissions..."
find /srv/www/htdocs/dvds -type d -exec chmod 755 {} \;
find /srv/www/htdocs/olddvds -type d -exec chmod 755 {} \; 2>/dev/null || true
find /srv/www/htdocs/api -type d -exec chmod 755 {} \; 2>/dev/null || true

# Set file permissions (644 = read for all, write for owner)
echo "Setting file permissions..."
find /srv/www/htdocs/dvds -type f -exec chmod 644 {} \;
find /srv/www/htdocs/olddvds -type f -exec chmod 644 {} \; 2>/dev/null || true
find /srv/www/htdocs/api -type f -exec chmod 644 {} \; 2>/dev/null || true

# Set ownership to web server user
echo "Setting ownership to web server user..."
chown -R www-data:www-data /srv/www/htdocs/dvds 2>/dev/null || chown -R apache:apache /srv/www/htdocs/dvds 2>/dev/null || true
chown -R www-data:www-data /srv/www/htdocs/olddvds 2>/dev/null || chown -R apache:apache /srv/www/htdocs/olddvds 2>/dev/null || true
chown -R www-data:www-data /srv/www/htdocs/api 2>/dev/null || chown -R apache:apache /srv/www/htdocs/api 2>/dev/null || true

echo "Permissions set successfully!"
echo ""
echo "Directory structure should be:"
echo "/srv/www/htdocs/"
echo "├── api/"
echo "│   └── dvds.php"
echo "├── dvds/ (or olddvds/)"
echo "│   ├── index.html"
echo "│   ├── vite.svg"
echo "│   └── assets/"
echo "│       ├── index-Qpyr_epj.css"
echo "│       └── index-Ct8Jnc7L.js"
echo ""
echo "Next steps:"
echo "1. Add the Apache configuration to your virtual host or .conf file"
echo "2. Restart Apache: sudo systemctl restart apache2"
echo "3. Test the application at: http://your-domain/dvds/ or /olddvds/"