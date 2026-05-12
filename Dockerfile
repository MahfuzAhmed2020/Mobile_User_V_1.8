FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql
RUN apt-get update && apt-get install -y ssmtp && \
    echo "mailhub=mailhog:1025" >> /etc/ssmtp/ssmtp.conf && \
    echo "FromLineOverride=YES" >> /etc/ssmtp/ssmtp.conf && \
    echo "root=no-reply@mobileuser.test" >> /etc/ssmtp/ssmtp.conf
