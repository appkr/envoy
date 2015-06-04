#--------------------------------------------------------------------------
# List of tasks, that you can run...
# e.g. envoy release
#--------------------------------------------------------------------------
#
# release           Publish new release
# hello_envoy       Check ssh connection
# server_provision  Production server provision
# migrate_db        Initialize database and seed base data
# add_crontab       Add crontab entry
#

@servers(['web' => 'homestead.vm'])

@setup
  // Production Server
  $http_host              = 'homestead.vm';
  $server_user            = 'vagrant';
  $server_group           = 'vagrant';
  $web_server             = 'nginx';

  // Production Database
  $db_root_password       = 'root';                                     // Required for initial mysql setup
  $db_database            = 'homestead';
  $db_username            = 'homestead';
  $db_password            = 'secret';

  // Path at Production Server
  $base_path              = '/home/vagrant/Code';
  $service_path           = '/home/vagrant/Code/myapp';
  $releases_path          = '/home/vagrant/Code/releases';
  $storage_path           = '/home/vagrant/Code/storage';

  // Source Repository
  $repo                   = 'git@github.com:appkr/l5-quickstart.git';
  $branch                 = 'master';

  // Container Folder for New Release
  $release_name           = 'release_' . date('YmdHis');
@endsetup

#------------------------------------------------------------------------------#
# Commands                                                                     #
#------------------------------------------------------------------------------#

@macro('release', ['on' => 'web', 'confirm' => true])
  make_release_path
  fetch_repo
  set_commons
  run_composer
  run_npm
  run_bower
  run_gulp
  update_release_permissions
  update_release_symlinks
  restart_webserver
@endmacro

@task('hello_envoy', ['on' => 'web'])
  echo "Hello Envoy!";
@endtask

@macro('server_provision', ['on' => 'web', 'confirm' => true])
  install_server
  configure_mysql
  create_db
  create_db_user
  configure_php
  configure_{{ $web_server }}
  install_composer
  install_bower_gulp
@endmacro

@task('add_crontab', ['on' => 'web', 'confirm' => true])
  echo "add_crontab: adding cron jobs";

  cat << EOT | sudo tee -a /var/spool/cron/root
  * * * * * php {{ $service_path }}/artisan schedule:run 1>> /dev/null 2>&1
@endtask

@task('migrate_db', ['on' => 'web', 'confirm' => true])
  echo "migrate_db: migrating table schema";

  cd {{ $service_path }};
  composer dumpautoload;
  php artisan migrate --force;
  php artisan db:seed --force;
@endtask

#------------------------------------------------------------------------------#
# Sub tasks                                                                    #
#------------------------------------------------------------------------------#

@task('install_server')
  echo "install_server: installing php, mysql, git, npm, node, memche...";

  sudo apt-get update;
  #sudo apt-get install -y --force-yes curl python-software-properties;
  #sudo add-apt-repository -y ppa:ondrej/php5;
  sudo apt-get update;
  sudo apt-get install -y --force-yes php5-fpm {{ $web_server }} git-core npm nodejs-legacy memcached libreadline6 libreadline6-dev mysql-server libapache2-mod-php5 php5-curl php5-gd php5-mcrypt php5-mysql php5-readline php5-memcache php5-memcached php5-sqlite;
@endtask

@task('configure_mysql')
  echo "configure_mysql: setting db password";

  sudo debconf-set-selections <<< "mysql-server mysql-server/root_password password {{ $db_root_password }}";
  sudo debconf-set-selections <<< "mysql-server mysql-server/root_password_again password {{ $db_root_password }}";
@endtask

@task('create_db')
  echo "create_db: creating database";

  mysql -uroot -p{{ $db_root_password }} -e "DROP DATABASE IF EXISTS {{ $db_database }}";
  mysql -uroot -p{{ $db_root_password }} -e "CREATE DATABASE {{ $db_database }} DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci";
@endtask

@task('create_db_user')
  echo "create_db_user: adding new database user";

  mysql -uroot -p{{ $db_root_password }} -e "use mysql";
  mysql -uroot -p{{ $db_root_password }} -e "create user {{ $db_username }}";
  mysql -uroot -p{{ $db_root_password }} -e "create user {{ $db_username }}@localhost identified by '{{ $db_password }}'";
  mysql -uroot -p{{ $db_root_password }} -e "grant all privileges on {{ $db_database }} to {{ $db_username }}@localhost identified by '{{ $db_password }}'";
  mysql -uroot -p{{ $db_root_password }} -e "flush privileges";
@endtask

