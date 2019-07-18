<?php

define("CI_SNAPSHOTS", true);

require("lib/init.php");

if( isset($_GET['dl']) ) {
    $dl_parts = explode("_", $_GET['dl']);
    if( count($dl_parts) >= 2 ) {
        $dl_key = $dl_parts[0];
        $dl_filename = str_replace($dl_key.'_', '', $_GET['dl']);
        
        $dl_sid = CheckSnapshotExists($dl_filename, $dl_key);
        if( $dl_sid === false ) {
            ExitFileNotFound();
        }
        
        $dl_filepath = getSnapshotFilePath($dl_filename, $dl_key);
        if( !is_file($dl_filepath) ) {
            ExitFileNotFound();
        } else {
            if( !is_readable($dl_filepath) ) {
                ExitFailedRequest("Failed Request - File Read Error");
            }
            
            @set_time_limit(0);
            
            $size = @filesize($dl_filepath);
            $file = @fopen($dl_filepath, "rb");
            if( $file !== false && $size !== false ) {
                UpdateSnapshotDownloads($dl_sid);
                AddDownloadLogRecord($dl_filepath);
                
                header('Content-Type: application/octet-stream');
                header("Content-Length: ${size}");
                header("Content-Transfer-Encoding: Binary");
                header("Content-Encoding: Binary");
                header("Content-Disposition: attachment; filename=\"". $dl_filename ."\"");
                header("Pragma: public");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                
                while(!feof($file)) {
                    print(@fread($file, 8192));
                    @ob_flush();
                    @flush();
                }
            } else {
                ExitFailedRequest();
            }
        }
    } else {
        ExitFileNotFound();
    }
    
} 
else {
    $page = file_get_contents('tpl/index.tpl.html');
    $totalSizeListedStr = "";
    
    try {
        $stmt = $dbh->prepare("SELECT `file_name`, `file_key`, `time_created`, `time_expires`, `max_downloads`, `num_downloads` 
                               FROM `Snapshots` 
                               WHERE `time_expires` > NOW()
                               ORDER BY `time_created` DESC");
        $stmt->execute();
        
        $elements = '<li class="hinfo"> Name <span class="fileinfo"><span class="filetime">Created</span><span class="filesize">Size</span>'.
                    '<span class="filetime">Expires</span><span class="filesize">DL #</span><span class="filegitlinks">Github</span></span></li>';
        
        $totalSizeListed = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if( $row['max_downloads'] > 0 && $row['num_downloads'] >= $row['max_downloads'] ) {
                continue;
            }
            
            $url = getSnapshotURL($row['file_name'], $row['file_key']);
            $filepath = getSnapshotFilePath($row['file_name'], $row['file_key']);
            
            $filesizebytes = 0;
            if( !is_file($filepath) ) {
                continue;
            } else {
                $filesizebytes = filesize($filepath);
                $totalSizeListed = $totalSizeListed + $filesizebytes;
            }
            $date = DateTime::createFromFormat("Y-m-d H:i:s", $row['time_created']);
            $exdate = DateTime::createFromFormat("Y-m-d H:i:s", $row['time_expires']);
            
            $datetime = $date->format('Y-m-d H:i');
            $datetime8601 = $date->format('c');
            $exdatetime = $exdate->format('Y-m-d H:i');
            $exdatetime8601 = $exdate->format('c');
            $filesize = human_filesize($filesizebytes);
            #$fname = $row['file_key'] . '/' . $row['file_name'];
            $fname = $row['file_name'];
            
            preg_match('/(?:-PR([0-9]+))?-([a-f0-9]{5,9})[\.-]{1}/i', $fname, $m);
            
            $gitLinks = $PR_ID = $Commit_ID = "";
            if( count($m) == 3 ) {
                if( !empty($m[1]) ) {
                    $PR_ID = '<a href="https://github.com/Mudlet/Mudlet/pull/'. $m[1] .'" title="View Pull Request on Github.com"><i class="far fa-code-merge"></i></a>';
                }
                if( !empty($m[2]) ) {
                    $Commit_ID = '<a href="https://github.com/Mudlet/Mudlet/commit/'. $m[2] .'" title="View Commit on Github.com"><i class="far fa-code-commit"></i></a>';
                }
            }
            elseif( count($m) == 2 ) {
                if( !empty($m[2]) ) {
                    $Commit_ID = '<a href="https://github.com/Mudlet/Mudlet/commit/'. $m[2] .'" title="View Commit on Github.com"><i class="far fa-code-commit"></i></a>';
                }
            }
            if( $Commit_ID != "" || $PR_ID != "" ) {
                $gitLinks = '<span class="filegitlinks">'. $PR_ID . $Commit_ID .'</span>';
            }
            
            $item = '<a class="filename" href="'.$url.'">'.$fname.'</a><span class="fileinfo">'.
                    '<span class="filetime" data-isotime="'. $datetime8601 .'">'. $datetime . 
                    '</span><span class="filesize">'. $filesize .'</span>'.
                    '<span class="filetime" data-isotime="'. $exdatetime8601 .'">'. $exdatetime .'</span>' .
                    '<span class="filedls">'. $row['num_downloads'] .'</span>'. $gitLinks .'</span>';
            
            $elements .= "<li class=\"filelist-item\">{$item}</li>\n";
        }
        $stmt = null;
        
        $content = '<ul class="filelist">'. $elements ."</ul>\n";
        $totalSizeListedStr = human_filesize( $totalSizeListed );
    } catch (PDOException $e) {
        $content = "Error while fetching Snapshot list!<br/>\n";
    }
    
    $page = str_replace('{pg_size_listed}', $totalSizeListedStr, $page);
    $page = str_replace('{SITE_URL}', SITE_URL, $page);
    $page = str_replace('{pg_timezone}', date_default_timezone_get(), $page);
    $page = str_replace('{pg_content}', $content, $page);
    echo($page);
}

