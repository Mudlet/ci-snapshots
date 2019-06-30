<?php

if( isset($_GET['known']) ) {
    echo("Known\n");
}
else {
    echo('Unknown - '. $_SERVER['REMOTE_ADDR'] ."\n");
}


