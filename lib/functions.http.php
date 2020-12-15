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

