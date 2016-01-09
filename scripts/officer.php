#! /usr/bin/env php
<?php

/**
 * Part of the appkr/envoy package.
 *
 *
 * @package    appkr/envoy
 * @version    2.0.8
 * @author     appkr <juwonkim@me.com>
 * @license    MIT
 * @copyright  (c) 2016, Juwon Kim
 */

/**
 * Class Officer
 *
 * Do to clerk job, like maintaining the ledger.
 */
class Officer
{
    /* Ascii colors */
    const GREEN = "\033[32m";
    const RED   = "\033[31m";
    const RESET = "\033[0m";
    const BOLD  = "\033[1m";

    /**
     * @var string
     */
    protected $cmd;

    /**
     * @var string|null Full path to the release.
     */
    protected $arg;

    /**
     * @var string The ledger file path.
     */
    protected $ledger;

    /**
     * @param $args
     */
    public function __construct($args)
    {
        try {
            $this->validate($args);
        } catch (InvalidArgumentException $e) {
            echo sprintf('%s%s%s', self::RED, $e->getMessage(), self::RESET) . PHP_EOL . PHP_EOL;
            $this->printSignature();
            exit(1);
        }

        // 'list' is reserved, so work-around to 'lists'
        $this->cmd = $args[1] == 'list' ? 'lists' : $args[1];
        $this->arg = isset($args[2]) ? $args[2] : null;

        $this->ledger = __DIR__ . '/./ledger.json';
        $this->initLedger();
    }

    /**
     * Delegate job.
     *
     * @return mixed
     */
    public function run()
    {
        try {
            list($exitCode, $message) = call_user_func([$this, $this->cmd]);
        } catch(Exception $e) {
            echo $this->error($e->getMessage());
            exit(1);
        }

        echo $exitCode == 0 ? $this->success($message) : $this->error($message);
        echo PHP_EOL;
        exit($exitCode);
    }

    /**
     * Do the "list" job.
     * - Read the ledger.
     * - Format the list.
     * - Print feedback.
     */
    protected function lists()
    {
        $releases = $this->getLedger();
        $compiled = [];

        foreach ($releases as $release) {
            $compiled[] = $release['active']
                ? sprintf("%s%s\tactive%s", self::GREEN, $release['path'], self::RESET)
                : $release['path'];
        }

        echo implode(PHP_EOL, $compiled);
        exit(0);
    }

    /**
     * Do the "deploy" job.
     * - Read the ledger.
     * - Mark previous releases at in-active.
     * - Add current release and save to the ledger.
     * - Print feedback.
     */
    protected function deploy()
    {
        $releases = $this->touchLedger(
            $this->getLedger(true),
            $this->arg
        );

        array_push($releases, [
            'path'      => $this->arg,
            'timestamp' => time(),
            'active'    => true
        ]);

        return $this->putLedger($releases)
            ? [0, "Code deployed at {$this->arg}, and is now active."]
            : [1, 'Failed to update release record.'];
    }

    /**
     * Do the checkout job.
     * - Validate the given release exists.
     * - Read the ledger.
     * - Mark the given release as the active and update ledger.
     */
    protected function checkout()
    {
        if (! is_dir($this->arg)) {
            $this->error("Cannot locate the given release, {$this->arg}");
            exit(1);
        }

        $releases = $this->touchLedger(
            $this->getLedger(),
            $this->arg
        );

        return $this->putLedger($releases)
            ? [0, "Checkout to {$this->arg}, and is now active."]
            : [1, "Failed to checkout to {$this->arg}."];
    }

    /**
     * Do the "prune" job.
     * - Read the ledger.
     * - Sort them by timestamp.
     * - Calculate purge list.
     * - Remove directory that is listed in the list.
     */
    protected function prune()
    {
        $releases = $this->getLedger();

        usort($releases, function($a, $b) {
            return (int) $a['timestamp'] > (int) $b['timestamp'];
        });

        $offset = 0;
        $length = count($releases) - $this->arg;

        if ($length <= 0) {
            throw new Exception('Nothing to prune.');
        }

        $targets = array_slice($releases, $offset, $length);
        $remains = array_slice($releases, $length);

        $results = $this->removeDirectory($targets);

        return $this->putLedger($remains)
            ? [0, $results]
            : [1, $results];
    }

    /**
     * Do the "reset" job.
     */
    protected function reset()
    {
        return $this->resetLedger()
            ? [0, 'Release record has been initialized.']
            : [1, 'Failed to initialize the release record.'];
    }

    /**
     * Read and return the content of the ledger.
     *
     * @param bool $ignoreEmpty
     * @return mixed
     * @throws \Exception
     */
    private function getLedger($ignoreEmpty = false)
    {
        $results = json_decode(file_get_contents($this->ledger), true);

        if (! $ignoreEmpty and empty($results)) {
            throw new Exception('Release not found.');
        }

        return empty($results) ? [] : $results;
    }

