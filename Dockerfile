# Etapa 1: Construcción de la interfaz gráfica
FROM node:20-bookworm AS builder
WORKDIR /app
COPY . .
RUN npm install -g pnpm && npm install --legacy-peer-deps && npm run build

# Etapa 2: Servidor Apache y PHP para la aplicación
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install --yes --no-install-recommends cron libapache2-mod-xsendfile libzip-dev zip ffmpeg locales libpng-dev libjpeg62-turbo-dev libpq-dev libwebp-dev libavif-dev nano libicu-dev libxslt1-dev curl python3 && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif && docker-php-ext-install bcmath exif gd pdo pdo_mysql pdo_pgsql pgsql zip intl xsl && apt-get clean && rm -rf /var/lib/apt/lists/* && mkdir /music && chown www-data:www-data /music && mkdir -p /var/www/html/storage/search-indexes && chown www-data:www-data /var/www/html/storage/search-indexes && mkdir -p /var/www/html/storage/app/public/images && chown -R www-data:www-data /var/www/html/storage/app && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && /usr/sbin/locale-gen && curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp && chmod a+rx /usr/local/bin/yt-dlp
COPY ./apache.conf /etc/apache2/sites-available/000-default.conf
COPY ./php.ini ${PHP_INI_DIR}/php.ini

RUN a2enmod rewrite

# Copiamos el código fuente de la aplicación
COPY . /var/www/html

# Copiamos los archivos visuales ya construidos
COPY --from=builder /app/public/build /var/www/html/public/build

RUN mv /var/www/html/public/manifest.json.example /var/www/html/public/manifest.json && chown -R www-data:www-data /var/www/html

VOLUME ["/music", "/var/www/html/storage/app/public/images", "/var/www/html/storage/search-indexes"]

RUN touch /var/www/html/.env.example
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN cd /var/www/html && php artisan route:cache && php artisan event:cache && php artisan view:cache
ENV FFMPEG_PATH=/usr/bin/ffmpeg \
    YTDLP_PATH=/usr/local/bin/yt-dlp \
    MEDIA_PATH=/music \
    STREAMING_METHOD=x-sendfile \
    LANG=en_US.UTF-8 \
    LANGUAGE=en_US:en \
    LC_ALL=en_US.UTF-8


COPY koel-entrypoint /usr/local/bin/
COPY koel-init /usr/local/bin/
RUN sed -i 's/\r$//' /usr/local/bin/koel-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/koel-init
RUN chmod +x /usr/local/bin/koel-init
RUN chmod +x /usr/local/bin/koel-entrypoint
RUN touch /var/www/html/.env && chown -R www-data:www-data /var/www/html
ENTRYPOINT ["koel-entrypoint"]
CMD ["apache2-foreground"]

EXPOSE 80

HEALTHCHECK --start-period=30s --interval=5m --timeout=5s CMD curl -f http://localhost/sw.js || exit 1