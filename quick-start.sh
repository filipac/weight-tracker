#!/bin/bash

# Weight Tracker - Quick Start Setup Script
# Run this script after cloning the repository to set up the project locally

set -e  # Exit on any error

echo "🚀 Setting up Weight Tracker project..."

# Check if PHP is installed and meets minimum version requirement
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.2 or higher."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
if ! php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);" 2>/dev/null; then
    echo "❌ PHP version $PHP_VERSION is not supported. Please install PHP 8.2 or higher."
    exit 1
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer from https://getcomposer.org/"
    exit 1
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js from https://nodejs.org/"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm."
    exit 1
fi

echo "✅ Prerequisites check passed"

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Copy environment file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
    
    # Generate application key
    echo "🔑 Generating application key..."
    php artisan key:generate
else
    echo "⚠️  .env file already exists, skipping environment setup"
fi

# Create SQLite database file if it doesn't exist
if [ ! -f "database/database.sqlite" ]; then
    echo "🗃️  Creating SQLite database..."
    touch database/database.sqlite
else
    echo "⚠️  SQLite database already exists"
fi

# Run database migrations
echo "🗃️  Running database migrations..."
php artisan migrate

# Build frontend assets
echo "🎨 Building frontend assets..."
npm run build

# Clear and cache configuration
echo "🧹 Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Update your .env file with any specific configuration"
echo "   2. If you want Withings integration, add WITHINGS_CLIENT_ID and WITHINGS_CLIENT_SECRET"
echo "   3. Start the development server with: composer run dev"
echo "   4. Or start individual services:"
echo "      - Laravel server: php artisan serve"
echo "      - Frontend assets: npm run dev"
echo ""
echo "🌐 The application will be available at http://localhost:8000"
echo ""
echo "💡 Useful commands:"
echo "   - Run tests: composer run test"
echo "   - Format code: ./vendor/bin/pint"
echo "   - Fresh migration with seed: php artisan migrate:fresh --seed"
echo "   - Update Notes.app: php artisan notes:update-weight"