@task('configure_php')
  echo "configure_php: setting php. error_reporting, display_errors, ...";

  sed -i "s/error_reporting = .*/error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT/" /etc/php5/{{ $web_server }}/php.ini;
  sed -i "s/display_errors = .*/display_errors = Off/" /etc/php5/{{ $web_server }}/php.ini;
  sed -i "s/cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php5/{{ $web_server }}/php.ini;

  #cat << EOT | sudo tee -a /etc/php5/mods-available/xdebug.ini
  #xdebug.scream=1
  #xdebug.cli_color=1
  #xdebug.show_local_vars=1
@endtask

@task('configure_apache2')
  echo "configure_apache2: setting apache2. enable rewrite module, set document root, ...";

  sudo a2enmod rewrite;

  block="
<VirtualHost *:80>
  DocumentRoot {{ $service_path }}/public
  <Directory {{ $service_path }}/public>
  Options -Indexes +FollowSymLinks +MultiViews
  AllowOverride All
  Order allow,deny
  Allow from all
  Require all granted
  </Directory>
</VirtualHost>
";

  sudo truncate -s 0 /etc/apache2/sites-available/000-default.conf;
  sudo echo "$block" > /etc/apache2/sites-available/000-default.conf;
@endtask

@task('configure_apache2_ssl')
  echo "configure_apache2_ssl: setting openssl certificate for apache2";

  sudo mkdir /etc/apache2/ssl;
  openssl genrsa -out "/etc/apache2/ssl/{{ $http_host }}.key" 1024 2>/dev/null;
  openssl req -new -key /etc/apache2/ssl/{{ $http_host }}.key -out /etc/apache2/ssl/{{ $http_host }}.csr -subj "/CN={{ $http_host }}/O=DevPortal/C=KR" 2>/dev/null;
  openssl x509 -req -days 365 -in /etc/apache2/ssl/{{ $http_host }}.csr -signkey /etc/apache2/ssl/{{ $http_host }}.key -out /etc/apache2/ssl/{{ $http_host }}.crt 2>/dev/null;

  block="
<IfModule mod_ssl.c>
  <VirtualHost _default_:443>
    DocumentRoot {{ $service_path }}/public
    <Directory {{ $service_path }}/public>
    Options -Indexes +FollowSymLinks +MultiViews
    AllowOverride All
    Order allow,deny
    allow from all
    Require all granted
    </Directory>
  </VirtualHost>
  SSLEngine on
  SSLProtocol all -SSLv2
  SSLCipherSuite ALL:!ADH:!EXPORT:!SSLv2:RC4+RSA:+HIGH:+MEDIUM
  SSLCertificateFile /etc/apache2/ssl/{{ $http_host }}.crt
  SSLCertificateKeyFile /etc/apache2/ssl/{{ $http_host }}.key
  <FilesMatch \"\.(cgi|shtml|phtml|php)$\">
  SSLOptions +StdEnvVars
  </FilesMatch>
</IfModule>
";

  sudo truncate -s 0 /etc/apache2/sites-available/default-ssl.conf;
  sudo echo "$block" > /etc/apache2/sites-available/default-ssl.conf;
@endtask

@task('configure_nginx')
  echo "configure_nginx: setting nginx. set document root, ...";

  block="server {
  listen ${3:-80};
  server_name {{ $http_host }};
  root \"{{ $service_path }}/public\";
  index index.html index.htm index.php;
  charset utf-8;
  location / {
    try_files \$uri \$uri/ /index.php?\$query_string;
  }
  location = /favicon.ico { access_log off; log_not_found off; }
  location = /robots.txt  { access_log off; log_not_found off; }
  access_log off;
  error_log  /var/log/nginx/{{ $http_host }}-error.log error;
  sendfile off;
  client_max_body_size 100m;
  location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/var/run/php5-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    fastcgi_intercept_errors off;
    fastcgi_buffer_size 16k;
    fastcgi_buffers 4 16k;
  }
  location ~ /\.ht {
    deny all;
  }
}
";

  sudo truncate -s 0 /etc/nginx/sites-available/{{ $http_host }};
  sudo echo "$block" > /etc/nginx/sites-available/{{ $http_host }};
  ln -fs "/etc/nginx/sites-available/{{ $http_host }}" "/etc/nginx/sites-enabled/{{ $http_host }}"
@endtask

@task('install_composer')
  echo "install_composer: installing composer to /usr/local/bin/";

  sudo curl -sS https://getcomposer.org/installer | php;
  sudo mv composer.phar /usr/local/bin/composer;
@endtask

@task('install_bower_gulp')
  echo "install_bower_gulp: installing bower and gulp";

  sudo npm install -g bower gulp;
@endtask

@task('make_release_path')
  echo "make_release_path: making a directory for housing of the releases";

  if [ ! -d {{ $releases_path }} ]; then
    mkdir {{ $releases_path }};
    sudo chown {{ $server_user }}:{{ $server_group }} {{ $releases_path }};
    sudo chmod 777 {{ $releases_path }};
  fi;
