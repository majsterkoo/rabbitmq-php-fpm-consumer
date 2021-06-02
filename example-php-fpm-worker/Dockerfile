FROM composer:2.0
WORKDIR /usr/src/myapp
COPY . .
WORKDIR /usr/src/myapp/example-php-fpm-worker
RUN ls
RUN composer install --ignore-platform-reqs

FROM php:8.0-fpm-alpine
WORKDIR /usr/src/myapp
COPY --from=0 /usr/src/myapp/ .

EXPOSE 9000
