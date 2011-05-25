<?php
include 'logo_start1.php'; 
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
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY confidence DESC OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) == 0)
  die('nothing found');

include 'list1.php';
include 'logo_start2.php';
printf("<center><h3>Popular discs:</h3>");
printf("<div id='entries_div'></div>\n");
printf("</center>");
?>
</body>
</html>
