FROM php:5-fpm

RUN mkdir -p /usr/src/app 
WORKDIR /usr/src/app 

RUN curl https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update
RUN apt-get install -y zlib1g-dev

RUN pecl install zip
RUN docker-php-ext-enable zip

COPY composer.json /usr/src/app/
COPY composer.lock /usr/src/app/

RUN composer install

COPY . /usr/src/app

RUN curl -A "Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5" http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz | gunzip > GeoLite2-City.mmdb.gz
