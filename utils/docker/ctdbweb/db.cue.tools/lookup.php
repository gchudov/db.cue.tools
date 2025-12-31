<?php
$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=pgbouncer port=6432")
	or die('Could not connect: ' . pg_last_error());
$tocid = @$_GET['tocid']
	or die('No id');
$result = pg_query("SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($dbconn, $tocid) . "';")
	or die('Query failed: ' . pg_last_error());
$record = pg_fetch_array($result);
if (!$record)
{
	ob_clean();
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
  header("Status: 404 Not Found");
	die('Not Found'); 
}

header('Content-type: text/xml; charset=UTF-8');
printf('<?xml version="1.0" encoding="UTF-8"?>');
printf('<ctdb xmlns="http://db.cuetools.net/ns/mmd-1.0#" xmlns:ext="http://db.cuetools.net/ns/ext-1.0#">');
while($record)
{
	printf('<entry id="%d" crc32="%08x" confidence="%d" npar="8" stride="5880" hasparity="%s">', $record['id'], $record['crc32']&0xffffffff, $record['subcount'], $record['hasparity'] == 't' ? "1" : "0" );
		printf('<parity>%s</parity>', $record['parity']);
		printf('<toc trackcount="%d" audiotracks="%d" firstaudio="%d">%s</toc>', 
			$record['trackcount'], $record['audiotracks'], $record['firstaudio'], $record['trackoffsets']);
	printf('</entry>');
	$record = pg_fetch_array($result);
}
pg_free_result($result);
printf("</ctdb>");
?>
