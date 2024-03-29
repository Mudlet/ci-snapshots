<?php

// Fully Qualified URL to the index location for your server.
define('SITE_URL', 'https://make.mudlet.org/snapshots/');

// Directory under the SITE_URL where uploaded snapshot files are stored.
// This directory is relative to both the SITE_URL and the execution path.
define('UPLOAD_DIR', 'files/');

// This directory is used to temporarily store incoming files, pre-publishing processing.
define('TEMP_DIR', 'tmp/');

// Do PUT method uploads require auth as well?
// User(s) must be configured in the `Users` database table.
// Add a User from command line with:  php ./cron.php adduser
define('PUT_REQUIRES_AUTH', false);

// File Extensions allowed for use in uploaded filenames.
define('ALLOWED_FILE_EXT', 'exe,msi,bin,dmg,zip,tar,gz,tz,xz,AppImage');

// Length of time in days that snapshots are kept on the server.
// This value is used at upload time to pre-calculate expiration of the files.
// Changing this value will not change existing file expiration times!
define('DEFAULT_FILE_LIFETIME_DAYS', 14);

// The maximum storage space set aside for this software.
// Any storage over this amount is pruned by oldest file first.
define('MAX_CAPACITY_BYTES', 37580960000);

// Toggle PUT response when MAX_CAPACITY_BYTES is reached.
//  True - delete the oldest stored snapshot(s) to make space for new file.
//  False - respond with an error code.
define('MAX_CAPACITY_DELETE_OLDEST', true);

// Number of hours old a file must be in order to be considered 'Stranded' in TEMP_DIR or UPLOAD_DIR.
// Stranded files are those which do not have Snapshot table entries.
define('STRANDED_FILE_WINDOW', 2);


// URL to the base artifact listing URL.  All URLs are built from this one.
define('GHA_LIST_URL', 'https://api.github.com/repos/Mudlet/Mudlet/actions/artifacts');

// URL for sending notification about processed artifacts.
// Uses POST method and sends application/json list of PR IDs.
define('GHA_QUEUE_NOTICE_URL', '');

// Regular Expression used to validate and extract IDs from artifact URLs.
define('GHA_URL_REGEX', '#https?://api\.github\.com/repos/Mudlet/Mudlet/actions/artifacts/(\d+)(?:/|/zip/?)?#iu');

// Authentication token for Github API calls - OAuth or Personal Access Token.
// An OAuth token needs the "actions" scope
// A personal access token needs the "public_repos" scope
define('GHA_AUTH_TOKEN', 'token');

// Timeout in seconds for github artifact request execution.
// this value controls the max time a request/download can take.
define('GHA_CURL_TIMEOUT', 180);

// Enforce HTTP Auth for GHA requests, similarly to PUT_REQUIRES_AUTH
define('GHA_REQUIRES_AUTH', false);

// prefix for filenames used to store github artifact queue data.
// this must not be left empty!
define('GHA_QFILE_PREFIX', 'ghaq_');

// file extension to append to queue data filename.
define('GHA_QFILE_EXT', '.json');

// number of seconds a github artifact queue item may remain in the queue.
// a few hours would be reasonable.
define('GHA_QUEUE_TIMEOUT', 18000);


// Database connection details
define('DB_HOST', '');

define('DB_NAME', '');

define('DB_USER', '');

define('DB_PASS', '');

define('DB_CHARSET', 'utf8mb4');


// i18n settings
$i18n_lang_available = array('en_US');
$i18n_lang_default = 'en_US';


/**
    Stop Editing here
**/
// Script execution path
$ScriptPath = dirname(__FILE__);

// i18n constants
$i18n_domain_path = $ScriptPath . '/i18n';
$i18n_domain_name = 'ci_snapshots';
// The result path is:  ./i18n/{language_code}/LC_MESSAGES/{$i18n_domain_name}.mo
