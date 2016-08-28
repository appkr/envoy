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
#   user@server:~$ sudo -S
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
# HOW TO RUN
#--------------------------------------------------------------------------
#
#   user@server:~$ sudo -s
#   root@server:~# wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/serve.sh
#   root@server:~# bash serve.sh example.com /path/to/document-root
#
#   FOR THIS EXAMPLE ENVOY PROJECT
#   root@server:~# bash serve.sh envoy.vm /home/deployer/www/envoy.vm/public
##

block="server {
    listen ${3:-80};

    server_name $1;

    root \"$2\";

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log /var/log/nginx/$1-access.log;
    error_log  /var/log/nginx/$1-error.log error;

    sendfile off;

    client_max_body_size 100m;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
"

echo "$block" > "/etc/nginx/sites-available/$1"
ln -fs "/etc/nginx/sites-available/$1" "/etc/nginx/sites-enabled/$1"

sudo service nginx restart
sudo service php7.0-fpm restart