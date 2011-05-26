<?php
include 'logo_start1.php'; 
require_once( 'phpctdb/ctdb.php' );
$count = 20;
$query = "SELECT * FROM submissions2 WHERE artist IS NULL OR artist='' OR title IS NULL";
$url = '';
$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY confidence DESC OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);
$json_entries = phpCTDB::query2json($dbconn, $query);
if (@$_GET['json']) die($json_entries);
if ($json_entries == '') die('nothing found');
include 'list1.php';
include 'logo_start2.php';
?>
<center><h3>Untitled discs:</h3>
<div id='entries_div'></div>
<br>
<div id='musicbrainz_div'></div>
</center>
</body>
</html>
