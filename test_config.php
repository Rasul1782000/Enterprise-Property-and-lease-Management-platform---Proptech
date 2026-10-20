<?php
require 'backend/vendor/autoload.php';
$app = require 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo 'DB: ' . config('database.connections.mysql.database') . "\n";
echo 'DB Host: ' . config('database.connections.mysql.host') . "\n";
echo 'DB User: ' . config('database.connections.mysql.username') . "\n";