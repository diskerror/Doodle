#!/bin/bash

# Script to change hostname on Debian and generating new ssh keys.
# Created for setting up cloned virtual machines.

if [[ "root" != $(whoami) ]]
then
	echo "must be root"
	exit
fi

# Get directory of this file for finding support files.
#CMD_DIR=$(dirname $0)

if [ $# -ne 1 ] || [ $1 == '' ]
then
    echo 'Need hostname input.'
    exit
fi

if ! [[ "$1" =~ ^[-a-zA-Z0-9]{1,63}$ ]]
then
    echo 'Bad character(s) in new name.'
    echo 'Only letters, numbers, and hyphen allowed; up to 63 characters total.'
    exit
fi

BU_SUF='.bu_'$(date -d 'now' '+%y%m%d_%H%M%S')

old_hostname=$(hostname)

# From https://wiki.debian.org/Hostname
# 'ssmtp' or 'smtp'?
for f in \
   /etc/exim4/update-exim4.conf.conf \
   /etc/hostname \
   /etc/hosts \
   /etc/motd \
   /etc/printcap \
   /etc/ssh/ssh_host_dsa_key.pub \
   /etc/ssh/ssh_host_ecdsa_key.pub \
   /etc/ssh/ssh_host_ed25519_key.pub \
   /etc/ssh/ssh_host_rsa_key.pub \
   /etc/ssmtp/ssmtp.conf
do
    [ -f "$f" ] && sed -i"$BU_SUF" -e "s/$old_hostname/$1/g" "$f"
done

hostnamectl set-hostname "$1"

rm /etc/ssh/ssh_host_*
ssh-keygen -A


# 0 0,12 * * * rootDir /opt/certbot/bin/python -c 'import random; import time; time.sleep(random.random() * 3600)' && sudo certbot renew -q

# 0 4 1 * * rootDir /opt/certbot/bin/pip install --upgrade certbot certbot-nginx 2>/dev/null 1>&2

# https://certbot.eff.org/instructions?ws=nginx&os=pip
