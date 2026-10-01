<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}


// Eviction tiers used when storage is over capacity - lower tiers are removed first.
// "Superseded" means a newer upload exists of the same kind, PR and flavour.
define('EVICT_TIER_SUPERSEDED_BUILD', 0);
define('EVICT_TIER_PR_BUILD', 1);
define('EVICT_TIER_OTHER_BUILD', 2);
define('EVICT_TIER_SUPERSEDED_PTB', 3);
// The newest PTB of each flavour is never evicted for space - the updater links to it.
define('EVICT_TIER_PROTECTED', 4);

// Companion files stored next to a snapshot, named "{key}_{file_name}{suffix}".
$SnapshotCompanionSuffixes = array('.sha256');


/**
 * Parse a CI snapshot filename into its build kind, PR number, commit and flavour.
 *
 * Examples of names produced by Mudlet's CI:
 *   Mudlet-4.19.1-ptb-2026-09-28-a1b2c3d4-windows-64.exe
 *   Mudlet-4.19.1-ptb-2026-09-28-a1b2c3d4rebuild2-windows-64.exe
 *   Mudlet-4.19.1-testing-pr10958-a1b2c3d4-linux-x64.AppImage
 *   Mudlet-4.19.1-testing-a1b2c3d4-windows-64.zip
 *
 * The flavour is everything after the commit (e.g. "windows-64.exe", "arm64.dmg"), which
 * stays the same from one build of a platform to the next.
 */
function ClassifySnapshotName($fileName)
{
    $info = array(
        'kind' => 'other',
        'pr' => null,
        'commit' => null,
        'flavour' => $fileName,
    );

    $re = '/-(ptb|testing)(?:-pr(\d+))?(?:-\d{4}-\d{2}-\d{2})?-([0-9a-f]{5,40})(?:rebuild\d+)?[.-](.+)$/i';
    if (preg_match($re, $fileName, $m) !== 1) {
        return $info;
    }

    $build = strtolower($m[1]);
    if ($build === 'ptb' && $m[2] === '') {
        $info['kind'] = 'ptb';
    } elseif ($m[2] !== '') {
        $info['kind'] = 'pr';
        $info['pr'] = intval($m[2]);
    } else {
        $info['kind'] = 'testing';
    }
    $info['commit'] = strtolower($m[3]);
    $info['flavour'] = strtolower($m[4]);

    return $info;
}

/**
 * Order snapshot rows for eviction, most expendable first.  Pure function so it can be tested
 * without a database.
 *
 * Each row needs `id`, `file_name` and `time_created`.  Rows in the protected tier are left out
 * of the result entirely.
 */
function GetSnapshotEvictionOrder($rows)
{
    $newestInGroup = array();
    $entries = array();
    foreach ($rows as $row) {
        $info = ClassifySnapshotName($row['file_name']);
        $group = $info['kind'] . '|' . strval($info['pr']) . '|' . $info['flavour'];
        $entries[] = array('row' => $row, 'info' => $info, 'group' => $group);

        if (!isset($newestInGroup[$group]) || IsSnapshotNewer($row, $newestInGroup[$group])) {
            $newestInGroup[$group] = $row;
        }
    }

    $candidates = array();
    foreach ($entries as $entry) {
        $row = $entry['row'];
        $kind = $entry['info']['kind'];
        $newest = ($newestInGroup[$entry['group']]['id'] == $row['id']);

        if ($kind === 'ptb') {
            $tier = $newest ? EVICT_TIER_PROTECTED : EVICT_TIER_SUPERSEDED_PTB;
        } elseif ($kind === 'other') {
            // Unrecognised names (manual uploads) have no meaningful grouping.
            $tier = EVICT_TIER_OTHER_BUILD;
        } elseif (!$newest) {
            $tier = EVICT_TIER_SUPERSEDED_BUILD;
        } elseif ($kind === 'pr') {
            $tier = EVICT_TIER_PR_BUILD;
        } else {
            $tier = EVICT_TIER_OTHER_BUILD;
        }

        if ($tier === EVICT_TIER_PROTECTED) {
            continue;
        }
        $row['evict_tier'] = $tier;
        $candidates[] = $row;
    }

    usort($candidates, function ($a, $b) {
        if ($a['evict_tier'] !== $b['evict_tier']) {
            return $a['evict_tier'] <=> $b['evict_tier'];
        }
        return IsSnapshotNewer($a, $b) ? 1 : (IsSnapshotNewer($b, $a) ? -1 : 0);
    });

    return $candidates;
}

function IsSnapshotNewer($a, $b)
{
    if ($a['time_created'] !== $b['time_created']) {
        return strcmp($a['time_created'], $b['time_created']) > 0;
    }
    return intval($a['id']) > intval($b['id']);
}

/**
 * Delete a snapshot's file and its companion files from disk.
 * Returns the number of bytes freed, or false if the snapshot file was already missing.
 */
function DeleteSnapshotFiles($fileName, $fileKey)
{
    global $SnapshotCompanionSuffixes;

    $filepath = getSnapshotFilePath($fileName, $fileKey);
    $found = is_file($filepath);
    $freed = 0;
    if ($found) {
        $freed += filesize($filepath);
        unlink($filepath);
    }
    foreach ($SnapshotCompanionSuffixes as $suffix) {
        if (is_file($filepath . $suffix)) {
            $freed += filesize($filepath . $suffix);
            unlink($filepath . $suffix);
        }
    }

    return $found ? $freed : false;
}

/**
 * If $fileName is a companion file (e.g. "foo.exe.sha256"), return the name of the snapshot it
 * belongs to, otherwise return false.
 */
function GetCompanionParentName($fileName)
{
    global $SnapshotCompanionSuffixes;

    foreach ($SnapshotCompanionSuffixes as $suffix) {
        $len = strlen($suffix);
        if (strlen($fileName) > $len && substr_compare($fileName, $suffix, -$len) === 0) {
            return substr($fileName, 0, -$len);
        }
    }
    return false;
}

/**
 * Remove snapshots, most expendable first, until at least $bytesToFree bytes are released.
 * Records whose files are already gone are cleaned up along the way.
 *
 * Returns array('files' => int, 'bytes' => int, 'records' => int).
 */
function FreeSnapshotSpace($bytesToFree)
{
    global $dbh;

    $result = array('files' => 0, 'bytes' => 0, 'records' => 0);
    if ($bytesToFree <= 0) {
        return $result;
    }

    $stmt = $dbh->prepare("SELECT `id`, `file_name`, `file_key`, `time_created` FROM `Snapshots`");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach (GetSnapshotEvictionOrder($rows) as $row) {
        $freed = DeleteSnapshotFiles($row['file_name'], $row['file_key']);
        RemoveSnapshotByID($row['id']);
        if ($freed === false) {
            $result['records'] += 1;
            continue;
        }

        $result['files'] += 1;
        $result['bytes'] += $freed;
        if ($result['bytes'] >= $bytesToFree) {
            break;
        }
    }

    return $result;
}

/**
 * Make room for an incoming file of $incomingBytes so storage stays under MAX_CAPACITY_BYTES.
 */
function MakeRoomForSnapshot($incomingBytes)
{
    $overBy = getSnapshotDirectorySize() + $incomingBytes - MAX_CAPACITY_BYTES;
    return FreeSnapshotSpace($overBy);
}
