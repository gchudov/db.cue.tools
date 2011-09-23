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
  $json_entries_table = simpleQuery($dbconn, "SELECT * FROM stats_drives ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'agents')
  $json_entries_table = simpleQuery($dbconn, "SELECT * FROM stats_agents ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'pregaps')
  $json_entries_table = simpleQuery($dbconn, "SELECT * FROM stats_pregaps ORDER BY cnt DESC LIMIT 100");
else if ($stattype == 'submissions')
{
  $hourly = isset($_GET['hourly']);
  $since = isset($_GET['since']) ? $_GET['since'] : $hourly ? gmdate('Y-m-d H:00:00', time() - 60*60*100) : gmdate('Y-m-d', time() - 60*60*24*100);
  $till = isset($_GET['till']) ? $_GET['till'] : $hourly ? gmdate('Y-m-d H:00:00', time()) : gmdate('Y-m-d', time());
  $stacked = isset($_GET['stacked']) ? $_GET['stacked'] == 1 : false;
  $result = pg_query_params($dbconn, "select date_trunc($1, hour) t, sum(eac) as eac, sum(cueripper) as cueripper, LEAST(sum(cuetools),800) as cuetools from hourly_stats where hour > $2 AND hour < $3 GROUP BY t ORDER by t", array($hourly ? 'hour' : 'day', $since, $till))
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
#if ($stattype != 'submissions')
#  header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60) . ' GMT');
if (isset($_GET['tqx'])) {
  $tqx = array();
  foreach (explode(';', $_GET['tqx']) as $kvpair) {
    $kva = explode(':', $kvpair, 2);
    if (count($kva) == 2) {
      $tqx[$kva[0]] = $kva[1];
    }
  }
  $resp = array('version' => '0.6', 'status' => 'ok');
  if (isset($tqx['reqId'])) $resp['reqId'] = $tqx['reqId'];
  $resp['table'] = $json_entries_table;
  $hdlr = isset($tqx['responseHandler']) ? $tqx['responseHandler'] : 'google.visualization.Query.setResponse';
  die($hdlr . '(' . json_encode($resp) . ')');
} else {
  $json_entries = json_encode($json_entries_table);
  die($json_entries);
}
