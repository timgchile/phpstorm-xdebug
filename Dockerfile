FROM php:8.0-cli

# basic env fix
ENV TERM xterm

ENV APP_DEBUG 1
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_MEMORY_LIMIT -1

WORKDIR /var/www

# install packages
RUN apt-get update \
  && apt-get dist-upgrade -y \
  && apt-get install --no-install-recommends -y apt-utils \
  && apt-get install --no-install-recommends -y nano curl git zip unzip findutils wget procps \
  libfreetype6-dev libjpeg62-turbo-dev libpng-dev pkg-config libssl-dev libcurl4-openssl-dev zlib1g-dev libxslt-dev \
  libicu-dev g++ libxml2-dev libpcre3-dev libzip-dev libsodium-dev libonig-dev gpg gpg-agent \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-install -j$(nproc) gd pcntl iconv curl intl xml xsl mbstring bcmath sodium sockets opcache soap zip \
  && pecl channel-update pecl.php.net \
  && pecl install -of xdebug ast apcu \
  && docker-php-ext-enable opcache sockets ast apcu xdebug \
  && rm -rf /tmp/* \
  && apt-get autoremove -y \
  && apt-get autoclean

COPY install-composer.sh /tmp

RUN bash /tmp/install-composer.sh \
  && composer self-update \
  && rm -rf /tmp/*

RUN mv "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;date.timezone =/date.timezone=UTC/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/display_errors = On/display_errors=0/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/display_startup_errors = On/display_startup_errors=0/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/session.use_strict_mode = 0/session.use_strict_mode=1/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.enable=1/opcache.enable=1/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.enable_cli=0/opcache.enable_cli=1/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.enable_file_override=0/opcache.enable_file_override=1/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/opcache.validate_timestamps=0/opcache.validate_timestamps=1/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.preload_user=/opcache.preload_user=www-data/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.memory_consumption=128/opcache.memory_consumption=500/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;opcache.max_accelerated_files=10000/opcache.max_accelerated_files=30000/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;realpath_cache_size = 4096K/realpath_cache_size=4096K/g" "${PHP_INI_DIR}/php.ini" \
  && sed -i "s/;realpath_cache_size = 120/realpath_cache_ttl=600/g" "${PHP_INI_DIR}/php.ini" \
  && echo "zend.detect_unicode=0" >> "${PHP_INI_DIR}/php.ini" \
  && echo "catch_workers_output=1" >> "${PHP_INI_DIR}/php.ini" \
  && echo "decorate_workers_output=0" >> "${PHP_INI_DIR}/php.ini" \
  && echo "xdebug.mode=develop,debug" >> "${PHP_INI_DIR}/php.ini" \
  && echo "xdebug.client_host=host.docker.internal" >> "${PHP_INI_DIR}/php.ini" \
  && echo "xdebug.log=/var/www/var/log/xdebug.log" >> "${PHP_INI_DIR}/php.ini"

HEALTHCHECK --interval=1m --timeout=3s --start-period=10s CMD curl -k -f http://localhost/healthcheck || exit 1

EXPOSE 80

ENTRYPOINT ["composer"]
CMD ["server:start:dev"]
