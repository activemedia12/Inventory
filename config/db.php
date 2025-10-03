<?php

$host = 'localhost';
$user = 'root';
$password = '';

$inventory = new mysqli($host, $user, $password, 'inventory');

if ($inventory->connect_error) {
    die('Connection failed');
}
