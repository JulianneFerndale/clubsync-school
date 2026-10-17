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
# This stage runs on the composer image's own PHP (8.5, no gd), not the 8.4 the app
# runs on, so its platform check would judge the wrong PHP — phpspreadsheet refuses
# 8.5 and needs gd. It is skipped here and run against the real runtime below.
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    --optimize-autoloader --no-scripts --ignore-platform-reqs

# ─── Stage 3: runtime (nginx + php-fpm) ───────────────────────────────────────
# 8.4, the version composer.lock was resolved on (symfony/clock needs >= 8.4.1).
FROM php:8.4-fpm-alpine AS app

# PHP extensions — install-php-extensions pulls build deps and cleans up itself.
# gd: receipt images (App\Support\PdfImage) and dompdf; gd + zip: the semester
# archive workbook (maatwebsite/excel writes .xlsx through ZipArchive).
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl opcache bcmath pcntl gd zip \
    && apk add --no-cache nginx \
    # PHP's 2M default is below the club handbook and CHED memorandum PDFs the
    # admin uploads; kept just under nginx's 20M client_max_body_size.
    && printf 'upload_max_filesize=20M\npost_max_size=20M\n' > /usr/local/etc/php/conf.d/uploads.ini

# PHP workers and opcache for production (see the files for the reasoning).
# PHP_FPM_WORKERS fits Render's free 512 MB; set it higher on a bigger server.
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
ENV PHP_FPM_WORKERS=8

WORKDIR /var/www/html

# App + vendor (from the composer stage), then the compiled assets (node stage).
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# The platform check skipped in the vendor stage, against the PHP that actually runs:
# a missing extension or unsupported PHP version fails the build here, not at runtime.
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer
RUN composer check-platform-reqs --no-dev --lock && rm /usr/local/bin/composer

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
