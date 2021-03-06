# Mudlet Snapshots Service
This software repository powers the [Mudlet Snapshot portal](https://make.mudlet.org/snapshots/) and functions required to enable more reliable and customized storage of Mudlet's various CI builds.  

## Usage for Uploads
File uploads can be accomplished as follows:  
`curl --upload-file ./test.gz https://make.mudlet.org/snapshots/test.gz`  
**-or-**  
`wget --method PUT --body-file="./test.gz" "https://make.mudlet.org/snapshots/test.gz" -O - -q`  

Both will return a link similar to this:  
`https://make.mudlet.org/snapshots/9162c7/test.gz`

With Authentication required:  
`curl -u user:pass --upload-file ./test.gz https://make.mudlet.org/snapshots/test.gz`  
**-or-**  
`wget --user=username --password=pass --method PUT --body-file="./test.gz" "https://make.mudlet.org/snapshots/test.gz" -O - -q`  

Additional Headers may also be sent with PUT requests:  
 `Max-Days` - Controls the expiration time of the uploaded file.  
 `Max-Downloads` - Controls expiration based on File Downloads as well as time, whichever happens first.  

To test access to Snapshots use:  
`https://make.mudlet.org/snapshots/knock/`  
    - Returns:  `Known`  
    - Returns:  `Unknown - <IP>`  

## Usage for JSON data
CI Snapshots provides an endpoint at `/json.php` for fetching snapshot data as JSON.  
To use the JSON data, send a GET request to https://make.mudlet.org/snapshots/json.php   

All data available will be returned by default. Optional arguments can be supplied to filter the returned data.  
Optional URL Paramers are:  
 - `prid`         -- a PR ID number from github.
 - `commitid`     -- a Commit ID from Git/Github.
 - `platform`     -- a string for the platform, which must be one of:  `windows`, `linux`, or `macos`  

The requested JSON list will show only entries which have matching values.  

## Installation Requirements
This software is powered by PHP and Apache with Mod_Rewrite.  Internationalization requires Intl and gettext php support.  
Most Apache configurations may disable PUT method requests by default, so we need to make some configuration changes to Apache (in Server or VirtualHost areas) in order to enable PUT method requests as well as configure our RewriteMap directive for mod_rewrite.
The required Apache Directives should be something similar to this:

    RewriteMap allowed "txt:/path/to/ip_list"
    <Limit GET POST HEAD PUT OPTIONS>
        Require all granted
    </Limit>
    <LimitExcept GET POST HEAD PUT OPTIONS>
        Require all denied
    </LimitExcept>
    Script PUT /path/to/put.php


## Installing
Download and unpack or Clone the software into a PHP-Enabled server directory.  Copy and rename the file `config.exmaple.php` to `config.php` and edit the configuration.  
Likewise, copy the `ip_list.example` to `ip_list` and edit the tab-separated list data to suit your needs.  
Ensure that you have created an `.htaccess` file and are using the directives found within `.htaccess.example` to enable the rewrite rules and other security controls.  
The software will automatically create the required tables in the database.  Simply navigate to the index with a web browser before using `cron.php` or attempting to `PUT` files.  