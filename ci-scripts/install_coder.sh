#!/bin/sh
set -e

# ---------------------------------------------------------------------------- #
#
# Installs The coder library so we can use t for code reviews.
#
# ---------------------------------------------------------------------------- #

# Check the current build.
if [ -z "${CODE_REVIEW+x}" ] || [ "$CODE_REVIEW" -ne 1 ]; then
 exit 0;
fi

cd "$TRAVIS_BUILD_DIR"
composer global require drupal/coder wimg/php-compatibility jakub-onderka/php-parallel-lint jakub-onderka/php-console-highlighter
phpcs --config-set installed_paths ~/.composer/vendor/drupal/coder/coder_sniffer,~/.composer/vendor/wimg/php-compatibility
