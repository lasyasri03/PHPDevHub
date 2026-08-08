<?php

$host = getenv('DB_HOST');
$port = (int) getenv('DB_PORT');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');
$name = getenv('DB_NAME');

echo "Host: " . $host . "<br>";
echo "Port: " . $port . "<br>";
echo "Database: " . $name . "<br>";
echo "Username: " . $user . "<br><br>";

$conn = mysqli_init();

if (!$conn) {
    die("mysqli_init failed");
}

if (!$conn->real_connect($host, $user, $pass, $name, $port)) {
    die("CONNECTION FAILED: " . mysqli_connect_error());
}

echo "DATABASE CONNECTION SUCCESSFUL!";
