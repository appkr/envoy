#--------------------------------------------------------------------------
# List of tasks, that you can run...
# e.g. envoy run hello
#--------------------------------------------------------------------------
#
# hello     Check ssh connection
# deploy    Publish new release
#

# @include not work! So resorted back to traditional way~.
<?php include(__DIR__ . '/./envoy.config.php'); ?>


@servers(['web' => config('server.domain')])


@task('hello', ['on' => ['web']])
  HOSTNAME=$(hostname);
  echo "Hello Envoy! Responding from $HOSTNAME";
@endtask


@macro('deploy', ['on' => 'web', 'confirm' => true])
  path
  fetch
  symlinks
  permissions
@endmacro


@task('path', ['on' => 'web'])
  paths=("{{ config('path.web') }}" "{{ config('path.shared') }}" "{{ config('path.release') }}");

  for path in "${paths[@]}"; do
    if [ ! -d $path ]; then
      mkdir $path;
      chgrp -h -R www-data $path;
    fi;
  done;
@endtask


@task('fetch', ['on' => 'web'])
  cd {{ config('path.release') }};
  git clone -b master {{ config('git.repo') }} {{ config('release.name') }};
@endtask


@task('permissions', ['on' => 'web'])
  cd {{ config('path.release') }};
  chgrp -h -R www-data {{ config('release.name') }};
@endtask


@task('symlinks', ['on' => 'web'])
  cd {{ config('path.release') }}/{{ config('release.name') }};

  # Symlink shared directory to current release.
  ln -nfs {{ config('path.shared') }} shared;
  chgrp -h -R www-data shared;

  # Symlink current release to service directory.
  ln -nfs {{ config('path.release') }}/{{ config('release.name') }} {{ config('path.base') }};
  chgrp -h -R www-data {{ config('path.base') }};
@endtask


@task('composer', ['on' => 'web'])
  cd {{ config('path.base') }};
  composer install --prefer-dist --no-scripts;

  # your custom command here

  service nginx restart;
  service php5-fpm restart;
@endtask


@task('prune', ['on' => 'web'])
  cd {{ config('path.web') }};
  wget https://raw.githubusercontent.com/appkr/envoy/master/scripts/prune_release.php;
  php {{ config('path.web') }}/prune_release.php {{ config('path.release') }} {{ config('release.keep') }};
@endtask
