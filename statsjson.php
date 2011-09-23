<?php
$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
  or die('Could not connect: ' . pg_last_error());

function simpleQuery($dbconn,$query)
{
  $result = pg_query($dbconn, $query)
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
  foreach($records as $record)
  {
    $json_entries[] = array('c' => array(
      array('v' => $record['label']),
      array('v' => (int)$record['cnt']),
    ));
  }
  return array('cols' => array(
    array('label' => 'Label', 'type' => 'string'),
    array('label' => 'Value', 'type' => 'number'),
  ), 'rows' => $json_entries);
}

$json_entries = false;
$stattype = $_GET['type'];
if ($stattype == 'drives')
  $json_entries_table = simpleQuery($dbconn,
    "select substring(drivename from '([^ ]*) ') as label, count(*) as cnt FROM submissions WHERE drivename IS NOT NULL GROUP BY label ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'agents')
  $json_entries_table = simpleQuery($dbconn,
    "select substring(agent from '([^:(]*)( [(]|:|$)') as label, count(*) as cnt FROM submissions WHERE agent IS NOT NULL GROUP BY label ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'pregaps')
  $json_entries_table = simpleQuery($dbconn,
    "select substring(trackoffsets from '([^ ]*) ') as label, count(*) as cnt FROM submissions2 WHERE int4(substring(trackoffsets from '([^ ]*) ')) < 450  AND int4(substring(trackoffsets from '([^ ]*) ')) != 0 GROUP BY label ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'submissions')
{
  $hourly = isset($_GET['hourly']);
  $since = isset($_GET['since']) ? $_GET['since'] : $hourly ? gmdate('Y-m-d H:00:00', time() - 60*60*24*10) : gmdate('Y-m-d', time() - 60*60*24*60);
  $till = isset($_GET['till']) ? $_GET['till'] : $hourly ? gmdate('Y-m-d H:00:00', time()) : gmdate('Y-m-d', time());
  $stacked = isset($_GET['stacked']) ? $_GET['stacked'] == 1 : false;
  $result = pg_query_params($dbconn, "select date_trunc($1, hour) t, sum(eac) as eac, sum(cueripper) as cueripper, sum(cuetools) as cuetools from hourly_stats where hour > $2 AND hour < $3 GROUP BY t ORDER by t", array($hourly ? 'hour' : 'day', $since, $till))
  #$result = pg_query_params($dbconn, "select date_trunc($1, time) t, count(NULLIF(agent ilike 'EAC%', false)) eac, count(NULLIF(agent ilike 'CUERipper%', false)) cueripper, count(NULLIF(agent ilike 'CUETools%', false)) cuetools from submissions where time > $2 group by t ORDER by t", array($hourly ? 'hour' : 'day', $since))
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
  $i=$j=$k=0;
  foreach($records as $record)
  {
    if (!$stacked) $i=$j=$k=0;
    $json_entries[] = array('c' => array(
      array('v' => $hourly ? substr($record['t'],5,11) : substr($record['t'],5,5)),
      array('v' => $j+= (int)$record['eac']),
      array('v' => $i+= (int)$record['cueripper']),
      array('v' => $k+= (int)$record['cuetools']),
    ));
  }
  $json_entries_table = array('cols' => array(
    array('label' => 'Date', 'type' => 'string'),
    array('label' => 'EAC', 'type' => 'number'),
    array('label' => 'CUERipper', 'type' => 'number'),
    array('label' => 'CUETools', 'type' => 'number'),
  ), 'rows' => $json_entries);
}
else die('bad stattype');
$json_entries = json_encode($json_entries_table);
if ($stattype != 'submissions')
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60) . ' GMT');
die($json_entries);
