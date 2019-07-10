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
    $totalClearedFiles = 0;
    $totalClearedRecords = 0;
    
    $sizeOverStr = human_filesize($targetSize);
    $sizeMaxStr = human_filesize(MAX_CAPACITY_BYTES);
    echo "Snapshot Storage is at maximum capacity! - {$sizeOverStr} over the max of {$sizeMaxStr}\n";
    
    $stmt = $dbh->prepare("SELECT `id`, `file_name`, `file_key`, `time_created` 
                           FROM `Snapshots`
                           ORDER BY `time_created` ASC
                           LIMIT 20 ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $delFilePath = getSnapshotFilePath($row['file_name'], $row['file_key']);
        if( is_file($delFilePath) ) {
            $clearedSize = $clearedSize + filesize($delFilePath);
            $totalClearedFiles = $totalClearedFiles + 1;
            unlink($delFilePath);
            RemoveSnapshotByID($row['id']);
        } else {
            // File not found, we should remove this record!
            $totalClearedRecords = $totalClearedRecords + 1;
            RemoveSnapshotByID($row['id']);
        }
        
        if( $clearedSize >= $targetSize ) {
            break;
        }
    }
    
    if( $totalClearedRecords > 0 ) {
        print("Removed {$totalClearedRecords} record(s) with missing files\n");
    }
    if( $totalClearedFiles > 0 ) {
        $totalSizeStr = human_filesize($clearedSize);
        print("Removed {$totalClearedFiles} old snapshots ({$totalSizeStr}) \n\n");
    }
}


// Check for IP Safe-List updates.
//  For now we check for travis IPs using the method 
//  documented here:  https://docs.travis-ci.com/user/ip-addresses/
//  Would like to find a method we could use for AppVeyor as well.
$ipListFile = $ScriptPath . '/ip_list';
function setIpListData($data) {
    global $ipListFile;
    $io = fopen($ipListFile, 'w');
    foreach($data as $idx => $lineparts) {
        $ip = $lineparts[0]; 
        $comment = $lineparts[1];
        if(!empty($comment)) {
            $comment = "\t# {$comment}";
        }
        $line = "{$ip}\t1{$comment}\n";
        fwrite($io, $line);
    }
    fclose($io);
}
function getIpListData() {
    global $ipListFile;
    $data = array();
    $io = fopen($ipListFile, 'r');
    while($line = fgets($io)) {
        preg_match('/([0-9a-f\.:]+)\t1(?:\t#\s*(.+))?/i', $line, $m);
        if(count($m) == 2) {
            $data[] = array($m[1], '');
        }
        if(count($m) == 3) {
            $data[] = array($m[1], $m[2]);
        }
    }
    fclose($io);
    return $data;
}
function getTravisIps() {
    $data = array();
    $io = popen('dig +short nat.travisci.net | sort', 'r');
    while($line = fgets($io)) {
        $data[] = trim($line);
    }
    pclose($io);
    return $data;
}
function filterIpsInSafeList($ipArray) {
    $ipListData = getIpListData();
    $ipList = array();
    foreach($ipListData as $idx => $set) {
        $ipList[] = $set[0];
    }
    return array_diff($ipArray, $ipList);
}

if( is_file($ipListFile) && is_readable($ipListFile) && is_writable($ipListFile) ) {
    $ipListDataArray = getIpListData();
    $ips = filterIpsInSafeList( getTravisIps() );
    foreach($ips as $idx => $ip) {
        $dObj = new DateTime();
        $date = $dObj->format('Y-m-d H:i T');
        $comment = "Travis CI address added by Cron task on {$date}";
        $ipListDataArray[] = array($ip, $comment);
    }
    
    if(count($ips) > 0) {
        echo "New Travis CI IPs added:\n";
        foreach($ips as $idx => $ip) {
            echo ' - ' . $ip . "\n";
        }
        echo "\n";
    }
    
    setIpListData( $ipListDataArray );
} else {
    echo 'Error - IP Safe-List file was not found!';
}

