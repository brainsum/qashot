# @todo: Install sendmail to /usr/sbin/sendmail.

# Base: debian:stretch-slim
FROM php:7.3.8-fpm-stretch

LABEL maintainer="mhavelant"
LABEL description="Custom docker image for Drupal."

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

COPY ["templates", "/etc/gotpl/"]
COPY ["docker-php-entrypoint.sh", "/"]
COPY ["bin", "/usr/local/bin/"]
COPY ["init", "/docker-entrypoint-init.d/"]

ARG PHP_DEV
ARG PHP_DEBUG

ENV \
    # General
    USER_NAME="php" \
    WEB_USER_NAME="www-data" \
    APP_ROOT="/var/www/html" \
    CONF_DIR="/var/www/conf" \
    FILES_DIR="/mnt/files" \
    APP_NAME="app" \
    ENVIRONMENT_NAME="development" \
    PHP_DEV="${PHP_DEV}" \
    PHP_DEBUG="${PHP_DEBUG}" \
    SSHD_PERMIT_USER_ENV="yes"

# www-data uid/gid 33.
RUN groupadd --gid 1000 "${USER_NAME}" \
  && useradd --uid 1000 --gid "${USER_NAME}" --shell /bin/bash --create-home "${USER_NAME}" \
  && usermod -aG "${WEB_USER_NAME}" "${USER_NAME}" \
  # Fix WEB_USER_NAME user home folder permissions.
  && chown "${WEB_USER_NAME}:${WEB_USER_NAME}" "/var/www/"

WORKDIR ${APP_ROOT}
VOLUME ${APP_ROOT}

USER ${USER_NAME}
EXPOSE 9000

ENTRYPOINT ["/docker-php-entrypoint.sh"]
CMD ["sudo", "-E", "LD_PRELOAD=/usr/lib/preloadable_libiconv.so", "php-fpm"]

USER root

ENV \
    PATH="${PATH}:/home/${USER_NAME}/.composer/vendor/bin:${APP_ROOT}/vendor/bin:${APP_ROOT}/bin:/var/www/.composer/vendor/bin:${PATH}" \
    # Gotpl
    GOTPL_VERSION=0.1.5 \
    # PHP
    PHP_VERSION=7.3 \
    # GNU Iconv
    GNU_ICONV_VERSION=1.14 \
    # Drupal
    DRUSH_LAUNCHER_VER="0.6.0" \
    DRUPAL_CONSOLE_LAUNCHER_VER="1.9.0" \
    DRUSH_LAUNCHER_FALLBACK="/home/${USER_NAME}/.composer/vendor/bin/drush" \
    \
    PHP_REALPATH_CACHE_TTL="3600" \
    PHP_OUTPUT_BUFFERING="16384"

# Enable PHP exts.
## See: https://github.com/wodby/php/blob/master/7/Dockerfile
## See: https://github.com/wodby/drupal-php/blob/master/7/Dockerfile
### @todo: Try to cache: /usr/local/lib/php/extensions/no-debug-non-zts-20170718/
### @todo: Maybe multistage builds?
RUN \
    apt-get -y update \
	&& apt-get -y --no-install-recommends install \
        # Sudo replacement.
        gosu=1.10-1+b2 \
        # @todo: Might no longer be needed.
        sudo \
	    software-properties-common \
        ca-certificates \
        dirmngr \
        gzip \
        unzip \
        bzip2 \
        openssh-client \
        jpegoptim \
        gnupg2 \
        curl \
        wget \
        patch \
        perl \
        imagemagick \
        rsync \
        mariadb-client \
        findutils \
        git \
        imagemagick \
        mariadb-client \
        patch \
        rsync \
        libevent-2.0-5 \
        libjpeg62-turbo \
        libpng16-16 \
        libpng-tools \
        libuuid1 \
        libzip4 \
        less \
        gsfonts \
        ibmagickcore-6.q16-3-extra \
        # Runtime
        # ext-imap
        libc-client2007e \
        # ext-mcrypt
        libmcrypt4 \
        # ext-memcached
        libmemcached11 \
        libmemcachedutil2 \
        # ext-tidy
        libtidy5 \
        # ext-yaml
        libyaml-0-2 \
        # ext-event
        libevent-openssl-2.0-5 \
        libevent-extra-2.0-5 \
        # ext-xsl
        libxslt1.1 \
        \
        # Dev packs
        libpcre2-dev \
        make \
        gcc \
        libtool \
        libtool-bin \
        build-essential \
        autoconf \
        tig \
        tmux \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libyaml-cpp-dev \
        libpng-dev \
        libzip-dev \
        # ext-event
        libevent-dev \
        # ext-gmp
        libgmp-dev \
        # ext-imap
        libc-client2007e-dev \
        libkrb5-dev \
        # ext-bz2
        libbz2-dev \
        # ext-intl
        libicu-dev \
        # ext-ldap
        libldb-dev \
        libldap2-dev \
        # ext-soap
        libxml2-dev \
        # ext-tidy
        libtidy-dev \
        # ext-xsl
        libxslt1-dev \
        # ext-imagick
        libmagickwand-dev \
        libmagickcore-dev \
        # ext-memcached
        libmemcached-dev \
        # ext-uuid
        uuid-dev \
        # ext-yaml
        libyaml-dev \
        # ext-mcrypt
        libmcrypt-dev \
    \
    # Libiconv preload fix; install gnu libiconv.
    && rm /usr/bin/iconv \
      && curl -SL "https://ftp.gnu.org/pub/gnu/libiconv/libiconv-${GNU_ICONV_VERSION}.tar.gz" | tar -xz -C . \
      && cd "libiconv-${GNU_ICONV_VERSION}" \
      && ./configure --prefix=/usr/local \
      && curl -SL "https://raw.githubusercontent.com/mxe/mxe/7e231efd245996b886b501dad780761205ecf376/src/libiconv-1-fixes.patch" | patch -p1 -u \
      && make \
      && make install \
      && libtool --finish /usr/local/lib \
      && cd .. \
      && rm -rf "libiconv-${GNU_ICONV_VERSION}" \
      && ln -s /usr/local/lib/preloadable_libiconv.so /usr/lib/preloadable_libiconv.so \
    \
    && docker-php-source extract \
    && NPROC="$(getconf _NPROCESSORS_ONLN)" \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-configure gd \
        --with-gd \
        --with-freetype-dir="/usr/include/" \
        --with-png-dir="/usr/include/" \
        --with-jpeg-dir="/usr/include/" \
    && docker-php-ext-install "-j${NPROC}" \
        iconv \
        bcmath \
        bz2 \
        calendar \
        exif \
        gmp \
        imap \
        intl \
        ldap \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        soap \
        sockets \
        tidy \
        xmlrpc \
        xsl \
        zip \
        gd \
        \
    && pecl config-set php_ini "${PHP_INI_DIR}/php.ini" \
    && pecl install \
        apcu-5.1.17 \
        ast-1.0.3 \
        ds-1.2.9 \
        event-2.5.3 \
        grpc-1.22.0 \
        igbinary-3.0.1 \
        imagick-3.4.4 \
        memcached-3.1.3 \
        uuid-1.0.4 \
        xdebug-2.7.2 \
        yaml-2.0.4 \
        mcrypt-1.0.2 \
#        oauth-2.0.3 \
#        mongodb-1.5.3 \
#        rdkafka-3.0.5 \
#        redis-4.2.0 \
    && docker-php-ext-enable \
        apcu \
        ast \
        ds \
        event \
        grpc \
        igbinary \
        imagick \
        memcached \
        uuid \
        xdebug \
        yaml \
        mcrypt \
