<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require __DIR__ . '/vendor/autoload.php';

$starttime = time();

$sql = "SELECT count(*) as c FROM map_tiles WHERE processed = 0";
$remainingTilesToProcess = db_query($sql);

foreach ($remainingTilesToProcess as $rem)
{
    $remainingTilesToProcess = $rem['c'];
}

if ($remainingTilesToProcess == 0) {
    exit;
}

mlog("Starting Map Job, remaining: $remainingTilesToProcess");

// read lock
$lock = (int)file_get_contents('lock.map.txt');
// lock
file_put_contents('lock.map.txt', 1);

if ($lock !== 0) {
    mlog("Job locked.");
    exit;
}

$addedTilesCount = 0;
$i = 0;

// fit into 1 minute
while (time() - $starttime < 55 && $addedTilesCount < $remainingTilesToProcess) {
    $i++;

    $sql = "SELECT x, y, z FROM map_tiles WHERE processed = 0 LIMIT 500";
    // Execute the query
    $tilesToProcess = db_query($sql);
    $conds = [];

    foreach ($tilesToProcess as $tile) {
        $x = $tile['x'];
        $y = $tile['y'];
        $z = $tile['z'];

        $conds[] = "(x = $x and y = $y and z = $z)";
    }

    $sqlConds = join(' or ', $conds);

    // SELECT map fields with most popular items
    $sql = "SELECT t.x, t.y, t.z, t.items FROM ( 
        SELECT x, y, z, items, row_number() over (partition by x, y, z order by count(items) desc) as rn 
        FROM tiles 
        where $sqlConds
        group by items, x, y, z 
        order by x, y, z 
    ) t 
    WHERE t.rn = 1;";

    // Execute the query
    $mapTiles = db_query($sql);

    // INSERT map data
    $sql = "REPLACE INTO map_tiles (x, y, z, items, processed) VALUES ";
    $tileValues = [];

    foreach ($mapTiles as $mapTile) {
        $tx = $mapTile['x'];
        $ty = $mapTile['y'];
        $tz = $mapTile['z'];
        $items = $mapTile['items'];
        $tileValues[] = "($tx, $ty, $tz, '$items', true)";
    }

    $sql .= join(", ", $tileValues);

    $addedTilesCount += count($tileValues);

    if (count($tileValues) > 0) {
        // Execute the query
        db_query($sql);
    }

    // mlog("Processed batch {$i}");
}

// unlock
file_put_contents('lock.map.txt', 0);

$remainingTilesToProcess = $remainingTilesToProcess - $addedTilesCount;

$time = time() - $starttime;
mlog("Job done, added tiles: $addedTilesCount, remaining: $remainingTilesToProcess, unlocked, job took: $time s.");

