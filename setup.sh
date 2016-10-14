#!/usr/bin/env bash

# Some colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NO_COLOR='\033[0m'

# Greeter text.
printf "\n\n"
printf "Hi!\n"
printf "This is a setup tool to make installing this project easier.\n"
printf "${RED}Note:${NO_COLOR} Some commands require ${RED}sudo${NO_COLOR}. Please make sure you have the right permissions for it!\n"
printf "To continue, please choose an environment:\n"

# Data arrays.
# @note: These need to be updated in parallel!
SUPPORTED_OPTIONS=( "amazee.io" )
SCRIPTS=( "./environment/amazee/" )

# Display options.
for i in "${!SUPPORTED_OPTIONS[@]}"
do
    printf "\t%s) %s\n" "$((i+1))" "${SUPPORTED_OPTIONS[$i]}"
done

printf "\t%s) %s\n" "0" "exit"

# Get a valid input.
FINISHED=false
while ! ${FINISHED}
do
    printf "%s: " "Your choice"
    read CHOICE

    if [ ${CHOICE} = 0 ]
    then
        printf "Bye!\n"
        exit 0
    fi

    if [ ${SUPPORTED_OPTIONS[$((CHOICE-1))]+true} ]
    then
        FINISHED=true
    else
        printf "Invalid choice, try again!\n"
    fi
done

printf "\n"

ORIG_PWD=$(pwd)

# Run the required script.
cd "${SCRIPTS[$((CHOICE-1))]}"
printf "Navigating to ${SCRIPTS[$((CHOICE-1))]} \n"

sudo bash "setup.sh" \
    && printf "\n${GREEN}Setup successful!${NO_COLOR}\n" \
    || printf "\n${RED}Setup failed!${NO_COLOR} \n"

cd ${ORIG_PWD}

# For security reasons, invalidate sudo session.
sudo -k