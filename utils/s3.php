<?php
error_reporting(-1);
mb_internal_encoding("UTF-8");

$bucket = 'p.cuetools.net';

require_once 'AWSSDKforPHP/sdk.class.php';
require_once '../phpctdb/ctdb.php';

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
    or die('Could not connect: ' . pg_last_error());
$s3 = new AmazonS3();
$s3->set_region(AmazonS3::REGION_US_E1);
//$s3->enable_path_style();
$s3->disable_ssl();
$s3->adjust_offset(60*60);
while (true)
{
pg_query("BEGIN");
$result = pg_query("SELECT * FROM submissions2 where parfile IS NOT NULL AND NOT s3 LIMIT 10")
        or die('Query failed: ' . pg_last_error());
$records = pg_fetch_all($result);
pg_free_result($result);

if (!$records || count($records) == 0) die("nothing to do\n");

$ts = 0;
foreach ($records as $record)
{
  $localname = '/var/www/ctdbweb/' . $record['parfile'];
  if (!file_exists($localname)) {
    echo 'File missing: ';
    print_r($record);
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET parfile = NULL WHERE id=$1", array($record['id']));
    pg_free_result($result);
    continue;
  }
  $ts += filesize($localname);
  $crc32 = $record['crc32'];
  $tocid = $record['tocid'];
  $tocidsafe = str_replace('.','+',$tocid);
  $filename = sprintf("%s%08x", $tocidsafe, $crc32);
  $s3->batch()->create_object($bucket, $filename, array(
	'fileUpload' => $localname,
	'acl' => AmazonS3::ACL_PUBLIC
  ));
  //echo $localname . ' => ' . $filename . "\n";

  $result = pg_query_params($dbconn, "UPDATE submissions2 SET s3 = TRUE WHERE id=$1", array($record['id']));
  pg_free_result($result);
}
$start = microtime(true);
$file_upload_response = $s3->batch()->send();
if (!$file_upload_response->areOK())
{
  pg_query("ABORT");
  die("abort\n");
}
$dur = microtime(true) - $start;
if ($dur < 0.01) $dur = 0.01;
printf("COMMIT %d files, %d bytes in %d secs (%dKB/s)\n", count($records), $ts, $dur, (int)($ts/$dur/1024));
pg_query("COMMIT");
}
