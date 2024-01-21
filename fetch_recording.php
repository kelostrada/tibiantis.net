<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database configuration
$hostname = $_ENV['DB_HOSTNAME']; // MySQL server hostname
$username = $_ENV['DB_USERNAME'];; // MySQL username
$password = $_ENV['DB_PASSWORD']; // MySQL password
$database = $_ENV['DB_DATABASE']; // MySQL database name

if ($_GET['key'] !== $_ENV['SECRET_KEY']) {
    header("Content-type:application/json");
    http_response_code(403);
    echo json_encode(array("error" => "Wrong API key"));
    exit;
}

function getFirstRecording()
{
    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query for SELECT statement
    $sql = "SELECT * FROM recordings WHERE processed = 0 ORDER BY inserted_at ASC LIMIT 1;";

    // Execute the query
    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    $recording = mysqli_fetch_object($result);

    if (!$recording) return false;

    $recording->stored_file = $recording->filename . "-" . $recording->sha;
    return $recording;
}

$recording = getFirstRecording();

header("Content-type:application/json");

if($recording) {
    echo json_encode($recording);
} else {
    echo json_encode(['id' => -1, 'stored_file' => 'no new recordings']);
}

