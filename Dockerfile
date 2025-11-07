# Use PHP 8.5 RC (Release Candidate)
# Note: If 8.5-rc-cli is not available, this will build from 8.5.0RC4 source
FROM php:8.5-rc-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    zip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install PHP dependencies if composer.json exists
RUN if [ -f composer.json ]; then composer install --no-scripts --no-autoloader; fi

# Copy application source
COPY . .

# Generate autoloader
RUN if [ -f composer.json ]; then composer dump-autoload --optimize; fi

# Default command
CMD ["php", "-a"]
