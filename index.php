<?php
include 'logo_start1.php';
require_once( 'phpctdb/ctdb.php' );

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

$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

include 'list1.php';
include 'logo_start2.php';
?>
<center><h3>CUETools Database: recent additions</h3>
<div id='entries_div_none'></div>
<br><div id='musicbrainz_div'></div>
<?php if ($isadmin) { ?><br><div id='submissions_div'></div><br><div id='admin_div'></div><?php } ?>
</center>
</body>
</html>
