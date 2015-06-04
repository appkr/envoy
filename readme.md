<a name="introduction"></a>
# What is this? What for?
It is envoy script for Laravel5 project. By utilizing this script you can

- Achieve zero down time of your Laravel-based service
- Maintain release history by not overwriting the previous release

<a name="how-it-works"></a>
## How it works?
By running the script from your local computer, your production server checkout the latest code from your source repository (e.g. Github), pull the project dependencies, and upon ready, link the current release directory to your webroot directory. In doing so, you will see what is happening on the remote production server through your local computer.

<a name="how-to-use"></a>
## How to use
Install the script in your Laravel5 project.

```
// at your local computer

cd your-laravel-project-root
wget https://raw.githubusercontent.com/appkr/envoy/master/Envoy.blade.php
```

Open up the downloaded script, edit configuration values to fit your project.

```
// Envoy.blade.php

@servers(['web' => 'your-production-server.com'])

@setup
// Server
$server_user            = 'www-data';
$server_group           = 'www-data';
$web_server             = 'apache2';

// Path
$base_path              = '/your-webroot';
$app_path               = '/your-webroot/your-project-path';
```

Run the script. Before initial attempt, please check you properly set the [requirement](#requirement).
```
// at your local computer

cd your-laravel-project-root
envoy run release
```

<a name="commands"></a>
## Avaliable Commands?
- **release** : Publish new release to the production server
- **hello_envoy** : Check ssh connection to the production server
- **server_provision** : Prepare the production server (e.g. install LAMP, composer, ...)
- **migrate_db** : Initialize production database, table and seed data
- **add_crontab** : Add crontab entry on the production server

<a name="requirement"></a>
## Requirement
Lots of shell commands included in this script requires `sudo` priviledge. If you encounter error message like `sudo: no tty present and no askpass program specified`, you can work around the error by adding the following line on your production server's `/etc/sudoers` file.
```
// /etc/sudoers
username ALL=(ALL) NOPASSWD: ALL
```

To get this script work, you should set the following on respective machine.

&nbsp;|Local computer|Source repository|Production server
---|-:-|-:-|-:-
Composer[^1]|O| |O
Npm[^2]| | |O
Bower[^3]| | |O
Gulp[^4]| | |O
Envoy task runner[^5]|O|&nbsp;|&nbsp;
SSH connection[^6]|to Sourcce respository <br/>to Production server|&nbsp;|to Source repository

**`note`** `git` and other required packages are assumed to be installed properly.

[^1]: 
  [Composer](https://getcomposer.org/) is a php dependency manager.
  
  ```
  sudo curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  composer --version
  ```

[^2]: 
  [Npm](https://nodejs.org/) is a node package manager.
  
  ```
  sudo apt-get install -y --force-yes npm nodejs-legacy
  npm --version
  ```

[^3]: 
  [Bower](http://bower.io/) is a package manager for javascript/css.
  
  ```
  sudo npm install -g bower
  bower --version
  ```

[^4]: 
  [Gulp](http://gulpjs.com/) is a build automation tool.
  
  ```
  sudo npm install -g gulp
  gulp --version
  ```

[^5]: 
  [Envoy](https://github.com/laravel/envoy) is a SSH task runner.
  ```
  composer global require "laravel/envoy=~1.0"
  export PATH=$PATH:~/.composer/vendor/bin
  envoy --version
  ```

[^6]: 
  `envoy` is a SSH task runner, which means you should have a valid ssh connection from your local computer to the production server against which you want to run the `envoy` script. And your production server should have a valid ssh connection to the git source repository to checkout the latest source code of your project. The easiest way to achieve this at your local and production side is adding following entry to `~/.ssh/config`:
  
  ```
  // ~/.ssh/config at your local computer
  Host alias-of-the-server
      Hostname your-production-server.com
      User ssh-username
      IdentityFile path-to-ssh-key
  
  // ~/.ssh/config at your production server
  Host git-repo-alias
      Hostname git-repo-url
      User git-username
      IdentityFile path-to-ssh-key
  ```




