<?php
require_once 'weatherFuncs.php';
require_once $argv[1];

$date = strtotime( $argv[2] );
echo rainSince( $rainCounters, $date )."in.\n";
?>
