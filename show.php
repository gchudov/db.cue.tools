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
if (!$mbmeta) $mbmeta = phpCTDB::freedblookup(phpCTDB::toc_toc2s($record));
$ids = explode(' ', $record['trackoffsets']);
$crcs = explode(' ', $record['trackcrcs']);
$tracklist = $mbmeta ? $mbmeta[0]['tracklist'] : false;

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
  $trname = $tracklist ? ($tr < count($tracklist) ? $tracklist[$tr]['name'] : "[data track]") : "";
  $json_tracks[] = array('c' => array(
    array('v' => $trname), 
    array('v' => $trstartmsf), 
    array('v' => $trlenmsf), 
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

if ($mbmeta)
  $json_releases = phpCTDB::musicbrainz2json($mbmeta);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
    <script type='text/javascript'>
      google.setOnLoadCallback(drawTable);
      function drawTable() {
        var data = new google.visualization.DataTable(<?php echo json_encode($json_tracks_table) ?>, 0.6);
        var table = new google.visualization.Table(document.getElementById('tracks_div'));
        for (var row = 0; row < data.getNumberOfRows(); row++)
        {
          data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        }
        table.draw(data, {allowHtml: true, width: 900, sort: 'disable', showRowNumber: true});
        <?php if ($mbmeta) { ?>
        var mbdata = new google.visualization.DataTable(<?php echo $json_releases ?>);
        var mbdiv = document.getElementById('releases_div');
        for (var row = 0; row < mbdata.getNumberOfRows(); row++)
        {
          mbdata.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          if (mbdata.getValue(row, 8))
          mbdata.setFormattedValue(row, 2, '<a target=_blank href="http://musicbrainz.org/release/' + mbdata.getValue(row, 8) + '">' + mbdata.getValue(row, 2) + '</a>');
          mbdata.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          mbdata.setFormattedValue(row, 6, mbdata.getValue(row, 6).substring(0, 30));
          mbdata.setProperty(row, 7, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        }
        var mbview = new google.visualization.DataView(mbdata);
        mbview.hideColumns([8]); 
        var mbtable = new google.visualization.Table(mbdiv);
        mbtable.draw(mbview, {allowHtml: true, width: 900, sort: 'disable', showRowNumber: false});
        google.visualization.events.addListener(mbtable, 'select', function() {
          if (mbtable.getSelection().length > 0 && document.getElementById('set_artist') != null) {
            var srow = mbtable.getSelection()[0].row;
            document.getElementById('set_artist').value = mbdata.getValue(srow,1);
            document.getElementById('set_title').value = mbdata.getValue(srow,2) + (mbdata.getValue(srow,3) != '' ? ' (disc ' + mbdata.getValue(srow,3) + ')' : '');
          }
        });
        <?php } ?>
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

printf('<br>');
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
  printf('<tr><td class=td_album>Parity file</td><td class=td_album><a href="http://p.cuetools.net/%s%08x">%s</a></td></tr>' . "\n", urlencode(str_replace('.','+',$record['tocid'])), $record['crc32'], $record['parfile']);
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
