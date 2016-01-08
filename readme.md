# Envoy Use Case Demo

## What is this? What for?

[Envoy is a SSH task runner](https://laravel.com/docs/envoy) written in PHP language. Which means you can automate a predefined server job in your local computer, without having to manually ssh in to the server and run the bunch of shell command. e.g. `$ envoy run release`

**Was born in Laravel world, but can be applicable to any project (framework or language agnostic).** 

This repository is an example of an Envoy script (`envoy.blade.php`), that demos the usefulness and the powerfulness of the Envoy. 

## Demo scenario

Note that Envoy is not a deployment tool, such as capistrano or deployer, but can be used as such. Git neither is a deployment tool. 

In this example Envoy script, written for code deployment scenario in conjunction with Git, poses a strategy that lowers the headache of code deployment to the production server. By adopting this strategy, you can:

1.  achieve zero down time of the service.
2.  achieve zero conflict in production server.
3.  maintain release histories (by not overwriting the previous release).

To do that, the script takes `git clone` strategy rather than `git pull or git fetch`. Once the clone is done, the script symlinks the current clone to the document root of the web server.

This script may not fit your needs 100% though, I bet you get the idea what to do next.

## Try yourself !

#### **Step #1** Install `laravel/envoy` executable 

This is an one time process.

```bash
$ composer global require "laravel/envoy=~1.0"
$ envoy --version # Laravel Envoy version x.x.x
```

**`Note`** Make sure to place the `~/.composer/vendor/bin` directory in your `PATH` so the `envoy` executable is found when you run the `envoy` command in your terminal.

#### **Step #2** Download the recipe scripts

`envoy.blade.php` and its associated files should be placed in every project.

```bash
# at your local computer

$ cd project
$ wget https://raw.githubusercontent.com/appkr/envoy/master/envoy.blade.php
$ wget https://raw.githubusercontent.com/appkr/envoy/master/envoy.config.php.example -O envoy.config.php
```

#### **Step #3** Configure your values

`envoy.config.php` was devised to avoid committing the sensitive information to the version control. Even though Envoy runs on local machine, sometimes the `envoy.blade.php` script need to be shared across team. In that case, the `envoy.config.php` comes in handy. Then, the file should be included in the `.gitignore` list. 

Each config variable is [self-explanatory and inline commented](https://github.com/appkr/envoy/blob/master/envoy.config.php.example). 

**`Note`** To get the config value at an Envoy script, use dot(`.`).

```php
// envoy.blade.php

@task('something', ['on' => 'web'])
  cd {{ config('path.release') }};
  ls -al;
@endtask
```

#### **Step #4** Install keys

This process is nothing to do with Envoy, but required for this demo script to be working.

Considering the deployment process, servers must be able to talk to each other. On the local machine, you push the code to the version control server, then you would publish `$ envoy run release` task (task will propagated to the production server through the ssh). Then the production server clones the code from the version control server.   

-   The local machine should have:
    - ssh private key to connect to the production server
    - ssh private key to connect to the version control server <sup>(1)</sup>
-   The production server shold have:
    - ssh public key to authenticate the local machine 
    - ssh private key to connect to the version control server (Can be same as <sup>(1)</sup>)
    
**`Note`** Before you run the first deployment using envoy, the production server has to know the github server. You can do it by ssh in to the production server and connect to github once. `$ ssh -T git@github.com`. See [this page](https://help.github.com/articles/generating-ssh-keys/).

#### **Step #5** Run the first envoy task

```bash
$ envoy run hello
# [my_host_name]: Hello Envoy! Responding from my_host_name
```

#### **Step #6** Customize your envoy script

Following Envoy tasks are predefined out of the box. Why not add or modify for yours?

-   `hello`
    : Check ssh connection
-   `release`
    : Publish new release
-   `list`
    : Show list of releases
-   `rollback`
    : Rollback to the given release (must provide --release=/path/to/release)
-   `prune`
    : Purge old releases (must provide --release=n, where n is a number of release to keep)

For example,

```bash
# after pushing new code to git ...
$ envoy run release
```

will produce the following (at server).

```bash
www
├── releases
│   └── release_YmdHis
│       ├── # other dirs or files
│       └── shared -> /home/username/www/www/shared
├── shared
└── my_domain_name -> /home/username/www/releases/release_YmdHis
```

Where `www/releases` is housing of all releases, distinguished by directory name of `release_YmdHis`. `www/shared` is a shared resources that every release has to refer, like cache storage, user uploaded files, etc. `www/my_domain_name` is the document root of the web server.

![](public/envoy-deployment.png)

## Side note

The following is the short explanation of this repository.

```bash
.
├── envoy.blade.php           // Envoy script
├── envoy.config.php.example  // Example configuration
└── scripts
    ├── provision.sh          // Script for server provision
    ├── officer.php           // Script to keep the release history
    │                         //   and will be installed on the server
    └── serve.sh              // Script for setting nginx sites
# Not listed dir/files were laid there for the purpose of demo.
```

**`Note`** With `scripts/provision.sh`, `scripts/serve.sh` you can quickly provision a server and nginx sites. For usage, refer to each script.

## License

MIT




