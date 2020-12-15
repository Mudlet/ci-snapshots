<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}


if (!function_exists('_')) {
    function _($msg)
    {
        return $msg;
    }
}

function isSafeExtension($ext)
{
    $safe = explode(',', ALLOWED_FILE_EXT);
    if (in_array($ext, $safe)) {
        return true;
    }
    return false;
}

function human_filesize($bytes, $decimals = 2)
{
    $sz = 'BKMGT';
    $factor = floor((strlen($bytes) - 1) / 3);
    $unit = @$sz[$factor];
    if ($unit == 'B') {
        $decimals = 0;
    }
    return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . $unit;
}

function getSnapshotDirectorySize()
{
    global $ScriptPath;
    $f = $ScriptPath . '/' . UPLOAD_DIR;
    $io = popen('/usr/bin/du -sb ' . $f, 'r');
    $size = fgets($io, 4096);
    $size = substr($size, 0, strpos($size, "\t"));
    pclose($io);
    return intval($size);
}

function getTempFileName()
{
    global $ScriptPath;
    $filepath = $ScriptPath . '/' . TEMP_DIR;
    if (!is_dir($filepath)) {
        mkdir($filepath, 0775, true);
    }
    $filename = tempnam($filepath, 'snps_');
    return $filename;
}

function makeSnapshotFileIDs($filename)
{
    global $ScriptPath;
    
    $uid = substr(uniqid(), -6);
    $url = SITE_URL . $uid . '/' . $filename;
    $filepath = $ScriptPath . '/' . UPLOAD_DIR . $uid . '_' . $filename;
    
    return array($filepath, $url, $uid);
}

function getSnapshotFilePath($filename, $key)
{
    global $ScriptPath;
    $filepath = $ScriptPath . '/' . UPLOAD_DIR . $key . '_' . $filename;
    return $filepath;
}

function getSnapshotURL($filename, $key)
{
    $url = SITE_URL . $key . '/' . $filename;
    return $url;
}


