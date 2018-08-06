#!/usr/bin/env sh

echo "Source: https://github.com/exakat/php-static-analysis-tools \n";

VENDOR_DIR="vendor"
VENDOR_BIN_DIR="vendor/bin"

#MODULE="public_preview"
DIR_TO_CHECK="web/modules/custom"

echo "\n\n"
echo "PHP Lint with version: ---------------"
php --version
echo "\n"

${VENDOR_BIN_DIR}/parallel-lint ${DIR_TO_CHECK}

echo "\n\n";
echo "PHPCS / Drupal+Practice+Compatibility7.0+)";
${VENDOR_BIN_DIR}/phpcs --config-set installed_paths ${VENDOR_DIR}/drupal/coder/coder_sniffer,${VENDOR_DIR}/phpcompatibility/php-compatibility
${VENDOR_BIN_DIR}/phpcs --standard=phpcs.xml "${DIR_TO_CHECK}" && echo "PHPCS: No error" || echo "PHPCS: Error"

#phpcbf --standard=phpcs.xml "${DIR_TO_CHECK}"

#d
#echo "\n\n"
#echo "PHPMD ----------------------------\n\n"
#
## Removed: controversial [camelCase stuff]
#STANDARDS="cleancode,codesize,naming,design,unusedcode"
## Don't care about camelCase stuff.
#phpmd "$DIR_TO_CHECK" text "$STANDARDS" --suffixes=php,module,inc,install,test,profile,theme --strict
#
#echo "\n\n"
#echo "PHP Copy Paste Detector ----------------------------\n\n"
#
#NAMES="*.php,*.module,*.module,*.theme,*.profile,*.inc,*.test"
#phpcpd --names "$NAMES" "$DIR_TO_CHECK"
#
#echo "\n\n"
#echo "PHP LOC ----------------------------\n\n"
#
#phploc --names "$NAMES" "$DIR_TO_CHECK"
#
#echo "\n\n"
#echo "PHP Metrics ----------------------------\n\n"
#phpmetrics "$DIR_TO_CHECK"
