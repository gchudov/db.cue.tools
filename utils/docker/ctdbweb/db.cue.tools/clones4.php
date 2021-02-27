<?php
include 'logo_start1.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 50;
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
pg_query("BEGIN TRANSACTION; DECLARE curs CURSOR FOR " . 
"select a.* from submissions2 a, submissions2 b where substring(a.trackoffsets from '(.*) ([^ ]*)$') = substring(b.trackoffsets from '(.*) ([^ ]*)$') AND (int4(substring(a.trackoffsets from ' ([^ ]*)$')) = int4(substring(b.trackoffsets from ' ([^ ]*)$')) + 150 OR int4(substring(b.trackoffsets from ' ([^ ]*)$')) = int4(substring(a.trackoffsets from ' ([^ ]*)$')) + 150) ORDER BY trackoffsets;") or die('Query failed: ' . pg_last_error());
$result = pg_query("MOVE FORWARD " . pg_escape_string($start) . " IN curs") or die('Query failed: ' . pg_last_error());
pg_free_result($result);

$json_entries = phpCTDB::query2json($dbconn, " FETCH " . pg_escape_string($count) . " FROM curs");
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

$result = pg_query("MOVE FORWARD ALL IN curs") or die('Query failed: ' . pg_last_error());
$total = $start + $count /* count(json) */ + pg_affected_rows($result);
pg_free_result($result);
pg_query("COMMIT TRANSACTION") or die('Query failed: ' . pg_last_error());
$url = '';

include 'list1.php';
include 'logo_start2.php';
printf("<center><h3>Clones (%d):</h3>", $total);
printf("<div id='entries_div'></div>\n");
printf("</center>");
?>
</body>
</html>
