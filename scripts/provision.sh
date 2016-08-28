#!/usr/bin/env bash

##
#--------------------------------------------------------------------------
# BEFORE RUN THE SCRIPT, YOU MUST DO THE FOLLOWING.
#--------------------------------------------------------------------------
#
#   WITHOUT FOLLOWING CONFIGURATION CHANGE, YOU MAY ENCOUNTER:
#   "sudo: no tty present and no askpass program specified ...",
#
#   RUN THIS COMMAND TO EDIT /etc/sudoers.
#   user@server:~$ sudo -s
#   root@server:~# visudo
#
#   ADD THE FOLLOWING LINES TO THE FILE AND SAVE.
#   (We assume username you are going to use is 'deployer')
#
#   deployer ALL=(ALL:ALL) NOPASSWD: ALL
#   %www-data ALL=(ALL:ALL) NOPASSWD:/usr/sbin/service php7.0-fpm restart,/usr/sbin/service nginx restart
##

##
#--------------------------------------------------------------------------
# How to run
#--------------------------------------------------------------------------
#
#   user@server:~$ sudo -s
#   root@server:~# wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/provision.sh
#   root@server:~# bash provision.sh deployer | tee log.txt
##

##
# House keeping: makes script not running without proper arguments.
##

if [[ -z "$1" ]]
then
  echo "Error: missing required parameters."
  echo "Usage: "
  echo "  bash provision.sh username"
  echo "    username    : OS and mysql username"
  exit
fi

##
# Set variables and makes the job not be interrupted by interactive questions.
##

export DEBIAN_FRONTEND=noninteractive
USERNAME=$1
PASSWD=$2

##
# Create OS user, add ubuntu user to www-data group.
##

useradd $USERNAME -g www-data -m
# This will prompt for UNIX password.
# Googled for unattended way of setting a password, but...
echo "Password for user, ${USERNAME}."
passwd $USERNAME
usermod -G www-data ubuntu

##
# Update package list & update system packages.
##

apt-get update
apt-get -y upgrade

##
# Force Locale.
##

echo "LC_ALL=en_US.UTF-8" >> /etc/default/locale
locale-gen en_US.UTF-8

##
# Install apt-add-repository extension.
# Install some PPAs(Personal Package Archive).
##

apt-get install -y software-properties-common curl

apt-add-repository ppa:nginx/development -y
apt-add-repository ppa:chris-lea/redis-server -y
apt-add-repository ppa:ondrej/php -y

##
# Add MySql source registry's key in order to use latest version.
##

# apt-key adv --keyserver ha.pool.sks-keyservers.net --recv-keys 5072E1F5
# sh -c 'echo "deb http://repo.mysql.com/apt/ubuntu/ trusty mysql-5.7" >> /etc/apt/sources.list.d/mysql.list'

curl -s https://packagecloud.io/gpg.key | apt-key add -

##
# Update package list again to reflect newly added MySql key.
##

apt-get update

##
# Install Some Basic Packages
##

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
    vim;

##
# Set timezone.
# Set server timezone to UTC is the BEST PRACTICE.
##

ln -sf /usr/share/zoneinfo/UTC /etc/localtime

##
# Install PHP Stuffs
##

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

##
# Install composer.
# Composer is a PHP's standard (library) dependency manager.
# @see https://getcomposer.org
##

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

##
# Add Composer Global Bin To Path
##

printf "\nPATH=\"/home/${USERNAME}/.composer/vendor/bin:\$PATH\"\n" | tee -a /home/${USERNAME}/.profile

##
# Set PHP CLI configuration.
##

sed -i "s/expose_php = .*/expose_php = Off/" /etc/php/7.0/cli/php.ini
# Commented out because out-of-box value is already confitured for production.
# sed -i "s/error_reporting = .*/error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT/" /etc/php/7.0/cli/php.ini
sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/7.0/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/7.0/cli/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/7.0/cli/php.ini

# Install Nginx & PHP-FPM.

apt-get install -y --force-yes \
    nginx \
    php7.0-fpm;

rm /etc/nginx/sites-enabled/default
rm /etc/nginx/sites-available/default
service nginx restart

##
# Setup PHP-FPM configurations
##

sed -i "s/expose_php = .*/expose_php = Off/" /etc/php/7.0/fpm/php.ini
# Commented out because out-of-box value is already confitured for production.
# sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/7.0/fpm/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/7.0/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/7.0/fpm/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php/7.0/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/7.0/fpm/php.ini
sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/7.0/fpm/php.ini

##
# Disable xdebug on the CLI.
##

sudo phpdismod -s cli xdebug

# Copy fastcgi_params to Nginx because they broke it on the PPA

