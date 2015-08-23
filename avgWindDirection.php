<?php
require 'weatherFuncs.php';

$vals = array();
$mags = array();
for ( $i=1; $i<count($argv); $i+=2 )
{
  $vals[] = $argv[$i];
  $mags[] = $argv[$i+1];
}

$avg = avgWindDirection( $vals, $mags );
echo "Average: $avg\n";
?>
