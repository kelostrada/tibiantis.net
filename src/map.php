<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require __DIR__ . '/../vendor/autoload.php';

$sql = "SELECT t.x, t.y, t.z, t.items FROM map_tiles t ORDER BY t.x, t.y, t.z";

// Execute the query
$mapTiles = db_query($sql);

foreach ($mapTiles as $tile) {
    echo pack("vvc", $tile['x'], $tile['y'], $tile['z']);

    foreach (json_decode($tile['items']) as $item) {
        echo pack("vc", $item->id, $item->count);
    }

    echo pack("v", 0xFF00);
}