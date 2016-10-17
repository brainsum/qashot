#!/usr/bin/env bash

# Some colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NO_COLOR='\033[0m'

# Greeter text.
printf "Setting up the amazee.io environment.\n\n"

# Copy files and folders to the document root.
printf "Copying files to the document root.. "
ORIG_PWD=$(pwd)
TARGET_DIR="../../"

cp --preserve=mode -r "./root/." "${TARGET_DIR}" \
    && printf "${GREEN}Done!\n${NO_COLOR}" \
    || { printf "${RED}Error!${NO_COLOR}\n"; exit 1; }

printf "Renaming helper scripts.. \n"
printf "Navigating to ${TARGET_DIR} \n"
cd "${TARGET_DIR}"

printf "1/2.. "
mv "example.startup.sh" "startup.sh" \
    && printf "${GREEN}Done!\n${NO_COLOR}" \
    || { printf "${RED}Error!${NO_COLOR}\n"; exit 1; }

printf "2/2.. "
mv "example.shutdown.sh" "shutdown.sh" \
    && printf "${GREEN}Done!\n${NO_COLOR}" \
    || { printf "${RED}Error!${NO_COLOR}\n"; exit 1; }

printf "Navigating to ${ORIG_PWD} \n"
cd "${ORIG_PWD}"

# Copy settings files.
TARGET_DIR="../../sites/default/"
mkdir -p -- "${TARGET_DIR}" \
    && printf "${GREEN}Needed directories found or created!${NO_COLOR} \n" \
    || { printf "${RED}Creating directories failed!${NO_COLOR}"; exit 1; }

printf "Copying settings.. "
cp --preserve=mode -r "./settings/." "${TARGET_DIR}" \
    && printf "${GREEN}Done!\n${NO_COLOR}" \
    || { printf "${RED}Error!${NO_COLOR}\n"; exit 1; }

# Creating needed folders.
TARGET_DIR="../../"
printf "Navigating to ${TARGET_DIR} \n"
cd "${TARGET_DIR}"

mkdir -p -- "sites/default/files" \
    && printf "${GREEN}Needed directories found or created!${NO_COLOR} \n" \
    || { printf "${RED}Creating directories failed!${NO_COLOR}"; exit 1; }

# Fix permissions.
printf "Fixing permissions.. \n"
# 644 for most files, 755 for most folders.
find . -type d -exec chmod 2755 {} \;
find . -type f -exec chmod 644 {} \;

# 444 for all settings files.
find "sites/default" -name "*settings.php" -exec chmod 444 {} \;

# 775 for files
chmod 2775 "sites/default/files"

# 3201 is the ID of the amazee.io user "drupal"
# 33 is the ID of the amazee.io user "www-data"
chown 3201:33 . -R
# @todo: for security? chmod a-r "CHANGELOG.txt"
# At this point:
#   Amazee files are in their place
#   Permissions are ok

# Install python.
# if python --version
# then
#     echo "Python found. \n"
# else
#     echo "Python not found. \n"
#     # add-apt-repository ppa:fkrull/deadsnakes
#     # sudo apt-get update
#     apt-get install python2.7
# fi

# Install ruby.
if ruby --version
then
    echo "Ruby found. \n"
else
    echo "Ruby not found. \n"
    apt-get install ruby-full
fi

# Install pygmy.
if pygmy --version
then
    echo "Pygmy found. \n"
else
    echo "Pygmy not found. \n"
    gem install pygmy

    # @see: http://askubuntu.com/questions/233222/how-can-i-disable-the-dns-that-network-manager-uses/233223#233223
    # Prepare for pygmy install
    # replace in file:
    #       file: /etc/NetworkManager/NetworkManager.conf
    #       string: dns=dnsmasq
    #       replace with: #dns=dnsmasq
    # sed -i ""
    #
    # restart network-manager \
    #     || printf "${RED}Restarting network manager failed Trying again.. ${NO_COLOR}";
    #
    # service network-manager restart \
    #     && printf "${GREEN}Success!${NO_COLOR} \n" \
    #     || { printf "${RED}Failed!${NO_COLOR}"; exit 1; }
    #
    # printf "${RED}WARNING!${NO_COLOR} You should restart your computer after the installations have finished. \n";
fi


