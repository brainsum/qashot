Skip to content
 
Search or jump to…

Pull requests
Issues
Marketplace
Explore
 @pedro-p Sign out
4
0 0 brainsum/commerce_gls
 Code  Issues 0  Pull requests 0  Projects 0  Wiki  Insights  Settings
commerce_gls/ci-scripts/test_coder.sh
baf1bd9  on Feb 17
@pedro-p pedro-p coding standard check added via travis
    
Executable File  42 lines (33 sloc)  975 Bytes
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

  if ! phpcs --ignore="*.md" --standard="$REVIEW_STANDARD" -p --colors --extensions=php,module,inc,install,test,profile,md "$1"; then
    HAS_ERRORS=1
  fi
}

# Review custom modules, run each folder separately to avoid memory limits.
echo
echo "${LBLUE}> Sniffing Modules following '${REVIEW_STANDARD}' standard. ${RESTORE}"

for dir in $TRAVIS_BUILD_DIR/*/ ; do
  code_review "$dir"
done

exit $HAS_ERRORS
