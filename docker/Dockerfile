# 使用指定基础镜像
FROM php:8.2-fpm-bullseye

# 设置环境变量 DEBIAN_FRONTEND
ENV DEBIAN_FRONTEND noninteractive

COPY www.conf.ini /usr/local/etc/php-fpm.d/www.conf
COPY php.ini-production /usr/local/etc/php/php.ini-production

# 安装必要的软件包和依赖项
RUN apt-get update \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN docker-php-ext-install pcntl bcmath mysqli pdo_mysql \
    && docker-php-ext-enable pcntl bcmath mysqli

# 安装 git unzip (安装laravel需要)
RUN apt install git unzip -y

RUN pecl install redis \
	&& docker-php-ext-enable redis

RUN apt install -y redis-server redis-tools \
    && /etc/init.d/redis-server start
# 安装 composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer