<?php
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
