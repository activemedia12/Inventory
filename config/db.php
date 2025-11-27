<?php

$host = 'localhost';
$user = 'root';
$password = '';

$inventory = new mysqli($host, $user, $password, 'inventory');

if ($inventory->connect_error) {
    die('Connection failed');
}

$inventory->set_charset("utf8mb4");
$inventory->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");