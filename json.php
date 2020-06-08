<?php

/*
  This script returns JSON data for the snapshot list, to be used in other automation.

  To use, send request to https://make.mudlet.org/snapshots/json.php
  Optional URL Paramers are:
   - prid=#         -- a PR ID number from github.
   - commit=#       -- a Commit ID from Git/Github.
   - platform=str   -- a string for the platform, one of:  windows, linux, macos

  The resulting JSON list will show only entries which have matching values.

*/


define("CI_SNAPSHOTS", true);

require("lib/init.php");

$PR_Filter = null;
if (isset($_GET['prid'])) {
    if (preg_match('/([0-9]+)/i', $_GET['prid'], $m)) {
        $PR_Filter = trim($m[1]);
    }
}

$Commit_Filter = null;
if (isset($_GET['commitid'])) {
    if (preg_match('/([a-f0-9]+)/i', $_GET['commitid'], $m)) {
        $Commit_Filter = trim($m[1]);
    }
}

$Platform_Filter = null;
if (isset($_GET['platform'])) {
    if (preg_match('/(windows|linux|macos)/i', $_GET['platform'], $m)) {
        $Platform_Filter = trim($m[1]);
    }
}


try {
    $stmt = $dbh->prepare("SELECT `file_name`, `file_key`, `time_created`, `time_expires`, `max_downloads`, `num_downloads`
                           FROM `Snapshots` 
                           WHERE `time_expires` > NOW()
                           ORDER BY `time_created` DESC");
    $stmt->execute();
    
    $elements = array(
        'status' => 'ok',
        'data' => array()
    );
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['max_downloads'] > 0 && $row['num_downloads'] >= $row['max_downloads']) {
            continue;
        }
        
        // reasonable defaults for json
        $snapshot = array(
            'url' => 'https://make.mudlet.org/snapshots/',
            'prid' => null,
            'commitid' => null,
            'platform' => 'unknown',
            'creation_time' => null
        );
        
        // URL for snapshot
        $url = getSnapshotURL($row['file_name'], $row['file_key']);
        $snapshot['url'] = $url;
        
        // PR and Commit IDs
        preg_match('/(?:-PR([0-9]+))?-([a-f0-9]{5,9})[\.-]{1}/i', $row['file_name'], $m);
        
        $PR_ID = $Commit_ID = "";
        if (count($m) == 3) {
            if (!empty($m[1])) {
                $PR_ID = $m[1];
            }
            if (!empty($m[2])) {
                $Commit_ID = $m[2];
            }
        } elseif (count($m) == 2) {
            if (!empty($m[2])) {
                $Commit_ID = $m[2];
            }
        }
        // apply filters, if any.
        if ($PR_Filter !== null && $PR_Filter != $PR_ID) {
            continue;
        }
        if ($Commit_Filter !== null && $Commit_Filter != $Commit_ID) {
            continue;
        }
        
        // set data post filters.
        $snapshot['prid'] = (empty($PR_ID)) ? null : $PR_ID;
        $snapshot['commitid'] = (empty($Commit_ID)) ? null : $Commit_ID;
        
        // Platform parsing.
        $lowerFilename = strtolower($row['file_name']);
        if (
            false !== strpos($lowerFilename, 'windows') ||
             false !== strpos($lowerFilename, 'exe')
        ) {
            $snapshot['platform'] = 'windows';
        }
        if (
            false !== strpos($lowerFilename, 'linux') ||
             false !== strpos($lowerFilename, 'appimage')
        ) {
            $snapshot['platform'] = 'linux';
        }
        if (false !== strpos($lowerFilename, 'dmg')) {
            $snapshot['platform'] = 'macos';
        }
        if ($Platform_Filter !== null && $Platform_Filter != $snapshot['platform']) {
            continue;
        }
        
        // Date/time
        $date = DateTime::createFromFormat("Y-m-d H:i:s", $row['time_created']);
        $datetime8601 = $date->format('c');
        $snapshot['creation_time'] = $datetime8601;
        
        // add this snapshot to the outgoing collection
        $elements['data'][] = $snapshot;
    }
    $stmt = null;
    
    if (count($elements['data']) > 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($elements);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'status' => 'error',
            'data' => 'no data!'
        ));
    }
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'status' => 'error',
        'data' => 'cannot fetch data!'
    ));
}
