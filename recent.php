<?php
include 'logo_start1.php';
require_once( 'phpctdb/ctdb.php' );

if (!$isadmin) makeAuth1($realm, 'Admin priveleges required');

$query = "";
$params = false;

$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
	$params[] = $where_discid;
  $query = 'e.tocid=$' . count($params);
}

$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
	$params[] = '%' . $where_artist . '%';
  $query = 'e.artist ilike $' . count($params);
}

$where_agent=@$_GET['agent'];
if ($where_agent != '')
{
	$params[] = $where_agent . '%';
  $query = 's.agent ilike $' . count($params);
}

$where_drivename=@$_GET['drivename'];
if ($where_drivename!= '')
{
	$params[] = $where_drivename . '%';
  $query = 's.drivename ilike $' . count($params);
}

$where_uid=@$_GET['uid'];
if ($where_uid != '')
{
	$params[] = $where_uid;
  $query = 's.userid=$' . count($params);
}

$where_ip=@$_GET['ip'];
if ($where_ip != '')
{
	$params[] = $where_ip;
  $query = 's.ip=$' . count($params);
}

$show_date = true;
if ($query == '')
//if ($query == '' || $query == 'ip=$1')
{
	$params[] ='24:00';
	$query = ($query == '' ? '' : $query . ' AND ') . 'now() - s.time < $' . (count($params));
	$show_date = false;
}

$result = pg_query_params($dbconn, "SELECT time, agent, drivename, userid, ip, s.entryid as entryid, s.confidence as confidence, e.confidence as confidence2, crc32, tocid, artist, title, firstaudio, audiotracks, trackcount, trackoffsets FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid WHERE " . $query . " ORDER by s.subid DESC LIMIT 100", $params)
  or die('Query failed: ' . pg_last_error());
$submissions = pg_fetch_all($result);
pg_free_result($result);

$json_submissions = null;
foreach($submissions as $record)
{
  $trcnt = ($record['firstaudio'] > 1) ?
    ('1+' . $record['audiotracks']) :
    (($record['audiotracks'] < $record['trackcount'])
     ? ($record['audiotracks'] . '+1')
     : $record['audiotracks']);
  $json_submissions[] = array(
    'c' => array(
      array('v' => $show_date ? $record['time'] : substr($record['time'],11)),
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
    array('label' => 'Date', 'type' => 'string'),
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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript'>
google.setOnLoadCallback(drawTable);
function ctdbEntryData(json)
{
  function decimalToHexString(number)
  {
    if (number < 0)
    {
        number = 0xFFFFFFFF + number + 1;
    }
    var hex = number.toString(16).toUpperCase();
    return "00000000".substr(0, 8 - hex.length) + hex;
  }

  var data = new google.visualization.DataTable(json);
  for (var row = 0; row < data.getNumberOfRows(); row++) {
    data.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var matches = data.getValue(row, 1).match(/(CUETools|CUERipper|EACv.* CTDB) ([\d\.]*)/);
    var img = matches[1] == 'CUETools' ? 'cuetools.png' :  matches[1] == 'CUERipper' ? 'cueripper.png' : matches[1] == 'EACv1.0b2 CTDB' ? 'eac.png' : ''; 
    data.setFormattedValue(row, 1, (img != '' ? '<img height=12 src="' + img + '">' : '') + '<a href="?agent=' + data.getValue(row, 1) + '">' + matches[2] + '</a>');
    data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 2, '<a href="?drive=' + data.getValue(row, 2) + '">' + data.getValue(row, 2).substring(0,20) + '</a>');
    data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 3, '<a href="?uid=' + data.getValue(row, 3) + '">' + data.getValue(row, 3).substring(0,6) + '</a>');
    data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var artist = data.getValue(row, 5);
    if (!artist) artist = "Unknown Artist";
    data.setFormattedValue(row, 5, '<a href="?artist=' + encodeURIComponent(artist) + '">' + artist.substring(0,30) + '</a>');
    var title = data.getValue(row, 6);
    if (!title) title = "Unknown Title";
    data.setFormattedValue(row, 6, title.substring(0,30));
    data.setProperty(row, 7, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var toc = data.getValue(row, 7);
    data.setFormattedValue(row, 7, '<a href="?tocid=' + toc + '">' + toc.substring(0,7) + '</a>');
    data.setProperty(row, 8, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 9, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 9, '<a href="show.php?id=' + data.getValue(row, 9).toString(10) + '">' + decimalToHexString(data.getValue(row, 11)) + '</a>');
    data.setProperty(row, 10, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
  }
  return data;
}
function drawTable()
{
  var data = ctdbEntryData(<?php echo $json_submissions;?>);
  var table = new google.visualization.Table(document.getElementById('entries_div'));
  var opts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, page: 'enable', pageSize: 20};
  var view = new google.visualization.DataView(data);
  view.hideColumns([11,12]);
  table.draw(view, opts);
}
</script>
<?php include 'logo_start2.php'; ?>
<center>
<h3>Recent additions:</h3>
<div id='entries_div'></div>
</center>
</body>
</html>
