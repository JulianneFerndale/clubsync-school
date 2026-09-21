# syntax=docker/dockerfile:1

# ─── Stage 1: build front-end assets (Vite) ──────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ─── Stage 2: install PHP dependencies (Composer, production only) ────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    --optimize-autoloader --no-scripts

# ─── Stage 3: runtime (nginx + php-fpm) ───────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# PHP extensions — install-php-extensions pulls build deps and cleans up itself.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl opcache bcmath pcntl \
    && apk add --no-cache nginx

WORKDIR /var/www/html

# App + vendor (from the composer stage), then the compiled assets (node stage).
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Web-server config + container entrypoint.
COPY docker/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Render injects its own $PORT at runtime; 8080 is the local default.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
