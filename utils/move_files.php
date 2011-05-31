<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$result = pg_query("SELECT * FROM submissions2 where parfile ilike 'parity/%' limit 10000")
	or die('Query failed: ' . pg_last_error());
$i=0;
printf("<center><h3>Converting:</h3>");
while(true == ($record = pg_fetch_array($result)))
{
  $crc32 = $record['crc32'];
  $tocid = phpCTDB::toc2tocid($record);
  if ($tocid != $record['tocid']) die('tocid mismatch');
  $tocidsafe = str_replace('.','+',$tocid);
  $target_path = sprintf("parity2/%s/%s", substr($tocidsafe, 0, 1), substr($tocidsafe, 1, 1));
  $parfile = sprintf("%s/%s.%08x.bin", $target_path, substr($tocidsafe, 2), $crc32);
  if (!@file_exists($parfile))
  {
//  printf("move %s %s<br>\n", $record['parfile'], $parfile);
    @mkdir($target_path, 0777, true);
    rename($record['parfile'], $parfile);
  }
  $result2 = pg_query_params($dbconn, "UPDATE submissions2 SET parfile=$1 WHERE tocid=$2 AND parfile=$3", array($parfile,  $tocid, $record['parfile']));
  pg_free_result($result2);
  $i++;
}
pg_free_result($result);
printf("Done %d", $i);
printf("</center>");
?>
</body>
</html>
