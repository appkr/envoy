#! /usr/bin/env php
<?php

/* ascii colors */
$green = "\033[32m";
$red   = "\033[31m";
$reset = "\033[0m";

/* house keeping validation */
if (count($argv) < 3) {
    echo <<<EOT
Usage prune_release {\$path} {\$keep}
    - \$path    : directory path housing the releases {$red}(required){$reset}
    - \$keep    : number of releases to keep {$red}(required){$reset}
EOT;
exit(1);
}

/* Helper functions */
function dd($var)
{
    die(var_dump($var));
}

class PruneRelease
{
    protected $path;
    protected $keep;
    protected $releases;

    public function __construct($arguments)
    {
        $this->path = $arguments[1];
        $this->keep = $arguments[2];
    }

    public function run()
    {
        $this->validate();

        $this->releases()->prune();
    }

    protected function validate()
    {
        if (! is_dir($this->path)) {
            exit("\033[31mArgument 1 is not a valid directory.\033[0m");
        }

        if (! is_numeric($this->keep) or (int) $this->keep < 1) {
            exit("\033[31mArgument 2 is too small or not a valid number.\033[0m");
        }

        return true;
    }

    protected function releases()
    {
        $this->releases = $this->elements($this->path);

        usort($this->releases, function($a, $b) {
            return filemtime($a) > filemtime($b);
        });

        return $this;
    }

    protected function prune()
    {
        $offset = 0;
        $length = count($this->releases) - $this->keep;

        if ($length <= 0) {
            exit("\033[31mNothing to prune.\033[0m");
        }

        $targets = array_slice($this->releases, $offset, $length);

        $this->removeDirectory($targets);

        return $this;
    }

    protected function removeDirectory($collection)
    {
        foreach ($collection as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($this->elements($item));
            } else {
                echo @unlink($item)
                    ? "\033[32m[file] {$item} removed\033[0m" . PHP_EOL
                    : "\033[31m[file] {$item} not removed\033[0m" . PHP_EOL;
            }

            if (is_dir($item)) {
                echo @rmdir($item)
                    ? "\033[32m[directory] {$item} removed\033[0m" . PHP_EOL
                    : "\033[31m[directory] {$item} not removed\033[0m" . PHP_EOL;
            }
        }

        return true;
    }

    protected function elements($path)
    {
        return glob($path . DIRECTORY_SEPARATOR . '*');
    }
}

/* main */
$app = new PruneRelease($argv);
$app->run();