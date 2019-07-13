<?php

require_once("config.php");

if( $_SERVER['REQUEST_METHOD'] !== 'PUT' ){
    header("Location: ". SITE_URL);
    exit();
}

function ExitUnauthorized() {
    header('WWW-Authenticate: Basic realm="Snapshots"');
    http_response_code(401);
    header($_SERVER["SERVER_PROTOCOL"].' 401 Unauthorized');
    echo("Unauthorized\n");
    exit();
}

function ExitForbidden() {
    http_response_code(403);
    header($_SERVER["SERVER_PROTOCOL"].' 403 Forbidden');
    echo("Forbidden\n");
    exit();
}

function ExitFullStorage() {
    http_response_code(507);
    header($_SERVER["SERVER_PROTOCOL"].' 507 Insufficient Storage');
    echo("Insufficient Storage - Try Again Later\n");
    exit();
}

if( (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) && PUT_REQUIRES_AUTH ){ 
    ExitUnauthorized();
}

// bootstrap web-app.
define("CI_SNAPSHOTS", true);
require_once('lib/init.php');


// test given auth credentials.
if( PUT_REQUIRES_AUTH ) {
    if( !CheckUserCredentials($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ) {
        ExitForbidden();
    }
}


if( getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == false ) {
    ExitFullStorage();
}

$basename = basename($_SERVER['REQUEST_URI']);
$uri_parts = pathinfo($basename);
if( ! isSafeExtension($uri_parts['extension']) ) {
    ExitFailedRequest("Failed to save {$basename} - Bad Extension\n");
}

$tmpname = getTempFileName();
$tmpfp = fopen($tmpname, "w");

// PUT data comes in on stdin 
$putdata = fopen("php://input", "r");
while( $data = fread($putdata, 8192) ) {
    fwrite($tmpfp, $data);
}
fclose($tmpfp);
fclose($putdata);


if( getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == true ) {
    $targetSize = filesize($tmpname);
    $clearedSize = 0;
    
    $stmt = $dbh->prepare("SELECT `file_name`, `file_key`, `time_created` 
                           FROM `Snapshots`
                           ORDER BY `time_created` ASC
                           LIMIT 10 ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $delFilePath = getSnapshotFilePath($row['file_name'], $row['file_key']);
        if( is_file($delFilePath) ) {
            $clearedSize = $clearedSize + filesize($delFilePath);
            unlink($delFilePath);
        }
        
        if( $clearedSize >= $targetSize ) {
            break;
        }
    }
}

$snapshot_ids = makeSnapshotFileIDs($basename);
$snapshot_path = $snapshot_ids[0];
$snapshot_url = $snapshot_ids[1];
$snapshot_uid = $snapshot_ids[2];
if( rename($tmpname, $snapshot_path) ) {
    $maxdays = DEFAULT_FILE_LIFETIME_DAYS;
    if( isset($_SERVER['HTTP_MAX_DAYS']) ) {
        if( preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DAYS']) === 1 ) {
            $maxdays = intval($_SERVER['HTTP_MAX_DAYS']);
        }
    }
    $maxdl = 0;
    if( isset($_SERVER['HTTP_MAX_DOWNLOADS']) ) {
        if( preg_match('/^[0-9]+$/i', $_SERVER['HTTP_MAX_DOWNLOADS']) === 1 ) {
            $maxdl = intval($_SERVER['HTTP_MAX_DOWNLOADS']);
        }
    }
    
    echo($snapshot_url);
    echo("\n"); 
    
    AddNewSnapshot($basename, $snapshot_uid, $maxdays, $maxdl);
    AddUploadLogRecord($snapshot_path);
    
} else {
    ExitFailedRequest("Failed to save {$basename}\n");
    unlink( $tmpname );
}

