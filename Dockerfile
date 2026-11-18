FROM php:8.2-apache

# Cài đặt các gói phụ thuộc hệ thống cần thiết cho PostgreSQL, SQLite và GD
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        gd \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Kích hoạt Apache mod_rewrite và mod_headers
RUN a2enmod rewrite headers

# Cấu hình tối ưu PHP cho môi trường Production trên Render
RUN { \
    echo 'upload_max_filesize = 50M'; \
    echo 'post_max_size = 50M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 120'; \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.use_strict_mode = 1'; \
    echo 'date.timezone = Asia/Ho_Chi_Minh'; \
} > /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html

# Copy toàn bộ mã nguồn vào thư mục web server
COPY . /var/www/html/

# Phân quyền thư mục lưu trữ data và uploads
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/uploads

# Script khởi động tự động gán cổng $PORT của Render
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