#        oauth \
#        mongodb \
#        redis \
#        rdkafka \
    # Event extension should be loaded after sockets.
    # http://osmanov-dev-notes.blogspot.com/2013/07/fixing-php-start-up-error-unable-to.html
    && mv "/usr/local/etc/php/conf.d/docker-php-ext-event.ini" "/usr/local/etc/php/conf.d/z-docker-php-ext-event.ini" \
    && wget -qO- "https://getcomposer.org/installer" | php -- --install-dir="/usr/local/bin" --filename=composer \
    # Cleanup.
    \
    && docker-php-source delete \
    && pecl clear-cache \
    && rm -rf \
        /usr/src/php/ext/ast \
        /usr/src/php/ext/uploadprogress \
        /usr/include/php \
        /usr/lib/php/build \
        /tmp/* \
        /usr/src/php.tar.xz \
        /root/.composer \
        /var/cache/apk/* \
    \
    && install -o "${USER_NAME}" -g "${USER_NAME}" -d \
        "${APP_ROOT}" \
        "${CONF_DIR}" \
        "/home/${USER_NAME}/.ssh" \
    \
    && install -o "${WEB_USER_NAME}" -g "${WEB_USER_NAME}" -d \
        "${FILES_DIR}/public" \
        "${FILES_DIR}/private" \
        "${FILES_DIR}/sessions" \
        "${FILES_DIR}/xdebug/traces" \
        "${FILES_DIR}/xdebug/profiler" \
        /home/"${WEB_USER_NAME}"/.ssh \
    \
    && chmod -R 775 "${FILES_DIR}" \
    && chown -R "${USER_NAME}:${USER_NAME}" \
        "${PHP_INI_DIR}/conf.d" \
        /usr/local/etc/php-fpm.d \
        /home/"${USER_NAME}"/.[^.]* \
    # Cleanup.
    && rm -rf \
        /var/lib/apt/lists/* \
        /var/cache/apt/* \
    && apt-get -y purge \
        *-dev \
        build-essential \
        autoconf \
        libtool \
        libtool-bin \
        gcc \
        make \
    && apt-get -y clean \
    && apt-get -y autoclean \
    && apt-get -y autoremove

# Wodby gotpl.
RUN \
    wget "https://github.com/wodby/gotpl/releases/download/${GOTPL_VERSION}/gotpl-linux-amd64-${GOTPL_VERSION}.tar.gz" \
    && tar -xzf "gotpl-linux-amd64-${GOTPL_VERSION}.tar.gz" \
    && mv gotpl /usr/local/bin/gotpl \
    && chmod +rx /usr/local/bin/gotpl

## Not needed because of Drush 9?
#    # Drush launcher
#    && drush_launcher_url="https://github.com/drush-ops/drush-launcher/releases/download/${DRUSH_LAUNCHER_VER}/drush.phar" \
#    && wget -O drush.phar "${drush_launcher_url}" \
#    && chmod +x drush.phar \
#    && mv drush.phar /usr/local/bin/drush \
#    \

COPY ["user-conf", "/etc/build-templates/user-conf"]

# Drupal deps.
RUN set -ex \
    && gosu "${USER_NAME}" composer global require hirak/prestissimo --optimize-autoloader \
    && gosu "${USER_NAME}" composer global require drush/drush:^9.0 --optimize-autoloader \
    # Drush extensions
    && gosu "${USER_NAME}" mkdir -p "/home/${USER_NAME}/.drush" \
    && drush_patchfile_url="https://bitbucket.org/davereid/drush-patchfile.git" \
    && gosu "${USER_NAME}" git clone "${drush_patchfile_url}" "/home/${USER_NAME}/.drush/drush-patchfile" \
    && drush_rr_url="https://ftp.drupal.org/files/projects/registry_rebuild-7.x-2.5.tar.gz" \
    && wget -qO- "${drush_rr_url}" | gosu "${USER_NAME}" tar zx -C "/home/${USER_NAME}/.drush" \
    \
    # Drupal console
    && console_url="https://github.com/hechoendrupal/drupal-console-launcher/releases/download/${DRUPAL_CONSOLE_LAUNCHER_VER}/drupal.phar" \
    && curl "${console_url}" -L -o drupal.phar \
    && mv drupal.phar /usr/local/bin/drupal \
    && chmod +x /usr/local/bin/drupal \
    \
    && mv /usr/local/bin/actions.mk /usr/local/bin/php.mk \
    # Change overridden target name to avoid warnings.
    && sed -i 's/git-checkout:/php-git-checkout:/' /usr/local/bin/php.mk \
    \
    && mkdir -p "${FILES_DIR}/config" \
    && chown "${WEB_USER_NAME}:${WEB_USER_NAME}" "${FILES_DIR}/config" \
    && chmod 775 "${FILES_DIR}/config" \
    \
    # Clean up
    && gosu "${USER_NAME}" composer clear-cache \
    && gosu "${USER_NAME}" "${DRUSH_LAUNCHER_FALLBACK}" cc drush \
    && gotpl /etc/build-templates/user-conf/.shrc.tmpl | gosu "${USER_NAME}" tee "/home/${USER_NAME}/.shrc" \
    && cp "/home/${USER_NAME}/.shrc" "/home/${USER_NAME}/.bashrc" \
    && cp "/home/${USER_NAME}/.shrc" "/home/${USER_NAME}/.bash_profile" \
    # Docker builds with sh, so we can't use [[ ]].
    && if [ -n "${PHP_DEV}" ]; then SUDOERS_TEMPLATE=".dev"; fi \
    && gotpl "/etc/build-templates/user-conf/sudoers${SUDOERS_TEMPLATE}.tmpl" | tee "/etc/sudoers.d/${USER_NAME}"

# Needs to be at the end, where preloadable_libiconv is available.
ENV LD_PRELOAD="/usr/lib/preloadable_libiconv.so /usr/local/bin/php"

USER php
