<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require __DIR__ . '/../vendor/autoload.php';

$starttime = time();

// read step
$step = (int)file_get_contents('step.map.txt');

mlog("Starting Job, step: $step");

// read lock
$lock = (int)file_get_contents('lock.map.txt');
// lock
file_put_contents('lock.map.txt', 1);

if ($lock !== 0) {
    mlog("Job locked.");
    exit;
}

$stepInc = 1;
$tiles = 3;

$xMin = 31900;
$xMax = 33500;
$yMin = 31500;
$yMax = 33000;

$addedTilesCount = 0;

for ($x = $xMin; $x < $xMax; $x += $tiles) {
    for ($y = $yMin + $step * $tiles; $y < $yMin + ($step + $stepInc) * $tiles; $y += $tiles) {
        $x1 = $x;
        $x2 = $x + $tiles;
        $y1 = $y;
        $y2 = $y + $tiles;

        // mlog("Processing ($x1, $y1) ($x2, $y2)");

        // SELECT map fields with most popular items
        $sql = "SELECT t.x, t.y, t.z, t.items FROM ( 
            SELECT x, y, z, items, row_number() over (partition by x, y, z order by count(items) desc) as rn 
            FROM tiles 
            where x >= $x1 and x < $x2 and y >= $y1 and y < $y2 
            group by items, x, y, z 
            order by x, y, z 
        ) t 
        WHERE t.rn = 1;";

        // mlog($sql);

        // Execute the query
        $mapTiles = db_query($sql);

        // INSERT map data
        $sql = "REPLACE INTO map_tiles (x, y, z, items) VALUES ";
        $tileValues = [];

        foreach ($mapTiles as $mapTile) {
            $tx = $mapTile['x'];
            $ty = $mapTile['y'];
            $tz = $mapTile['z'];
            $items = $mapTile['items'];
            $tileValues[] = "($tx, $ty, $tz, '$items')";
        }

        $sql .= join(", ", $tileValues);

        $addedTilesCount += count($tileValues);

        // mlog($sql);

        if (count($tileValues) > 0) {
            // Execute the query
            db_query($sql);
        }
    }
}

$yFrom = $yMin + $step * $tiles;
$yTo = $yMin + ($step + $stepInc) * $tiles - 1;
mlog("Checked ($xMin, $yFrom) to ($xMax, $yTo)");

// set step
if ($yFrom > $yMax) {
    $step = 0;
    mlog("Finished Job.");
} else {
    $step += $stepInc;
}

// save step
file_put_contents('step.map.txt', $step);
// unlock
file_put_contents('lock.map.txt', 0);

$time = time() - $starttime;
mlog("Job done, added tiles: $addedTilesCount, unlocked, next step: $step, job took: $time s.");

