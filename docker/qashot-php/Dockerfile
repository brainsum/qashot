FROM brainsum/drupal-php:0.3.0

LABEL maintainer="mhavelant"
LABEL description="Custom docker image for QAShot."

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

USER root
# Enable user namespaces for avoiding Chrome and etc. issues.
COPY ["conf/00-local-userns.conf", "/etc/sysctl.d/00-local-userns.conf"]

RUN \
    # Fix for chormium sandbox.
    ## Enable namespace cloning.
    ### See: https://github.com/GoogleChrome/puppeteer/blob/master/docs/troubleshooting.md#recommended-enable-user-namespace-cloning
#    && gosu root sysctl -w kernel.unprivileged_userns_clone=1 \
    ## The WEB_USER_NAME user is running it mainly.
    ## The WEB_USER_NAME user has /var/www set as its home dir.
    ## @todo: Fix web user permissions in base image.
    gosu "${WEB_USER_NAME}" mkdir -p "/var/www/Downloads" \
    && gosu root usermod -aG audio,video ${WEB_USER_NAME} \
    ## Also fix for USER_NAME, just in case..
    && gosu "${USER_NAME}" mkdir -p "/home/${USER_NAME}/Downloads" \
    && gosu root usermod -aG audio,video ${USER_NAME}

# Override base image envs.
ENV \
    APP_NAME="qashot" \
    NODE_VERSION=8.16.0 \
    NODE_ENV=production \
    FIREFOX_VERSION=59.0b9 \
    # XVFB/Xdummy
    DISPLAY=:0.0

