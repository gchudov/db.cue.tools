<?php
include 'logo_start1.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 10;
if (isset($_GET['count'])) {
    $count = intval($_GET['count']);
    if ($count < 1) $count = 1;
    if ($count > 10) $count = 10;
}

$query = 'SELECT * FROM submissions2';
$term = ' WHERE ';
$url = '';

$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
  $query = $query . $term . "tocid='" . pg_escape_string($dbconn, $where_discid) . "'";
  $term = ' AND ';
  $url = $url . '&tocid=' . urlencode($where_discid);
}
$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
  $query = $query . $term . "artist ilike '" . pg_escape_string($dbconn, $where_artist) . "'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}
$where_album = @$_GET['album'];
if ($where_album != '')
{
  $query = $query . $term . "album ilike '" . pg_escape_string($dbconn, $where_album) . "'";
  $term = ' AND ';
  $url = $url . '&album=' . urlencode($where_album);
}
if ($term == ' WHERE ')
{
	$query = $query . $term . "subcount>=50";
	$term = ' AND ';
}
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$url = $url . '&count=' . $count;
$query = $query . " ORDER BY subcount DESC, id DESC OFFSET " . pg_escape_string($dbconn, $start) . " LIMIT " . pg_escape_string($dbconn, $count);

$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

$ctdb_page_title = 'Popular discs';

include 'list1.php';
include 'logo_start2.php';
?>
<center>
<div id='entries_div'></div>
<br><?php include 'ctdbbox.php';?>
<br><div id='musicbrainz_div'></div>
<?php if ($isadmin) { ?><br><div id='submissions_div'></div><?php } ?>
<?php if ($isadmin) { ?><br><div id='admin_div'></div><?php } ?>
<br>
</center>
</body>
</html>
