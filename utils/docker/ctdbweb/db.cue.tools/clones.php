<?php
include 'logo_start1.php';
require_once( 'phpctdb/ctdb.php' );

die('disabled');

$count = 20;
$term = ' WHERE ';
$query = "SELECT * FROM submissions2 a";
$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
  $query = $query . $term . "artist ilike '" . pg_escape_string($where_artist) . "'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}
if ($term == ' WHERE ')
{
  $query = $query . $term . "subcount>=5";
  $term = ' AND ';
}
$query = $query . $term . "(subcount > 1) AND exists(select *from submissions2 t where t.tocid=a.tocid and t.trackoffsets=a.trackoffsets and t.id != a.id AND (t.subcount > 1))";
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY tocid, id OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);

$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

include 'list1.php';
include 'logo_start2.php';
?>
<center><h3>CUETools Database: clones</h3>
<div id='entries_div'></div>
<br><div id='musicbrainz_div'></div>
<?php if ($isadmin) { ?><br><div id='submissions_div'></div><?php } ?>
</center>
</body>
</html>
