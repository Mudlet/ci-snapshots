<?php
/***
*  This file has one task, scan for, process, and remove 'enqueued' github artifact transfer requests.
*  Processing a queue entry means generating a request to our own endpoint.  because I'm lazy...
*  Artifacts are available only after a workflow/job is complete, but can be queued long before that.
*
*  As it takes some time to process multiple files, care should be taken when deciding on a schedule for this job.

    We need to accept a certain amount of 404s and errors, putting the queued file back until it is done or expired.

***/

require_once('config.php');

header('Content-Type: text/plain');

if (php_sapi_name() != 'cli') {
    header("Location: " . SITE_URL);
    exit();
}

define("CI_SNAPSHOTS", true);
require_once('lib/functions.common.php');
require_once('lib/functions.http.php');


$JobLockMaxTTL = 1200; // seconds from lock creation to consider the job expired.
$PRList_file = $ScriptPath . DIRECTORY_SEPARATOR . TEMP_DIR . '.pr-queue.json';


class GithubArtifactList {
    private $data = array();
    public $artifacts_total = 0;
    public $artifacts = array();
    
    public function __construct() {
        $req_url = GHA_LIST_URL . '?per_page=100';
        
        echo("Fetching artifact list - 100 per-page\n");
        $req = do_curl_get($req_url, array('Authorization: token ' . GHA_AUTH_TOKEN));
        if ($req === false) {
            return false;
        }
        if ($req[2] !== 200) {
            return false;
        }
        
        $jdata = json_decode($req[0], true);
        if ($jdata === null) {
            return false;
        }
        $this->data = $jdata;
        
        if (isset($jdata['artifacts'])) {
            $this->artifacts = $jdata['artifacts'];
        } else {
            return false;
        }
        
        if (isset($jdata['total_count'])) {
            $this->artifacts_total = $jdata['total_count'];
        } else {
            return false;
        }
        
    }
    
    function countArtifactsByName( $artifact_name ) {
        $found=0;
        foreach( $this->artifacts as $idx => $a ) {
            if ( $artifact_name == $a['name'] ) {
                $found = $found + 1;
            }
        }
        return $found;
    }
    
    function getArtifactIdByName( $artifact_name ) {
        $id=false;
        foreach( $this->artifacts as $idx => $a ) {
            if ( $artifact_name == $a['name'] ) {
                $id = $a['id'];
            }
        }
        return $id;
    }
}


function processSnapshotFetch( $url, $headers ) {
    echo("Fetching:  $url \n");
    $r = do_curl_get($url, $headers);
    
    if ($r === false) {
        echo("Error while fetching\n");
        return false;
    }
    
    if ($r[2] !== 200) {
        echo("Response not 200, is: ${r[2]}\n");
        echo("Error Text:  $r[0] \n");
        return false;
    }
    
    if ($r[2] === 200) {
        echo("Response:  ${r[0]} \n");
    }
    
    return true;
}

function processQueueFile($filepath) {
    global $ghalObj;
    
    $raw = file_get_contents($filepath);
    $data = json_decode($raw, true);
    
    // unlink in cases of invalid data.
    if ($data === false) {
        echo('Error decoding JSON! - ' . json_last_error() . "\n" );
        return false;
    }
    if (!isset($data['name']) || !isset($data['unzip'])) {
        echo("Data missing 'name' and 'unzip' required members!\n");
        return false;
    }
    
    // we need to clean up carefuly here...
    // unlink if we have more than one file
    // if no files, we may need to either search deeper,
    // or we need to wait for a later check - artifact may not exist yet!
    $c = $ghalObj->countArtifactsByName( $data['name'] );
    if ($c > 1) {
        $n = $data['name'];
        echo("More than one artifact with the name: $n\n");
        return false;
    }
    
    if ($c == 0) {
        $n = $data['name'];
        echo("No artifact with the name: $n \n");
        // in this case we check the queue entry time for expiry, otherwise leave the item.
        $ex_time = $data['qtime'] + GHA_QUEUE_TIMEOUT;
        if (time() > $ex_time) {
            echo("Queue item has expired.\n");
            return false;
        }
        return true;
    }
    
    
    $queue_url = SITE_URL . 'github_artifact.php';
    $headers = array();
    $unzip = ($data['unzip']) ? '1' : '0';
    
    if (isset($data['id'])) {
        $id = $data['id'];
    } else {
        $id = $ghalObj->getArtifactIdByName( $data['name'] );
    }
    
    if (isset($data['maxdays'])) {
        $headers[] = 'Max-Days: ' . strval( $data['maxdays'] );
    }
    if (isset($data['maxdls'])) {
        $headers[] = 'Max-Downloads: ' . strval( $data['maxdls'] );
    }
    
    $url = $queue_url . '?id=' . strval($id) . '&unzip=' . $unzip;
    
    if ( processSnapshotFetch( $url, $headers ) ) {
        unlink($filepath);
    }
    
    return true;
}

