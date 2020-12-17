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

if (getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == false) {
    ExitFullStorage();
}


$unzip = false;
if ( isset($_REQUEST['unzip']) ) {
    $unzip = filter_var($_REQUEST['unzip'], FILTER_VALIDATE_BOOLEAN);
} else {
    ExitClientError('Missing required parameter unzip');
}


$ghaid = 0;
$ghaurl = '';
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    // build artifact_url
    $ghaid = filter_var($_REQUEST['id'], FILTER_VALIDATE_INT);
    if ($ghaid === false) {
        ExitClientError('id parameter is not acceptable');
    }
    $ghaurl = GHA_LIST_URL . '/' . $ghaid ;
} else {
    ExitClientError('Missing required parameter id');
}

$req = do_curl_get($ghaurl, array('Authorization: token ' . GHA_AUTH_TOKEN));

if ($req === false) {
    ExitFailedRequest('Failed to fetch Artifact data');
}
if ($req[2] === 404) {
    ExitClientError('Artifact not found - ID# ' . strval($ghaid) . ' Returned: ' . strval($req[2]) );
}
if ($req[2] !== 200) {
    ExitClientError('Artifact fetch error - ID# ' . strval($ghaid) . ' Returned: ' . strval($req[2]) );
}


$jdata = json_decode($req[0], true);
if ($jdata === null) {
    $eid = json_last_error();
    ExitFailedRequest('Failed to parse Artifact JSON - ' . strval($eid));
}


$dl_tmpname = getTempFileName();
if ( isset($jdata['archive_download_url']) ) {
    $dl_url = $jdata['archive_download_url'];
    if (empty($dl_url) || false == preg_match(GHA_URL_REGEX, $dl_url)) {
        @unlink($dl_tmpname);
        ExitFailedRequest('Archive download url is invalid');
    }
    
    $dl_req = do_curl_download($dl_url, $dl_tmpname, array('Authorization: token ' . GHA_AUTH_TOKEN));
    if ($dl_req === false) {
        @unlink($dl_tmpname);
        ExitFailedRequest('Artifact archive download failed');
    }
    if ($dl_req[2] === 404) {
        @unlink($dl_tmpname);
        ExitClientError('Artifact archive not found - ID# ' . strval($ghaid) . ' Returned: ' . strval($dl_req[2]) );
    }
    if ($dl_req[2] !== 200) {
        @unlink($dl_tmpname);
        ExitClientError('Artifact archive fetch error - ID# ' . strval($ghaid) . ' Returned: ' . strval($dl_req[2]) );
    }
} else {
    @unlink($dl_tmpname);
    ExitFailedRequest('Artifact JSON data is missing `archive_download_url` member');
}


// if we unzip, we must have only one file in the ZIP - exe, tar, etc.
// if we don't unzip, we need to build the artifact name using JSON data and .zip extension.
if ($unzip) {
    $zip = new ZipArchive();
    $res = $zip->open($dl_tmpname, ZipArchive::RDONLY);

    if ( $res === false ) {
        @$zip->close();
        @unlink($dl_tmpname);
        ExitFailedRequest('Artifact archive failed to open');
    }

    if ( $zip->numFiles > 1 ) {
        $nfiles = $zip->numFiles;
        
        @$zip->close();
        @unlink($dl_tmpname);
        ExitFailedRequest('Artifact archive contains more than 1 file - ' . strval($nfiles) );
    }

    $fstat = $zip->statIndex( 0 );
    $artifact_filename = $fstat['name'];

    if ( strpos($artifact_filename, '/') !== false || strpos($artifact_filename, '\\') !== false ) {
        @$zip->close();
        @unlink($dl_tmpname);
        ExitFailedRequest('Artifact archive contains invalid filename');
    }

    $artifact_tmpname = dirname($dl_tmpname) . DIRECTORY_SEPARATOR . $artifact_filename;
    $ext_dir = dirname( $dl_tmpname );

    // extraction should result in a path matching that of $artifact_tmpname
    $exs = $zip->extractTo($ext_dir, array($artifact_filename));
    $zip->close();
    @unlink($dl_tmpname);

    if ( $exs === false || !file_exists($artifact_tmpname) ) {
        ExitFailedRequest('Artifact extraction failed');
    }

    // more checks here... crc32b
    $hash = hash_file('crc32b', $artifact_tmpname);
    $array = unpack('N', pack('H*', $hash));
    $crc32 = $array[1];
    if ( $crc32 != $fstat['crc'] ) {
        @unlink($artifact_tmpname);
        ExitFailedRequest('Artifact file failed crc');
    }

    @unlink($dl_tmpname);
    $dl_tmpname = $artifact_tmpname;
    $basename = basename($dl_tmpname);
} else {
    $basename = $jdata['name'] . '.zip';
}


//$basename = basename($_SERVER['REQUEST_URI']);
$uri_parts = pathinfo($basename);
if (! isSafeExtension($uri_parts['extension'])) {
    ExitFailedRequest("Failed to save {$basename} - Bad Extension\n");
}

if (getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == true) {
    $targetSize = filesize($dl_tmpname);
    $clearedSize = 0;
    
    $stmt = $dbh->prepare("SELECT `file_name`, `file_key`, `time_created` 
                           FROM `Snapshots`
                           ORDER BY `time_created` ASC
                           LIMIT 10 ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $delFilePath = getSnapshotFilePath($row['file_name'], $row['file_key']);
        if (is_file($delFilePath)) {
            $clearedSize = $clearedSize + filesize($delFilePath);
            unlink($delFilePath);
        }
        
        if ($clearedSize >= $targetSize) {
            break;
        }
    }
}

$snapshot_ids = makeSnapshotFileIDs($basename);
$snapshot_path = $snapshot_ids[0];
$snapshot_url = $snapshot_ids[1];
$snapshot_uid = $snapshot_ids[2];
if (rename($dl_tmpname, $snapshot_path)) {
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
    
    echo($snapshot_url);
    echo("\n");
    
    AddNewSnapshot($basename, $snapshot_uid, $maxdays, $maxdl);
    AddUploadLogRecord($snapshot_path);
} else {
    ExitFailedRequest("Failed to save {$basename}\n");
    unlink($dl_tmpname);
}
