<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}


function CreateSnapshotsTable()
{
    global $dbh;
    
    $sql = "CREATE TABLE `Snapshots` (
    `id` INTEGER NULL AUTO_INCREMENT DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_key` VARCHAR(16) NOT NULL,
    `time_created` DATETIME NOT NULL,
    `time_expires` DATETIME NOT NULL,
    `max_downloads` INTEGER NULL DEFAULT 0,
    `num_downloads` INTEGER NULL DEFAULT 0,
    PRIMARY KEY (`id`)
    );";
    
    $res = $dbh->query($sql);
}

function SnapshotsTableExists()
{
    global $dbh;
    
    $tbl_result = false;
    try {
        $tbl_result = $dbh->query("SELECT 1 FROM `Snapshots` LIMIT 1");
    } catch (PDOException $e) {
        return false;
    }
    return $tbl_result !== false;
}

function AddNewSnapshot($name, $key, $expires_in_days = 14, $max_downloads = 0)
{
    global $dbh;
    
    $exdate = new DateTime();
    $exdate->add(new DateInterval('P' . strval($expires_in_days) . 'D'));
    $expire_date = $exdate->format("Y-m-d H:i:s");
    
    $sql = "INSERT INTO `Snapshots` (`file_name`, `file_key`, `time_created`, `time_expires`, `max_downloads`) VALUES (:fname, :fkey, NOW(), :expires, :maxdl)";
    
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':fname', $name);
    $stmt->bindParam(':fkey', $key);
    $stmt->bindParam(':expires', $expire_date, PDO::PARAM_STR);
    $stmt->bindParam(':maxdl', $max_downloads, PDO::PARAM_INT);
    $stmt->execute();
}

function RemoveSnapshotByID($id)
{
    global $dbh;
    
    $stmt = $dbh->prepare("DELETE FROM `Snapshots` WHERE `id`=:sid");
    $stmt->bindParam(':sid', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function CheckSnapshotExists($name, $key)
{
    global $dbh;
    
    $stmt = $dbh->prepare("SELECT `id` FROM `Snapshots` WHERE `file_name`=:fname AND `file_key`=:fkey");
    $stmt->bindParam(':fname', $name);
    $stmt->bindParam(':fkey', $key);
    $stmt->execute();
    
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res !== false) {
        return $res['id'];
    }
    return false;
}

function UpdateSnapshotDownloads($sid)
{
    global $dbh;
    
    $stmt = $dbh->prepare("UPDATE `Snapshots` SET `num_downloads` = `num_downloads` + 1 WHERE id=:sid");
    $stmt->bindParam(':sid', $sid, PDO::PARAM_INT);
    $res = $stmt->execute();
    
    if ($res === false) {
        return false;
    } else {
        return true;
    }
}

function UsersTableExists()
{
    global $dbh;
    
    $tbl_result = false;
    try {
        $tbl_result = $dbh->query("SELECT 1 FROM `Users` LIMIT 1");
    } catch (PDOException $e) {
        return false;
    }
    return $tbl_result !== false;
}

function CreateUsersTable()
{
    global $dbh;
    
    $sql = "CREATE TABLE `Users` (
    `id` INTEGER NULL AUTO_INCREMENT DEFAULT NULL,
    `name` VARCHAR(128) NOT NULL DEFAULT 'NULL',
    `phash` VARCHAR(255) NOT NULL DEFAULT 'NULL',
    PRIMARY KEY (`id`)
    );";
    
    $res = $dbh->query($sql);
}

function CheckUserCredentials($name, $pass)
{
    global $dbh;
    
    if (empty($name) || empty($pass)) {
        return false;
    }
    
    $stmt = $dbh->prepare("SELECT `id`,`phash` FROM `Users` WHERE `name`=:user");
    $stmt->bindParam(':user', $name);
    $r = $stmt->execute();
    if (!$r) {
        return false;
    }
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($pass, $res['phash'])) {
        return false;
    } else {
        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['mudletsnaps_user_id'] = $res['id'];
        }
        return true;
    }
}

function AddUserRecord($username, $passhash)
{
    global $dbh;
    
    $stmt = $dbh->prepare("INSERT INTO `Users` (`name`, `phash`) VALUES (:name, :phash);");
    $stmt->bindParam(':name', $username);
    $stmt->bindParam(':phash', $passhash);
    
    return $stmt->execute();
}

function LogUploadsTableExists()
{
    global $dbh;
    
    $tbl_result = false;
    try {
        $tbl_result = $dbh->query("SELECT 1 FROM `LogUploads` LIMIT 1");
    } catch (PDOException $e) {
        return false;
    }
    return $tbl_result !== false;
}

function CreateLogUploadsTable()
{
    global $dbh;
    
    $sql = 'CREATE TABLE `LogUploads` (
      `id` INTEGER NULL AUTO_INCREMENT DEFAULT NULL,
      `user_id` INTEGER NOT NULL DEFAULT 0,
      `file_size` INTEGER NOT NULL,
      `event_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `file_name` VARCHAR(255) NOT NULL,
      `ip_addr` VARCHAR(39) NOT NULL,
      PRIMARY KEY (`id`)
    );';
    
    $res = $dbh->query($sql);
}

function AddUploadLogRecord($filepath)
{
    global $dbh;
    
    if (!is_file($filepath) || ! is_readable($filepath)) {
        return false;
    }
    
    $filename = basename($filepath);
    $filesize = filesize($filepath);
    $user_id = 0;
    if (session_status() == PHP_SESSION_ACTIVE) {
        $user_id = $_SESSION['mudletsnaps_user_id'];
    }
    
    $stmt = $dbh->prepare(
        'INSERT INTO `LogUploads` (`user_id`, `file_size`, `file_name`, `ip_addr`) 
         VALUES (:uid, :size, :fname, :ip);'
    );
    $stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':size', $filesize, PDO::PARAM_INT);
    $stmt->bindParam(':fname', $filename, PDO::PARAM_STR);
    $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    
    return $stmt->execute();
}

function LogDownloadsTableExists()
{
    global $dbh;
    
    $tbl_result = false;
    try {
        $tbl_result = $dbh->query("SELECT 1 FROM `LogDownloads` LIMIT 1");
    } catch (PDOException $e) {
        return false;
    }
    return $tbl_result !== false;
}

function CreateLogDownloadsTable()
{
    global $dbh;
    
    $sql = 'CREATE TABLE `LogDownloads` (
      `id` INTEGER NULL AUTO_INCREMENT DEFAULT NULL,
      `user_id` INTEGER NOT NULL DEFAULT 0,
      `file_size` INTEGER NOT NULL,
      `event_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `file_name` VARCHAR(255) NOT NULL,
      `ip_addr` VARCHAR(39) NOT NULL,
      PRIMARY KEY (`id`)
    );';
    
    $res = $dbh->query($sql);
}

function AddDownloadLogRecord($filepath)
{
    global $dbh;
    
    if (!is_file($filepath) || ! is_readable($filepath)) {
        return false;
    }
    
    $filename = basename($filepath);
    $filesize = filesize($filepath);
    $user_id = 0;
    if (session_status() == PHP_SESSION_ACTIVE) {
        $user_id = $_SESSION['mudletsnaps_user_id'];
    }
    
    $stmt = $dbh->prepare(
        'INSERT INTO `LogDownloads` (`user_id`, `file_size`, `file_name`, `ip_addr`) 
         VALUES (:uid, :size, :fname, :ip);'
    );
    $stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':size', $filesize, PDO::PARAM_INT);
    $stmt->bindParam(':fname', $filename, PDO::PARAM_STR);
    $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    
    return $stmt->execute();
}

