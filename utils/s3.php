<?php
error_reporting(-1);
mb_internal_encoding("UTF-8");

$bucket = 'parity-cuetools-net';

require_once 'AWSSDKforPHP/sdk.class.php';
require_once '../phpctdb/ctdb.php';

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
    or die('Could not connect: ' . pg_last_error());
$s3 = new AmazonS3();

while (true)
{
pg_query("BEGIN");
$result = pg_query("SELECT * FROM submissions2 where parfile IS NOT NULL AND NOT s3 LIMIT 100")
        or die('Query failed: ' . pg_last_error());
$records = pg_fetch_all($result);
pg_free_result($result);

if (!$records || count($records) == 0) die("nothing to do\n");

foreach ($records as $record)
{
  $crc32 = $record['crc32'];
  $tocid = $record['tocid'];
  $tocidsafe = str_replace('.','+',$tocid);
  $filename = sprintf("%s%08x", $tocidsafe, $crc32);
  $localname = '/var/www/ctdbweb/' . $record['parfile'];
  $s3->batch()->create_object($bucket, $filename, array(
	'fileUpload' => $localname,
	'acl' => AmazonS3::ACL_PUBLIC
  ));
  echo $localname . ' => ' . $filename . "\n";

  $result = pg_query_params($dbconn, "UPDATE submissions2 SET s3 = TRUE WHERE id=$1", array($record['id']));
  pg_free_result($result);
}
$file_upload_response = $s3->batch()->send();

if (!$file_upload_response->areOK())
{
  pg_query("ABORT");
  die("abort\n");
}

pg_query("COMMIT");
}
