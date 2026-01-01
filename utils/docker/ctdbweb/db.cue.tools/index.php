<?php
include 'logo_start1.php';
require_once( 'phpctdb/ctdb.php' );

if ((@$_GET['login'] && !$userinfo) || (@$_GET['logout'] && $userinfo)) makeAuth1($realm, 'Login requested');

$count = 10;
if (isset($_GET['count'])) {
    $count = intval($_GET['count']);
    if ($count < 1) $count = 1;
    if ($count > 10) $count = 10;
}

$query = 'SELECT * FROM submissions2';
$term = ' WHERE ';
$url = '';
$where_id=@$_GET['id'];
if ($where_id != '')
{
  $query = $query . $term . "id=" . pg_escape_string($dbconn, $where_id);
  $term = ' AND ';
  $url = $url . '&id=' . urlencode($where_id);
}
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
  $query = $query . $term . "artist ilike '%" . pg_escape_string($dbconn, $where_artist) . "%'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}
$where_album = @$_GET['album'];
if ($where_album != '')
{
  $query = $query . $term . "album ilike '%" . pg_escape_string($dbconn, $where_album) . "%'";
  $term = ' AND ';
  $url = $url . '&album=' . urlencode($where_album);
}

$url = $url . '&count=' . $count;

$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY id DESC OFFSET " . pg_escape_string($dbconn, $start) . " LIMIT " . pg_escape_string($dbconn, $count);

$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

$ctdb_page_title = isset($where_id) ? '' : 'Recent additions';

include 'list1.php';
include 'logo_start2.php';
?>
<div style="margin:auto; width:1210px;">
<div id='entries_div' style='margin: 0 0 1em 0;'></div>
<?php include 'ctdbbox.php';?>
<div id='musicbrainz_div'></div>
<?php if ($isadmin) { ?><br><div id='submissions_div'></div><br><div id='admin_div'></div><?php } ?>
</div>
</body>
</html>
