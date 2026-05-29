FROM php:8.3-apache-bookworm

ARG KOEL_VERSION_REF=v9.4.0

RUN curl -L https://github.com/koel/koel/releases/download/${KOEL_VERSION_REF}/koel-${KOEL_VERSION_REF}.tar.gz | tar -xz -C /tmp && chown www-data:www-data /tmp/koel && chmod 755 /tmp/koel && cd /tmp/koel/ && rm -rf .editorconfig .eslintignore .eslintrc .git .gitattributes .github .gitignore .gitmodules .gitpod.dockerfile .gitpod.yml .cursor/ .junie/ .husky/ .vscode/ api-docs cypress cypress.json nginx.conf.example package.json phpstan.neon.dist phpunit.xml.dist resources/artifacts/ ruleset.xml scripts/ tag.sh vite.config.js tests/songs/ pnpm-lock.yaml README.md CODE_OF_CONDUCT.md tailwind.config.js eslint.config.js postcss.config.cjs commitlint.config.js .htaccess.example

RUN apt-get update && apt-get install --yes --no-install-recommends cron libapache2-mod-xsendfile libzip-dev zip ffmpeg locales libpng-dev libjpeg62-turbo-dev libpq-dev libwebp-dev libavif-dev nano && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif && docker-php-ext-install bcmath exif gd pdo pdo_mysql pdo_pgsql pgsql zip && apt-get clean && rm -rf /var/lib/apt/lists/* && mkdir /music && chown www-data:www-data /music && mkdir -p /var/www/html/storage/search-indexes && chown www-data:www-data /var/www/html/storage/search-indexes && mkdir -p /var/www/html/storage/app/public/images && chown -R www-data:www-data /var/www/html/storage/app && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && /usr/sbin/locale-gen

COPY ./apache.conf /etc/apache2/sites-available/000-default.conf

COPY ./php.ini ${PHP_INI_DIR}/php.ini

RUN a2enmod rewrite

RUN cp -R /tmp/koel/. /var/www/html && mv /var/www/html/public/manifest.json.example /var/www/html/public/manifest.json && chown -R www-data:www-data /var/www/html

VOLUME ["/music", "/var/www/html/storage/app/public/images", "/var/www/html/storage/search-indexes"]

RUN cd /var/www/html && php artisan route:cache && php artisan event:cache && php artisan view:cache

ENV FFMPEG_PATH=/usr/bin/ffmpeg \
    MEDIA_PATH=/music \
    STREAMING_METHOD=x-sendfile \
    LANG=en_US.UTF-8 \
    LANGUAGE=en_US:en \
    LC_ALL=en_US.UTF-8

COPY koel-entrypoint /usr/local/bin/
COPY koel-init /usr/local/bin/
ENTRYPOINT ["koel-entrypoint"]
CMD ["apache2-foreground"]

EXPOSE 80

HEALTHCHECK --start-period=30s --interval=5m --timeout=5s CMD curl -f http://localhost/sw.js || exit 1
