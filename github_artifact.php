<?php
/***
*  This file allows transfering artifacts stored in Github Artifact Storage to be verified and moved to our snapshot system.
*
*  URL Params:
*  - id:  artifact ID used to build an artifact_url.
*  - unzip:  use the file (singular) within the zip as the snapshot, or use the zip and artifact name.
*
*  Header Options:
*  - Max-Downloads:  number of downloads before snapshot is expired/removed.
*  - Max-Days:  number of days from upload time before snapshot is expired.
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

$unzip = false;
if ( isset($_REQUEST['unzip']) ) {
    $unzip = filter_var($_REQUEST['unzip'], FILTER_VALIDATE_BOOLEAN);
} else {
    ExitClientError('Missing required parameter unzip');
}


$ghaid = 0;
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $ghaid = filter_var($_REQUEST['id'], FILTER_VALIDATE_INT);
    if ($ghaid === false) {
        ExitClientError('id parameter is not acceptable');
    }
} else {
    ExitClientError('Missing required parameter id');
}

$maxdays = DEFAULT_FILE_LIFETIME_DAYS;
if (isset($_SERVER['HTTP_MAX_DAYS'])) {
    if (preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DAYS']) === 1) {
        $maxdays = intval($_SERVER['HTTP_MAX_DAYS']);
    }
}
$maxdl = 0;
if (isset($_SERVER['HTTP_MAX_DOWNLOADS'])) {
    if (preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DOWNLOADS']) === 1) {
        $maxdl = intval($_SERVER['HTTP_MAX_DOWNLOADS']);
    }
}

$result = FetchGithubArtifact($ghaid, $unzip, $maxdays, $maxdl);
if ($result[0] === 200) {
    echo($result[1]);
    echo("\n");
} elseif ($result[0] === 507) {
    ExitFullStorage();
} elseif ($result[0] === 400) {
    ExitClientError($result[1]);
} else {
    ExitFailedRequest($result[1]);
}
