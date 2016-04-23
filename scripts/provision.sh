#!/usr/bin/env bash

# Before run this script...
#--------------------------------------------------------------------------
#
# Get sudo permission.
#   user@server:~$ sudo -s
#
# Add User and group.
#   user@server:~# adduser deployer
#   user@server:~# usermod -G www-data deployer
#   user@server:~# id deployer
#   user@server:~# groups www-data
#
# TROUBLESHOOTING.
#
#   If you encounter error message like "sudo: no tty present
#   and no askpass program specified ...", you can work around this error
#   by adding the following line on your production server's /etc/sudoers.
#
#   user@server:~# visudo
#
#   Add following lines to the file and save.
#
#   deployer ALL=(ALL:ALL) NOPASSWD: ALL
#   %www-data ALL=(ALL:ALL) NOPASSWD:/usr/sbin/service php7.0-fpm restart,/usr/sbin/service nginx restart
#
#--------------------------------------------------------------------------
# How to run
#--------------------------------------------------------------------------
#
#   user@server:~# bash provision.sh deployer password | tee log.txt
#

if [[ -z "$1" ]]
then
  echo "Error: missing required parameters."
  echo "Usage: "
  echo "  ./provision username"
  exit
fi

export DEBIAN_FRONTEND=noninteractive
USERNAME=$1
PASSWD=$2

# Update Package List

apt-get update

# Update System Packages

apt-get -y upgrade

# Force Locale

echo "LC_ALL=en_US.UTF-8" >> /etc/default/locale
locale-gen en_US.UTF-8

# Install Some PPAs

apt-get install -y software-properties-common curl

apt-add-repository ppa:nginx/stable -y
apt-add-repository ppa:rwky/redis -y
apt-add-repository ppa:ondrej/php -y

# gpg: key 5072E1F5: public key "MySQL Release Engineering <mysql-build@oss.oracle.com>" imported
apt-key adv --keyserver ha.pool.sks-keyservers.net --recv-keys 5072E1F5
sh -c 'echo "deb http://repo.mysql.com/apt/ubuntu/ trusty mysql-5.7" >> /etc/apt/sources.list.d/mysql.list'

curl --silent --location https://deb.nodesource.com/setup_5.x | bash -

# Update Package Lists

apt-get update

# Install Some Basic Packages

apt-get install -y --force-yes \
    build-essential \
    dos2unix \
    gcc \
    git \
    libmcrypt4 \
    libpcre3-dev \
    make \
    python2.7-dev \
    python-pip \
    re2c \
    supervisor \
    unattended-upgrades \
    whois \
    vim \
    libnotify-bin;

# Set My Timezone

ln -sf /usr/share/zoneinfo/UTC /etc/localtime

# Install PHP Stuffs

apt-get install -y --force-yes \
    php7.0-cli \
    php7.0-dev \
    php-pgsql \
    php-sqlite3 \
    php-gd \
    php-apcu \
    php-curl \
    php7.0-mcrypt \
    php-imap \
    php-mysql \
    php-memcached \
    php7.0-readline \
    php-xdebug \
    php-mbstring \
    php-xml \
    php7.0-zip \
    php7.0-intl \
    php7.0-bcmath \
    php-soap;

# Install Composer

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Add Composer Global Bin To Path

printf "\nPATH=\"/home/${USERNAME}/.composer/vendor/bin:\$PATH\"\n" | tee -a /home/${USERNAME}/.profile
printf "\nAPP_ENV=production\n" | tee -a /home/${USERNAME}/.profile

# Install Laravel Envoy & Installer

#sudo su $USERNAME <<'EOF'
#/usr/local/bin/composer global require "laravel/envoy=~1.0"
#/usr/local/bin/composer global require "laravel/installer=~1.1"
#EOF

# Set Some PHP CLI Settings

sed -i "s/expose_php = .*/expose_php = Off/" /etc/php/7.0/cli/php.ini
#sed -i "s/error_reporting = .*/error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT/" /etc/php/7.0/cli/php.ini
sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/7.0/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/7.0/cli/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/7.0/cli/php.ini

# Install Nginx & PHP-FPM

apt-get install -y --force-yes nginx php7.0-fpm

rm /etc/nginx/sites-enabled/default
rm /etc/nginx/sites-available/default
service nginx restart

# Add The HHVM Key & Repository

#wget -O - http://dl.hhvm.com/conf/hhvm.gpg.key | apt-key add -
#echo deb http://dl.hhvm.com/ubuntu trusty main | tee /etc/apt/sources.list.d/hhvm.list
#apt-get update
#apt-get install -y hhvm

# Configure HHVM To Run As Homestead

# service hhvm stop
# sed -i 's/#RUN_AS_USER="www-data"/RUN_AS_USER="${USERNAME}"/' /etc/default/hhvm
# service hhvm start

# Start HHVM On System Start

#update-rc.d hhvm defaults

# Setup Some PHP-FPM Options

sed -i "s/expose_php = .*/expose_php = Off/" /etc/php/7.0/fpm/php.ini
#sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/7.0/fpm/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/7.0/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/7.0/fpm/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/7.0/fpm/php.ini
sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/7.0/fpm/php.ini

#echo "xdebug.remote_enable = 1" >> /etc/php5/fpm/conf.d/20-xdebug.ini
#echo "xdebug.remote_connect_back = 1" >> /etc/php5/fpm/conf.d/20-xdebug.ini
#echo "xdebug.remote_port = 9000" >> /etc/php5/fpm/conf.d/20-xdebug.ini
#echo "xdebug.max_nesting_level = 512" >> /etc/php5/fpm/conf.d/20-xdebug.ini

