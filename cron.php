<?php

require_once('config.php');

if(php_sapi_name() != 'cli') {
    header("Location: ". SITE_URL);
    exit();
} 

define("CI_SNAPSHOTS", true);
require_once("lib/init.php");


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


// check that storage isn't using over MAX_CAPACITY_BYTES
$dirSize = getSnapshotDirectorySize();
if( $dirSize > MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == true ) {
    $targetSize = ($dirSize - MAX_CAPACITY_BYTES);
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
