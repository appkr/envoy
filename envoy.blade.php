#--------------------------------------------------------------------------
# List of tasks, that you can run...
# e.g. envoy run hello
#--------------------------------------------------------------------------
#
# hello     Check ssh connection
# release   Publish new release
# list      Show list of releases
# rollback  Rollback to the given release (must provide --release=/path/to/release)
# prune     Purge old releases (must provide --release=n, where n is a number)
#

# @include not work! So resorted back to traditional way~.
<?php include(__DIR__ . '/./envoy.config.php'); ?>


@servers(['web' => config('server.domain')])


@setup
  /* Directories to make */
  $directories = [config('path.web'), config('path.shared'), config('path.release')];
@endsetup


@task('hello', ['on' => ['web']])
  HOSTNAME=$(hostname);
  echo "Hello Envoy! Responding from $HOSTNAME";
@endtask


@task('release', ['on' => ['web']])
  {{--Create directories if not exists--}}
  @foreach ($directories as $dir)
    if [ ! -d {{$dir}} ]; then
      mkdir {{ $dir }};
      chgrp -h -R www-data {{ $dir }};
    fi;
  @endforeach

  {{--Download book keeping officer--}}
  if [ ! -f {{ config('path.web') }}/officer.php ]; then
    wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/officer.php -O {{ config('path.web') }}/officer.php;
  fi;

  {{--Fetch code from git--}}
  cd {{ config('path.release') }};
  git clone -b master {{ config('git.repo') }} {{ config('release.name') }};

  {{--Run composer install--}}
  cd {{ config('path.release') }}/{{ config('release.name') }};
  composer install --prefer-dist --no-scripts;
  {{--composer after-install script here--}}
  {{--your custom command here--}}

  {{--Symlink shared directory to current release.--}}
  {{--e.g. storage, .env, user uploaded file storage, ...--}}
  ln -nfs {{ config('path.shared') }} shared;
  chgrp -h -R www-data shared;

  {{--Symlink current release to service directory.--}}
  ln -nfs {{ config('path.release') }}/{{ config('release.name') }} {{ config('path.base') }};
  chgrp -h -R www-data {{ config('path.base') }};

  {{--Set permission and change owner. Do one final more for safety.--}}
  chgrp -h -R www-data {{ config('path.release') }}/{{ config('release.name') }};

  {{--Book keeping--}}
  php {{ config('path.web') }}/officer.php deploy {{ config('path.release') }}/{{ config('release.name') }};

  {{--Restart web server.--}}
  {{--service nginx restart;--}}
  {{--service php5-fpm restart;--}}
@endtask


@task('prune', ['on' => 'web'])
  if [ ! -f {{ config('path.web') }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  @if (isset($keep) and $keep > 0)
    php {{ config('path.web') }}/officer.php prune {{ $keep }};
  @else
    echo 'Must provide --keep=n, where n is a number.';
  @endif
@endtask


@task('hire_officer', ['on' => 'web'])
  {{--Download "officer.php" to the server--}}
  wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/officer.php -O {{ config('path.web') }}/officer.php;
  echo '"officer.php" is ready!';
@endtask


@task('list', ['on' => 'web'])
  {{--Show the list of release--}}
  if [ ! -f {{ config('path.web') }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  php {{ config('path.web') }}/officer.php list;
@endtask


@task('rollback', ['on' => 'web'])
  {{--Rollback to the given release path--}}
  if [ ! -f {{ config('path.web') }}/officer.php ]; then
    echo '"officer.php" script not found.';
    echo '\$ envoy run hire_officer';
    exit 1;
  fi;

  @if (isset($release))
    cd {{ $release }};

    {{--Symlink shared directory to the given release.--}}
    ln -nfs {{ config('path.shared') }} shared;
    chgrp -h -R www-data shared;

    {{--Symlink the given release to service directory.--}}
    ln -nfs {{ $release }} {{ config('path.base') }};
    chgrp -h -R www-data {{ config('path.base') }};

    {{--Book keeping--}}
    php {{ config('path.web') }}/officer.php checkout {{ $release }};

    {{--Restart web server.--}}
    {{--service nginx restart;--}}
    {{--service php5-fpm restart;--}}
  @else
    echo 'Must provide --release=/full/path/to/release.';
  @endif
@endtask