cat > /etc/nginx/fastcgi_params << EOF
fastcgi_param   QUERY_STRING        \$query_string;
fastcgi_param   REQUEST_METHOD      \$request_method;
fastcgi_param   CONTENT_TYPE        \$content_type;
fastcgi_param   CONTENT_LENGTH      \$content_length;
fastcgi_param   SCRIPT_FILENAME     \$request_filename;
fastcgi_param   SCRIPT_NAME         \$fastcgi_script_name;
fastcgi_param   REQUEST_URI         \$request_uri;
fastcgi_param   DOCUMENT_URI        \$document_uri;
fastcgi_param   DOCUMENT_ROOT       \$document_root;
fastcgi_param   SERVER_PROTOCOL     \$server_protocol;
fastcgi_param   GATEWAY_INTERFACE   CGI/1.1;
fastcgi_param   SERVER_SOFTWARE     nginx/\$nginx_version;
fastcgi_param   REMOTE_ADDR         \$remote_addr;
fastcgi_param   REMOTE_PORT         \$remote_port;
fastcgi_param   SERVER_ADDR         \$server_addr;
fastcgi_param   SERVER_PORT         \$server_port;
fastcgi_param   SERVER_NAME         \$server_name;
fastcgi_param   HTTPS               \$https if_not_empty;
fastcgi_param   REDIRECT_STATUS     200;
EOF

##
# Set Nginx & PHP-FPM user
##

sed -i "s/user www-data;/user ${USERNAME};/" /etc/nginx/nginx.conf
sed -i "s/# server_names_hash_bucket_size.*/server_names_hash_bucket_size 64;/" /etc/nginx/nginx.conf

sed -i "s/user = www-data/user = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/group = www-data/group = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf

sed -i "s/listen\.owner.*/listen.owner = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/listen\.group.*/listen.group = ${USERNAME}/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/7.0/fpm/pool.d/www.conf

service nginx restart
service php7.0-fpm restart

##
# Install MySQL.
##

debconf-set-selections <<< "mysql-community-server mysql-community-server/data-dir select ''"
debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password secret"
debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password secret"
apt-get install -y mysql-server

##
# Configure MySQL password lifetime.
##

# Comment out to avoid error.
# echo "default_password_lifetime = 0" >> /etc/mysql/my.cnf

##
# Configure MySQL to be accessible from a remote computer.
##

sed -i '/^bind-address/s/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf

##
# Grant root user's privilege against MySql
##

mysql --user="root" --password="secret" -e "GRANT ALL ON *.* TO 'root'@'localhost' IDENTIFIED BY 'secret' WITH GRANT OPTION;"
mysql --user="root" --password="secret" -e "GRANT ALL ON *.* TO 'root'@'0.0.0.0' IDENTIFIED BY 'secret' WITH GRANT OPTION;"
service mysql restart

##
# Create MySql user account provided by you.
# Grant root user's privilege against MySql.
##

mysql --user="root" --password="secret" -e "CREATE USER '${USERNAME}'@'localhost' IDENTIFIED BY 'secret';"
mysql --user="root" --password="secret" -e "CREATE USER '${USERNAME}'@'0.0.0.0' IDENTIFIED BY 'secret';"

mysql --user="root" --password="secret" -e "GRANT ALL ON *.* TO '${USERNAME}'@'0.0.0.0' IDENTIFIED BY 'secret' WITH GRANT OPTION;"
mysql --user="root" --password="secret" -e "GRANT ALL ON *.* TO '${USERNAME}'@'%' IDENTIFIED BY 'secret' WITH GRANT OPTION;"
mysql --user="root" --password="secret" -e "FLUSH PRIVILEGES;"

service mysql restart

##
# Add timezone support to MySQL
##

mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --user=root --password=secret mysql

##
# Install Postgres.
##

apt-get install -y postgresql

##
# Configure Postgres's remote access.
##

# Configure Postgres Remote Access

sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/g" /etc/postgresql/9.5/main/postgresql.conf
echo "host all all 10.0.2.2/32 md5" | tee -a /etc/postgresql/9.5/main/pg_hba.conf
sudo -u postgres psql -c "CREATE ROLE ${USERNAME} LOGIN UNENCRYPTED PASSWORD 'secret' SUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;"

service postgresql restart

##
# Install Redis, Memche, Beanstalk
##

apt-get install -y \
    redis-server \
    memcached \
    beanstalkd;

##
# Configure Beanstalk & start it.
##

sed -i "s/#START=yes/START=yes/" /etc/default/beanstalkd
/etc/init.d/beanstalkd start

##
# Configure Supervisor & start it.
##

systemctl enable supervisor.service
service supervisor start

# Enable Swap Memory

/bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024
/sbin/mkswap /var/swap.1
/sbin/swapon /var/swap.1
