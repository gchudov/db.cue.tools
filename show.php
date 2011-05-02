<?php
//mb_http_output('UTF-8');
//mb_internal_encoding('UTF-8');
include 'logo_start.php';
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


	$set_artist = @$_POST['set_artist_mb'];
	if (!$set_artist) $set_artist = @$_POST['set_artist'];
	$set_title = @$_POST['set_title_mb'];
	if (!$set_title) $set_title = @$_POST['set_title'];

	if ($set_artist || $set_title)
	{
		$result = pg_query_params($dbconn, "UPDATE submissions2 SET artist=$2, title=$3 WHERE id=$1", array((int)$id, $set_artist, $set_title))
		  or die('Query failed: ' . pg_last_error());
		if (pg_affected_rows($result) < 1) die('not found');
		if (pg_affected_rows($result) > 1) die('not unique');
		pg_free_result($result);
	}
}
//$path = phpCTDB::ctdbid2path($tocid, $ctdbid);
//
//$last_modified_time = filemtime($path);
//$etag = md5_file($path);
//
//header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified_time)." GMT");
//header("ETag: $etag");
//
//if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $last_modified_time ||
//    @trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
//    header("HTTP/1.1 304 Not Modified");
//    exit;
//}

$result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array((int)$id))
  or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) < 1) die('not found');
if (pg_num_rows($result) > 1) die('not unique');
$record = pg_fetch_array($result);
pg_free_result($result);

$mbid = phpCTDB::toc2mbid($record);
$mbmeta = phpCTDB::mblookup($mbid);

printf('<center>');

$imgfound = false;
if ($mbmeta)
	foreach ($mbmeta as $mbr)
		if ($mbr['coverarturl'])
		{
			if (!$imgfound) include 'table_start.php';
			printf('<img src="%s">', $mbr['coverarturl']);
			$imgfound = true;
		}
if ($imgfound) {
	include 'table_end.php';
	printf('<br>');
}
include 'table_start.php';
printf('<table border=0 cellspacing=0 cellpadding=6>');
//printf('<tr><td>TOC ID</td><td>%s</td></tr>', phpCTDB::toc2tocid($record));
printf('<tr><td>TOC ID</td><td>%s</td></tr>', $record['tocid']);
printf('<tr><td>CRC32 ID</td><td>%08X</td></tr>', $record['crc32']);
printf('<tr><td>Musicbrainz ID</td><td><a href="http://musicbrainz.org/bare/cdlookup.html?toc=%s">%s</a> (%s)</tr>', phpCTDB::toc2mbtoc($record), $mbid, $mbmeta ? count($mbmeta) : "-");
printf('<tr><td>CDDB/Freedb ID</td><td><form align=right method=post action="http://www.freedb.org/freedb_discid_check.php" name=mySearchForm>%s <input type=hidden name=page value=1><input type=hidden name=discid value="%s"><input type="submit" value="Lookup"></form></td></tr>', phpCTDB::toc2cddbid($record), phpCTDB::toc2cddbid($record));
//printf('<tr><td>Full TOC</td><td>%s</td></tr>', $record['trackoffsets']);
if ($isadmin)
{
	sscanf(phpCTDB::toc2arid($record),"%04x%04x", $arId0h, $arId0);
	printf('<tr><td>AccurateRip ID</td><td><a href="http://www.accuraterip.com/accuraterip/%x/%x/%x/dBAR-%03d-%s.bin">%s</a></td></tr>', $arId0 & 15, ($arId0 >> 4) & 15, ($arId0 >> 8) & 15, $record['trackcount'], phpCTDB::toc2arid($record), phpCTDB::toc2arid($record));
	printf('<form enctype="multipart/form-data" action="%s" method="POST">', $_SERVER['PHP_SELF']);
	printf('<input type=hidden name=id value=%s>', $id);
	printf('<tr><td>Confidence</td><td>%d</td></tr>', $record['confidence']);
	printf('<tr><td>Parity file</td><td><a href="repair.php?tocid=%s&id=%s">%s</a></td></tr>', $record['tocid'], $record['id'], $record['parfile']);
  printf('<tr><td colspan=2 align=center>');
  printf('</td></tr>');
} else
{
	printf('<tr><td>AccurateRip ID</td><td>%s</td></tr>', phpCTDB::toc2arid($record));
}
//printf('<tr><td valign=top>TOC</td><td align=center>');
//printf('</td></tr>');
if ($isadmin)
	printf('<tr><td>Artist</td><td><input maxlength=200 size=50 type="Text" name="set_artist" value="%s" \></td></tr>', $record['artist']);
else if ($record['artist'] != '')
	printf('<tr><td>Artist</td><td>%s</td></tr>', $record['artist']);
if ($mbmeta)
	foreach ($mbmeta as $mbr)
		if ($mbr['artistname'] != $record['artist'])
		{
			if ($isadmin)
				printf('<tr><td>Artist (MB)<input type=RADIO name="set_artist_mb" value="%s"></td>', $mbr['artistname']);
			else
				printf('<tr><td>Artist (MB)</td>');
			printf('<td>%s</td></tr>', $mbr['artistname']);
		}
if ($isadmin)
	printf('<tr><td>Title</td><td><input maxlength=200 size=50 type="Text" name="set_title" value="%s" \></td></tr>', $record['title']);
else if ($record['title'] != '')
	printf('<tr><td>Title</td><td>%s</td></tr>', $record['title']);
if ($mbmeta)
	foreach ($mbmeta as $mbr)
		if ($mbr['albumname'] != $record['title'])
		{
			if ($isadmin)
				printf('<tr><td>Title (MB)<input type=RADIO name="set_title_mb" value="%s"></td>', $mbr['albumname']);
			else
				printf('<tr><td>Title (MB)</td>');
			printf('<td>%s</td></tr>', $mbr['albumname']);
		}
if ($isadmin)
	printf('<tr><td><input type="checkbox" name="delete" value="delete">Delete</td><td colspan=1 align=left><input type="submit" name="update" value="Update" /></td></tr>');
?>
</table>
<?php 
include 'table_end.php';
printf('<br>');
include 'table_start.php';
printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Track</th><th>Start</th><th>Length</th><th>Start sector</th><th>End sector</th><th>CRC</th></tr>');
function TimeToString($time)
{
	$frame = $time % 75;
  $time = floor($time/75);
  $sec = $time % 60;
  $time = floor($time/60);
  $min = $time;
  return sprintf('%d:%02d.%02d',$min,$sec,$frame);
}
$ids = explode(' ', $record['trackoffsets']);
$crcs = explode(' ', $record['trackcrcs']);
for ($tr = 0; $tr < count($ids) - 1; $tr++)
{
  $trstart = $ids[$tr];
  $trend = $ids[$tr + 1] - 1;
	if ($record['firstaudio'] == 1 && $record['audiotracks'] < $record['trackcount'] && $tr == $record['audiotracks'] - 1)
		$trend -= 11400;
  $trstartmsf = TimeToString($trstart);
  $trlenmsf = TimeToString($trend + 1 - $trstart);
  $trmod = $tr + 1 - $record['firstaudio'];
  $trcrc = $trmod >= 0 && $trmod < count($crcs) ? $crcs[$trmod] : "";
	printf('<tr><td class=td_ar>%d</td><td class=td_ar>%s</td><td class=td_ar>%s</td><td class=td_ar>%d</td><td class=td_ar>%d</td><td class=td_ar>%s</td></tr>', $tr + 1, $trstartmsf, $trlenmsf, $trstart, $trend, $trcrc);
}
printf("</table>");
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
