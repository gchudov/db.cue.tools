<?php
set_include_path('/var/www/html/');
require 'vendor/autoload.php';
error_reporting(-1);
mb_internal_encoding("UTF-8");

$bucket = 'p.cuetools.net';

#require 'AWSSDKforPHP/aws.phar';
#require_once 'AWSSDKforPHP/sdk.class.php';
require_once 'phpctdb/ctdb.php';

$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=pgbouncer port=6432")
    or die('Could not connect: ' . pg_last_error($dbconn));
$s3 = new Aws\S3\S3Client([
    'version' => 'latest',
    'region'  => 'us-east-1'
]);
//$s3->enable_path_style();
//$s3->disable_ssl();
//$s3->adjust_offset(60*60);
while (true)
{
pg_query($dbconn, "BEGIN");
$result = pg_query($dbconn, "SELECT * FROM submissions2 WHERE hasparity = true AND s3 = false ORDER BY id LIMIT 10")
        or die('Query failed: ' . pg_last_error($dbconn));
$records = pg_fetch_all($result);
pg_free_result($result);

if (!$records || count($records) == 0) die(gmdate("M j G:i:s") . " nothing to do\n");

$ts = 0;
$promises = array(); 
foreach ($records as $record)
{
  $localname = '/var/www/html/parity/' . $record['id'];
  if (!file_exists($localname)) {
    echo 'File missing: ';
    print_r($record);
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET hasparity = false WHERE id=$1 AND NOT s3", array($record['id']));
    pg_free_result($result);
    continue;
  }
  $ts += filesize($localname);
  $promises[] = $s3->PutObjectAsync([
      'ACL' => 'public-read',
      'Bucket' => $bucket,
      'Key' => $record['id'],
      'SourceFile' => $localname
  ]);

  $result = pg_query_params($dbconn, "UPDATE submissions2 SET s3 = TRUE WHERE id=$1", array($record['id']));
  pg_free_result($result);
}
$start = microtime(true);
if ($promises) GuzzleHttp\Promise\Utils::all($promises)->then(function (array $responses) {
  foreach ($responses as $response) {
    printf("%s uploaded.\n", $response["ObjectURL"]);
#    print $response;
  }
}, function (array $responses) {
  foreach ($responses as $response) {
    print $response;
  }
  pg_query($dbconn, "ABORT");
  die("abort\n");
})->wait();
$dur = microtime(true) - $start;
if ($dur < 0.01) $dur = 0.01;
printf("%s COMMIT %d files, %d bytes in %d secs (%dKB/s)\n", gmdate("M j G:i:s"), count($records), $ts, $dur, (int)($ts/$dur/1024));
pg_query($dbconn, "COMMIT");
foreach ($records as $record)
{
  $filename = sprintf("%s%08x", str_replace('.', '+', $record['tocid']), $record['crc32']&0xffffffff);
  $localname = '/var/www/html/parity/' . $record['id'];
  unlink($localname);
}
}
