<?php
error_reporting(-1);
mb_internal_encoding("UTF-8");
require_once 'AWSSDKforPHP/sdk.class.php';

$s3 = new AmazonS3();
$s3->set_region(AmazonS3::REGION_US_E1);
//$s3->enable_path_style();
$s3->disable_ssl();
$bucket = $argv && count($argv) > 1 ? $argv[1] : 'p.cuetools.net';
$marker = $argv && count($argv) > 2 ? $argv[2] : null;
$objs = $s3->list_objects($bucket, array('marker' => $marker, 'max-keys' => 500));
while(!empty($objs->body->Contents))
{
  foreach($objs->body->Contents as $obj)
  {
    $marker = (string) $obj->Key;
//    echo $marker . " " . $obj->Size .  "\n";
  }
  print_r($objs);
  echo $objs->body->NextKeyMarker . "\n";
  $objs = $s3->list_objects($bucket, array('marker' => $marker, 'max-keys' => 500));
}

