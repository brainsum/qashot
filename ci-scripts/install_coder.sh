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

composer global require hirak/prestissimo:^0.3
composer global require drupal/coder:^8.3 squizlabs/php_codesniffer:^3.3 phpcompatibility/php-compatibility:^9.0 jakub-onderka/php-parallel-lint:^1.0 jakub-onderka/php-console-highlighter:^0.4 dealerdirect/phpcodesniffer-composer-installer:^0.4

ls -lsa ${VENDOR_DIR}/bin

${VENDOR_DIR}/bin/phpcs --config-set installed_paths ${VENDOR_DIR}/drupal/coder/coder_sniffer,${VENDOR_DIR}/phpcompatibility/php-compatibility
