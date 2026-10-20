<?php
$passwords = ['', 'secret', 'root', 'password', 'mysql'];
foreach ($passwords as $pwd) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', $pwd);
        echo "Connected with password: '$pwd'\n";
        exit(0);
    } catch (Exception $e) {
        echo "Failed with password: '$pwd' - " . $e->getMessage() . "\n";
    }
}