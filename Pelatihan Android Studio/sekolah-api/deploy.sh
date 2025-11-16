#!/bin/bash

# Sekolah API Production Deployment Script
# This script optimizes and deploys the Laravel application for production

set -e  # Exit on any error

echo "🚀 Starting Sekolah API Production Deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_status "PHP Version: $PHP_VERSION"

# Check if required PHP extensions are installed
REQUIRED_EXTENSIONS=("pdo" "mysqlnd" "redis" "mbstring" "xml" "curl" "zip")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "$ext"; then
        print_success "PHP extension $ext is installed"
    else
        print_warning "PHP extension $ext is not installed. Installing..."
        # Note: This would need to be adapted based on the system package manager
    fi
done

print_status "Setting up production environment..."

# Copy environment file if it doesn't exist
if [ ! -f ".env" ]; then
    cp .env.example .env
    print_success "Created .env file from .env.example"
else
    print_warning ".env file already exists"
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    print_status "Generating application key..."
    php artisan key:generate --force
    print_success "Application key generated"
fi

print_status "Optimizing application for production..."

# Clear all caches
print_status "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Optimize configuration and routes
print_status "Optimizing configuration and routes..."
php artisan config:cache
php artisan route:cache

# Optimize views (if using Blade)
print_status "Optimizing views..."
php artisan view:cache

# Optimize events and listeners
print_status "Optimizing events..."
php artisan event:cache

print_status "Setting up database..."

# Check if we should run migrations
if [ "$1" = "--fresh" ]; then
    print_warning "Running fresh migrations (this will drop all data!)..."
    php artisan migrate:fresh --force
else
    print_status "Running migrations..."
    php artisan migrate --force
fi

# Seed database if requested
if [ "$1" = "--seed" ] || [ "$2" = "--seed" ]; then
    print_status "Seeding database..."
    php artisan db:seed --force
fi

print_status "Optimizing storage..."

# Create storage link
php artisan storage:link

# Set proper permissions
print_status "Setting proper permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod -R 644 .env

print_status "Running final optimizations..."

# Optimize composer autoloader
print_status "Optimizing composer autoloader..."
composer dump-autoload --optimize --classmap-authoritative

# Create optimized production build
print_status "Creating optimized production build..."
php artisan optimize

print_status "Checking system requirements..."

# Check if Redis is running
if command -v redis-cli >/dev/null 2>&1; then
    if redis-cli ping >/dev/null 2>&1; then
        print_success "Redis is running"
    else
        print_warning "Redis is not running. Please start Redis for optimal performance."
    fi
else
    print_warning "Redis CLI not found. Please install Redis for optimal performance."
fi

# Check if MySQL is running
if command -v mysql >/dev/null 2>&1; then
    print_success "MySQL client found"
else
    print_warning "MySQL client not found. Please install MySQL."
fi

print_status "Deployment completed successfully!"

echo ""
print_success "🎉 Sekolah API has been optimized and deployed!"
echo ""
echo "Next steps:"
echo "1. Configure your web server (Nginx/Apache)"
echo "2. Set up SSL certificate"
echo "3. Configure cron jobs for queue workers"
echo "4. Monitor application performance"
echo ""
echo "Useful commands:"
echo "- php artisan queue:work (for background jobs)"
echo "- php artisan schedule:run (for scheduled tasks)"
echo "- php artisan cache:prune (to clear expired cache)"
echo ""

# Show system information
echo "System Information:"
echo "==================="
echo "Laravel Version: $(php artisan --version)"
echo "PHP Version: $PHP_VERSION"
echo "Environment: $(php artisan env)"
echo "Cache Driver: $(php artisan config:get cache.default)"
echo "Database: $(php artisan config:get database.default)"
echo ""

print_success "Deployment script completed successfully!"
