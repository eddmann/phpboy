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

# Install Xdebug for profiling (Step 14: Performance Profiling)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configure Xdebug for profiling (disabled by default, enabled via environment variable)
RUN echo "xdebug.mode=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.output_dir=/app/var/profiling" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.profiler_output_name=cachegrind.out.%t" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configure OPcache for performance (Step 14: Performance Profiling)
RUN docker-php-ext-install opcache \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP 8.5 JIT (disabled by default, can be enabled via environment variable)
RUN echo "opcache.jit_buffer_size=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=off" >> /usr/local/etc/php/conf.d/opcache.ini

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
