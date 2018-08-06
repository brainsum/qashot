#!/bin/sh
set -e

# ---------------------------------------------------------------------------- #
#
# Run the coder review.
#
# ---------------------------------------------------------------------------- #

# Check the current build.
if [ -z "${CODE_REVIEW+x}" ] || [ "$CODE_REVIEW" -ne 1 ]; then
 exit 0;
fi

if ! phpcs --standard=../phpcs.xml "${PHPCS_CHECK_DIRECTORY}"; then
    exit 1;
fi

exit 0;
