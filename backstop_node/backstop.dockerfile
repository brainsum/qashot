## See https://medium.com/@iamnayr/a-multi-part-analysis-of-node-docker-image-sizes-using-yarn-vs-traditional-npm-2c20f034c08f#.epj37e3wa
# For cleanup:
## docker rmi $(docker images -f "dangling=true" -q) && docker volume rm $(docker volume ls -qf dangling=true)

FROM node:8.3.0-slim

WORKDIR /app

COPY . .

RUN apt-get -y update && \
    apt-get -y --no-install-recommends install \
        python \
        build-essential \
        autoconf \
        libtool \
        pkg-config \
        # Stuff for phantomjs
        bzip2 \
        # Stuff for xvfb
        xauth \
        # Stuff for SlimerJS.
        xvfb \
        firefox-esr=52.* && \

    # Install node packages.
    yarn install && \
    #    yarn run build && \
        yarn cache clean && \

    # Cleanup.
    rm -rf \
        /var/lib/apt/lists/* \
        /var/cache/apt/* \
        /usr/include/php \
        /usr/lib/php/build && \

    apt-get -y purge \
        autoconf \
        libtool \
        && \

    apt-get -y clean && \
    apt-get -y autoclean && \
    apt-get -y autoremove

# Note: This means docker-compose exec will default to node.
USER node
# Add node_modules binaries to path.
ENV PATH="${PATH}:/app/node_modules/.bin"
# Run app.
CMD [ "yarn", "start" ]