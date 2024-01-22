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

function getRecording($id)
{
    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query for SELECT statement
    $sql = "SELECT * FROM recordings WHERE id = '$id';";

    // Execute the query
    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    // Check if there are rows returned
    return mysqli_fetch_object($result);
}

function updateRecording($id, $characterName)
{
    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query using a prepared statement
    $sql = "UPDATE recordings SET character_name = ?, processed = 1 WHERE id = ?";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bind_param("si", $characterName, $id);

    // Execute the statement
    if (!$stmt->execute()) {
        die("Failed to update recording $id");
    }

    // Close the statement
    $stmt->close();

    // Close the database connection
    $conn->close();
}

function insertTiles($recordingId, $tiles)
{
    if (count($tiles) == 0) {
        return 0;
    }

    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query using a prepared statement
    $sql = "INSERT INTO tiles (x, y, z, items, recording_id) VALUES ";

    $tileValues = [];
    foreach($tiles as $tile) {
        $items = json_encode($tile->items);
        $tileValues[] = "($tile->x, $tile->y, $tile->z, '$items', $recordingId)";
    }

    $sql .= join(", ", $tileValues);

    // Execute the query
    $result = $conn->query($sql);

    if ($result) {
        $sql = "SELECT count(*) FROM tiles WHERE recording_id = $recordingId";

        // Execute the query
        $result = $conn->query($sql);
        $result = $result->fetch_row();
        $result = $result[0];
    }

    // Close the database connection
    $conn->close();

    return $result;
}

function markMapTileToProcess($tiles)
{
    if (count($tiles) == 0) {
        return 0;
    }

    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $tileValues = [];

    foreach($tiles as $tile) {
        $tileValues[] = "($tile->x, $tile->y, $tile->z, false)";
    }

    $tileValues = join(", ", $tileValues);

    $sql = "INSERT INTO map_tiles (x, y, z, processed) VALUES $tileValues ON DUPLICATE KEY UPDATE processed=false;";

    // Execute the query
    $conn->query($sql);

    // Close the database connection
    $conn->close();
}

$input = json_decode(file_get_contents("php://input"));

$recordingId = $input->recordingId;
$characterName = $input->characterName;

updateRecording($recordingId, $characterName);
markMapTileToProcess($input->tiles);
$result = insertTiles($recordingId, $input->tiles);

// $recording = getRecording($recordingId);

header("Content-type:application/json");
echo json_encode(['result' => (int)$result]);


