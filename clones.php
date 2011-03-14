<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 25;
$query = "select * from submissions2 a where exists(select *from submissions2 t where t.tocid=a.tocid and t.trackoffsets=a.trackoffsets and t.id != a.id)";
$url = '';
$query = $query . " ORDER BY tocid";
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
$start = @$_GET['start'];
if (pg_num_rows($result) == 0)
  die('nothing found');
if ($count > pg_num_rows($result))
	$count = pg_num_rows($result);
if ($start == '') $start = pg_num_rows($result) - $count;

printf("<center><h3>Clones (%d):</h3>", pg_num_rows($result));
include 'list.php';
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
