FROM bitnami/symfony:latest

COPY . /app

ENV SYMFONY_PROJECT_SKELETON symfony/skeleton
ENV SYMFONY_DATABASE_PASSWORD symfony/skeleton
ENV ALLOW_EMPTY_PASSWORD yes

RUN echo extension=curl >> /opt/bitnami/php/etc/php.ini
RUN echo extension=imagick.so >> /opt/bitnami/php/etc/php.ini
RUN echo extension=curl >> /opt/bitnami/php/etc/php-fpm.d/www.conf
RUN echo extension=imagick.so >> /opt/bitnami/php/etc/php-fpm.d/www.conf


# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update && apt-get -y install cron git \
        libmagickcore-6.q16-2-extra \
        potrace wget imagemagick php-imagick curl

RUN echo '*/10 * * * * \
    wget --spider https://web-scraper-api-tu22.onrender.com/ \
    >> /var/log/idle-polling-cron' \
    >> /etc/cron.d/idle-polling

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/idle-polling

# Apply cron job
RUN crontab /etc/cron.d/idle-polling

RUN chmod 777 /app/scripts/deploy.sh
RUN chmod +x /app/scripts/deploy.sh
RUN /app/scripts/deploy.sh
EXPOSE 80

CMD [ "/opt/bitnami/scripts/symfony/run.sh" ]
