# Build all needed extesion
ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-fpm-alpine AS build

MAINTAINER Andrii Kasian <akasian@adobe.com>

RUN apk add --update --no-cache ${PHPIZE_DEPS} bash git tar \
    freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev \
    libressl-dev \
    libzip-dev \
    oniguruma-dev


# for local only ENV CFLAGS="-O3 -march=native"
RUN docker-php-source extract
RUN case ${PHP_VERSION}  in \
  7.3) docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ ;; \
  *) docker-php-ext-configure gd --with-freetype --with-jpeg ;; \
esac

RUN case ${PHP_VERSION}  in \
  7.3) ;; \
  *) docker-php-ext-configure mbstring --disable-mbregex  ;; \
esac

RUN docker-php-ext-install -j$(nproc) \
    sockets \
    gd \
    mbstring \
    opcache \
    pcntl \
    zip \
    simplexml \
    pdo_sqlite

RUN pecl channel-update pecl.php.net
RUN pecl install -o -f mongodb && docker-php-ext-enable mongodb


RUN php --version && php -m
RUN php -r 'print_r(gd_info());'
RUN cat /etc/alpine-release

FROM alpine:3.15 AS base

ENV APP_ROOT=/magento
ENV NOT_ROOT_USER=www-data


COPY --from=build /usr/local/bin/php /usr/local/bin/php
COPY --from=build /usr/local/sbin/php-fpm /usr/local/sbin/php-fpm
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
RUN apk add --update --no-cache \
		ca-certificates \
		bash \
		git \
		vim \
    unzip \
		curl \
		openssh-client \
		procps \
		strace \
		openssl \
		mysql-client \
		$( \
    		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
    			| tr ',' '\n' \
    			| sort -u \
    			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    			| tr '\n' ' ' \
    	) \
    && rm -rf /var/cache/apk/* &



RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


#Setup sendmail in php
RUN echo '#!/usr/bin/php' > /usr/local/bin/sendmail \
 && chmod +x /usr/local/bin/sendmail

# Clean Up
RUN rm -rf /var/cache/apk/* && \
    rm -rf /tmp/* /var/tmp/*

### Collapse layers ###

FROM alpine:3.15
COPY --from=base / .

COPY . /app
WORKDIR /app

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "./webroot", "./etc/router.php"]