    /**
     * Get the content of the ledger.
     *
     * @return bool|int
     */
    private function initLedger()
    {
        if (! file_exists($this->ledger)) {
            return $this->resetLedger();
        }

        return true;
    }

    /**
     * Truncate the ledger.
     *
     * @return int
     */
    private function resetLedger()
    {
        return file_put_contents($this->ledger, json_encode([]));
    }

    /**
     * Book keeping.
     *
     * @param $releases
     * @return bool
     */
    private function putLedger($releases)
    {
        return file_put_contents($this->ledger, json_encode($releases, JSON_PRETTY_PRINT)) !== false
            ? true : false;
    }

    /**
     * Update ledger.
     *
     * @param array $releases
     * @param null   $active name of the active release
     * @return array
     */
    private function touchLedger($releases, $active = null)
    {
        $modified = [];

        foreach ($releases as $release) {
            if (! is_dir($release['path'])) {
                // Release dir may have been deleted manually.
                continue;
            }

            $switch = ($release['path'] == $active) ? true : false;

            $modified[] = [
                'path'      => $release['path'],
                'timestamp' => $release['timestamp'],
                'active'    => $switch,
            ];
        }

        return $modified;
    }

    /**
     * Do the dirty work...
     * Todo. Bad design.. Arguments type not consistent.
     */
    private function removeDirectory($collection)
    {
        $results = [];

        foreach ($collection as $release) {
            if (isset($release['active']) and $release['active'] == true) {
                continue;
            }

            $path = isset($release['path']) ? $release['path'] : $release;

            if (is_link($path)) {
                @unlink($path);
            } else {
                if (is_dir($path)) {
                    $this->removeDirectory($this->getElements($path));
                } elseif (is_file($path)) {
                    @unlink($path);
                }
            }

            if (is_dir($path)) {
                $results[] = @rmdir($path) ? "{$path} removed." : "{$path} removing failed.";
            }
        }

        return implode(PHP_EOL, $results);
    }

    /**
     * Get children dirs or files belongs to the given $path.
     * Helper for recursion.
     *
     * @param string $path
     * @return array
     */
    protected function getElements($path)
    {
        return glob($path . DIRECTORY_SEPARATOR . '{,.}[!\.]?*', GLOB_BRACE);
    }

    /**
     * Provide console feedback.
     *
     * @param string $message
     * @param string $color
     * @return string
     */
    private function success($message = 'Success', $color = self::GREEN)
    {
        return sprintf('%s%s%s', $color, $message, self::RESET) . PHP_EOL;
    }

    /**
     * Provide console feedback.
     *
     * @param string $message
     * @param string $color
     * @return string
     */
    private function error($message = 'Error', $color = self::RED)
    {
        return sprintf('%s%s%s', $color, $message, self::RESET) . PHP_EOL;
    }

    /**
     * Validate arguments.
     *
     * @param $args
     */
    private function validate($args)
    {
        if (count($args) < 2) {
            throw new InvalidArgumentException('Command not passed.');
        }

        if (isset($args[1])) {
            if (! in_array($args[1], ['deploy', 'checkout', 'list', 'prune', 'reset'])) {
                throw new InvalidArgumentException('Not acceptable command, ' . $args[1]);
            }

            if (in_array($args[1], ['deploy', 'checkout', 'prune']) and ! isset($args[2])) {
                throw new InvalidArgumentException('Argument must be provided.');
            }

            if (in_array($args[1], ['list', 'reset']) and count($args) > 2) {
                throw new InvalidArgumentException('Unnecessary argument provided.');
            }

            if ($args[1] == 'prune' and ! is_numeric($args[2])) {
                throw new InvalidArgumentException('Argument should be a numeric value.');
            }
        }
    }

    /**
     * Show signature of this script.
     */
    private function printSignature()
    {
        echo <<<EOT
Usage: php officer \033[1mcommand [option]\033[0m

    \033[1mAvailable commands:\033[0m
    \033[32mdeploy\033[0m      Hey officer, record this release.
    \033[32mcheckout\033[0m    Hey officer, I'd like to rollback to the given release.
    \033[32mprune\033[0m       Hey officer, purge the old releases.
    \033[32mlist\033[0m        Hey officer, give me the list of live releases.
    \033[32mreset\033[0m       Hey officer, reset the release record.

    \033[1mArguments:\033[0m
    \033[32mrelease\033[0m     \033[1mName of the release directory.\033[0m Must be provided for \033[1mdeploy, checkout\033[0m command.
    \033[32mkeep\033[0m        \033[1mNumber of recent releases to keep.\033[0m Must be provided for \033[1mprune\033[0m command.
EOT;
        echo PHP_EOL;
        exit(1);
    }
}

/* Main */
$app = new Officer($argv);
$app->run();
