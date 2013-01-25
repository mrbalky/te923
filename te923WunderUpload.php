<?php
/**************************************************
**************** configure here *******************
***************************************************/
// Schedule in crontab like so:
//    * * * * * php te923WunderUpload.php <station ID> <wunderground password> [<cache file name>]
// Runs every minute.
//
// Path to te923con.  You can leave it blank if te923con is
//   * in the the same directory as this script
//   * in /usr/bin
//   * in /usr/local/bin
$pathToTE923Tool = '/opt/te923/te923con';

// Set to true if you want lots of debug output:
define( "VERBOSE", TRUE );

// Set to true to run the te923con application with sudo.  If set to true, you
// should also make sure to configure te923con for no password sudo.  If set to
// false, you should schedule the script in the root user's crontab.
define( "USE_SUDO", TRUE );

// How many days of rain data would you like kept?  Wunderground only cares
// about last hour and since midnight, but let's keep a month or so...
define( "DAYS_OF_RAIN_TO_KEEP", 4000 );

// Should we keep the raw weather data in a particular file for further
// processing (by rrd-weather, perhaps)?
define( "KEEP_RAW_WEATHER_DATA_FILE", '/opt/te923/cache/te923raw.txt' );
/*************** end configuration *********************/
require_once( 'te923.php' );
require_once( 'weatherFuncs.php' );

// Calculate rain keep limit timestamp
define( "HOUR_OF_SECONDS", 60 * 60 );
define( "DAY_OF_SECONDS", 24 * HOUR_OF_SECONDS );
define( "RAIN_KEEP_LIMIT", time() - ( DAYS_OF_RAIN_TO_KEEP * DAY_OF_SECONDS ) );
define( "WIND_KEEP_LIMIT", time() - DAY_OF_SECONDS );

function addIfValid( $qsArg, $value )
{
  if ( is_numeric($value) )
    return( "&$qsArg=$value" );
  return( '' );
}

// Command line parameters (needs usage message)
$stationID = $argv[1];
$password = $argv[2];
$cacheFile = $argv[3];
if ( array_key_exists( 4, $argv ) )
  $keepRaw = $argv[4];
else
  $keepRaw = KEEP_RAW_WEATHER_DATA_FILE;

// Get the weather data from the weather station
$rawData = getTE923WeatherData( $pathToTE923Tool, VERBOSE, $keepRaw );
if ( $rawData == '' )
{
  echo "Failed to get raw weather data\n";
}
else
{
  // Rain data is cached on disk.  If it's not there, we can't calculate accumulations.
  $rainCounters = array();
  $rainSinceMidnight = 0;
  $rainInLastHour = 0;
  $windHistory = array();
  if ( $cacheFile == '' )  // no cache; just get latest conditions
    $weatherData = parseTE923WeatherData( $rawData, VERBOSE );
  else
  {
    // load previous data from the cache (wipes out $weatherData)
    if ( file_exists( $cacheFile ) )
      require_once( $cacheFile );

    // Get the latest conditions, then update the rain counters and wind history
    $weatherData = parseTE923WeatherData( $rawData, VERBOSE );
    updateRainCounters( $weatherData, $rainCounters, RAIN_KEEP_LIMIT );
    updateWindHistory( $weatherData, $windHistory, WIND_KEEP_LIMIT );

    // Now we can calculate "rain today" and "rain in last hour" for wunderground
    $rainSinceMidnight = rainSinceMidnight( $rainCounters );
    $rainInLastHour = rainInLastHour( $rainCounters );

    // Calculate 5 minute moving average wind direction and speed
    $avgWind = avgWind( $windHistory, 5 * 60 );

    $newCache = "<?\n".
                '$lastStatusTime='.$weatherData['UNIXTIME'].";\n".
                '$lastStatus=\''.date('r',$weatherData['UNIXTIME'])."';\n".
                '$weatherData='.var_export( $weatherData, TRUE ).";\n".
                '$rainCounters='.var_export( $rainCounters, TRUE ).";\n".
                '$rainInLastHour='.$rainInLastHour.";\n".
                '$rainSinceMidnight='.$rainSinceMidnight.";\n".
                '$windHistory='.var_export( $windHistory, TRUE ).";\n".
                "?>\n";

    file_put_contents( $cacheFile, $newCache );
  }

  $outdoorTemp = getOutdoorTemp( $weatherData );
  $outdoorHumidity = getOutdoorHumidity( $weatherData );

  if ( ! is_numeric( $outdoorTemp ) )
  {
    echo "Outdoor temp is not a number: $outdoorTemp\n";
    exit;
  }
  
  // Build the Weather Underground upload URL
  $url = 'http://weatherstation.wunderground.com/weatherstation/updateweatherstation.php?action=updateraw'.
         "&ID=$stationID".
         "&PASSWORD=$password".
         '&dateutc='.$weatherData['TS'].
         addIfValid( 'winddir', $avgWind['WD'] ).        // $weatherData['WD'].
         addIfValid( 'windspeedmph', $avgWind['WS'] ) .   // $weatherData['WS'].
         addIfValid( 'windgustmph', $weatherData['WG'] ) .
         addIfValid( 'humidity', $outdoorHumidity ) .
         addIfValid( 'tempf', $outdoorTemp ) .
         addIfValid( 'dewptf', $weatherData['DP'] ) .
         addIfValid( 'rainin', $rainInLastHour ) .
         addIfValid( 'dailyrainin', $rainSinceMidnight ) .
         addIfValid( 'baromin', $weatherData['PRESS'] ) .
         addIfValid( 'UV', $weatherData['UV'] ) .
         '&softwaretype=te923tool%20%2B%20temaki';
  if ( VERBOSE )
    echo "Upload URL: $url\n";

  // Finally, upload to wunderground
  echo( "\nUpload result: ".file_get_contents( $url ) );
}
/*************************** test code for the rain counters ****************
$weatherData = array( 'UNIXTIME' => time(), 'RC' => 80 );
$rainCounters = array( array( 'UNIXTIME' => 1271861071, 'RC' => 78 ),
                       array( 'UNIXTIME' => 1271861012, 'RC' => 76 ),
                       array( 'UNIXTIME' => 1271860966, 'RC' => 70 ) );
updateRainCounters( $weatherData, $rainCounters, RAIN_KEEP_LIMIT );
print_r( $rainCounters );
echo( rainSince( $rainCounters, 1271861071 )."\n" );
echo( rainSince( $rainCounters, 1271861012 )."\n" );
echo( rainSince( $rainCounters, 1271860966 )."\n" );
exit;
*************************** test code for the rain counters ****************/
?>
