#!/bin/sh
set -e

# Render tự động cung cấp biến môi trường $PORT (mặc định là 80 hoặc 10000)
PORT="${PORT:-80}"

# Cập nhật cổng lắng nghe của Apache theo $PORT được Render chỉ định
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Đảm bảo thư mục dữ liệu và uploads tồn tại với quyền đọc/ghi
mkdir -p /var/www/html/data /var/www/html/uploads
chown -R www-data:www-data /var/www/html/data /var/www/html/uploads

exec "$@"
