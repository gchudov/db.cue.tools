<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$result = pg_query('SELECT * FROM submissions')
	or die('Query failed: ' . pg_last_error());

printf("<center><h3>Recent additions:</h3>");
while(true == ($record = pg_fetch_array($result)))
{
	$record2 = false;
	$fulltoc = $record['fulltoc'];
	$ids = explode(' ', $fulltoc);
	$offs = '';
	for ($tr = 4; $tr < count($ids); $tr++)	$offs = $offs . ($ids[$tr] - 150) . ' ';
	$offs = $offs . ($ids[3] - 150);

	$tocid = '';
	$pregap = $ids[3 + $ids[0]];
	for ($tr = 4 + $ids[0]; $tr < 4 + $ids[1]; $tr++)
		$tocid = sprintf('%s%08x', $tocid, $ids[$tr] - $pregap);
	$leadout = ($ids[0] == 1 && $ids[1] < $ids[2]) ? // Enhanced CD
		$ids[4 + $ids[1]] - 11400 : $ids[3];
	$tocid = sprintf('%s%08x', $tocid, $leadout - $pregap);
	$tocid = str_pad($tocid, 800, '0');
	$tocid = base64_encode(pack("H*" , sha1($tocid)));
	$tocid = str_replace('+', '.', str_replace('/', '_', str_replace('=', '-', $tocid)));

	$record2['tocid'] = $tocid;
	$record2['trackcount'] = $ids[2];
	$record2['audiotracks'] = $ids[1] - $ids[0] + 1;
	$record2['firstaudio'] = $ids[0];
	$record2['trackoffsets'] = $offs;
	$record2['crc32'] = $record['ctdbid'];
	$record2['confidence'] = $record['confidence'];
	$record2['parity'] = $record['parity'];
	$record2['parfile'] = sprintf("%s/%08x.bin", phpCTDB::discid2path($record['discid']), $record['ctdbid']);
	$record2['userid'] = $record['userid'];
	$record2['agent'] = $record['agent'];
	$record2['time'] = $record['time'];
	$record2['artist'] = $record['artist'];
	$record2['title'] = $record['title'];
	$subres = pg_insert($dbconn, 'submissions2', $record2);
}
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
