<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 50;
$query = "select a.* from submissions2 a, submissions2 b where substring(a.trackoffsets from '(.*) ([^ ]*)$') = substring(b.trackoffsets from '(.*) ([^ ]*)$') AND (substring(a.trackoffsets from ' ([^ ]*)$')=int4( substring(b.trackoffsets from ' ([^ ]*)$')) + 150 OR substring(b.trackoffsets from ' ([^ ]*)$')=int4( substring(a.trackoffsets from ' ([^ ]*)$')) + 150)";
$url = '';
$query = $query . " ORDER BY trackoffsets";
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
