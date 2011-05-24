<?php
include 'logo_start1.php';

if ((@$_GET['login'] && !$userinfo) || (@$_GET['logout'] && $userinfo)) makeAuth1($realm, 'Login requested');

$count = 20;
$query = 'SELECT * FROM submissions2';
$term = ' WHERE ';
$url = '';
$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
  $query = $query . $term . "tocid='" . pg_escape_string($where_discid) . "'";
  $term = ' AND ';
  $url = $url . '&tocid=' . urlencode($where_discid);
}
$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
  $query = $query . $term . "artist ilike '%" . pg_escape_string($where_artist) . "%'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}

$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY id DESC OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);
$result = @pg_query($query);
if (!$result) {
  header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error"); 
  die(pg_last_error());
}
if (pg_num_rows($result) == 0)
  die('nothing found');

$nfmt = array('style' => 'font-family:courier; text-align:right;');
$json_entries = false;
while(true == ($record = pg_fetch_array($result)))
{
  $trcnt = ($record['firstaudio'] > 1) ? 
    ('1+' . $record['audiotracks']) : 
    (($record['audiotracks'] < $record['trackcount']) 
      ? ($record['audiotracks'] . '+1') 
      : $record['audiotracks']);
  $json_entries[] = array('c' => array(
    array('v' => $record['artist'], 'f' => sprintf('<a href="?artist=%s">%s</a>', urlencode($record['artist']), mb_substr($record['artist'],0,60))),
    array('v' => $record['title'], 'f' => mb_substr($record['title'],0,60)),
    array('v' => $record['tocid'], 'p' => $nfmt, 'f' => sprintf('<a href="?tocid=%s">%s</a>', $record['tocid'], $record['tocid'])),
    array('v' => $trcnt, 'p' => $nfmt),
    array('v' => $record['id'], 'p' => $nfmt, 'f' => sprintf('<a href="show.php?id=%d">%08x</a>', $record['id'], $record['crc32'])),
    array('v' => $record['confidence'], 'p' => $nfmt),
  ));
}
$json_entries_table = array('cols' => array(
  array('label' => 'Artist', 'type' => 'string'),
  array('label' => 'Album', 'type' => 'string'),
  array('label' => 'Disc Id', 'type' => 'string'),
  array('label' => 'Tracks', 'type' => 'string'),
  array('label' => 'CTDB Id', 'type' => 'string'),
  array('label' => 'AR', 'type' => 'string'),
), 'rows' => $json_entries);

pg_free_result($result);
if (@$_GET['json'])
  die(json_encode($json_entries_table));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
<script type='text/javascript'>
google.setOnLoadCallback(drawTable);
function drawTable() 
{
  var data = new google.visualization.DataTable(<?php echo json_encode($json_entries_table)?>);
  var table = new google.visualization.Table(document.getElementById('entries_div'));
  var opts = {allowHtml: true, width: 1200, sort: 'disable', showRowNumber: false, pageSize: <?php echo $count?>, page: 'event', pagingButtonsConfiguration : 'both'};
  var start = <?php echo $start?>;
  var prev = start != 0;
  var next = data.getNumberOfRows() >= <?php echo $count?>;
  opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
  google.visualization.events.addListener(table, 'page', function(e) {
    var xmlhttp = new XMLHttpRequest();
    var shift = <?php echo $count?> * e['page'];
    xmlhttp.open("GET", '?json=1&start=' + (start + shift) + '<?php echo $url?>', false); // true
    //xmlhttp.onreadystatechange=function() {
    //}
    xmlhttp.send(null);
    if (xmlhttp.readyState != 4) {
      alert('error ' + xmlhttp.readyState);
      return;
    }
    if (xmlhttp.status != 200) {
      alert(xmlhttp.responseText != '' ? xmlhttp.responseText : xmlhttp.statusText);
      return;
    }
    data = new google.visualization.DataTable(xmlhttp.responseText);
    start += shift;
  prev = start != 0;
  next = data.getNumberOfRows() >= <?php echo $count?>;
  opts['pagingButtonsConfiguration'] = prev && next ? 'both' : prev ? 'prev' : next ? 'next' : 'none';
    table.draw(data, opts);
  });

  table.draw(data, opts);
}
</script>
<?php
include 'logo_start2.php'; 
printf("<center><h3>Recent additions:</h3>");
printf("<div id='entries_div'></div>\n");
if ($where_discid != '' && $isadmin) {
  printf('<br>');
  include 'table_start.php';
  printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>CTDB Id</th><th>AR</th></tr>');

  $result = pg_query_params($dbconn, "SELECT time, agent, userid, ip, s.entryid as entryid, s.confidence as confidence, crc32 FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid WHERE e.tocid = $1 ORDER by entryid DESC", array($where_discid))
    or die('Query failed: ' . pg_last_error());
  while (TRUE == ($record3 = pg_fetch_array($result)))
    printf('<tr><td class=td_ar>%s</td><td class=td_ar><a href="/recent.php?agent=%s">%.15s</a></td><td class=td_ar><a href="/recent.php?uid=%s">%s</a></td><td class=td_ar>%s</td><td class=td_ar>%08x</td><td class=td_ar>%d</td></tr>',
      $record3['time'],
      $record3['agent'], $record3['agent'],
      $record3['userid'], '*',
      @$record3['ip'],
			$record3['crc32'],
			$record3['confidence']);
  pg_free_result($result);
  printf("</table>");
  include 'table_end.php';
}
printf("</center>");
?>
</body>
</html>
