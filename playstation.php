<?php
include 'logo_start1.php'; 
require_once( 'phpctdb/ctdb.php' );

$count = 20;
$query = 'SELECT * FROM submissions2';
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . ' WHERE firstaudio > 1' .  " ORDER BY confidence DESC OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);

$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');

include 'list1.php';
include 'logo_start2.php';
printf("<center><h3>CUETools Database: playstation type discs</h3>");
printf("<div id='entries_div'></div>\n");
printf("<br>\n");
printf("<div id='musicbrainz_div'></div>\n");
printf("</center>");
?>
</body>
</html>
