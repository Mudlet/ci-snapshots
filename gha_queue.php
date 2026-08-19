<?php
/***
*  This file allows provides a queue system for artifacts.
*  Where a name matches several artifacts - a job re-run uploads a second under the same name - the newest is used.
*
***/

require_once("config.php");

define("CI_SNAPSHOTS", true);
require_once('lib/functions.common.php');
require_once('lib/functions.http.php');

header('Content-Type: text/plain');

if ((!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) && GHA_REQUIRES_AUTH) {
    ExitUnauthorized();
}

// bootstrap web-app.
require_once('lib/init.php');

// test given auth credentials.
if (GHA_REQUIRES_AUTH) {
    if (!CheckUserCredentials($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        ExitForbidden();
    }
}

if (getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == false) {
    ExitFullStorage();
}


$artifact_name = null;
if ( isset($_REQUEST['artifact_name']) && !empty($_REQUEST['artifact_name']) ) {
    if ( false == preg_match('/^[a-z0-9-_\.]+$/iu', $_REQUEST['artifact_name']) ) {
        ExitClientError('Invalid artifact name');
    } else {
        $artifact_name = trim($_REQUEST['artifact_name']);
    }
} else {
    ExitClientError('Missing required parameter artifact_name');
}

$unzip = false;
if ( isset($_REQUEST['unzip']) ) {
    $unzip = filter_var($_REQUEST['unzip'], FILTER_VALIDATE_BOOLEAN);
} else {
    ExitClientError('Missing required parameter unzip');
}


$artifact_queue_file = $ScriptPath . DIRECTORY_SEPARATOR . TEMP_DIR . GHA_QFILE_PREFIX . $artifact_name . GHA_QFILE_EXT ;
$gha_list_url = GHA_LIST_URL . '?per_page=100&page=1';

if ( file_exists($artifact_queue_file) ) {
    ExitFailedRequest('Artifact is already queued');
}

$list_req = do_curl_get($gha_list_url, array('Authorization: token ' . GHA_AUTH_TOKEN));
if ( $list_req === false ) {
    ExitFailedRequest('Error checking artifact list');
}
if ( $list_req[2] !== 200 ) {
    ExitFailedRequest('Failed to fetch artifact list - ' . strval($list_req[2]) );
}

$list_data = json_decode($list_req[0], true);

if ( ! isset($list_data['artifacts']) ) {
    ExitFailedRequest('Failed to find artifacts');
}

$artifact_id = null;
$artifact_created = -1;
if ( !empty($list_data['artifacts']) ) {
    // A name can legitimately match more than once: re-running a build job uploads a second
    // artifact under the same name. Pick the newest by creation time rather than trusting the
    // order the API happens to return, so a re-run publishes its own build and not the stale
    // one from the earlier attempt.
    // Only the first 100 entries are searched, so a name can still drop out of the window.
    foreach( $list_data['artifacts'] as $idx => $arti ) {
        if ( $artifact_name != $arti['name'] ) {
            continue;
        }

        $created = isset($arti['created_at']) ? strtotime($arti['created_at']) : false;
        if ( $created === false ) {
            $created = 0;
        }

        if ( $created > $artifact_created ) {
            $artifact_created = $created;
            $artifact_id = $arti['id'];
        }
    }
}

$maxdays = 0;
if (isset($_SERVER['HTTP_MAX_DAYS'])) {
    if (preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DAYS']) === 1) {
        $maxdays = intval($_SERVER['HTTP_MAX_DAYS']);
    }
}
$maxdls = 0;
if (isset($_SERVER['HTTP_MAX_DOWNLOADS'])) {
    if (preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DOWNLOADS']) === 1) {
        $maxdls = intval($_SERVER['HTTP_MAX_DOWNLOADS']);
    }
}


$artifact_data = array(
    'name' => $artifact_name,
    'unzip' => $unzip,
    'qtime' => time()
);

if ($artifact_id !== null) {
    $artifact_data['id'] = $artifact_id;
}
if ($maxdays != 0) {
    $artifact_data['maxdays'] = $maxdays;
}
if ($maxdls != 0) {
    $artifact_data['maxdls'] = $maxdls;
}

$queue_json = json_encode($artifact_data);
if ( false !== file_put_contents($artifact_queue_file, $queue_json, LOCK_EX) ) {
    echo('Artifact enqueued');
} else {
    ExitFailedRequest('Failed to enqueue artifact');
}



