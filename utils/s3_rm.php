<?php
error_reporting(-1);
mb_internal_encoding("UTF-8");
$bucket = 'parity-cuetools-net';
require_once 'AWSSDKforPHP/sdk.class.php';

$s3 = new AmazonS3();
$s3->set_region(AmazonS3::REGION_US_E1);
$s3->disable_ssl();
//$s3->enable_path_style();
$i = 0;
do
{
  $key = trim(fgets(STDIN));
  if (!$key || $key == '') break;
  $s3->batch()->delete_object($bucket, $key);
  $i++;
  if ($i > 5) {
    $file_upload_response = $s3->batch()->send();
    if (!$file_upload_response->areOK()) die('oops');
    echo $key . "\n";
    $i = 0;
  }
} while (true);
if ($i > 0) {
    $file_upload_response = $s3->batch()->send();
    if (!$file_upload_response->areOK()) die('oops');
}

