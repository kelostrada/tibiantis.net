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

function getRecordingFile($recordingId)
{
    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query for SELECT statement
    $sql = "SELECT * FROM recordings WHERE id = $recordingId;";

    // Execute the query
    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    // Check if there are rows returned
    $recording = mysqli_fetch_object($result);

    $recording->stored_file = $recording->filename . "-" . $recording->sha;
    $recording->file_path = "../recordings/" . $recording->stored_file;
    return $recording;
}

$file = getRecordingFile($_GET['recording_id']);

$fp = fopen($file->file_path, 'rb');

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.$file->stored_file);
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($file->file_path));

fpassthru($fp);
exit;