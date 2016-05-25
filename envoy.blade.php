#--------------------------------------------------------------------------
# List of tasks, that you can run...
# e.g. envoy run hello
#--------------------------------------------------------------------------
#
# hello     Check ssh connection
# deploy    Publish new release
# list      Show list of releases
# checkout  Checkout to the given release (must provide --release=/path/to/release)
# prune     Purge old releases (must provide --keep=n, where n is a number)
#
#--------------------------------------------------------------------------
# Note that the server shoulbe be accessible through ssh with 'username' account
# $ ssh username@hostname
#--------------------------------------------------------------------------
#

@servers(['web' => 'aws-seoul-deploy'])


@setup
  $username = 'deployer';                       // username at the server
  $remote = 'git@github.com:appkr/envoy.git';   // github repository to clone
  $base_dir = "/home/{$username}/www";          // document that holds projects
  $project_root = "{$base_dir}/envoy.appkr.kr"; // project root
  $shared_dir = "{$base_dir}/shared";           // directory that will house shared dir/files
  $release_dir = "{$base_dir}/releases";        // release directory
  $distname = 'release_' . date('YmdHis');      // release name

  // ------------------------------------------------------------------
  // Leave the following as it is, if you don't know what they are for.
  // ------------------------------------------------------------------

  $required_dirs = [
    $shared_dir,
    $release_dir,
  ];

  $shared_item = [
    "{$shared_dir}/.env" => "{$release_dir}/{$distname}/.env",
    "{$shared_dir}/storage" => "{$release_dir}/{$distname}/storage",
    "{$shared_dir}/cache" => "{$release_dir}/{$distname}/bootstrap/cache",
  ];
@endsetup


@task('hello', ['on' => ['web']])
  HOSTNAME=$(hostname);
  echo "Hello Envoy! Responding from $HOSTNAME";
@endtask


@task('deploy', ['on' => ['web']])
  {{--Create directories if not exists--}}
  @foreach ($required_dirs as $dir)
    [ ! -d {{ $dir }} ] && mkdir -p {{ $dir }};
  @endforeach

  {{--Download book keeping officer--}}
  if [ ! -f {{ $base_dir }}/officer.php ]; then
    wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/officer.php -O {{ $base_dir }}/officer.php;
  fi;

  {{--Clone code from git--}}
  cd {{ $release_dir }} && git clone -b master {{ $remote }} {{ $distname }};

  [ ! -f {{ $shared_dir }}/.env ] && cp {{ $release_dir }}/{{ $distname }}/.env.example {{ $shared_dir }}/.env;
  [ ! -d {{ $shared_dir }}/storage ] && cp -R {{ $release_dir }}/{{ $distname }}/storage {{ $shared_dir }};
  [ ! -d {{ $shared_dir }}/cache ] && cp -R {{ $release_dir }}/{{ $distname }}/bootstrap/cache {{ $shared_dir }};

  {{--Symlink shared directory to current release.--}}
  {{--e.g. storage, .env, user uploaded file storage, ...--}}
  @foreach($shared_item as $global => $local)
    [ -f {{ $local }} ] && rm {{ $local }};
    [ -d {{ $local }} ] && rm -rf {{ $local }};
    ln -nfs {{ $global }} {{ $local }};
  @endforeach

  {{--Run composer install--}}
  cd {{ $release_dir }}/{{ $distname }} && composer install --prefer-dist --no-scripts --no-dev;

  {{--Any additional command here--}}
  {{--e.g. php artisan clear-compiled;--}}

  {{--Symlink current release to service directory.--}}
  ln -nfs {{ $release_dir }}/{{ $distname }} {{ $project_root }};

  {{--Set permission and change owner--}}
  chmod -R 775 {{ $shared_dir }}/storage;
  chmod -R 775 {{ $shared_dir }}/cache;
  chgrp -h -R www-data {{ $release_dir }}/{{ $distname }};

  {{--Book keeping--}}
  php {{ $base_dir }}/officer.php deploy {{ $release_dir }}/{{ $distname }};

  {{--Restart web server.--}}
  sudo service nginx restart;
  sudo service php7.0-fpm restart;
@endtask


@task('prune', ['on' => 'web'])
  if [ ! -f {{ $base_dir }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  @if (isset($keep) and $keep > 0)
    php {{ $base_dir }}/officer.php prune {{ $keep }};
  @else
    echo 'Must provide --keep=n, where n is a number.';
  @endif
@endtask


@task('hire_officer', ['on' => 'web'])
  {{--Download "officer.php" to the server--}}
  wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/officer.php -O {{ $base_dir }}/officer.php;
  echo '"officer.php" is ready! Ready to roll master!';
@endtask


@task('list', ['on' => 'web'])
  {{--Show the list of release--}}
  if [ ! -f {{ $base_dir }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  php {{ $base_dir }}/officer.php list;
@endtask


@task('checkout', ['on' => 'web'])
  {{--checkout to the given release path--}}
  if [ ! -f {{ $base_dir }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  @if (isset($release))
    cd {{ $release }};

    {{--Symlink shared directory to the given release.--}}
    @foreach($shared_item as $global => $local)
      [ -f {{ $local }} ] && rm {{ $local }};
      [ -d {{ $local }} ] && rm -rf {{ $local }};
      ln -nfs {{ $global }} {{ $local }};
    @endforeach

    {{--Symlink the given release to service directory.--}}
    ln -nfs {{ $release }} {{ $project_root }};

    {{--Book keeping--}}
    php {{ $base_dir }}/officer.php checkout {{ $release }};
    chgrp -h -R www-data {{ $release_dir }}/{{ $distname }};

    {{--Restart web server.--}}
    sudo service nginx restart;
    sudo service php7.0-fpm restart;
  @else
    echo 'Must provide --release=/full/path/to/release.';
  @endif
@endtask