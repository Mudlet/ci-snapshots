# Mudlet Snapshots Service
This software repository powers the [Mudlet Snapshot portal](https://make.mudlet.org/snapshots/) and functions required to enable more reliable and customized storage of Mudlet's various CI builds.  

## Usage
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

## Installation Requirements
This software is powered by PHP and Apache with Mod_Rewrite.  Most Apache configurations may disable PUT method requests by default, so we need to make some configuration changes to Apache (in Server or VirtualHost areas) in order to enable PUT method requests as well as configure our RewriteMap directive for mod_rewrite.
The required Apache Directives should be something similar to this:

    RewriteMap allowed "txt:/path/to/ip_list"
    <Limit GET POST HEAD PUT OPTIONS>
        Require all granted
    </Limit>
    <LimitExcept GET POST HEAD PUT OPTIONS>
        Require all denied
    </LimitExcept>
    Script PUT /path/to/put.php

