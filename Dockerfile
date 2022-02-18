FROM library/php:8.0-cli

ENV TZ="Europe/Amsterdam" \
    APPLICATION_NAME="DonlSync"

RUN apt-get update -y && \
    apt-get install libzip-dev zlib1g libpng-dev libpq-dev postgresql-client curl zip unzip --no-install-recommends -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install opcache gd pdo pgsql pdo_pgsql zip && \
    echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/memory_limit.ini

COPY --from=library/composer:2 /usr/bin/composer /usr/local/bin/composer

RUN useradd -r -u 900 donl-sync

WORKDIR /usr/src/donl-sync

COPY . .

RUN composer install --no-cache --prefer-dist --no-dev --optimize-autoloader --classmap-authoritative && \
    chown -R donl-sync:donl-sync /usr/src/donl-sync && \
    chmod -R o-rwx /usr/src/donl-sync && \
    find ./bin -type f -name "*.sh" -exec chmod u+x {} \;

USER donl-sync

CMD ["/bin/bash"]
