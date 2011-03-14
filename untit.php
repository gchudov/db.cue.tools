<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 25;
$query = "SELECT * FROM submissions2 WHERE artist IS NULL OR artist='' OR title IS NULL";
$url = '';
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