# Backstop and sys deps.
RUN \
    apt-get -y update \
	&& apt-get -y --no-install-recommends install \
        pkg-config=0.29-4+b1 \
        # apt-utils=??? \
        # Browser common
        libasound2=1.1.3-5 \
        libatk1.0-0=2.22.0-1 \
        libc6=2.24-11+deb9u4 \
        libcairo2=1.14.8-1 \
        libdbus-1-3=1.10.28-0+deb9u1 \
        libfontconfig1=2.11.0-6.7+b1 \
        libfreetype6=2.6.3-3.2 \
        libgcc1=1:6.3.0-18+deb9u1 \
        libgdk-pixbuf2.0-0=2.36.5-2+deb9u2 \
        libglib2.0-0=2.50.3-2 \
        libpango-1.0-0=1.40.5-1 \
        libstdc++6=6.3.0-18+deb9u1 \
        libvpx4=1.6.1-3+deb9u1 \
        libx11-6=2:1.6.4-3+deb9u1 \
        libx11-xcb1=2:1.6.4-3+deb9u1 \
        libxcb1=1.12-1 \
        libxcomposite1=1:0.4.4-2 \
        libxdamage1=1:1.1.4-2+b3 \
        libxext6=2:1.3.3-1+b2 \
        libxfixes3=1:5.0.3-1 \
        libxrender1=1:0.9.10-1 \
        zlib1g=1:1.2.8.dfsg-5 \
        # Firefox-esr
        libgtk-3-0=3.22.11-1 \
        ttf-freefont=20120503-6 \
        fontconfig=2.11.0-6.7+b1 \
        libjsoncpp1=1.7.4-3 \
        libstartup-notification0=0.12-4+b2 \
        libxt6=1:1.1.5-1 \
        dbus=1.10.28-0+deb9u1 \
        ## Already there, but let's define them anyways.
        libcairo-gobject2=1.14.8-1 \
        libdbus-glib-1-2=0.108-2 \
        libffi6=3.2.1-6 \
        libxcb-shm0=1.12-1 \
        debianutils=4.8.1.1 \
        # Chromium.
        libatomic1=6.3.0-18+deb9u1 \
        libatspi2.0-0=2.22.0-6+deb9u1 \
        libswresample2=7:3.2.14-1~deb9u1 \
        libavcodec57=7:3.2.14-1~deb9u1 \
        libavformat57=7:3.2.14-1~deb9u1 \
        libavutil55=7:3.2.14-1~deb9u1 \
        libcups2=2.2.1-8+deb9u2 \
        libdrm2=2.4.74-1 \
        libevent-2.0-5=2.0.21-stable-3 \
        libexpat1=2.2.0-2+deb9u2 \
        libflac8=1.3.2-1 \
        libgtk2.0-0=2.24.31-2 \
        libicu57=57.1-6+deb9u2 \
        libjpeg62-turbo=1:1.5.1-2 \
        libminizip1=1.1-8+b1 \
        libnspr4=2:4.12-6 \
        libnss3=2:3.26.2-1.1+deb9u1 \
        libopenjp2-7=2.1.2-1.1+deb9u3 \
        libopus0=1.2~alpha2-1 \
        libpangocairo-1.0-0=1.40.5-1 \
        libpangoft2-1.0-0=1.40.5-1 \
        libpci3=1:3.5.2-1 \
        libpng16-16=1.6.28-1+deb9u1 \
        libpulse0=10.0-1+deb9u1 \
        libre2-3=20170101+dfsg-1 \
        libsnappy1v5=1.1.3-3 \
        libwebp6=0.5.2-1 \
        libwebpdemux2=0.5.2-1 \
        libwebpmux2=0.5.2-1 \
        libxcursor1=1:1.1.14-1+deb9u2 \
        libxi6=2:1.7.9-1 \
        libxml2=2.9.4+dfsg1-2.2+deb9u2 \
        libxrandr2=2:1.5.1-1 \
        libxslt1.1=1.1.29-2.1 \
        libxss1=1:1.2.2-1 \
        libxtst6=2:1.2.3-1 \
        x11-utils=7.7+3+b1 \
        xdg-utils=1.1.1-1+deb9u1 \
        libatk-bridge2.0-0=2.22.0-2 \
	    # QAShot
	    procps=2:3.3.12-3+deb9u1 \
	    # CasperJS
	    python=2.7.13-2 \
	    \
	    # Explicitly install FF.
        && curl -o "firefox-${FIREFOX_VERSION}.tar.bz2" "https://ftp.mozilla.org/pub/firefox/releases/${FIREFOX_VERSION}/linux-$(uname -m)/en-US/firefox-${FIREFOX_VERSION}.tar.bz2" \
        && tar -xjf "firefox-${FIREFOX_VERSION}.tar.bz2" \
        && mv firefox /opt/ \
        && ln -s /opt/firefox/firefox /usr/bin/firefox \
	    # Cleanup.
        && rm -rf \
            /var/lib/apt/lists/* \
            /var/cache/apt/* \
        && apt-get -y purge -- \
            *-dev \
        && apt-get -y clean \
        && apt-get -y autoclean \
        && apt-get -y autoremove

# Build node.
## Based on: https://github.com/nodejs/docker-node/blob/master/8/stretch/Dockerfile
# @note: disable-ipv6 is needed to solve this issue:
## gpg: keyserver receive failed: Cannot assign requested address
RUN gosu root mkdir -m 600 ~/.gnupg \
  && gosu root echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf \
  && ARCH= && dpkgArch="$(dpkg --print-architecture)" \
  && case "${dpkgArch##*-}" in \
    amd64) ARCH='x64';; \
    ppc64el) ARCH='ppc64le';; \
    s390x) ARCH='s390x';; \
    arm64) ARCH='arm64';; \
    armhf) ARCH='armv7l';; \
    i386) ARCH='x86';; \
    *) echo "unsupported architecture"; exit 1 ;; \
  esac \
  # gpg keys listed at https://github.com/nodejs/node#release-keys
  && set -ex \
  && for key in \
    94AE36675C464D64BAFA68DD7434390BDBE9B9C5 \
    FD3A5288F042B6850C66B31F09FE44734EB7990E \
    71DCFD284A79C3B38668286BC97EC7A07EDE3FC1 \
    DD8F2338BAE7501E3DD5AC78C273792F7D83545D \
    C4F0DFFF4E8C1A8236409D08E73BC641CC11F4C8 \
    B9AE9905FFD7803F25714661B63B535A4C206CA9 \
    77984A986EBC2AA786BC0F66B01FBB92821C587A \
    8FCCA13FEF1D0C2E91008E09770F7A9A5AE15600 \
    4ED778F539E3634C779C87C6D7062848A1AB005C \
    A48C2BEE680E841632CD4E44F07496B3EB3C1762 \
    B9E2F5981AA6E0CD28160D9FF13993A75599653C \
  ; do \
    gpg --batch --keyserver hkp://p80.pool.sks-keyservers.net:80 --recv-keys "$key" || \
    gpg --batch --keyserver hkp://ipv4.pool.sks-keyservers.net --recv-keys "$key" || \
    gpg --batch --keyserver hkp://pgp.mit.edu:80 --recv-keys "$key" ; \
  done \
  && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-linux-$ARCH.tar.xz" \
  && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/SHASUMS256.txt.asc" \
  && gpg --batch --decrypt --output SHASUMS256.txt SHASUMS256.txt.asc \
  && grep " node-v$NODE_VERSION-linux-$ARCH.tar.xz\$" SHASUMS256.txt | sha256sum -c - \
  && tar -xJf "node-v$NODE_VERSION-linux-$ARCH.tar.xz" -C /usr/local --strip-components=1 --no-same-owner \
  && rm "node-v$NODE_VERSION-linux-$ARCH.tar.xz" SHASUMS256.txt.asc SHASUMS256.txt \
  && ln -s /usr/local/bin/node /usr/local/bin/nodejs

ENV \
    # NPM
    NPM_CONFIG_UNSAFE_PERM=true \
    # Firefox
    MOZ_HEADLESS=1 \
    # Versions.
    PHANTOMJS_VERSION=2.1.16 \
    CASPERJS_VERSION=1.1.4 \
    SLIMERJS_VERSION=1.0.0 \
    BACKSTOPJS_VERSION=3.8.8

# Backstop.
RUN gosu root npm install -g --unsafe-perm=true --allow-root phantomjs-prebuilt@${PHANTOMJS_VERSION} \
    # Empty out tmp, as phantomjs downloads stuff there, but doesn't clean it up.
    && rm -rf -- /tmp/*
RUN gosu root npm install -g --unsafe-perm=true --allow-root casperjs@${CASPERJS_VERSION}
RUN gosu root npm install -g --unsafe-perm=true --allow-root slimerjs@${SLIMERJS_VERSION}
RUN gosu root npm install -g --unsafe-perm=true --allow-root backstopjs@${BACKSTOPJS_VERSION}

USER php
