#!/bin/sh
set -eu

# Generate SSH host keys on first boot (kept until the container is recreated).
ssh-keygen -A

# Keep ownership right when /home/deployer comes from a fresh volume.
chown deployer:deployer /home/deployer /home/deployer/.ssh /home/deployer/.ssh/authorized_keys
chmod 700 /home/deployer/.ssh
chmod 600 /home/deployer/.ssh/authorized_keys
chown deployer:deployer /var/www/app

php-fpm8.3 --daemonize
nginx

exec /usr/sbin/sshd -D -e
