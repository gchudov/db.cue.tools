<?php
include 'logo_start1.php';
require_once( 'phpctdb/ctdb.php' );

if (!$isadmin) makeAuth1($realm, 'Admin priveleges required');

if (@$_GET['json']) {
$query = "";
$params = array();

$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
  $params[] = $where_discid;
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 'e.tocid=$' . count($params);
}

$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
  $params[] = '%' . $where_artist . '%';
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 'e.artist ilike $' . count($params);
}

$where_agent=@$_GET['agent'];
if ($where_agent != '')
{
  $params[] = $where_agent . '%';
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 's.agent ilike $' . count($params);
}

$where_drivename=@$_GET['drivename'];
if ($where_drivename!= '')
{
  $params[] = $where_drivename . '%';
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 's.drivename ilike $' . count($params);
}

$where_uid=@$_GET['uid'];
if ($where_uid != '')
{
  $params[] = $where_uid;
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 's.userid=$' . count($params);
}

$where_ip=@$_GET['ip'];
if ($where_ip != '')
{
  $params[] = $where_ip;
  $query .= $query == '' ? ' WHERE ' : ' AND ';
  $query .= 's.ip=$' . count($params);
}

/*
$show_date = true;
if ($query == '')
//if ($query == '' || $query == 'ip=$1')
{
  $params[] ='24:00';
  $query = $query == '' ? ' WHERE ' : $query . ' AND ';
  $query = $query . 'now() - s.time < $' . (count($params));
  $show_date = false;
}
*/
$result = pg_query_params($dbconn, "SELECT time, agent, drivename, userid, ip, s.entryid as entryid, s.confidence as confidence, e.confidence as confidence2, crc32, tocid, artist, title, firstaudio, audiotracks, trackcount, trackoffsets FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid" . $query . " ORDER by s.subid DESC LIMIT 100", $params)
  or die('Query failed: ' . pg_last_error());
$submissions = pg_fetch_all($result);
pg_free_result($result);

$json_submissions = null;
foreach($submissions as $record)
{
  $trcnt = ($record['firstaudio'] > 1) ?
    (($record['firstaudio'] - 1) . '+' . $record['audiotracks']) :
    (($record['audiotracks'] < $record['trackcount'])
     ? ($record['audiotracks'] . '+1')
     : $record['audiotracks']);
  $json_submissions[] = array(
    'c' => array(
      array('v' => strtotime($record['time'])),
      array('v' => $record['agent'] ? $record['agent'] : ''),
      array('v' => $record['drivename'] ? $record['drivename'] : ''),
      array('v' => $record['userid'] ? $record['userid'] : ''),
      array('v' => @$record['ip']),
      array('v' => $record['artist']),
      array('v' => $record['title']),
      array('v' => $record['tocid']),
      array('v' => $trcnt),
      array('v' => (int)$record['entryid']),
      array('v' => $record['confidence'] == $record['confidence2'] ? $record['confidence'] : sprintf('%d/%d', $record['confidence'], $record['confidence2'])),
      array('v' => (int)$record['crc32']),
      array('v' => phpCTDB::toc2mbid($record)),
    ));
}
$json_submissions_table = array(
  'cols' => array(
    array('label' => 'Date', 'type' => 'number'),
    array('label' => 'Agent', 'type' => 'string'),
    array('label' => 'Drive', 'type' => 'string'),
    array('label' => 'User', 'type' => 'string'),
    array('label' => 'IP', 'type' => 'string'),
    array('label' => 'Artist', 'type' => 'string'),
    array('label' => 'Album', 'type' => 'string'),
    array('label' => 'TOC Id', 'type' => 'string'),
    array('label' => 'Tr#', 'type' => 'string'),
    array('label' => 'CTDB Id', 'type' => 'number'),
    array('label' => 'AR', 'type' => 'string'),
    array('label' => 'CRC32', 'type' => 'number'),
    array('label' => 'MB Id', 'type' => 'string'),
    ),
  'rows' => $json_submissions);
$json_submissions = json_encode($json_submissions_table);
  $body = $json_submissions;
  $etag = crc32($body);
  header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*5) . ' GMT');
  header("ETag:  " . $etag);
  if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
    header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
    exit;
  }
  die($body);
} else
  header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript' src="ctdb10.js"></script>
<script type='text/javascript'>
google.setOnLoadCallback(drawTable);
function drawTable()
{
  var sbdiv = document.getElementById('submissions_div');
  var sbtable = new google.visualization.Table(sbdiv);
  var xmlhttp = new XMLHttpRequest();
  xmlhttp.open("GET", window.location.search + (window.location.search != '' ? '&' : '?') + 'json=1', true);
  sbdiv.innerHTML = '<img src="http://s3.cuetools.net/throb.gif" alt="Loading submissions log...">';
  xmlhttp.onreadystatechange=function() {
    if (xmlhttp.readyState != 4 || xmlhttp.status == 0) return;
    if (xmlhttp.status != 200) {
      sbdiv.innerHTML = xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText;
      xmlhttp = null;
      return;
    }
    if (xmlhttp.responseText == 'null') {
      sbdiv.innerHTML = '<img src="http://s3.cuetools.net/face-sad.png" alt="No submissions found">';
      xmlhttp = null;
      return;
    }
    var sbdata = ctdbSubmissionData(xmlhttp.responseText);
    xmlhttp = null;
    var sbopts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, page: 'enable', pageSize: 20};
    var sbview = new google.visualization.DataView(sbdata);
    sbview.hideColumns([11,12]);
    sbtable.draw(sbview, sbopts);
  };
  xmlhttp.send(null);
}
</script>
<?php include 'logo_start2.php'; ?>
<center>
<h3>Recent additions:</h3>
<div id='submissions_div'></div>
</center>
</body>
</html>
