<?php

if (!defined("CI_SNAPSHOTS")) {
    exit();
}

function SetStatusHeader($code=200, $msg='OK') {
    $sapi = substr(php_sapi_name(), 0, 3);
    if ($sapi == 'cgi' || $sapi == 'fpm') {
        header( 'Status: ' . $code . ' ' . $msg, true, $code );
    } else {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
        header( $protocol . ' ' . $code . ' ' . $msg, true, $code );
    }
    
    // HTTP/2.0 removes Reason Phrases from CGI statuses, but it may be informative.
    header( 'X-Status-Text: ' . $msg );
}

function ExitClientError($msg, $code=400)
{
    if (empty($msg)) {
        $msg = _('Failed - Your Request Contains Errors') . "\n";
    }
    
    SetStatusHeader($code, $msg);
    echo($msg);
    exit();
}

function ExitFailedRequest($msg)
{
    if (empty($msg)) {
        $msg = _('Failed - Internal Server Error') . "\n";
    }
    
    SetStatusHeader(500, $msg);
    echo($msg);
    exit();
}

function ExitFileNotFound()
{
    SetStatusHeader(404, 'Not Found');
    
    $page = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>' . _('404 Not Found') . '</title>
</head><body>
<h1>' . _('Not Found') . '</h1>
<p>' . _('The requested URL was not found on this server.') . '</p> <!-- URL: ' . $_SERVER['REQUEST_URI'] . ' -->
<hr>
<address>' . apache_get_version() . _(' Server at ') . $_SERVER['SERVER_NAME'] . _(' Port ') . strval($_SERVER['SERVER_PORT']) . '</address>
</body></html>';
    echo($page);
    exit();
}

function ExitUnauthorized()
{
    header('WWW-Authenticate: Basic realm="Snapshots"');
    SetStatusHeader(401, 'Unauthorized');
    echo("Unauthorized\n");
    exit();
}

function ExitForbidden()
{
    SetStatusHeader(403, 'Forbidden');
    echo("Forbidden\n");
    exit();
}

function ExitFullStorage()
{
    SetStatusHeader(507, 'Insufficient Storage');
    echo("Insufficient Storage - Try Again Later\n");
    exit();
}


if(!function_exists('apache_get_version'))
{
    function apache_get_version()
    {
        if(!isset($_SERVER['SERVER_SOFTWARE']) || strlen($_SERVER['SERVER_SOFTWARE']) == 0)
        {
            return 'Apache/HTTPD';
        }
        return $_SERVER["SERVER_SOFTWARE"];
    }
}


/* 
* Wrapper function for curl GET requests.
* returns false on error.
* on successs returns an array with 4 members.
* array indexes:
* -0- response body data.
* -1- response headers array.
* -2- response HTTP status code.
* -3- time taken for request.
*
*/
function do_curl_get($url, $send_headers=array(), $err400=false) {
    
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ( $url === false || preg_match('/^https?:\/\/.+/iu',$url) === false ) {
        trigger_error('Given $url is valid or not using http/https scheme.');
        return false;
    }
    
    $timeout_limit = GHA_CURL_TIMEOUT;
    set_time_limit($timeout_limit + 30);
    
    $rtime_start = time();
    $ch = curl_init( $url );
    $recv_headers = [];
    curl_setopt($ch, CURLOPT_FAILONERROR, $err400);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_limit);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; make.mudlet.org/0.1)");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($send_headers) ) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $send_headers);
    }
    
    // catch every valid header that CURL gets, we'll need that info for rate-limits, etc.
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$recv_headers)
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
            return $len;
        
        $recv_headers[strtolower(trim($header[0]))][] = trim($header[1]);
        
        return $len;
    });
    
    $result = curl_exec($ch);
    if ( $result === false ) {
        //trigger_error(curl_error($ch));
        return false;
    }
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    $rtime = time() - $rtime_start;
    return array($result, $recv_headers, $httpcode, $rtime);
}


/* 
* Wrapper function for curl POST requests.
* $data may be an array if Content-Type can be 'multipart/form-data'.
* returns false on error.
* on successs returns an array with 4 members.
* array indexes:
* -0- response body data.
* -1- response headers array.
* -2- response HTTP status code.
* -3- time taken for request.
*
*/
function do_curl_post($url, $data='', $send_headers=array(), $err400=true) {
    
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ( $url === false || preg_match('/^https?:\/\/.+/iu',$url) === false ) {
        trigger_error('Given $url is valid or not using http/https scheme.');
        return false;
    }
    
    $timeout_limit = GHA_CURL_TIMEOUT;
    set_time_limit($timeout_limit + 30);
    
    $rtime_start = time();
    $ch = curl_init( $url );
    $recv_headers = [];
    curl_setopt($ch, CURLOPT_FAILONERROR, $err400);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_limit);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; make.mudlet.org/0.1)");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($send_headers) ) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $send_headers);
    }
    
    // catch every valid header that CURL gets, we'll need that info for rate-limits, etc.
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$recv_headers)
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
            return $len;
        
        $recv_headers[strtolower(trim($header[0]))][] = trim($header[1]);
        
        return $len;
    });
    
    $result = curl_exec($ch);
    if ( $result === false ) {
        //trigger_error(curl_error($ch));
        return false;
    }
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    $rtime = time() - $rtime_start;
    return array($result, $recv_headers, $httpcode, $rtime);
}


/* 
* Wrapper function for curl.
* returns false on error.
* on successs returns an array with 4 members.
* array indexes:
* -0- response body data.
* -1- response headers array.
* -2- response HTTP status code.
* -3- time taken for request.
*
*/
function do_curl_download($url, $filepath, $send_headers=array(), $err400=true) {
    
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ( $url === false || preg_match('/^https?:\/\/.+/iu',$url) === false ) {
        trigger_error('Given $url is valid or not using http/https scheme.');
        return false;
    }
    
    if ( ! is_writable($filepath) ) {
        trigger_error('Given $filepath is not writable!');
        return false;
    }
    
    
    $timeout_limit = GHA_CURL_TIMEOUT;
    set_time_limit($timeout_limit + 30);
    
    $rtime_start = time();
    $fh = fopen($filepath, 'wb');
    $ch = curl_init( $url );
    $recv_headers = [];
    curl_setopt($ch, CURLOPT_FAILONERROR, $err400);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_limit);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; make.mudlet.org/0.1)");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FILE, $fh);
    if (!empty($send_headers) ) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $send_headers);
    }
    
    // catch every valid header that CURL gets, we'll need that info for rate-limits, etc.
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$recv_headers)
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
            return $len;
        
        $recv_headers[strtolower(trim($header[0]))][] = trim($header[1]);
        
        return $len;
    });
    
    $result = curl_exec($ch);
    if ( $result === false ) {
        //trigger_error(curl_error($ch));
        return false;
    }
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    fclose($fh);
    
    $rtime = time() - $rtime_start;
    return array($result, $recv_headers, $httpcode, $rtime);
}



