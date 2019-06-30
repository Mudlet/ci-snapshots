<?php

if( !defined("CI_SNAPSHOTS") ){
    exit();
}

require_once("config.php");
require_once("lib/functions.php");

// MySQL Connection Setup
try {
    $dsn = 'mysql: host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET,
    ); 
    $dbh = new PDO($dsn, DB_USER, DB_PASS, $options);
    $dsn = null;
    
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    print("<h1>Database Connection Error!</h1><br/>");
    exit();
}

// Table Check
if( ! SnapshotsTableExists() ) {
    CreateSnapshotsTable();
    header("Location: " . SITE_URL);
    exit();
}

if( ! UsersTableExists() ) {
    CreateUsersTable();
    header("Location: " . SITE_URL);
    exit();
}
