<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}

session_start();
$_SESSION['mudletsnaps_user_id'] = 0;

require_once("config.php");

// Detect user localization, apply if available.
$i18n_locale = $i18n_lang_default;
if (function_exists('gettext') && function_exists('locale_accept_from_http')) {
    $_lang_header = (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : $i18n_lang_default;
    $i18n_locale_prefer = locale_accept_from_http($_lang_header);
    $i18n_locale = locale_lookup($i18n_lang_available, $i18n_locale_prefer, false, $i18n_lang_default);
    
    putenv('LC_ALL=' . $i18n_locale);
    $i18n_test = setlocale(LC_ALL, $i18n_locale);
    if ($i18n_test === false) {
        $i18n_locale = $i18n_lang_default;
        putenv('LC_ALL=' . $i18n_locale);
        setlocale(LC_ALL, $i18n_locale);
    }
    
    bindtextdomain($i18n_domain_name, $i18n_domain_path);
    textdomain($i18n_domain_name);
}

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
} catch (PDOException $e) {
    print('<h1>' . _('Database Connection Error!') . '</h1><br/>');
    exit();
}

// Table Check
if (! SnapshotsTableExists()) {
    CreateSnapshotsTable();
    header("Location: " . SITE_URL);
    exit();
}

if (! UsersTableExists()) {
    CreateUsersTable();
    header("Location: " . SITE_URL);
    exit();
}

if (! LogUploadsTableExists()) {
    CreateLogUploadsTable();
    header("Location: " . SITE_URL);
    exit();
}

if (! LogDownloadsTableExists()) {
    CreateLogDownloadsTable();
    header("Location: " . SITE_URL);
    exit();
}
