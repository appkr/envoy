# Envoy Use Case Demo

## What is this? What for?

[Envoy is a SSH task runner](https://laravel.com/docs/5.2/envoy) written in PHP language. **Was born in Laravel world, but can be applicable any framework or language.** This repository is an envoy script, that demos the usefulness of the Envoy.

This repository poses a strategy that lowers the headache of code deployment to the production server. By adopting this strategy, you can:

1.  achieve zero down time of the service
2.  maintain release histories (by not overwriting the previous release)

To do that, the script takes `git clone` strategy rather than `git pull or git fetch`. Once the clone is done, the script symlinks the current clone to the document root.

This script may not fit your project needs at 100% though, I bet you get the idea what to do next.

## Install envoy executable and recipe

#### **Step #1** You need an envoy `laravel/envoy` executable 

This is an one time process.

```bash
$ composer global require "laravel/envoy=~1.0"
$ envoy --version # Laravel Envoy version x.x.x
```

**`Note`** Make sure to place the `~/.composer/vendor/bin` directory in your `PATH` so the `envoy` executable is found when you run the `envoy` command in your terminal.

#### **Step #2** Download the recipe scripts into your project

`envoy.blade.php` and its associated files should be placed in every project.

```bash
# at your local computer

$ cd project
$ wget https://raw.githubusercontent.com/appkr/envoy/master/envoy.blade.php
$ wget https://raw.githubusercontent.com/appkr/envoy/master/envoy.config.php.example -O envoy.config.php
```

#### **Step #3** Configure your value

`envoy.config.php` was devised to avoid committing the sensitive information (e.g. github token) to the version control. Even though envoy runs on local machine, sometimes the `envoy.blade.php` script need to be shared across team. In that case, the `envoy.config.php` comes in handy. So, the file should be included in the `.gitignore` list. 

Open it up and set your value. Each variable is [self-explanatory and inline commented](https://github.com/appkr/envoy/blob/master/envoy.config.php.example). 

##### Accessing config at Envoy script

Use dot(`.`) to access the sub items.

```php
// envoy.blade.php

@task('fetch_repo', ['on' => 'web'])
  cd {{ config('path.release') }}; // to get the value of $configurations['path']['release']
  ls -al;
@endtask
```

## **Step #4** Install keys

Considering the deployment process, servers must be able to talk each other. On your local machine, you push the code to the version control server. Then you publish `$ envoy run deploy` task at your local machine (task will propagated to the production server through the ssh). Then the production server clones the code from the version control server.   

-   on local machine, there should be:
    - ssh private key to connect to the production server
    - ssh private key to connect to the version control server <sup>(1)</sup>
-   on production server, there should be:
    - ssh public key to authenticate the local machine 
    - ssh private key to connect to the version control server (Can be same as <sup>(1)</sup>)
    
**`Note`** Before you run the first deployment using envoy, you have to update `~/.ssh/known_hosts` by ssh-ing into the production server and connect to github once. `$ ssh -T git@github.com`. See [this page](https://help.github.com/articles/generating-ssh-keys/).

## **Step #5** Run the first envoy tank

```bash
$ envoy run hello
# [my_host_name]: Hello Envoy! Responding from my_host_name
```

## **Step #6** Customize your envoy script

If your project is relying on composer, putting `composer` and `prune` task in the macro is recommended.

```php
@macro('deploy', ['on' => 'web', 'confirm' => true])
  //...
  permissions
  composer
  prune
@endmacro
```

The following is the structure of this project.

```bash
.
├── composer.json             // dummy dependency to demo ($ envoy run composer) 
├── envoy.blade.php           // envoy script
├── envoy.config.php.example  // example configuration
├── public
│   └── index.html            // landing page just for demo
├── scripts
│   ├── provision.sh          // script for server provision
│   ├── prune_release.php     // script to remove old releases ($ envoy run prune)
│   └── serve.sh              // script for setting nginx sites 
└── vendor                    // dummy dependency to demo ($ envoy run composer)
    ├── autoload.php
    └── composer
```

## Todo

-   Implement 'rollback' task
-   Accept multiple server in configuration

## License

MIT




