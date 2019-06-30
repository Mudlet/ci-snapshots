<?php

require_once('config.php');

if(php_sapi_name() != 'cli') {
    header("Location: ". SITE_URL);
    exit();
} 

define("CI_SNAPSHOTS", true);
require_once("lib/init.php");

if( isset($argv[1]) && $argv[1] == 'adduser'){
    if( isset($argv[2]) ) {
        $username = trim($argv[2]);
    } else {
        echo("Enter a Username: (max 120 characters): \n");
        $fr=fopen("php://stdin","r");
        $username = fgets($fr,128);
        $username = rtrim($input);
        fclose ($fr);
    }
    
    echo("Enter a Password (max 128 bytes): \n");
    
    $fr=fopen("php://stdin","r");
    $input = fgets($fr,128);
    $input = rtrim($input);
    fclose ($fr);

    $hash = password_hash($input, PASSWORD_BCRYPT);
    AddUserRecord( $username, $hash );
    
    exit();
}


// check the creation time of all files, remove any that are older than max life or are over their download limit.
$stmt = $dbh->prepare("SELECT `id`, `file_name`, `file_key` FROM `Snapshots` 
                       WHERE `time_expires` <= NOW() OR (`max_downloads` > 0 AND `num_downloads` >= `max_downloads`)");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalFiles = 0;
$totalSize = 0;
foreach($rows as $idx => $row) {
    $filepath = getSnapshotFilePath($row['file_name'], $row['file_key']);
    if( is_file($filepath) ) {
        $totalSize = $totalSize + filesize($filepath);
        $totalFiles = $totalFiles + 1;
        unlink($filepath);
    }
    RemoveSnapshotByID($row['id']);
}
if( $totalFiles > 0 ) {
    $totalSizeStr = human_filesize($totalSize);
    print("Removed {$totalFiles} Expired files ({$totalSizeStr})\n\n");
}


// check for stranded uploads
$file_dir = $ScriptPath .'/'. UPLOAD_DIR ;
$dir_list = scandir($file_dir);
$totalStranded = 0;
$totalSize = 0;
foreach($dir_list as $idx => $file) {
    if( $file == '.' || $file == '..' ) {
        continue;
    }
    $filepath = $file_dir . $file;
    if( is_dir($filepath) ) {
        continue;
    }
    
    $file_parts = explode("_", $file);
    if( count($file_parts) >= 2 ) {
        $file_key = $file_parts[0];
        $file_name = str_replace($file_key.'_', '', $file);
        
        if( ! CheckSnapshotExists($file_name, $file_key) ) {
            $windowSeconds = 3600 * STRANDED_FILE_WINDOW;
            $filemtime = filemtime($filepath);
            if( !$filemtime ) {
                $filemtime = time();
            }
            $fileWindow = $filemtime + $windowSeconds;
            
            if( time() < $fileWindow ) {
                continue;
            }
            
            print("Removing Stranded File:  {$file} \n");
            $totalStranded = $totalStranded + 1;
            $totalSize = $totalSize + filesize($filepath);
            unlink($filepath);
        }
    }
}
if( $totalStranded > 0 ) {
    $totalSizeStr = human_filesize($totalSize);
    print("Removed {$totalStranded} Expired files ({$totalSizeStr})\n\n");
}
