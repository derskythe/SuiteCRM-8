# syntax=docker/dockerfile:1
FROM node:18-bookworm AS build_angular

# Angular build
ARG TZ="Asia/Baku"
ARG TERM="xterm-256color"
ARG APT_FLAGS_COMMON="-qq -y"
ARG APT_FLAGS_PERSISTENT="${APT_FLAGS_COMMON} --no-install-recommends"
ARG WORK_DIR="/src/app"

ENV TZ="${TZ}" \
    TERM="${TERM}" \
    WORK_DIR="${WORK_DIR}" \
    DEBIAN_FRONTEND=noninteractive

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

WORKDIR ${WORK_DIR}

RUN apt-get update ${APT_FLAGS_COMMON} &&  \
    apt-get upgrade ${APT_FLAGS_COMMON} &&  \
    apt-get install ${APT_FLAGS_PERSISTENT} \
      bash \
      git

COPY . .

RUN yarn global add @angular/cli;                                        \
    yarn install;                                                        \
    npm run build-dev;                                                   \
    npm run build-dev:defaultExt;                                        \
    mkdir publish;                                                       \
    mkdir publish/public;                                                \
    mv -f "${WORK_DIR}/public/dist" "${WORK_DIR}/publish/public";        \
    mv -f "${WORK_DIR}/public/extensions" "${WORK_DIR}/publish/public";  \
    mv -f "${WORK_DIR}/dist" "${WORK_DIR}/publish";

FROM php:8.2.24-apache-bookworm AS base
# Prepare PHP and Apache

ARG APT_FLAGS_COMMON="-qq -y"
ARG APT_FLAGS_PERSISTENT="${APT_FLAGS_COMMON} --no-install-recommends"
ARG WORK_DIR="/var/www/html/app"
ARG APACHE_LOG_DIR="${WORK_DIR}/logs/apache"
ARG TZ="Asia/Baku"
ARG PHP_INI_DIR="/usr/local/etc/php"
ARG APACHE_RUN_USER="www-data"
ARG APACHE_RUN_GROUP="www-data"
ARG APACHE_CONFDIR="/etc/apache2"
ARG APACHE_RUN_DIR="/var/www"
ARG APACHE_PID_FILE="${APACHE_RUN_DIR}/apache.pid"
ARG APACHE_DOCUMENT_ROOT="${APACHE_RUN_DIR}/html"
ARG APACHE_RUN_DIR=${WORK_DIR}
ARG APACHE_MOD_AVAIL_DIR="${APACHE_CONFDIR}/mods-available"
ARG APACHE_MOD_ENABLED_DIR="${APACHE_CONFDIR}/mods-enabled"
ARG APACHE_CONF_ENABLED_DIR="${APACHE_CONFDIR}/conf-enabled"
ARG APACHE_CONF_AVAILABLE_DIR="${APACHE_CONFDIR}/conf-available"

ENV WORK_DIR="${WORK_DIR}" \
    TZ=${TZ} \
    APACHE_DOCUMENT_ROOT="${APACHE_DOCUMENT_ROOT}" \
    APACHE_RUN_DIR="${APACHE_RUN_DIR}" \
    APACHE_PID_FILE="${APACHE_PID_FILE}" \
    APACHE_RUN_USER="${APACHE_RUN_USER}" \
    APACHE_RUN_GROUP="${APACHE_RUN_GROUP}" \
    PHP_INI_DIR="${PHP_INI_DIR}" \
    DEBIAN_FRONTEND=noninteractive

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

WORKDIR "${WORK_DIR}"

