#!/bin/bash
chown www-data:www-data /var/www/html/storage \
&& chmod 0770 /var/www/html/storage \
&& php-fpm8.1 -F