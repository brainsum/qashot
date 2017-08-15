#!/bin/sh

# ---------------------------------------------------------------------------- #
#
# Run the coder review.
#
# ---------------------------------------------------------------------------- #

# Check the current build.
if [ -z "${CODE_REVIEW+x}" ] || [ "$CODE_REVIEW" -ne 1 ]; then
 exit 0;
fi

HAS_ERRORS=0

##
# Function to run the actual code review
#
# This function takes 2 params:
# @param string $1
#   The file path to the directory or file to check.
# @param string $2
#   The ignore pattern(s).
##
code_review () {
  echo "${LWHITE}$1${RESTORE}"

  if ! phpcs --standard="$REVIEW_STANDARD" -p --colors --extensions=php,module,inc,install,test,profile "$1"; then
    HAS_ERRORS=1
  fi
}

# Review custom modules, run each folder separately to avoid memory limits.
echo
echo "${LBLUE}> Sniffing Modules following '${REVIEW_STANDARD}' standard. ${RESTORE}"

for dir in $TRAVIS_BUILD_DIR/web/modules/custom/*/ ; do
  code_review "$dir"
done

exit $HAS_ERRORS
