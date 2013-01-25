<?
require( 'te923.php' );
require( 'weatherFuncs.php' );

define( 'MAX_LOOPS', 4000 );
define( 'USE_SUDO', TRUE );

$loopCount = 0;

$interval = $argv[1];
$weatherFile = '';
if ( count($argv) > 2 )
  $weatherFile = $argv[2];
if ( count($argv) > 3 )
  $statusFile = $argv[3];

$prevD = 99999;
$prevS = 0999999;
$prevG = 0999999;
while ( $loopCount++ < MAX_LOOPS )
{
  $weatherData = parseTE923WeatherData( getTE923WeatherData( '~/cronjobs', FALSE, $weatherFile ) );
  if ( $weatherData['WD'] != $prevD || $weatherData['WS'] != $prevS || $weatherData['WG'] != $prevG )
    echo "\n".$weatherData['TS']."; dir: ".$weatherData['WD']."; speed: ".$weatherData['WS']."; gust: ".$weatherData['WG']."\n";
  else
    echo ".";
  $prevD = $weatherData['WD'];
  $prevS = $weatherData['WS'];
  $prevG = $weatherData['WG'];
  sleep( $interval );
}


