<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require __DIR__ . '/../vendor/autoload.php';

$starttime = time();

// read step
$step = (int)file_get_contents('step.txt');

mlog("Starting Job, step: $step");

// read lock
$lock = (int)file_get_contents('lock.txt');
// lock
file_put_contents('lock.txt', 1);

if ($lock !== 0) {
    mlog("Job locked.");
    exit;
}

$temp = file_get_contents('temp.json');
if ($temp) {
    $temp = json_decode($temp, true);
} else {
    $temp = ['characters' => [], 'uploaders' => []];
}

$stepInc = 1;
$tiles = 4;

$xMin = 31900;
$xMax = 33500;
$yMin = 31500;
$yMax = 33000;

try {
    for ($x = $xMin; $x < $xMax; $x += $tiles) {
        for ($y = $yMin + $step * $tiles; $y < $yMin + ($step + $stepInc) * $tiles; $y += $tiles) {
            $x1 = $x;
            $x2 = $x + $tiles;
            $y1 = $y;
            $y2 = $y + $tiles;

            // mlog("Processing ($x1, $y1) ($x2, $y2)");

            // SELECT characters
            $sql = "SELECT r.character_name, COUNT(DISTINCT t.x, t.y, t.z) as count
            FROM `tiles` t
            JOIN `recordings` r ON r.id = t.recording_id
            WHERE t.x >= $x1 and t.x < $x2 AND t.y >= $y1 AND t.y < $y2
            GROUP by r.character_name;";

            // Execute the query
            $characters = db_query($sql);

            foreach ($characters as $character) {
                $temp['characters'][$character['character_name']] += $character['count'];
            }

            // SELECT uploaders
            $sql = "SELECT r.uploader, COUNT(DISTINCT t.x, t.y, t.z) as count
            FROM `tiles` t
            JOIN `recordings` r ON r.id = t.recording_id
            WHERE t.x >= $x1 and t.x < $x2 AND t.y >= $y1 AND t.y < $y2
            GROUP by r.uploader;";

            // Execute the query
            $uploaders = db_query($sql);

            foreach ($uploaders as $uploader) {
                $temp['uploaders'][$uploader['uploader']] += $uploader['count'];
            }
        }
    }
} catch(Exception $e) {
    mlog('Caught exception: ' .  $e->getMessage());

    // unlock
    file_put_contents('lock.txt', 0);
    exit;
}

$yFrom = $yMin + $step * $tiles;
$yTo = $yMin + ($step + $stepInc) * $tiles - 1;
mlog("Checked ($xMin, $yFrom) to ($xMax, $yTo)");

// reset temp data and save results
if ($yFrom > $yMax) {
    $step = 0;

    mlog("Finished Job. SQLs:");

    // INSERT character data
    $sql = "REPLACE INTO character_unique_tiles (character_name, count) VALUES ";

    $characterValues = [];
    foreach($temp['characters'] as $characterName => $count) {
        $characterName = mescape_string($characterName);
        $characterValues[] = "('$characterName', $count)";
    }

    $sql .= join(", ", $characterValues);
    
    mlog($sql);

    // Execute the query
    db_query($sql);

    // INSERT uploader data
    $sql = "REPLACE INTO uploader_unique_tiles (uploader, count) VALUES ";

    $uploaderValues = [];
    foreach($temp['uploaders'] as $uploaderName => $count) {
        $uploaderName = mescape_string($uploaderName);
        $uploaderValues[] = "('$uploaderName', $count)";
    }

    $sql .= join(", ", $uploaderValues);

    mlog($sql);

    // Execute the query
    db_query($sql);

    file_put_contents('temp.json', json_encode(false));
} else {
    $step += $stepInc;
    file_put_contents('temp.json', json_encode($temp, JSON_PRETTY_PRINT));
}

// save step & unlock
file_put_contents('step.txt', $step);
// unlock
file_put_contents('lock.txt', 0);

$time = time() - $starttime;
mlog("Job done, unlocked, next step: $step, job took: $time s.");