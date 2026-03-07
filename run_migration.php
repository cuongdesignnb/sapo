<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new \Symfony\Component\Console\Input\ArrayInput(['command' => 'migrate', '--force' => true]),
    new \Symfony\Component\Console\Output\ConsoleOutput()
);
echo "Status: $status\n";
