FROM ubuntu:24.04

ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

USER root
WORKDIR /app

RUN apt-get update && apt-get upgrade -y \
   && mkdir -p /etc/apt/keyrings \
   && apt-get install -y gnupg gosu curl zip unzip git supervisor sqlite3 vim rsync ca-certificates \
   && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
   && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
   && apt-get update \
   && apt-get install -y \
   php8.5-cli \
   php8.5-fpm \
   php8.5-curl \
   php8.5-apcu \
   php8.5-mbstring \
   php8.5-xml \
   php8.5-pcov \
   && php -r "readfile('https://getcomposer.org/installer');" | php -- --version=2.9.2 --install-dir=/usr/bin/ --filename=composer \
   && apt-get -y autoremove \
   && apt-get clean \
   && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
