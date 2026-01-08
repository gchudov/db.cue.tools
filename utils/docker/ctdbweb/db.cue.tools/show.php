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

$toc = phpCTDB::toc_toc2s($record);
$mbid = phpCTDB::tocs2mbid($toc);
$ids = explode(' ', $record['trackoffsets']);
$crcs = null;
if ($record['track_crcs'] != null) phpCTDB::pg_array_parse($record['track_crcs'], $crcs);
foreach($crcs as &$track_crc) $track_crc = sprintf("%08x", $track_crc&0xffffffff);

$json_tracks = false;
for ($tr = 0; $tr < count($ids) - 1; $tr++)
{
  $trstart = 150 + (int)$ids[$tr];
  $trend = 150 + $ids[$tr + 1] - 1;
  if ($record['firstaudio'] == 1 && $record['audiotracks'] < $record['trackcount'] && $tr == $record['audiotracks'] - 1)
    $trend -= 11400;
  $trstartmsf = TimeToString($trstart);
  $trlenmsf = TimeToString($trend + 1 - $trstart);
  $trmod = $tr + 1 - $record['firstaudio'];
  $trcrc = $trmod >= 0 && $trmod < count($crcs) ? $crcs[$trmod] : "";
  $json_tracks[] = array('c' => array(
    array('v' => ''), 
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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22table%22%5D%7D%5D%7D"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script type='text/javascript' src="<?php echo $ctdbcfg_s3?>/ctdb.js?id=<?php echo $ctdbcfg_s3_id?>"></script>
    <!--script type='text/javascript' src="<?php echo $ctdbcfg_s3?>/ctdb.min.js?id=<?php echo $ctdbcfg_s3_id?>"></script-->
    <script type='text/javascript'>
      google.setOnLoadCallback(drawTable);
      function drawTable() {
        var data = new google.visualization.DataTable(<?php echo json_encode($json_tracks_table) ?>, 0.6);
        var table = new google.visualization.Table(document.getElementById('tracks_div'));
        for (var row = 0; row < data.getNumberOfRows(); row++)
        {
          data.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-nowrap');
          data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
          data.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
        }
        table.draw(data, {allowHtml: true, width: 800, sort: 'disable', showRowNumber: true});

        var mbdiv = $('#releases_div');
        mbdiv.html('<center><img src="http://s3.cuetools.net/throb.gif" alt="Looking up metadata..."></center>');
        $.ajax({
          url: "http://db.cuetools.net/lookup2.php?version=3&ctdb=0&metadata=extensive&fuzzy=1&toc=<?php echo $toc?>&jsonp=?",
          cache: true,
          dataType: "jsonp",
          jsonpCallback: "jQuery17108581121710594743_1333124620119",
          error: function() {
            mbdiv.html('<center><img src="http://s3.cuetools.net/face-sad.png" alt="No metadata found"></center>');
          },
          success: function(json) {
          if (json == null) {
            mbdiv.html('<center><img src="http://s3.cuetools.net/face-sad.png" alt="No metadata found"></center>');
            return;
          }
          var mbdata = ctdbMetaData(json);
          xmlhttp = null;
          var mbview = new google.visualization.DataView(mbdata);
          mbview.hideColumns([8,9,11,12,13]);
          var mbtable = new google.visualization.Table(mbdiv[0]);
          mbtable.draw(mbview, {allowHtml: true, width: 1200, page: 'enable', pageSize: 10, sort: 'disable', showRowNumber: false});
          var imglist1 = new Array();
          var vidlist1 = new Array();
          for (var row = 0; row < mbdata.getNumberOfRows(); row++) {
            var imglist2 = mbdata.getValue(row, 11);
            if (imglist2 != null) imglist1 = imglist1.concat(imglist2);
            var vidlist2 = mbdata.getValue(row, 12);
            if (vidlist2 != null) vidlist1 = vidlist1.concat(vidlist2);
          }
          var coverartElement = document.getElementById('coverart');
          var videosElement = document.getElementById('videos');
          function resetCoverart() {
            if (coverartElement != null && videosElement != null) {
              var tracklist = mbdata.getValue(mbtable.getSelection().length > 0 ? mbtable.getSelection()[0].row : 0,13);
              for(var tr=0; tr < data.getNumberOfRows(); tr++)
                data.setValue(tr,0,tr in tracklist ? tracklist[tr].name : '' /*'[data track]'*/);
              table.draw(data, {allowHtml: true, width: 800, sort: 'disable', showRowNumber: true});
              var imglist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,11) : imglist1;
              coverartElement.innerHTML = ctdbCoverart(imglist, mbtable.getSelection().length == 0, 4);
              var vidlist = mbtable.getSelection().length > 0 ? mbdata.getValue(mbtable.getSelection()[0].row,12) : vidlist1;
              videosElement.innerHTML = ctdbVideos(vidlist, 3);
            }
          }
          resetCoverart();
          google.visualization.events.addListener(mbtable, 'select', function() {
            resetCoverart();
            if (mbtable.getSelection().length > 0 && document.getElementById('set_artist') != null) {
              var srow = mbtable.getSelection()[0].row;
              document.getElementById('set_artist').value = mbdata.getValue(srow,1);
              document.getElementById('set_title').value = mbdata.getValue(srow,2) + (mbdata.getValue(srow,3) != '' ? ' (disc ' + mbdata.getValue(srow,3) + ')' : '');
            }
          });
          }
        });
      }
    </script>
<?php
include 'logo_start2.php';
?>
<div style="margin:auto; width:1210px;">
<table class="ctdbbox" border=0 cellspacing=0 cellpadding=0 width="1200">
<tr><td class=td_album><img width=16 height=16 border=0 alt="CTDB" src="http://s3.cuetools.net/icons/cueripper.png"></td><td class=td_discid width=50%><a href="lookup2.php?version=2&ctdb=1&metadata=extensive&fuzzy=1&toc=<?php echo phpCTDB::toc_toc2s($record); ?>"><?php echo $record['tocid']; ?></a></td><td rowspan=10 style="vertical-align: top;"><div id='tracks_div'></div></td></tr>
<tr><td class=td_album><img width=16 height=16 border=0 alt="Musicbrainz" src="http://s3.cuetools.net/icons/musicbrainz.png"></td><td class=td_discid><a href="http://musicbrainz.org/bare/cdlookup.html?toc=<?php echo phpCTDB::toc2mbtoc($record);?>"><?php echo $mbid;?></a></tr>
<tr><td class=td_album><img width=16 height=16 border=0 alt="FreeDB" src="http://s3.cuetools.net/icons/freedb.png"></td><td class=td_discid>><?php echo phpCTDB::toc2cddbid($record);?></td></tr>
<?php
//printf('<tr><td>Full TOC</td><td>%s</td></tr>', $record['trackoffsets']);
if ($isadmin)
{
  sscanf(phpCTDB::toc2arid($record),"%04x%04x", $arId0h, $arId0);
  printf('<tr><td class=td_album><img width=16 height=16 border=0 alt="AccurateRip" src="http://s3.cuetools.net/icons/ar.png"></td><td class=td_discid><a href="http://www.accuraterip.com/accuraterip/%x/%x/%x/dBAR-%03d-%s.bin">%s</a></td></tr>' . "\n", $arId0 & 15, ($arId0 >> 4) & 15, ($arId0 >> 8) & 15, $record['audiotracks'], phpCTDB::toc2arid($record), phpCTDB::toc2arid($record));
} else
{
  printf('<tr><td class=td_album><img width=16 height=16 border=0 alt="AccurateRip" src="http://s3.cuetools.net/icons/ar.png"></td><td class=td_discid>%s</td></tr>', phpCTDB::toc2arid($record));
}
?>
<tr><td class=td_album>CRC32</td><td class=td_discid><?php printf('%08X', $record['crc32']);?></td></tr>
<tr><td class=td_album>Conf.</td><td class=td_discid><?php echo $record['subcount'];?></td></tr>
<?php
if ($isadmin)
{
  printf('<form enctype="multipart/form-data" action="%s" method="POST">', $_SERVER['PHP_SELF']);
  printf('<input type=hidden name=id value=%s>', $id);
  $parityfile = $record['id'];
  printf('<tr><td class=td_album>Parity file</td><td class=td_discid><a href="http://p.cuetools.net/%s">%s</a></td></tr>' . "\n", urlencode($parityfile), $record['hasparity'] == 't' ? ($record['s3'] == 't' ? "s3" : "pending") : "none");
  printf('<tr><td colspan=2 align=center></td></tr>');
  printf('<tr><td class=td_album>Artist</td><td class=td_album><input maxlength=200 size=40 type="Text" name="set_artist" id="set_artist" value="%s" \></td></tr>' . "\n", $record['artist']);
  printf('<tr><td class=td_album>Title</td><td><input maxlength=200 size=40 type="Text" name="set_title" id="set_title" value="%s" \></td></tr>' . "\n", $record['title']);
  printf('<tr><td class=td_album><input type="checkbox" name="delete" value="delete">Delete</td><td colspan=1 align=left><input type="submit" name="update" value="Update" /></td></tr>');
}
?>
<tr><td></td><td height=64 id="coverart"></td></tr>
<tr><td></td><td height=64 id="videos"></td></tr>
</table>
<div id='releases_div'>
<?php
if ($record['artist'] != '' || $record['title'] != '')
  printf("<center><h3>%s - %s</h3></center>\n", $record['artist'], $record['title']);
?>
</div>
</div>
<?php 
if ($isadmin) {
	printf('<br>');
	printf('<table class="ctdbbox"><tr><td>');
  printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>Conf</th></tr>');
	$result = pg_query_params($dbconn, "SELECT * FROM submissions WHERE entryid=$1", array($record['id']))
  	or die('Query failed: ' . pg_last_error());
	while (TRUE == ($record3 = pg_fetch_array($result)))
	  printf('<tr><td class=td_ar>%s</td><td class=td_ar><a href="/?agent=%s">%.15s</a></td><td class=td_ar><a href="/?uid=%s">%s</a></td><td class=td_ar>%s</td></tr>', 
      $record3['time'], 
      $record3['agent'], $record3['agent'], 
      $record3['userid'], '*',
      @$record3['ip']);
	pg_free_result($result);
  printf("</table>");
	printf('</td></tr></table>');
}
?>
</body>
</html>
