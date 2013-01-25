<?php
require_once( 'dewpoint.php' );

define( "RC_RATIO_IN", 0.02789 );
define( "RC_RATIO_MM", 0.708 );

/*****************************************************************/
function findTE923Tool( $pathToTE923Tool )
{
  // get the directory this script is in
  $cwd = dirname( __FILE__ );

  // Make sure any configured path ends with /
  if ( $pathToTE923Tool != '' )
    $pathToTE923Tool .= '/';

  // Try to find the executable in several places.
  if ( !file_exists( "${pathToTE923Tool}te923con" ) || $pathToTE923Tool == '' )
    $pathToTE923Tool = "$cwd/";                      // try current directory
  if ( !file_exists( "${pathToTE923Tool}te923con" ) )
    $pathToTE923Tool = '/usr/bin/';                  // try /usr/bin
  if ( !file_exists( "${pathToTE923Tool}te923con" ) )
    $pathToTE923Tool = '/usr/local/bin/';            // try /usr/local/bin
  if ( !file_exists( "${pathToTE923Tool}te923con" ) )
    return( '' );  // bummer; nowhere to be found
  return( $pathToTE923Tool );
}

/*****************************************************************/
function getTE923WeatherData( $pathToTE923Tool, $verbose = FALSE, $keepRawResults = '' )
{
  $pathToTE923Tool = findTE923Tool( $pathToTE923Tool );
  $rawData = execTE923Cmd( "${pathToTE923Tool}te923con -i -", $verbose, $keepRawResults );
  if ( $verbose )
    echo "Raw data: $rawData\n";
  return( $rawData );
}

/*****************************************************************/
function getTE923StatusData( $pathToTE923Tool, $verbose = FALSE, $keepRawResults = '' )
{
  $pathToTE923Tool = findTE923Tool( $pathToTE923Tool );
  $rawData = execTE923Cmd( "${pathToTE923Tool}te923con -s", $verbose, $keepRawResults );
  if ( $verbose )
    echo "Raw data: $rawData\n";
  return( $rawData );
}

/*****************************************************************/
function execTE923Cmd( $cmd, $verbose = FALSE, $keepRawResults = '' )
{
  // It may be that we are told to keep the raw output
  $rawResultsFile = $keepRawResults;
  if ( $keepRawResults == '' )
    $rawResultsFile = tempnam( '/tmp', '' );
  else
    echo "keeping $keepRawResults\n";

  if ( USE_SUDO )
    $cmd = "sudo $cmd > $rawResultsFile";
  else
    $cmd = "$cmd > $rawResultsFile";
  if ( $verbose )
    echo "Command: $cmd\n";
  exec( $cmd );
  $data = file_get_contents( $rawResultsFile );

  if ( $keepRawResults == '' )
    unlink( $rawResultsFile );

  return( $data );
}

/*****************************************************************/
function cToF( $c )
{
  if ( ! is_numeric($c) )
    return( $c );
  return( $c * 9 / 5 ) + 32;
}

/*****************************************************************/
function fToC( $f )
{
  if ( $f == '' )
    return( '' );
  return( ( $f - 32 ) * 5 / 9 );
}

/*****************************************************************/
function rainCountToMM( $rc )
{
  return( $rc * RC_RATIO_MM );
}

