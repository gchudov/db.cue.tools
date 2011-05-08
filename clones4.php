<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 50;
$query = "select a.* from submissions2 a, submissions2 b where substring(a.trackoffsets from '(.*) ([^ ]*)$') = substring(b.trackoffsets from '(.*) ([^ ]*)$') AND (int4(substring(a.trackoffsets from ' ([^ ]*)$'))=int4( substring(b.trackoffsets from ' ([^ ]*)$')) + 150 OR int4(substring(b.trackoffsets from ' ([^ ]*)$'))=int4( substring(a.trackoffsets from ' ([^ ]*)$')) + 150)";
$result = pg_query(str_replace('select a.*','select count(a.*)', $query));
$total = pg_fetch_row($result);
$total = $total[0];
pg_free_result($result);
$url = '';
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY trackoffsets OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) == 0)
  die('nothing found');

printf("<center><h3>Clones (%d):</h3>", $total);
include 'list.php';
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
