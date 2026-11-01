<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}

// Returns array(HTTP status, message): the snapshot URL on 200, otherwise the reason it failed.
function FetchGithubArtifact($ghaid, $unzip, $maxdays, $maxdl)
{
    global $ScriptPath;

    if (getSnapshotDirectorySize() >= MAX_CAPACITY_BYTES && MAX_CAPACITY_DELETE_OLDEST == false) {
        return array(507, 'Insufficient Storage - Try Again Later');
    }

    $ghaurl = GHA_LIST_URL . '/' . $ghaid;
    $req = do_curl_get($ghaurl, array('Authorization: token ' . GHA_AUTH_TOKEN));

    if ($req === false) {
        return array(500, 'Failed to fetch Artifact data');
    }
    if ($req[2] === 404) {
        return array(400, 'Artifact not found - ID# ' . strval($ghaid) . ' Returned: ' . strval($req[2]));
    }
    if ($req[2] !== 200) {
        return array(400, 'Artifact fetch error - ID# ' . strval($ghaid) . ' Returned: ' . strval($req[2]));
    }

    $jdata = json_decode($req[0], true);
    if ($jdata === null) {
        $eid = json_last_error();
        return array(500, 'Failed to parse Artifact JSON - ' . strval($eid));
    }

    $dl_tmpname = getTempFileName();
    if (!isset($jdata['archive_download_url'])) {
        @unlink($dl_tmpname);
        return array(500, 'Artifact JSON data is missing `archive_download_url` member');
    }

    $dl_url = $jdata['archive_download_url'];
    if (empty($dl_url) || false == preg_match(GHA_URL_REGEX, $dl_url)) {
        @unlink($dl_tmpname);
        return array(500, 'Archive download url is invalid');
    }

    $dl_req = do_curl_download($dl_url, $dl_tmpname, array('Authorization: token ' . GHA_AUTH_TOKEN));
    if ($dl_req === false) {
        @unlink($dl_tmpname);
        return array(500, 'Artifact archive download failed');
    }
    if ($dl_req[2] === 404) {
        @unlink($dl_tmpname);
        return array(400, 'Artifact archive not found - ID# ' . strval($ghaid) . ' Returned: ' . strval($dl_req[2]));
    }
    if ($dl_req[2] !== 200) {
        @unlink($dl_tmpname);
        return array(400, 'Artifact archive fetch error - ID# ' . strval($ghaid) . ' Returned: ' . strval($dl_req[2]));
    }

    // if we unzip, we extract the main file from the ZIP - exe, tar, etc.
    // Companion files like .sha256 checksums are also extracted alongside it.
    // if we don't unzip, we need to build the artifact name using JSON data and .zip extension.
    $companion_files = array();
    $ext_dir = dirname($dl_tmpname);
    if ($unzip) {
        $zip = new ZipArchive();
        $res = $zip->open($dl_tmpname, ZipArchive::RDONLY);

        if ($res === false) {
            @$zip->close();
            @unlink($dl_tmpname);
            return array(500, 'Artifact archive failed to open');
        }

        // Find the main artifact file (not a companion like .sha256)
        $artifact_filename = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fname = $zip->statIndex($i)['name'];
            if (preg_match('/\.sha256$/', $fname)) {
                $companion_files[] = $fname;
            } else {
                if ($artifact_filename !== null) {
                    $numFiles = $zip->numFiles;
                    @$zip->close();
                    @unlink($dl_tmpname);
                    return array(500, 'Artifact archive contains multiple main files - ' . strval($numFiles));
                }
                $artifact_filename = $fname;
            }
        }

        if ($artifact_filename === null) {
            @$zip->close();
            @unlink($dl_tmpname);
            return array(500, 'Artifact archive contains no main file');
        }

        $fstat = $zip->statIndex($zip->locateName($artifact_filename));

        if (strpos($artifact_filename, '/') !== false || strpos($artifact_filename, '\\') !== false) {
            @$zip->close();
            @unlink($dl_tmpname);
            return array(500, 'Artifact archive contains invalid filename');
        }

        $artifact_tmpname = $ext_dir . DIRECTORY_SEPARATOR . $artifact_filename;

        // Extract the main artifact file
        $exs = $zip->extractTo($ext_dir, array($artifact_filename));
        // Also extract companion files (.sha256 checksums) alongside it
        if (!empty($companion_files)) {
            $zip->extractTo($ext_dir, $companion_files);
        }
        $zip->close();
        @unlink($dl_tmpname);

        if ($exs === false || !file_exists($artifact_tmpname)) {
            return array(500, 'Artifact extraction failed');
        }

        // more checks here... crc32b
        $hash = hash_file('crc32b', $artifact_tmpname);
        $array = unpack('N', pack('H*', $hash));
        $crc32 = $array[1];
        if ($crc32 != $fstat['crc']) {
            @unlink($artifact_tmpname);
            return array(500, 'Artifact file failed crc');
        }

        $dl_tmpname = $artifact_tmpname;
        $basename = basename($dl_tmpname);
    } else {
        $basename = $jdata['name'] . '.zip';
    }

    $uri_parts = pathinfo($basename);
    if (! isSafeExtension($uri_parts['extension'])) {
        return array(500, "Failed to save {$basename} - Bad Extension\n");
    }

    if (MAX_CAPACITY_DELETE_OLDEST == true) {
        MakeRoomForSnapshot(filesize($dl_tmpname));
    }

    $snapshot_ids = makeSnapshotFileIDs($basename);
    $snapshot_path = $snapshot_ids[0];
    $snapshot_url = $snapshot_ids[1];
    $snapshot_uid = $snapshot_ids[2];
    if (!rename($dl_tmpname, $snapshot_path)) {
        unlink($dl_tmpname);
        return array(500, "Failed to save {$basename}\n");
    }

    // Move companion files (.sha256 checksums) alongside the main artifact
    foreach ($companion_files as $cfile) {
        $cfile_tmp = $ext_dir . DIRECTORY_SEPARATOR . $cfile;
        if (file_exists($cfile_tmp)) {
            $cfile_dest = $ScriptPath . '/' . UPLOAD_DIR . $snapshot_uid . '_' . $cfile;
            rename($cfile_tmp, $cfile_dest);
        }
    }

    AddNewSnapshot($basename, $snapshot_uid, $maxdays, $maxdl);
    AddUploadLogRecord($snapshot_path);

    return array(200, $snapshot_url);
}
