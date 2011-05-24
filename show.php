<?php 
include 'logo_start1.php'; 
require_once( 'phpctdb/ctdb.php' );
if ((@$_GET['login'] && !$userinfo) || (@$_GET['logout'] && $userinfo)) makeAuth1($realm, 'Login requested');

$id = @$_GET['id'];

if ($isadmin)
{
  if (!$id) $id = $_POST['id'];

  if (@$_POST['delete']=='delete') {
    $result = pg_query_params($dbconn, "DELETE FROM submissions WHERE entryid=$1", array((int)$id))
      or die('Query failed: ' . pg_last_error());
    pg_free_result($result);
    $result = pg_query_params($dbconn, "DELETE FROM submissions2 WHERE id=$1", array((int)$id))
      or die('Query failed: ' . pg_last_error());
    if (pg_affected_rows($result) < 1) die('not found');
    if (pg_affected_rows($result) > 1) die('not unique');
    pg_free_result($result);
    die('deleted');
  }

  $set_artist = @$_POST['set_artist'];
  $set_title = @$_POST['set_title'];

  if ($set_artist || $set_title)
  {
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET artist=$2, title=$3 WHERE id=$1", array((int)$id, $set_artist, $set_title))
      or die('Query failed: ' . pg_last_error());
    if (pg_affected_rows($result) < 1) die('not found');
    if (pg_affected_rows($result) > 1) die('not unique');
    pg_free_result($result);
 }
}

function TimeToString($time)
{
  $frame = $time % 75;
  $time = floor($time/75);
  $sec = $time % 60;
  $time = floor($time/60);
  $min = $time;
  return sprintf('%d:%02d.%02d',$min,$sec,$frame);
}

$result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array((int)$id))
  or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) < 1) die('not found');
if (pg_num_rows($result) > 1) die('not unique');
$record = pg_fetch_array($result);
pg_free_result($result);

$mbid = phpCTDB::toc2mbid($record);
$mbmeta = phpCTDB::mblookup($mbid);
$ids = explode(' ', $record['trackoffsets']);
$crcs = explode(' ', $record['trackcrcs']);
$tracklist = $mbmeta ? $mbmeta[0]['tracklist'] : false;

$timefmt = array('style' => 'font-family:courier; text-align:right;');
//$timefmt = array('className' => 'td_ar');
$json_tracks = false;
for ($tr = 0; $tr < count($ids) - 1; $tr++)
{
  $trstart = (int)$ids[$tr];
  $trend = $ids[$tr + 1] - 1;
  if ($record['firstaudio'] == 1 && $record['audiotracks'] < $record['trackcount'] && $tr == $record['audiotracks'] - 1)
    $trend -= 11400;
  $trstartmsf = TimeToString($trstart);
  $trlenmsf = TimeToString($trend + 1 - $trstart);
  $trmod = $tr + 1 - $record['firstaudio'];
  $trcrc = $trmod >= 0 && $trmod < count($crcs) ? $crcs[$trmod] : "";
  //print_r($mbmeta[0]);
  $trname = $tracklist ? ($trmod >= 0 && $trmod < count($tracklist) ? $tracklist[$trmod]['name'] : "[data track]") : "";
  $json_tracks[] = array('c' => array(
    array('v' => $trname), 
    array('v' => $trstartmsf, 'p' => $timefmt), 
    array('v' => $trlenmsf, 'p' => $timefmt), 
    array('v' => $trstart), 
    array('v' => $trend),
    array('v' => $trcrc),
  ));
}
$json_tracks_table = array('cols' => array(
  array('label' => 'Track', 'type' => 'string'),
  array('label' => 'Start', 'type' => 'string'),
  array('label' => 'Length', 'type' => 'string'),
  array('label' => 'Start', 'type' => 'number'),
  array('label' => 'End', 'type' => 'number'),
  array('label' => 'CRC', 'type' => 'string'),
), 'rows' => $json_tracks);