function loadPRIdsQueue() {
    global $PRList_file;
    
    $list = array();
    if ( file_exists( $PRList_file ) ) {
        $d = file_get_contents($PRList_file);
        $list = json_decode($d);
    }
    
    return $list;
}

function savePRIdsQueue($PRList) {
    global $PRList_file;
    
    $data = json_encode($PRList);
    
    file_put_contents($PRList_file, $data);
}

function clearPRIdsQueue() {
    global $PRList_file;
    if ( file_exists($PRList_file) ) {
        unlink($PRList_file);
    }
}


$job_lock = $ScriptPath . DIRECTORY_SEPARATOR . '.gha_cron.lock';
if ( file_exists( $job_lock ) ) {
    $lock_time = filemtime($job_lock);
    $lock_exp_time = $lock_time + $JobLockMaxTTL;
    if (time() < $lock_exp_time) {
        echo("Job already running, quitting. \n");
        exit();   
    } else {
        echo("Job already running, but lock has expired! \n");
        unlink($job_lock);
        exit();
    }
} elseif( touch($job_lock) === false ) {
    echo("Could not set job lock, quitting. \n");
    exit();
}

$timer_start = microtime(true);

$ghalObj = new GithubArtifactList();

$TempDir = $ScriptPath . DIRECTORY_SEPARATOR . TEMP_DIR ;
$FileRegex = '/^' . preg_quote(GHA_QFILE_PREFIX, '/') . '(.+)' . preg_quote(GHA_QFILE_EXT, '/') . '$/iu';
$PRIdList = loadPRIdsQueue();
$files = scandir($TempDir);
foreach( $files as $idx => $file ) {
    if ($file == '.' || $file == '..') {
        continue;
    }
    $m = null;
    if ( false == preg_match($FileRegex, $file, $m) ) {
        continue;
    }

    $ts = microtime(true);
    echo("Processing queue file:  $file \n");

    if ( !empty($m) ) {
        $p = null;
        $s = preg_match('/(?:-PR([0-9]+))?-([a-f0-9]{5,9})[\.-]{1}/i', $m[1], $p);
        $c = $ghalObj->countArtifactsByName( $m[1] );
        
        if ( $s === 1 && count($p) == 3 ) {
            $prid = intval($p[1]);
            if ( !in_array($prid, $PRIdList) && $prid !== 0 && $c > 0) {
                $PRIdList[] = $prid;
            }
        }
    }

    $filepath = $TempDir . $file;
    $status = processQueueFile( $filepath );
    
    if ($status === false) {
        unlink( $filepath );
    }
    $n = microtime(true) - $ts;
    printf("Processing finished in %4.2f seconds.\n\n", $n);
}

if ( count($PRIdList) > 0 && !empty(GHA_QUEUE_NOTICE_URL) ) {
    $post_data = json_encode($PRIdList);
    
    echo("Sending PR link update notice for: \n - " . $post_data . "\n");
    $res = do_curl_post(GHA_QUEUE_NOTICE_URL, $post_data, array('Content-Type: application/json'));
    if ( $res === false ) {
        echo(" - Failed \n");
        savePRIdsQueue($PRIdList);
    } elseif ( $res[2] == 204 ) {
        echo(" - OK \n");
        clearPRIdsQueue();
    }
}

if ( file_exists($job_lock) ) {
    echo("Removing job lock. \n");
    unlink($job_lock);
}

$t = microtime(true) - $timer_start;
printf("Finished in %4.2f seconds\n\n\n", $t);
