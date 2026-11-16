# Fake VPS used as a deployment target during development and in e2e tests.
# Mirrors a typical Ubuntu 24.04 Laravel server: OpenSSH, PHP 8.3-FPM, Composer,
# Node 20, Nginx and an unprivileged `deployer` user.
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git gnupg nginx openssh-server sudo unzip \
        php8.3-cli php8.3-fpm php8.3-bcmath php8.3-curl php8.3-intl php8.3-mbstring \
        php8.3-pgsql php8.3-mysql php8.3-sqlite3 php8.3-xml php8.3-zip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Deploy user: key-only SSH login, owns /var/www.
RUN useradd --create-home --shell /bin/bash deployer \
    && install -d -m 700 -o deployer -g deployer /home/deployer/.ssh \
    && install -m 600 -o deployer -g deployer /dev/null /home/deployer/.ssh/authorized_keys \
    && install -d -o deployer -g deployer /var/www/app \
    && mkdir -p /run/sshd /run/php

# Containers have no systemd: this shim implements the only command Pushpilot
# may run with sudo, so the sudoers line below is identical to a real server.
COPY systemctl /usr/bin/systemctl
COPY sudoers-deployer /etc/sudoers.d/deployer
RUN chmod 755 /usr/bin/systemctl && chmod 440 /etc/sudoers.d/deployer && visudo -cf /etc/sudoers.d/deployer

COPY sshd_config /etc/ssh/sshd_config.d/pushpilot.conf
COPY nginx.conf /etc/nginx/sites-available/default
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh

EXPOSE 22 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
