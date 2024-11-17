#!/bin/bash

# Function to validate URL format
validate_url() {
    if [[ ! $1 =~ ^https?://[A-Za-z0-9.-]+\.[A-Za-z]{2,}/?.*$ ]]; then
        echo "Invalid URL format. Please enter a valid URL (e.g., https://example.com)"
        return 1
    fi
    return 0
}

# Function to validate email format
validate_email() {
    if [[ ! $1 =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
        echo "Invalid email format. Please enter a valid email address"
        return 1
    fi
    return 0
}

# Collect parameters with validation
while true; do
    read -p "Enter database name: " DB_NAME
    [[ ! -z "$DB_NAME" ]] && break
    echo "Database name cannot be empty"
done

while true; do
    read -p "Enter database user: " DB_USER
    [[ ! -z "$DB_USER" ]] && break
    echo "Database user cannot be empty"
done

while true; do
    read -p "Enter database password: " DB_PASSWORD
    [[ ! -z "$DB_PASSWORD" ]] && break
    echo "Database password cannot be empty"
done

while true; do
    read -p "Enter database host: " DB_HOST
    [[ ! -z "$DB_HOST" ]] && break
    echo "Database host cannot be empty"
done

while true; do
    read -p "Enter WordPress URL (including http(s)://): " WP_URL
    validate_url "$WP_URL" && break
done

while true; do
    read -p "Enter WordPress admin username: " WP_ADMIN_USER
    [[ ! -z "$WP_ADMIN_USER" ]] && break
    echo "Admin username cannot be empty"
done

while true; do
    read -p "Enter WordPress admin password: " WP_ADMIN_PASS
    [[ ! -z "$WP_ADMIN_PASS" ]] && break
    echo "Admin password cannot be empty"
done

while true; do
    read -p "Enter WordPress admin email: " WP_ADMIN_EMAIL
    validate_email "$WP_ADMIN_EMAIL" && break
done

while true; do
    read -p "Enter site slug: " SITE_SLUG
    [[ ! -z "$SITE_SLUG" ]] && break
    echo "Site slug cannot be empty"
done

# Download and extract WordPress
echo "Downloading WordPress..."
wp core download

# Create wp-config.php
echo "Creating wp-config.php..."
wp config create \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASSWORD" \
    --dbhost="$DB_HOST" \
    --force

# Install WordPress
echo "Installing WordPress..."
wp core install \
    --url="$WP_URL" \
    --title='WordPress Site' \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL"

# Import database
echo "Importing database..."
wp db import /home/aideploy/dbsetup.sql

# Search and replace URLs
echo "Updating URLs..."
wp search-replace 'oldurl.com' "$WP_URL"

# Set up wp-config.php in secure location
echo "Setting up secure wp-config.php location..."
sudo mkdir -p "/home/www-data/.wpconfigs/$SITE_SLUG"
sudo mv wp-config.php "/home/www-data/.wpconfigs/$SITE_SLUG/"
sudo ln -s "/home/www-data/.wpconfigs/$SITE_SLUG/wp-config.php" wp-config.php

# Set proper permissions
echo "Setting permissions..."
sudo chown -R www-data:www-data "/home/www-data/.wpconfigs/$SITE_SLUG"

echo "WordPress deployment completed successfully!"
