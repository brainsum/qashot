FROM brainsum/drupal-php:0.1.1

LABEL maintainer="mhavelant"
LABEL description="Custom docker image for QAShot."

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

USER root

# Override base image envs.
ENV \
    APP_NAME="qashot" \
    NODE_VERSION=6.16.0 \
    NODE_ENV=production \
    FIREFOX_VERSION=52.9.0esr \
    # XVFB
    DISPLAY=localhost:0.0

# Backstop and sys deps.
RUN \
    apt-get -y update \
	&& apt-get -y --no-install-recommends install \
        pkg-config=0.29-4+b1 \
        fontconfig=2.11.0-6.7+b1 \
	    xvfb=2:1.19.2-1+deb9u5 \
	    xauth=1:1.0.9-1+b2 \
	    # Sudo replacement.
	    ## @todo: Move to base image.
	    gosu=1.10-1+b2 \
	    # Runtime
	    # QAShot
	    procps=2:3.3.12-3+deb9u1 \
	    # CasperJS
	    python=2.7.13-2 \
	    # Firefox
	    libgtk-3-0=3.22.11-1 \
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
## Based on: https://github.com/nodejs/docker-node/blob/master/6/stretch/Dockerfile
RUN ARCH= && dpkgArch="$(dpkg --print-architecture)" \
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
    # Chrome
    CHROME_BIN=/usr/bin/chromium-browser \
    CHROME_PATH=/usr/lib/chromium/ \
    # Firefox
    ## For firefox 55+. We could also use slimerjs --headless.
    ## @todo: When this works, remove xvfb.
    MOZ_HEADLESS=1 \
    # Versions.
    PHANTOMJS_VERSION=2.1.7 \
    CASPERJS_VERSION=1.1.4 \
    SLIMERJS_VERSION=0.10.3 \
#    BACKSTOPJS_VERSION=3.8.8 \
    BACKSTOPJS_VERSION=3.0.26

# Backstop.
RUN gosu root npm install -g --unsafe-perm=true --allow-root phantomjs@${PHANTOMJS_VERSION} \
    # Empty out tmp, as phantomjs downloads stuff there, but doesn't clean it up.
    && rm -rf -- /tmp/*
RUN gosu root npm install -g --unsafe-perm=true --allow-root casperjs@${CASPERJS_VERSION}
RUN gosu root npm install -g --unsafe-perm=true --allow-root slimerjs@${SLIMERJS_VERSION}
RUN gosu root npm install -g --unsafe-perm=true --allow-root backstopjs@${BACKSTOPJS_VERSION}

USER php