# Install required libs
# Enable Apache modules
# Setup Virtual Host
# Move php.ini.development file to php.ini
# Change memory limit to 1Gb to success setup
# Show info
# Download Composer
# Configure GD and IMAP
# Install extensions
# Install PECL XDebug
# Enable all extensions
# Set shell and some other things
# Clean-up
# apache2-dev libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev libsodium-dev libsqlite3-dev
# libssl-dev libxml2-dev zlib1g-dev
RUN apt-get update ${APT_FLAGS_COMMON} && apt-get install ${APT_FLAGS_PERSISTENT} \
    wget \
    7zip \
    autotools-dev \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    libpng-dev  \
    libmcrypt-dev \
    libmagickwand-dev \
    libldap-dev \
    libldap-common \
    libzip-dev \
    libc-client-dev \
    libkrb5-dev \
    libyaml-dev \
    libkf5imap-dev \
    strace \
    ltrace \
    vim \
    libedit-dev \
    libxpm-dev \
    libwebp-dev \
    libfreetype6  \
    libgmp10 \
    libgmp-dev \
    libicu-dev \
    rsync \
    && \
    printf "PS1='\[\\\\033[32m\][\\\\u@\h\\\\[\\\\033[32m\\\\]]\\\\[\\\\033[00m\\\\] \\\\[\\\\033[36m\\\\]\\\\w\\\\[\\\\033[0m\\\\] \\\\[\\\\033[33m\\\\]\\\\$\\\\[\\\\033[00m\\\\] '\nalias ll='ls -lha --color=auto'\nalias ls='ls -ah --color=auto'\n" >> ~/.bashrc && \
    rm -Rf                                 \
      /var/log/*.log                       \
      /var/log/apt/*                       \
      /usr/share/doc/*                     \
      /usr/share/icons/*                   \
      /var/cache/apt                       \
      /tmp/*                               \
      /var/lib/apt/lists/*                 \
      /var/cache/debconf/templates.dat-old \
      /var/cache/debconf/config.dat-old    \
    ;

FROM base AS install

ARG EXT_LIST=" intl           \
               opcache        \
               ldap           \
               gd             \
               zip            \
               gettext        \
               imap           \
               pdo_mysql      \
               mysqli         \
               exif           \
               soap"

RUN     ln -s ${APACHE_MOD_AVAIL_DIR}/log_debug.load    ${APACHE_MOD_ENABLED_DIR}/log_debug.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/info.load         ${APACHE_MOD_ENABLED_DIR}/info.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/rewrite.load      ${APACHE_MOD_ENABLED_DIR}/rewrite.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/vhost_alias.load  ${APACHE_MOD_ENABLED_DIR}/vhost_alias.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/unique_id.load    ${APACHE_MOD_ENABLED_DIR}/unique_id.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/request.load      ${APACHE_MOD_ENABLED_DIR}/request.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/mime_magic.load   ${APACHE_MOD_ENABLED_DIR}/mime_magic.load && \
        ln -s ${APACHE_MOD_AVAIL_DIR}/mime_magic.conf   ${APACHE_CONF_ENABLED_DIR}/mime_magic.conf && \
        printf '<VirtualHost *:8080>\nAddDefaultCharset utf-8\n\nCustomLog /dev/stdout vhost_combined\nErrorLog /dev/stderr\nLogLevel info\nRewriteEngine On\nDocumentRoot ${WORK_DIR}/public\n<Directory ${WORK_DIR}/public>\nAllowOverride All\nOrder Allow,Deny\nAllow from All\n</Directory>\n</VirtualHost>\n' \
          > ${APACHE_CONFDIR}/sites-enabled/000-default.conf && \
        mv "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/memory_limit *= *[[:digit:]]*M/memory_limit = 1024M/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/post_max_size *= *[[:digit:]]*M/post_max_size = 128M/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/display_errors = (Off|On)/display_errors = Off/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/upload_max_filesize *= *[[:digit:]]*M/upload_max_filesize = 128M/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/\;error_log = syslog/error_log = \/var\/www\/html\/app\/logs\/php\.log/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/user_ini\.filename = *$/user_ini\.filename = \"\.user\.ini\"/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/\;date.timezone =\s*/date.timezone = "${TZ}"/' "${PHP_INI_DIR}/php.ini" && \
        sed -ri -e 's/Listen *[[:digit:]]*/Listen 8080/' "${APACHE_CONFDIR}/ports.conf" && \
        sed -ri -e 's/^#AddDefaultCharset/AddDefaultCharset/' "${APACHE_CONF_AVAILABLE_DIR}/charset.conf" && \
        printf 'ServerName localhost\nCustomLog /dev/stdout combined\nCustomLog /dev/stdout vhost_combined\nErrorLog /dev/stderr\nLogLevel info\n' > ${APACHE_CONF_AVAILABLE_DIR}/other-vhosts-access-log.conf && \
        TMP_FILE=`mktemp` && head -n1 ${APACHE_CONFDIR}/envvars > $TMP_FILE && echo "export APACHE_LOG_DIR=${APACHE_LOG_DIR}" >> $TMP_FILE &&  tail -n +2 ${APACHE_CONFDIR}/envvars >> $TMP_FILE && mv -f $TMP_FILE ${APACHE_CONFDIR}/envvars && \
        php -i && \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
        docker-php-source extract && \
        docker-php-ext-configure gd --enable-gd --with-webp --with-jpeg --with-xpm --with-freetype && \
        docker-php-ext-configure imap --with-kerberos --with-imap-ssl && \
        docker-php-ext-install -j$(nproc) ${EXT_LIST}                   && \
        pecl install xdebug && \
        docker-php-ext-enable ${EXT_LIST} && \
        docker-php-source delete            && \
    rm -Rf                                 \
      /var/log/*.log                       \
      /var/log/apt/*                       \
      /usr/share/doc/*                     \
      /usr/share/icons/*                   \
      /var/cache/apt                       \
      /tmp/*                               \
      /var/lib/apt/lists/*                 \
      /var/cache/debconf/templates.dat-old \
      /var/cache/debconf/config.dat-old    \
    ;

FROM install AS final

###> recipes ###
###< recipes ###

ARG TZ="Asia/Baku"
ARG APP_ENV="dev"
ARG XDEBUG_MODE="off"
ARG STABILITY="dev"
ENV APP_ENV=${APP_ENV} \
    XDEBUG_MODE=${XDEBUG_MODE} \
    TZ=${TZ} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    WORK_DIR="${WORK_DIR}" \
    STABILITY="${STABILITY}"

WORKDIR "${WORK_DIR}"

COPY . .

RUN composer update --no-progress &&  \
    composer install --no-cache --prefer-dist --no-autoloader --no-scripts --no-progress --ansi

# Copy sources from context and from build_angular layer
COPY --from=build_angular /src/app/publish/* .
#COPY --from=build_angular "/src/app/public/extensions" "./public"
#COPY --from=build_angular "/src/app/dist" "."

# Run Composer
RUN set -eux; \
    composer dump-autoload --classmap-authoritative --ansi; \
    composer dump-env dev; \
    composer run-script post-install-cmd; \
    true && \
    chmod +x ./bin/console && \
    find ${WORK_DIR} -type d -not -perm 2755 -exec chmod 2755 {} \; && \
    find ${WORK_DIR} -type f -not -perm 0644 -exec chmod 0644 {} \; && \
    find ${WORK_DIR} ! -user ${APACHE_RUN_USER} -exec chown --no-dereference ${APACHE_RUN_USER}:${APACHE_RUN_USER} {} \; && \
    true && \
    rm -Rf \
      /var/log/*.log \
      /var/log/apt/* \
      /usr/share/doc/* \
      /usr/share/icons/* \
      /var/cache/apt \
      /tmp/* \
      /var/lib/apt/lists/* \
      /var/cache/debconf/templates.dat-old \
      /var/cache/debconf/config.dat-old \
    ; \
    sync

EXPOSE 8080