# Copy fastcgi_params to Nginx because they broke it on the PPA

cat > /etc/nginx/fastcgi_params << EOF
fastcgi_param	QUERY_STRING		\$query_string;
fastcgi_param	REQUEST_METHOD		\$request_method;
fastcgi_param	CONTENT_TYPE		\$content_type;
fastcgi_param	CONTENT_LENGTH		\$content_length;
fastcgi_param	SCRIPT_FILENAME		\$request_filename;
fastcgi_param	SCRIPT_NAME		    \$fastcgi_script_name;
fastcgi_param	REQUEST_URI		    \$request_uri;
fastcgi_param	DOCUMENT_URI		\$document_uri;
fastcgi_param	DOCUMENT_ROOT		\$document_root;
fastcgi_param	SERVER_PROTOCOL		\$server_protocol;
fastcgi_param	GATEWAY_INTERFACE	CGI/1.1;
fastcgi_param	SERVER_SOFTWARE		nginx/\$nginx_version;
fastcgi_param	REMOTE_ADDR		    \$remote_addr;
fastcgi_param	REMOTE_PORT		    \$remote_port;
fastcgi_param	SERVER_ADDR		    \$server_addr;
fastcgi_param	SERVER_PORT		    \$server_port;
fastcgi_param	SERVER_NAME		    \$server_name;
fastcgi_param	HTTPS			    \$https if_not_empty;
fastcgi_param	REDIRECT_STATUS		200;
EOF

# Set The Nginx & PHP-FPM User

sed -i "s/user www-data;/user ${USERNAME};/" /etc/nginx/nginx.conf
sed -i "s/# server_names_hash_bucket_size.*/server_names_hash_bucket_size 64;/" /etc/nginx/nginx.conf

sed -i "s/user = www-data/user = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/group = www-data/group = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf

sed -i "s/listen\.owner.*/listen.owner = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/listen\.group.*/listen.group = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/7.0/fpm/pool.d/www.conf

service nginx restart
service php7.0-fpm restart

# Add User To WWW-Data

#usermod -a -G www-data $USERNAME
#id $USERNAME
#groups www-data

# Install Node

apt-get install -y --force-yes nodejs
#/usr/bin/npm install -g gulp
#/usr/bin/npm install -g bower

# Install SQLite

apt-get install -y --force-yes sqlite3 libsqlite3-dev

# Install MySQL

debconf-set-selections <<< "mysql-community-server mysql-community-server/data-dir select ''"
debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password ${PASSWD}"
debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password ${PASSWD}"
apt-get install -y mysql-server

# Configure MySQL Password Lifetime

echo "default_password_lifetime = 0" >> /etc/mysql/my.cnf

# Configure MySQL Remote Access

sed -i '/^bind-address/s/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf

mysql --user="root" --password="${PASSWD}" -e "GRANT ALL ON *.* TO root@'0.0.0.0' IDENTIFIED BY '${PASSWD}' WITH GRANT OPTION;"
service mysql restart

mysql --user="root" --password="${PASSWD}" -e "CREATE USER '${USERNAME}'@'0.0.0.0' IDENTIFIED BY '${PASSWD}';"
mysql --user="root" --password="${PASSWD}" -e "GRANT ALL ON *.* TO '${USERNAME}'@'0.0.0.0' IDENTIFIED BY '${PASSWD}' WITH GRANT OPTION;"
mysql --user="root" --password="${PASSWD}" -e "GRANT ALL ON *.* TO '${USERNAME}'@'%' IDENTIFIED BY '${PASSWD}' WITH GRANT OPTION;"
mysql --user="root" --password="${PASSWD}" -e "FLUSH PRIVILEGES;"
#mysql --user="root" --password="${PASSWD}" -e "CREATE DATABASE ${USERNAME};"
service mysql restart

# Add Timezone Support To MySQL

mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --user=root --password=${PASSWD} mysql

# Install Postgres

apt-get install -y postgresql-9.5 postgresql-contrib-9.5

# Configure Postgres Remote Access

echo "host all all 10.0.2.2/32 md5" | tee -a /etc/postgresql/9.5/main/pg_hba.conf
sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/g" /etc/postgresql/9.5/main/postgresql.conf
echo "host all all 10.0.2.2/32 md5" | tee -a /etc/postgresql/9.5/main/pg_hba.conf
sudo -u postgres psql -c "CREATE ROLE ${USERNAME} LOGIN UNENCRYPTED PASSWORD '${PASSWD}' SUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;"
sudo -u postgres /usr/bin/createdb --echo --owner=${USERNAME} ${USERNAME}
service postgresql restart

# Install Blackfire

# apt-get install -y blackfire-agent blackfire-php

# Install A Few Other Things

apt-get install -y redis-server memcached beanstalkd

# Configure Beanstalkd

sed -i "s/#START=yes/START=yes/" /etc/default/beanstalkd
/etc/init.d/beanstalkd start

# Enable Swap Memory

/bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024
/sbin/mkswap /var/swap.1
/sbin/swapon /var/swap.1

# Register bash aliases

cat > /home/${USERNAME}/.bash_aliases << EOF
alias ..="cd .."
alias ...="cd ../.."

alias h='cd ~'
alias c='clear'

function serve() {
    if [[ "\$1" && "\$2" ]]
    then
        sudo dos2unix ~/serve.sh
        sudo bash ~/serve.sh "\$1" "\$2" 80
    else
        echo "Error: missing required parameters."
        echo "Usage: "
        echo "  serve domain path"
    fi
}
EOF