<?php

declare(strict_types=1);
$integration = __DIR__;
$binary = $integration . '/vendor/pestphp/pest/bin/pest';
$packages = ['interceptor', 'interceptor-app', 'http-paginate-interceptor', 'http-respond-interceptor', 'serialize-interceptor'];
$status = 0;
foreach ($packages as $package) {
    $root = dirname($integration, 2) . '/' . $package;
    $configuration = is_file($root . '/phpunit.xml') ? $root . '/phpunit.xml' : $root . '/phpunit.xml.dist';
    $tests = $package === 'interceptor-app' ? '../tests' : '../../' . $package . '/tests';
    $command = [PHP_BINARY];
    if (($ini = php_ini_loaded_file()) !== false) {
        array_push($command, '-c', $ini);
    }
    array_push($command, $binary, '--test-directory=' . $tests, '--configuration=' . $configuration, '--colors=never', ...array_slice($argv, 1));
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start tests for ' . $package);
    }
    $exit = proc_close($process);
    if ($exit !== 0) {
        $status = 1;
    }
}
exit($status);
