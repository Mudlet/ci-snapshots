<?php

define('CI_SNAPSHOTS', true);

require 'lib/init.php';

if (isset($_GET['dl'])) {
    $dl_parts = explode('_', $_GET['dl']);
    if (count($dl_parts) >= 2) {
        $dl_key      = $dl_parts[0];
        $dl_filename = str_replace($dl_key . '_', '', $_GET['dl']);

        $dl_sid = CheckSnapshotExists($dl_filename, $dl_key);
        if ($dl_sid === false) {
            ExitFileNotFound();
        }

        $dl_filepath = getSnapshotFilePath($dl_filename, $dl_key);
        if (! is_file($dl_filepath)) {
            ExitFileNotFound();
        } else {
            if (! is_readable($dl_filepath)) {
                ExitFailedRequest(_('Failed Request - File Read Error'));
            }

            @set_time_limit(0);

            $size = @filesize($dl_filepath);
            $ext = strtolower(substr($dl_filepath, -3));
            switch($ext) {
                case 'zip':
                    $ctype = 'application/zip';
                    break;
                case 'tar':
                    // we can't be sure if its tar+gzip, tar+bz2, or just plain tar.
                    // mime sniffing with magic.mime is probably best here...
                    $ctype = mime_content_type($dl_filepath);
                    break;
                case 'dmg':
                    $ctype = 'application/x-apple-diskimage';
                    break;
                case 'exe':
                    $ctype = 'application/vnd.microsoft.portable-executable';
                    break;
                default:  
                    $ctype = 'application/octet-stream';
                    break;
            }
            
            // check for Range header and implied Download Resume.
            $range_start = $range_end = null;
            if (isset($_SERVER['HTTP_RANGE'])) {
                $range_header = trim($_SERVER['HTTP_RANGE']);
                $has_range = preg_match('/^bytes=(-?\d+|\d+-\d+)(?:,.*)?$/iu', $range_header, $range_match);
                
                if ($has_range === 1 && count($range_match) == 2) {
                    $range_str = $range_match[1];
                    if (substr($range_str, 0, 1) === '-') {
                        $from_end = abs(intval($range_str));
                        $range_start = ($size - 1) - $from_end;
                        $range_end = ($size - 1);
                    } else {
                        $rparts = explode('-', $range_str);
                        $range_start = abs(intval( $rparts[0] ));
                        $range_end = abs(intval( $rparts[1] ));
                        
                        $range_end = min($range_end, ($size - 1));
                        $range_start = ($range_end < $range_start) ? 0 : $range_start;
                    }
                }
                
                if ($range_start == 0 && $range_end == ($size - 1)) {
                    $range_start = null;
                    $range_end = null;
                }
            }
            
            $file = @fopen($dl_filepath, 'rb');
            if ($file !== false && $size !== false) {
                UpdateSnapshotDownloads($dl_sid);
                AddDownloadLogRecord($dl_filepath);
                
                if ($range_start !== null && $range_end !== null) {
                    @fseek($file, $range_start);
                    
                    $part_size = ($range_end - $range_start + 1);
                    
                    header('HTTP/1.1 206 Partial Content');
                    header('Content-Range: bytes ' . strval($range_start) . '-' . strval($range_end) .'/'. strval($size));
                    header("Content-Length: ${part_size}");
                } else {
                    header("Content-Length: ${size}");
                }                
                header('Accept-Ranges: bytes');
                header('Content-Type: ' . $ctype);
                header('Content-Transfer-Encoding: Binary');
                header('Content-Encoding: Binary');
                header('Content-Disposition: attachment; filename="' . $dl_filename . '"');
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

                // Note: this may need to be adjusted to support a range between mid and end...
                while (! feof($file)) {
                    print( @fread($file, 8192) );
                    @ob_flush();
                    @flush();
                }
                fclose($file);
            } else {
                ExitFailedRequest();
            }
        }
    } else {
        ExitFileNotFound();
    }
} else {
    $page               = file_get_contents('tpl/index.tpl.html');
    $totalSizeListedStr = '';

    try {
        $stmt = $dbh->prepare(
            'SELECT `file_name`, `file_key`, `time_created`, `time_expires`, `max_downloads`, `num_downloads` 
                               FROM `Snapshots` 
                               WHERE `time_expires` > NOW()
                               ORDER BY `time_created` DESC'
        );
        $stmt->execute();

        $elements = '<li class="hinfo">' . _('Name') .
                      '<span class="fileinfo">' .
                        '<span class="filetime">' . _('Created') . '</span>' .
                        '<span class="filesize">' . _('Size') . '</span>' .
                        '<span class="filetime">' . _('Expires') . '</span>' .
                        '<span class="filesize">' . _('DL #') . '</span>' .
                        '<span class="filegitlinks">' . _('Github') . '</span>' .
                    "</span></li>\n";
        $latest_branch_snaps = array(
            'windows' => null,
            'linux'   => null,
            'macos'   => null,
        );
        $branch_names        = array( 'pull-request', 'branch' ); // defaults needed for client-side js.
        $branch_options      = '';

        $totalSizeListed = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['max_downloads'] > 0 && $row['num_downloads'] >= $row['max_downloads']) {
                continue;
            }

            $url      = getSnapshotURL($row['file_name'], $row['file_key']);
            $filepath = getSnapshotFilePath($row['file_name'], $row['file_key']);

            $filesizebytes = 0;
            if (! is_file($filepath)) {
                continue;
            } else {
                $filesizebytes   = filesize($filepath);
                $totalSizeListed = $totalSizeListed + $filesizebytes;
            }
            $date   = DateTime::createFromFormat('Y-m-d H:i:s', $row['time_created']);
            $exdate = DateTime::createFromFormat('Y-m-d H:i:s', $row['time_expires']);

            $datetime       = $date->format('Y-m-d H:i');
            $datetime8601   = $date->format('c');
            $exdatetime     = $exdate->format('Y-m-d H:i');
            $exdatetime8601 = $exdate->format('c');
            $filesize       = human_filesize($filesizebytes);
            $fname          = $row['file_name'];

            // should probably start pushing explicit data to the DB instead of doing this regex stuff
            // but that would be more complicated in CI...
            preg_match('/(?:-PR([0-9]+))?-([a-f0-9]{5,9})[\.-]{1}/i', $fname, $m);
            preg_match('/\d+(?:\.\d+)+-(ptb)-(?:PR\d+-|\d+-)?/i', $fname, $bm);

            // Build? Branch?  The regex pulls exact match but this code is extensible.
            $branch_class = '';
            if (count($bm) == 2) {
                if (! empty($bm[1])) {
                    if (! in_array($bm[1], $branch_names)) {
                        $branch_names[]  = $bm[1];
                        $opt_text = $bm[1] . ' ' . _('Only');
                        $branch_options .= '<option value="' . $bm[1] . '">' . $opt_text . '</option>';
                    }
                    $branch_class = $bm[1];
                }
            }

            $source_class = 'branch ' . $branch_class;
            $gitLinks     = $PR_ID = $Commit_ID = '';
            if (count($m) == 3) {
                if (! empty($m[1])) {
                    $source_class = 'pull-request ' . $branch_class;
                    $git_url = 'https://github.com/Mudlet/Mudlet/pull/' . $m[1];
                    $git_ttl = _('View Pull Request on Github.com');
                    $PR_ID = '<a href="' . $git_url . '" title="' . $git_ttl . '">' .
                               '<i class="far fa-code-merge"></i></a>';
                }
                if (! empty($m[2])) {
                    $git_url = 'https://github.com/Mudlet/Mudlet/commit/' . $m[2];
                    $git_ttl = _('View Commit on Github.com');
                    $Commit_ID = '<a href="' . $git_url . '" title="' . $git_ttl . '">' .
                                 '<i class="far fa-code-commit"></i></a>';
                }
            } elseif (count($m) == 2) {
                if (! empty($m[2])) {
                    $git_url = 'https://github.com/Mudlet/Mudlet/commit/' . $m[2];
                    $git_ttl = _('View Commit on Github.com');
                    $Commit_ID = '<a href="' . $git_url . '" title="' . $git_ttl . '">' .
                                 '<i class="far fa-code-commit"></i></a>';
                }
            }
            if ($Commit_ID != '' || $PR_ID != '') {
                $gitLinks = '<span class="filegitlinks">' . $PR_ID . $Commit_ID . '</span>';
            }

            $platform_icon = '<i class="fas fa-file-archive platform-icon"></i>';
            $platform_type = 'unknown';
            $lowerFilename = strtolower($row['file_name']);
            if (
                false !== strpos($lowerFilename, 'windows') ||
                 false !== strpos($lowerFilename, 'exe')
            ) {
                $platform_icon = '<i class="fab fa-windows platform-icon"></i>';
                $platform_type = 'windows';
            }
            if (
                false !== strpos($lowerFilename, 'linux') ||
                 false !== strpos($lowerFilename, 'appimage')
            ) {
                $platform_icon = '<i class="fab fa-linux platform-icon"></i>';
                $platform_type = 'linux';
            }
            if (false !== strpos($lowerFilename, 'dmg')) {
                $platform_icon = '<i class="fab fa-apple platform-icon"></i>';
                $platform_type = 'macos';
            }

            $item_classes = implode(' ', array( $platform_type, $source_class ));

            $item_link = '<a class="filename" href="' . $url . '" rel="nofollow">' . $platform_icon . $fname . '</a>';

            $item = $item_link . '<span class="fileinfo">' .
                    '<span class="filetime" data-isotime="' . $datetime8601 . '">' . $datetime .
                    '</span><span class="filesize">' . $filesize . '</span>' .
                    '<span class="filetime" data-isotime="' . $exdatetime8601 . '">' . $exdatetime . '</span>' .
                    '<span class="filedls">' . $row['num_downloads'] . '</span>' . $gitLinks . '</span>';

            $item = "<li class=\"filelist-item {$item_classes}\">{$item}</li>\n";

            $elements .= $item;


            // NOTE: This mess allows extensible filtering of the "Latest" list.
            // $inputSource = '';
            // if ( isset($_GET['source']) ) {
            // $inputSource = strval($_GET['source']);
            // if (stripos('all,branch,pull-request', $inputSource) !== false ) {
            // $inputSource = '';
            // }
            // }
            // if ( strpos($source_class, 'branch') !== false && (empty($inputSource) ||
            //      (strpos($source_class, $inputSource) !== false && !empty($inputSource))) ) {
            if (strpos($source_class, 'branch') !== false && strpos($source_class, 'ptb') !== false) {
                if ($latest_branch_snaps['windows'] == null && $platform_type == 'windows') {
                    $latest_branch_snaps['windows'] = '<span class="windows"><label>Windows:</label> ' . $item_link .
                                                      '</span>';
                }

                if ($latest_branch_snaps['linux'] == null && $platform_type == 'linux') {
                    $latest_branch_snaps['linux'] = '<span class="linux"><label>Linux:</label> ' . $item_link .
                                                    '</span>';
                }

                if ($latest_branch_snaps['macos'] == null && $platform_type == 'macos') {
                    $latest_branch_snaps['macos'] = '<span class="macos"><label>Mac OS X:</label> ' . $item_link .
                                                    '</span>';
                }
            }
        }
        $stmt = null;

        $content = '<ul class="filelist">' . $elements . "</ul>\n";

        $latest_branch_content = implode('<br>', $latest_branch_snaps);

        $totalSizeListedStr = human_filesize($totalSizeListed);
    } catch (PDOException $e) {
        $content               = _('Error while fetching Snapshot list!') . "<br/>\n";
        $latest_branch_content = $content;
    }
    
    $tpl_keys = array(
        'PG_LANG'           => $i18n_locale,
        'BRANCH_NAMES_OPTS' => $branch_options,
        'BRANCH_NAMES_JS'   => json_encode($branch_names),
        'PG_SIZE_LISTED'    => $totalSizeListedStr,
        'SITE_URL'          => SITE_URL,
        'PG_TIMEZONE'       => date_default_timezone_get(),
        'SNAPSHOT_LIST'     => $content,
        'LATEST_BRANCH_SNAPSHOTS'   => $latest_branch_content
    );
    
    require_once 'tpl/index.lang.php';
    $tpl_keys = array_merge($tpl_keys, $tpl_language_keys);
    
    foreach ($tpl_keys as $k => $v) {
        $key = '{' . $k . '}';
        $page = str_replace($key, $v, $page);
    }
    
    header('Content-Language: ' . $i18n_locale);
    $page = $page . "\n<!-- Locale Pref: " . $i18n_locale_prefer . ' -->';
    echo( utf8_encode($page) );
}