$json_releases = false;
if ($mbmeta)
  foreach ($mbmeta as $mbr)
  {
    $label = '';
    $labels_orig = @$mbr['label'];
    if ($labels_orig)
      foreach ($labels_orig as $l)
        $label = $label . ($label != '' ? ', ' : '') . $l['name'] . (@$l['catno'] ? ' ' . $l['catno'] : '');
 
    $json_releases[] = array('c' => array(
      array('v' => (int)$mbr['first_release_date_year']),
      array('v' => $mbr['artistname']), 
      array('v' => $mbr['albumname']), 
      array('v' => $mbr['totaldiscs'] != 1 ? $mbr['discnumber'] . '/' . $mbr['totaldiscs'] . ($mbr['discname'] ? ': ' . $mbr['discname'] : '') : ''),
      array('v' => $mbr['country']), 
      array('v' => $mbr['releasedate']), 
      array('v' => mb_strlen($label) > 20 ? mb_substr($label,0,18) . '...' : $label), 
      array('v' => $mbr['barcode'], 'p' => $timefmt), 
      array('v' => $mbr['gid']),
    ));
  }

$json_releases_table = array('cols' => array(
  array('label' => 'Year', 'type' => 'number'),
  array('label' => 'Artist', 'type' => 'string'),
  array('label' => 'Album', 'type' => 'string'),
  array('label' => 'Disc', 'type' => 'string'),
  array('label' => 'C', 'type' => 'string'),
  array('label' => 'Release', 'type' => 'string'),
  array('label' => 'Label', 'type' => 'string'),
  array('label' => 'Barcode', 'type' => 'string'),
  array('label' => 'gid', 'type' => 'string'),
), 'rows' => $json_releases);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <script type='text/javascript' src='https://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages:['table']});
      google.setOnLoadCallback(drawTable);
      function drawTable() {
        var data = new google.visualization.DataTable(<?php echo json_encode($json_tracks_table) ?>, 0.6);
        var table = new google.visualization.Table(document.getElementById('tracks_div'));
        table.draw(data, {allowHtml: true, width: 900, sort: 'disable', showRowNumber: true});
        var data = new google.visualization.DataTable(<?php echo json_encode($json_releases_table) ?>);
        if (data.getNumberOfRows() > 0) {
        var formatter = new google.visualization.TablePatternFormat('<a href="http://musicbrainz.org/release/{0}">{1}</a>');
        formatter.format(data, [8, 2], 2); // Apply formatter and set the formatted value of the first column.
        var view = new google.visualization.DataView(data);
        view.setColumns([0,1,2,3,4,5,6,7]); // Create a view with the first column only.
        var table = new google.visualization.Table(document.getElementById('releases_div'));
        table.draw(view, {allowHtml: true, width: 900, sort: 'disable', showRowNumber: false});
        google.visualization.events.addListener(table, 'select', function() {
          if (table.getSelection().length > 0 && document.getElementById('set_artist') != null) {
            var srow = table.getSelection()[0].row;
            document.getElementById('set_artist').value = data.getValue(srow,1);
            document.getElementById('set_title').value = data.getValue(srow,2) + (data.getValue(srow,3) != '' ? ' (disc ' + data.getValue(srow,3) + ')' : '');
          }
        });
        }
      }
    </script>
<?php
include 'logo_start2.php';

printf('<center>');

$imgfound = false;
if ($mbmeta)
	foreach ($mbmeta as $mbr)
		if ($mbr['coverarturl'] && $mbr['coverarturl'] != '')
		{
			if (!$imgfound) include 'table_start.php';
			printf('<img src="%s">', $mbr['coverarturl']);
			$imgfound = true;
		}
if ($imgfound) {
	include 'table_end.php';
	printf('<br>');
}

printf("<div id='releases_div'></div>\n");
if (!$mbmeta && ($record['artist'] != '' || $record['title'] != ''))
  printf("<h3>%s - %s</h3>\n", $record['artist'], $record['title']);
else
  printf('<br>');
printf("<div id='tracks_div'></div>\n");
printf('<br>');

