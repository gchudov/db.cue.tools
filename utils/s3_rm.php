<?php
error_reporting(-1);
mb_internal_encoding("UTF-8");
$bucket = 'parity-cuetools-net';
require_once 'AWSSDKforPHP/sdk.class.php';

$s3 = new AmazonS3();
$queue = new $s3->batch_class(10);
$s3->set_region(AmazonS3::REGION_US_E1);
$s3->disable_ssl();
//$s3->enable_path_style();
do
{
  $key = trim(fgets(STDIN));
  if (!$key || $key == '') break;
  $s3->batch($queue)->delete_object($bucket, $key);
} while (true);
$file_upload_response = $s3->batch($queue)->send();
if (!$file_upload_response->areOK()) die('oops');

