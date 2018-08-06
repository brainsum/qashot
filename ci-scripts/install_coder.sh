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
echo "------- DEBUG --------"
echo "Vendor dir: ${VENDOR_DIR}"
echo "List vendors.."
ls ${VENDOR_DIR}
echo "List wimg.."
ls ${VENDOR_DIR}/wimg/php-compatibility
echo "------- END DEBUG ---------"

composer global require hirak/prestissimo
composer global require drupal/coder wimg/php-compatibility jakub-onderka/php-parallel-lint jakub-onderka/php-console-highlighter
phpcs --config-set installed_paths ${VENDOR_DIR}/drupal/coder/coder_sniffer,${VENDOR_DIR}/wimg/php-compatibility
