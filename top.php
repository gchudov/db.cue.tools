<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

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
  $query = $query . $term . "artist ilike '" . pg_escape_string($where_artist) . "'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}
if ($term == ' WHERE ')
{
	$query = $query . $term . "confidence>=100";
	$term = ' AND ';
}
$query = $query . " ORDER BY confidence";
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
$start = @$_GET['start'];
if (pg_num_rows($result) == 0)
  die('nothing found');
if ($count > pg_num_rows($result))
	$count = pg_num_rows($result);
if ($start == '') $start = pg_num_rows($result) - $count;

printf("<center><h3>Popular discs:</h3>");
include 'list.php';
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