@endtask

@task('fetch_repo')
  echo "fetch_repo: pulling the latest code from git";

  cd {{ $releases_path }};
  if [ ! -d {{ $releases_path }}/{{ $release_name }} ]; then
    git clone -b {{ $branch }} {{ $repo }} {{ $release_name }};
  else
    cd {{ $releases_path }}/{{ $release_name }};
    git pull;
  fi;
@endtask

@task('set_commons')
  echo "set_commons: creating storage folder and .env file";

  if [ ! -f {{ $base_path }}/.env ]; then
    wget https://raw.githubusercontent.com/laravel/laravel/master/.env.example;
    mv {{ $base_path }}/.env.example {{ $base_path }}/.env;
    sed -i "s/APP_ENV=.*/APP_ENV=production/" {{ $base_path }}/.env;
    sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" {{ $base_path }}/.env;
    sed -i "s/DB_DATABASE=.*/DB_DATABASE={{ $db_database }}/" {{ $base_path }}/.env;
    sed -i "s/DB_USERNAME=.*/DB_USERNAME={{ $db_username }}/" {{ $base_path }}/.env;
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD={{ $db_password }}/" {{ $base_path }}/.env;
  fi;

  if [ ! -d {{ $storage_path }} ]; then
    mkdir {{ $storage_path }} && cd {{ $storage_path }};
    mkdir app framework logs framework/cache framework/sessions framework/views;
    sudo chmod -R 777 {{ $storage_path }};
  fi;
@endtask

@task('run_composer')
  echo "run_composer: installing project dependencies";

  sudo composer self-update;
  cd {{ $releases_path }}/{{ $release_name }};
  mkdir vendor;
  sudo chmod -R 777 vendor;
  composer install --no-dev --no-scripts --verbose;
  php artisan clear-compiled --env=production;
  php artisan optimize --env=production;
@endtask

@task('run_npm')
  echo "run_npm: installing required node packages";

  cd {{ $releases_path }}/{{ $release_name }};
  sudo npm install;
@endtask

@task('run_bower')
  echo "run_bower: installing required external resources (js/css)";

  cd {{ $releases_path }}/{{ $release_name }};
  bower install;

  # install missing components in bower repository
  #cd {{ $releases_path }}/{{ $release_name }}/resources/vendor/bootswatch-scss;
  #sudo mkdir sandstone && cd sandstone;
  #wget https://raw.githubusercontent.com/log0ymxm/bootswatch-scss/master/sandstone/_bootswatch.scss;
  #wget https://raw.githubusercontent.com/log0ymxm/bootswatch-scss/master/sandstone/_variables.scss;
@endtask

@task('run_gulp')
  echo "run_gulp: builing resources";

  cd {{ $releases_path }}/{{ $release_name }};
  gulp --production;
@endtask

@task('update_release_permissions')
  echo "update_release_permissions: chaning ownership of this release to current user";

  sudo chown -R {{ $server_user }}:{{ $server_group }} {{ $releases_path }}/{{ $release_name }};
@endtask

@task('update_release_symlinks')
  echo "update_release_symlinks: linking this release to the service";

  ln -nfs {{ $releases_path }}/{{ $release_name }} {{ $service_path }};

  if [ -d {{ $releases_path }}/{{ $release_name }}/storage ]; then
    rm -rf {{ $releases_path }}/{{ $release_name }}/storage;
  fi;

  cd {{ $releases_path }}/{{ $release_name }};

  ln -nfs {{ $storage_path }} storage;
  #sudo chown -R {{ $server_user }}:{{ $server_group }} storage;
  #sudo chmod -R 777 storage;

  if [ -f {{ $releases_path }}/{{ $release_name }}/.env ]; then
    rm .env;
  fi;

  ln -nfs {{ $base_path }}/.env .env;
  #sudo chown {{ $server_user }}:{{ $server_group }} .env;

  sudo chown -R {{ $server_user }}:{{ $server_group }} {{ $service_path }};
@endtask

@task('restart_webserver')
  echo "restart_webserver: restarting web";

  sudo service {{ $web_server }} restart;
@endtask

@task('restart_mysql')
  echo "restart_mysql: restarting database";

  sudo service mysql.server restart;
@endtask

@task('register_queue_worker')
  echo "register_queue_worker: add new queue subscriber";

  #set your queue listener
  #php {{ $service_path }}/artisan queue:subscribe ...;
@endtask

@task('report')
  echo "All done.";

  #{{--@after--}}
  #  {{--@slack('hook_url', '#channel', 'message.')--}}
  #{{--@endafter--}}
@endtask
