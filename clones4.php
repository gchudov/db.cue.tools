<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 50;
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
pg_query("BEGIN TRANSACTION; DECLARE curs CURSOR FOR " . 
"select a.* from submissions2 a, submissions2 b where substring(a.trackoffsets from '(.*) ([^ ]*)$') = substring(b.trackoffsets from '(.*) ([^ ]*)$') AND (substring(a.trackoffsets from ' ([^ ]*)$')=int4( substring(b.trackoffsets from ' ([^ ]*)$')) + 150 OR substring(b.trackoffsets from ' ([^ ]*)$')=int4( substring(a.trackoffsets from ' ([^ ]*)$')) + 150) ORDER BY trackoffsets;") or die('Query failed: ' . pg_last_error());
$result = pg_query("MOVE FORWARD " . pg_escape_string($start) . " IN curs") or die('Query failed: ' . pg_last_error());
pg_free_result($result);
$result = pg_query(" FETCH " . pg_escape_string($count) . " FROM curs") or die('Query failed: ' . pg_last_error());
$result1 = pg_query("MOVE FORWARD ALL IN curs") or die('Query failed: ' . pg_last_error());
$total = $start + pg_num_rows($result) + pg_affected_rows($result1);
pg_free_result($result1);
pg_query("COMMIT TRANSACTION") or die('Query failed: ' . pg_last_error());
$url = '';
if (pg_num_rows($result) == 0)
  die('nothing found');
//$total += pg_cmdtuples($result);

printf("<center><h3>Clones (%d):</h3>", $total);
include 'list.php';
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
