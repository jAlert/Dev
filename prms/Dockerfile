FROM php:8.4-fpm

# Replace apt sources with explicit HTTP mirrors (avoids SSL interception)
# Use OS codename dynamically so this works on both Bookworm and Trixie base images
RUN echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4 \
    && CODENAME=$(. /etc/os-release && echo $VERSION_CODENAME) \
    && rm -f /etc/apt/sources.list.d/debian.sources \
    && echo "deb http://deb.debian.org/debian $CODENAME main" > /etc/apt/sources.list \
    && echo "deb http://deb.debian.org/debian $CODENAME-updates main" >> /etc/apt/sources.list \
    && echo "deb http://security.debian.org/debian-security $CODENAME-security main" >> /etc/apt/sources.list

# System dependencies
RUN apt-get update && apt-get upgrade -y && apt-get install -y \
        git curl zip unzip libzip-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libxml2-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        bcmath \
        gd \
        pcntl \
        exif \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js (for Vite build)
RUN curl -fsSL --insecure https://deb.nodesource.com/setup_22.x | bash - \
    && sed -i 's|https://|http://|g' /etc/apt/sources.list.d/nodesource.list 2>/dev/null || true \
    && apt-get update && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install PHP deps
COPY composer.json composer.lock ./
RUN composer config --global disable-tls true && \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Install JS deps & build assets
COPY package.json package-lock.json ./
RUN npm config set strict-ssl false && npm ci

# Copy application
COPY . .

# Ensure bootstrap/cache exists (excluded from .dockerignore)
RUN mkdir -p bootstrap/cache && chmod -R 775 bootstrap/cache

# Finish composer (autoloader + scripts)
RUN composer dump-autoload --optimize

# Build frontend assets
RUN npm run build

# Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
