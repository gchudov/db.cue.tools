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
  $count = isset($_GET['count']) ? $_GET['count'] : 100;
  $secondscount = 60 * 60 * ($hourly ? 1 : 24) * $count;
  $mask = $hourly ? 'Y-m-d H:00:00' : 'Y-m-d';
  $since = gmdate($mask, time() - $secondscount);
  $till = gmdate($mask, time());
  $stacked = isset($_GET['stacked']) ? $_GET['stacked'] == 1 : false;
  $result = pg_query_params($dbconn, "select date_trunc($1, hour) t, sum(eac) as eac, sum(cueripper) as cueripper, sum(cuetools) as cuetools from hourly_stats where hour > $2 AND hour < $3 GROUP BY t ORDER by t", array($hourly ? 'hour' : 'day', $since, $till))
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
  $i=$j=$k=0;
  foreach($records as $record)
  {
    if (!$stacked) $i=$j=$k=0;
    $json_entries[] = array('c' => array(
      array('v' => $hourly ? substr($record['t'],5,11) : substr($record['t'],0,10)),
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
#header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*5) . ' GMT');
header('Content-type: text/javascript; charset=UTF-8');
if (isset($_GET['tqx'])) {
  $tqx = array();
  foreach (explode(';', $_GET['tqx']) as $kvpair) {
    $kva = explode(':', $kvpair, 2);
    if (count($kva) == 2) {
      $tqx[$kva[0]] = $kva[1];
    }
  }
  $sig = (string)crc32(json_encode($json_entries_table));
  $resp = array('version' => '0.6', 'status' => 'ok');
  if (isset($tqx['reqId'])) $resp['reqId'] = $tqx['reqId'];
  $hdlr = isset($tqx['responseHandler']) ? $tqx['responseHandler'] : 'google.visualization.Query.setResponse';
  if (isset($tqx['sig']) && $tqx['sig'] == $sig) {
    $resp['status'] = 'error';
    $resp['errors'][] = array('reason' => 'not_modified', 'message' => 'Data not modified');
    die($hdlr . '(' . json_encode($resp) . ')');
  }
  $resp['sig'] = $sig;
  $resp['table'] = $json_entries_table;
  die($hdlr . '(' . json_encode($resp) . ')');
} else {
  $json_entries = json_encode($json_entries_table);
  die($json_entries);
}
