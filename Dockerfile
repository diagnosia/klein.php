# Development image for running the test suite and code checks (Klein is a library, there is no app to serve).
# Follows the enterprise base image (diagnosia-enterprise/docker-base-image): official php image on
# Debian 13 (trixie), PECL extensions pinned and installed with PIE, composer from its bin image.
ARG PHP_VERSION=8.5.13

FROM composer/composer:2.10.3-bin AS composer
# PIE, the PHP Foundation's installer for PECL extensions: https://github.com/php/pie
FROM ghcr.io/php/pie:1.5.5-bin AS pie

FROM php:${PHP_VERSION}-cli-trixie

ENV TZ=UTC
ENV LANG="C.UTF-8"
ENV DEBIAN_FRONTEND=noninteractive

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# git and unzip are needed by composer; vim for interactive debugging shells
RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip vim \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PECL extensions, compiled against the PHP of the base image. mbstring, curl and xml are already part
# of the php image. apcu is used for the route regex cache (src/Klein/Klein.php), pcov for coverage
# and xdebug for debugging. The php image ships the compiler toolchain ($PHPIZE_DEPS) PIE needs.
COPY --from=pie /pie /usr/local/bin/pie
RUN pie install apcu/apcu:5.1.28 \
    && pie install pecl/pcov:1.0.12 \
    && pie install xdebug/xdebug:3.5.3 \
    && rm -rf /tmp/* ~/.pie ~/.cache \
    && php -m > /dev/null

# php.ini-development (dev image: errors displayed) plus the timezone default from the enterprise base image
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf '[PHP]\ndate.timezone = UTC\n' > "$PHP_INI_DIR/conf.d/00-base.ini"

COPY --from=composer /composer /usr/bin/composer

WORKDIR /app

CMD ["bash"]