include 'table_start.php';
printf('<table border=0 cellspacing=0 cellpadding=6>');
//printf('<tr><td>TOC ID</td><td>%s</td></tr>', phpCTDB::toc2tocid($record));
printf('<tr><td class=td_album>CTDB ID</td><td class=td_discid><a href="lookup2.php?toc=%s&musicbrainz=1&fuzzy=0">%s</a></td></tr>', phpCTDB::toc_toc2s($record), $record['tocid']);
printf('<tr><td class=td_album>Musicbrainz ID</td><td class=td_discid><a href="http://musicbrainz.org/bare/cdlookup.html?toc=%s">%s</a> (%s)</tr>', phpCTDB::toc2mbtoc($record), $mbid, $mbmeta ? count($mbmeta) : "-");
printf('<tr><td class=td_album>CDDB/Freedb ID</td><td class=td_discid><form align=right method=post action="http://www.freedb.org/freedb_discid_check.php" name=mySearchForm>%s <input type=hidden name=page value=1><input type=hidden name=discid value="%s"><input type="submit" value="Lookup"></form></td></tr>', phpCTDB::toc2cddbid($record), phpCTDB::toc2cddbid($record));
//printf('<tr><td>Full TOC</td><td>%s</td></tr>', $record['trackoffsets']);
if ($isadmin)
{
  sscanf(phpCTDB::toc2arid($record),"%04x%04x", $arId0h, $arId0);
  printf('<tr><td class=td_album>AccurateRip ID</td><td class=td_discid><a href="http://www.accuraterip.com/accuraterip/%x/%x/%x/dBAR-%03d-%s.bin">%s</a></td></tr>' . "\n", $arId0 & 15, ($arId0 >> 4) & 15, ($arId0 >> 8) & 15, $record['trackcount'], phpCTDB::toc2arid($record), phpCTDB::toc2arid($record));
} else
{
  printf('<tr><td class=td_album>AccurateRip ID</td><td class=td_discid>%s</td></tr>', phpCTDB::toc2arid($record));
}
printf('<tr><td class=td_album>CRC32</td><td class=td_discid>%08X</td></tr>', $record['crc32']);
printf('<tr><td class=td_album>Confidence</td><td class=td_discid>%d</td></tr>' . "\n", $record['confidence']);
if ($isadmin)
{
  printf('<form enctype="multipart/form-data" action="%s" method="POST">', $_SERVER['PHP_SELF']);
  printf('<input type=hidden name=id value=%s>', $id);
  printf('<tr><td class=td_album>Parity file</td><td class=td_album><a href="repair.php?id=%s">%s</a></td></tr>' . "\n", $record['id'], $record['parfile']);
  printf('<tr><td colspan=2 align=center></td></tr>');
  printf('<tr><td class=td_album>Artist</td><td class=td_album><input maxlength=200 size=50 type="Text" name="set_artist" id="set_artist" value="%s" \></td></tr>' . "\n", $record['artist']);
  printf('<tr><td class=td_album>Title</td><td><input maxlength=200 size=50 type="Text" name="set_title" id="set_title" value="%s" \></td></tr>' . "\n", $record['title']);
  printf('<tr><td class=td_album><input type="checkbox" name="delete" value="delete">Delete</td><td colspan=1 align=left><input type="submit" name="update" value="Update" /></td></tr>');
}
?>
</table>
<?php 
include 'table_end.php';
if ($isadmin) {
	printf('<br>');
	include 'table_start.php';
  printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>Conf</th></tr>');
	$result = pg_query_params($dbconn, "SELECT * FROM submissions WHERE entryid=$1", array($record['id']))
  	or die('Query failed: ' . pg_last_error());
	while (TRUE == ($record3 = pg_fetch_array($result)))
	  printf('<tr><td class=td_ar>%s</td><td class=td_ar><a href="/?agent=%s">%.15s</a></td><td class=td_ar><a href="/?uid=%s">%s</a></td><td class=td_ar>%s</td><td class=td_ar>%s</td></tr>', 
      $record3['time'], 
      $record3['agent'], $record3['agent'], 
      $record3['userid'], '*',
      @$record3['ip'],
      @$record3['confidence']);
	pg_free_result($result);
  printf("</table>");
	include 'table_end.php';
}
?>
</center>
</body>
</html>
