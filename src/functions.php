<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function mescape_string($string) {
    // Database configuration
    $hostname = $_ENV['DB_HOSTNAME']; // MySQL server hostname
    $username = $_ENV['DB_USERNAME'];; // MySQL username
    $password = $_ENV['DB_PASSWORD']; // MySQL password
    $database = $_ENV['DB_DATABASE']; // MySQL database name

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $result = mysqli_real_escape_string($conn, $string);

    // Close the database connection
    $conn->close();

    return $result;
}

function db_query($sql) {
    // Database configuration
    $hostname = $_ENV['DB_HOSTNAME']; // MySQL server hostname
    $username = $_ENV['DB_USERNAME'];; // MySQL username
    $password = $_ENV['DB_PASSWORD']; // MySQL password
    $database = $_ENV['DB_DATABASE']; // MySQL database name

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    return $result;
}

function mlog($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}