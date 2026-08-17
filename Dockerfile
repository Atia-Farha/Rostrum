# -----------------------------------------------------------------------------
# Stage 1: Build Frontend Assets (Vite)
# -----------------------------------------------------------------------------
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: Install PHP Production Dependencies
# -----------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS composer-builder
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 3: Final Production Image (PHP 8.4 FPM + Nginx)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

# Install system utilities, nginx, and required PHP extensions
RUN apk add --no-cache \
    nginx \
    bash \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        bcmath

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Remove local hot file if present in context
RUN rm -f public/hot

# Copy built vendor and frontend assets from previous stages
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=node-builder /app/public/build ./public/build

# Setup storage and cache directories and permissions
RUN mkdir -p \
    storage/app/public/audio \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

# Copy custom Nginx configuration & entrypoint script
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose Render default port (injected via $PORT at runtime, fallback 8080)
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
