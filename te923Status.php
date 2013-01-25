<?php
/**************************************************
**************** configure here *******************
**************************************************/
// schedule in root user's crontab like this:
//     0 0 * * * sleep 30;php /home/foo/cronjobs/te923Status.php foo@bar.com > /home/foo/cronjobs/log/weatherStatus.out 2>&1
// Runs once a day at midnight
// 30 second sleep is to offset request to the TE923 device from the weather data download
//
// Path to te923con.  You can leave it blank if te923con is
//   * in the the same directory as this script
//   * in /usr/bin
//   * in /usr/local/bin
$pathToTE923Tool = '/opt/te923/te923con';

// Set to true if you want lots of debug output:
$verbose = TRUE;

// Set to true to run the te923con application with sudo.  If set to true, you
// should also make sure to configure te923con for no password sudo.
define( "USE_SUDO", TRUE );
/*************** end configuration *********************/
require_once('te923.php');

// Command line parameters; needs usage message
$notifyEmail = $argv[1];

// Get the weather data from the weather station
$rawData = getTE923StatusData( $pathToTE923Tool, $verbose );
if ( $rawData == '' )
{
  $message = "Failed to get raw weather data\n";
  echo "$message\n";
}
else
{
  $statusData = parseTE923StatusData( $rawData, $verbose );

  $message = '';
  if ( $statusData['BATR'] == 0 )
    $message .= "Battery in rain sensor is low\n";
  if ( $statusData['BATU'] == 0 )
    $message .= "Battery in UV sensor is low\n";
  if ( $statusData['BATW'] == 0 )
    $message .= "Battery in wind sensor is low\n";
  if ( $statusData['BAT5'] == 0 )
    $message .= "Battery in temp sensor 5 is low\n";
  if ( $statusData['BAT4'] == 0 )
    $message .= "Battery in temp sensor 4 is low\n";
  if ( $statusData['BAT3'] == 0 )
    $message .= "Battery in temp sensor 3 is low\n";
  if ( $statusData['BAT2'] == 0 )
    $message .= "Battery in temp sensor 2 is low\n";
  if ( $statusData['BAT1'] == 0 )
    $message .= "Battery in temp sensor 1 is low\n";
}

if ( strlen( $message ) > 0 )
{
  echo "$message\n";
  echo "Sending notification email...\n";
  mail( $notifyEmail, 'TE923 station battery status', $message );
}
?>
