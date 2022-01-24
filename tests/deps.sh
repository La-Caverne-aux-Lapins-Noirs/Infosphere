#!/bin/sh
apt-get install php-sqlite3
apt-get install phpunit
apt-get install php-pear php-dev libcurl3-openssl-dev
pecl install pecl_http
pecl install xdebug
/etc/init.d/apache2 restart
echo 'zend_extension="/usr/lib/php/20180731/xdebug.so"' >> /etc/php/7.3/cli/php.ini
