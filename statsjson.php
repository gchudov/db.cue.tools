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
  $since = isset($_GET['since']) ? $_GET['since'] : gmdate('Y-m-d', time() - 60*60*24*30);
  $stacked = isset($_GET['stacked']) ? $_GET['stacked'] == 1 : false;
  $result = pg_query_params($dbconn, "select date_trunc('day', time) t, count(NULLIF(agent ilike 'EAC%', false)) eac, count(NULLIF(agent ilike 'CUERipper%', false)) cueripper, count(NULLIF(agent ilike 'CUETools%', false)) cuetools from submissions where time > $1 group by t ORDER by t", array($since))
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
  foreach($records as $record)
  {
    if (!$stacked) $i=$j=$k=0;
    $json_entries[] = array('c' => array(
      array('v' => substr($record['t'],0,10)),
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
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*4) . ' GMT');
die($json_entries);
