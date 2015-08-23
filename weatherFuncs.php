<?php
/************************************************************************/
function updateRainCounters( $weatherData, &$rainCounters, $rainKeepLimit )
{
  $dataPoint = array( 'TIME' => date('Y-m-d H:i:s',$weatherData['UNIXTIME']), 'UNIXTIME' => $weatherData['UNIXTIME'], 'RC' => $weatherData['RC'] );
  $counterCount = count( $rainCounters );
  if ( VERBOSE )
    print_r( $dataPoint );

  // If there's no previous data, just insert the new data point
  if ( $counterCount == 0 )
    $rainCounters[] = $dataPoint;
  else
  {
    // If the device rain counter has reset, start over with the new data point
    if ( $rainCounters[0]['RC'] > $weatherData['RC'] )
    {
      $rainCounters = array();
      $rainCounters[] = $dataPoint;
    }
    // Otherwise if the rain counter is the same as last time, replace the last counter
    else if ( $rainCounters[0]['RC'] == $weatherData['RC'] )
      $rainCounters[0] = $dataPoint;
    else
      $rainCounters = array_merge( array( $dataPoint ), $rainCounters );
  }

  // Now throw away data older than our keep threshold
  for ( $i=0; $i<count($rainCounters) &&
              $rainCounters[$i]['UNIXTIME'] > $rainKeepLimit; $i++ );
  if ( $i > 0 )
    array_splice( $rainCounters, $i );
}

/************************************************************************/
function rainSinceMidnight( $rainCounters )
{
  $now = getdate();
  $midnight = strtotime( $now['month'].' '.$now['mday'].','.$now['year'].' 00:00' );
  return( rainSince( $rainCounters, $midnight ) );
}

/************************************************************************/
function rainInLastHour( $rainCounters )
{
  $oneHourAgo = time() - HOUR_OF_SECONDS;
  return( rainSince( $rainCounters, $oneHourAgo ) );
}

/************************************************************************/
function rainSince( $rainCounters, $beginTime )
{
  $rain = 0;
  $counterCount = count( $rainCounters );
  if ( $counterCount > 1 )
  {
    $beginRain = NULL;
    $endRain = $rainCounters[0]['RC'];
    for ( $i=1; $i<$counterCount && 
                $rainCounters[$i]['UNIXTIME'] >= $beginTime; $i++ )
      $beginRain = $rainCounters[$i]['RC'];
    if ( $beginRain != NULL )
      $rain = $endRain - $beginRain;
  }
  return( $rain );
}

/************************************************************************/
function updateWindHistory( $weatherData, &$windHistory, $windKeepLimit )
{
  $dataPoint = array( 'TIME' => date('Y-m-d H:i:s',$weatherData['UNIXTIME']), 'UNIXTIME' => $weatherData['UNIXTIME'], 'WD' => $weatherData['WD'], 'WS' => $weatherData['WS'] );
  if ( VERBOSE )
    print_r( $dataPoint );

  // Add the data point
  $windHistory[] = $dataPoint;

  // Now throw away data older than our keep threshold
  for ( $i=0; $i<count($windHistory) &&
              $windHistory[$i]['UNIXTIME'] < $windKeepLimit; $i++ );
  if ( $i > 0 )
    array_splice( $windHistory, 0, $i );
}

/************************************************************************/
function avgWind( $windHistory, $cutoffSeconds, $verbose = FALSE )
{
  if ( count($windHistory) == 0 )
    return( array( "WD" => 0,
                   "WS" => 0, ) );

  $directions = array();
  $speeds = array();
  $startTime = time() - $cutoffSeconds;
  foreach( $windHistory as $dataPoint )
  {
    if ( $dataPoint['UNIXTIME'] > $startTime )
    {
      if ( $verbose )
        print_r( $dataPoint );
      $directions[] = $dataPoint['WD'];
      $speeds[] = $dataPoint['WS'];
    }
  }

  $total = 0;
  for ( $i=0; $i<count($speeds); $i++ )
    $total += $speeds[$i];

  return( array( "WD" => avgWindDirection( $directions, $speeds ),
                 "WS" => $total/count($speeds) ) );
}

/************************************************************************/
function avgWindDirection( $directions, $speeds = NULL )
{
  $cosVals = array();
  $sinVals = array();

  if ( count($directions) == 0 )
    return( 0 );

  // Convert stored directions to vectors and store in separate arrays
  for ( $i=0; $i<count($directions); $i++ )
  {
    $valInRads = deg2rad( floatval($directions[$i]) );
    $count = ( $speeds != NULL ? $speeds[$i] : 1 );
    for ( $j=0; $j<$count; $j++ )
    {
      $cosVals[] = cos( $valInRads );
      $sinVals[] = sin( $valInRads );
    }
  }

  // Total all stored directions
  $cosTotal = 0.0;
  $sinTotal = 0.0;

  for ( $i=0; $i<count($cosVals); $i++ )
  {
    $cosTotal += $cosVals[$i];
    $sinTotal += $sinVals[$i];
  }

  // Calculate average of stored directions
  $cosAverage = 0;
  $sinAverage = 0;
  if ( count($cosVals) != 0 )
  {
    $cosAverage = $cosTotal / count($cosVals); // Do something with magnitude...
    $sinAverage = $sinTotal / count($cosVals);
  }

  // Calculate arctangent of average vectors
  $atanVal = 0.0;
  $atanVal = atan2( $sinAverage, $cosAverage );
  $atanVal = rad2deg( $atanVal );

  // Modify arctangent if negative
  if ( $atanVal < 0 )
    $atanVal += 360;
  if ( $atanVal == 360 )
    $atanVal = 0;

  return( $atanVal );
}
?>