/*****************************************************************/
function parseTE923WeatherData( $rawData, $verbose = FALSE, $imperialUnits = TRUE )
{
  $data = array();
  if ( $rawData != '' )
  {
    $rawData = explode(':',trim($rawData));

    $data['TS']    = gmdate('Y-m-d H:i:s',$rawData[0]); //   timestamp; formatted for DB or wunderground
    $data['UNIXTIME'] = $rawData[0];          //   timestamp; unix seconds since epoch
    $data['T0']    = $rawData[1];          //   temperature from indoor sensor in °C
    $data['H0']    = $rawData[2];          //   humidity from indoor sensor in % rel
    $data['T1']    = $rawData[3];          //
    $data['H1']    = $rawData[4];          //     more
    $data['T2']    = $rawData[5];          //         temp
    $data['H2']    = $rawData[6];          //             and
    $data['T3']    = $rawData[7];          //                humidity
    $data['H3']    = $rawData[8];          //                        sensors
    $data['T4']    = $rawData[9];          //                               if
    $data['H4']    = $rawData[10];         //                                 present
    $data['T5']    = $rawData[11];         //
    $data['H5']    = $rawData[12];         //
    $data['PRESS'] = $rawData[13];         //   air pressure in mBar
    $data['UV']    = $rawData[14];         //   UV index from UV sensor
    $data['FC']    = $rawData[15];         //   station forecast; see below for more details
    $data['STORM'] = $rawData[16];         //   stormwarning; 0 - no warning; 1 - fix your dog
    $data['WD']    = $rawData[17]*22.5;    //   wind direction in n x 22.5°; 0 -> north
    $data['WS']    = $rawData[18];         //   wind speed in m/s
    $data['WG']    = $rawData[19];         //   wind gust speed in m/s
    $data['WC']    = $rawData[20];         //   windchill temperature in °C
    $data['RC']    = rainCountToMM( $rawData[21] );  //   rain counter (maybe since station starts measurement) as value

    // Calculate dew point from outdoor sensor
    $temp = getOutdoorTemp( $data );
    $humidity = getOutdoorHumidity( $data );
    $data['DP'] = dewPoint( $humidity, $temp );
    if ( is_numeric($data['DP']) )
      $data['DP'] = round( $data['DP'], 2 );
    if ( $verbose )
      echo "dew point: from ${humidity}%, ${temp}C = ${data['DP']}C\n";

    // convert from metric to imperial units if requested
    if ( $imperialUnits )
    {
      $data['T0']    = cToF( $data['T0'] );
      $data['T1']    = cToF( $data['T1'] );
      $data['T2']    = cToF( $data['T2'] );
      $data['T3']    = cToF( $data['T3'] );
      $data['T4']    = cToF( $data['T4'] );
      $data['T5']    = cToF( $data['T5'] );
      $data['PRESS'] = $data['PRESS'] * 0.02954; // mBar to inHg
      $data['WS']    = $data['WS'] * 2.24;       // m/s to mph
      $data['WG']    = $data['WG'] * 2.24;       // m/s to mph
      $data['WC']    = cToF( $data['WC'] );
      $data['RC']    = $data['RC'] * 0.03937;    // mm to in
      $data['DP']    = cToF( $data['DP'] );
    }

    // Round the accumulated rain counter (had issues with PHP not considering apparently
    // equal values as equal.  This fixed it.
    $data['RC'] = round( $data['RC'], 3 );

    // optional output
    if ( $verbose )
    {
      print_r( "Parsed data:\n" );
      print_r( $data );
      print_r( "\n" );
    }
  }

  return( $data );
}

/*****************************************************************/
function parseTE923StatusData( $rawData, $verbose = FALSE )
{
  $data = array();
  if ( $rawData != '' )
  {
    // For status od the station is:
    //
    // SYSSW:BARSW:EXTSW:RCCSW:WINSW:BATR:BATU:BATW:BAT5:BAT4:BAT5:BAT2:BAT1
    //
    //   SYSSW  - software version of system controller
    //   BARSW  - software version of barometer
    //   EXTSW  - software version of UV and channel controller
    //   RCCSW  - software version of rain controller
    //   WINSW  - software version of wind controller
    //   BATR   - battery of rain sensor (1-good (not present), 0-low)
    //   BATU   - battery of UV sensor (1-good (not present), 0-low)
    //   BATW   - battery of wind sensor (1-good (not present), 0-low)
    //   BAT5   - battery of sensor 5 (1-good (not present), 0-low)
    //   BAT4   - battery of sensor 4 (1-good (not present), 0-low)
    //   BAT3   - battery of sensor 3 (1-good (not present), 0-low)
    //   BAT2   - battery of sensor 2 (1-good (not present), 0-low)
    //   BAT1   - battery of sensor 1 (1-good (not present), 0-low)
    $rawData = explode(':',trim($rawData));
    $data = array(
           'BATR' => $rawData[5],
           'BATU' => $rawData[6],
           'BATW' => $rawData[7],
           'BAT5' => $rawData[8],
           'BAT4' => $rawData[9],
           'BAT3' => $rawData[10],
           'BAT2' => $rawData[11],
           'BAT1' => $rawData[12] );
  }
  return( $data );
}

function getOutdoorTemp( $weatherData )
{
  return( getFirstValidReading( 'T', $weatherData ) );
}

function getOutdoorHumidity( $weatherData )
{
  return( getFirstValidReading( 'H', $weatherData ) );
}

function getFirstValidReading( $keyRoot, $weatherData )
{
  for ( $i=1; $i<=5; $i++ )
  {
    $key = "$keyRoot$i";
    if ( is_numeric( $weatherData[$key] ) )
    {
      return( $weatherData[$key] );
    }
  }
  return( '-' );
}
?>
