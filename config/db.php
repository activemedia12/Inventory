<?php

$host = 'localhost';
$user = 'u382513771_admin';
$password = 'Amdp1205';
$database = 'u382513771_inventory
';

$mysqli = new mysqli($host, $user, $password, $database);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}
