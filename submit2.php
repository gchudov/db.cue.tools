<?php
require_once( 'phpctdb/ctdb.php' );

//if ($_SERVER['HTTP_USER_AGENT'] != "CUETools 205")
//  die ("user agent " . $_SERVER['HTTP_USER_AGENT'] . " is not allowed");

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
    or die('Could not connect: ' . pg_last_error());

$confirmid = @$_POST['confirmid'];
if (!$confirmid)
{
  $result= pg_query("SELECT nextval('submissions2_id_seq')");
  $sub2_id = pg_fetch_result($result,0,0);
  pg_free_result($result);
} else
  $sub2_id = $confirmid;

$toc_s = @$_POST['toc'];
if (!$toc_s) die('toc not specified');

$toc = phpCTDB::toc_s2toc($toc_s);
$tocid = phpCTDB::toc2tocid($toc);

$paritysample = @$_POST['parity'];
if (!$paritysample) die('parity not specified');

$crc32 = $_POST['crc32'];
if (!$crc32) die('crc32 not specified');

$trackcrcs = $_POST['trackcrcs'];
if (!$trackcrcs) die('trackcrcs not specified');

$confidence = $_POST['confidence'];
if (!$confidence) die('confidence not specified');

$quality = $_POST['quality'];

if (@$_POST['parityfile'])
{
  $file = $_FILES['parityfile'];
  if ($file['error'])
    die("Error " . $file['error'] . "; upload_max_filesize " . ini_get('upload_max_filesize'));
  $tmpname = $file['tmp_name'];
  @file_exists($tmpname) or die("file doesn't exist");
  if (filesize($tmpname) == 0) die("file is empty");
  $tocidsafe = str_replace('.','+',$tocid); 
  $target_path = sprintf("parity/%s/%s", substr($tocidsafe, 0, 1), substr($tocidsafe, 1, 1));
  $parfile = sprintf("%s/%s.%08x.bin", $target_path, substr($tocidsafe, 2), $crc32);
} else {
  $tmpname = false;
  $parfile = false;
}

$needparfile = false;
if ($confirmid)
{ 
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array($confirmid))
    or die('Query failed: ' . pg_last_error());
  $oldrecord = pg_fetch_array($result)
    or die('Query failed: ' . pg_last_error());
  pg_free_result($result);
  $oldparfile = @$oldrecord['parfile'];
  if (!$oldparfile || !@file_exists($oldparfile)) $needparfile = true;
  @$oldrecord['trackcrcs'] or $needparfile = true;
}
else
{
  if ($quality < 50)
    die('insufficient quality');
//  if ($confidence > 1) 
    $needparfile = true;
}

if ($parfile && !$needparfile)
  die ('parity not needed'); // parfile = false?
if (!$parfile && $needparfile)
  die ('parity needed');

$record3 = false;
$record3['entryid'] = $sub2_id;
$record3['confidence'] = $confidence;
$record3['userid'] = @$_POST['userid'];
$record3['drivename'] = @$_POST['drivename'];
$record3['agent'] = $_SERVER['HTTP_USER_AGENT'];
$record3['time'] = gmdate ("Y-m-d H:i:s");
$record3['ip'] = $_SERVER["REMOTE_ADDR"];
if (isset($_POST['barcode'])) $record3['barcode'] = $_POST['barcode'];

if ($confirmid) {
  if ($parfile)
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, parfile=$1, parity=$2, crc32=$3, trackcrcs=$4 WHERE id=$5 AND tocid=$6", array($parfile, $paritysample, $crc32, $trackcrcs, $sub2_id, $tocid));
  else
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1 WHERE id=$1 AND tocid=$2", array($sub2_id, $tocid));
  $result or die('Query failed: ' . pg_last_error());
  if (pg_affected_rows($result) < 1) die('not found');
  if (pg_affected_rows($result) > 1) die('not unique');
  pg_free_result($result);
  if ($oldparfile && $parfile) unlink($oldparfile);
} else
{
  $record = false;
  $record['id'] = $sub2_id;
  $record['trackcount'] = $toc['trackcount'];
  $record['audiotracks'] = $toc['audiotracks'];
  $record['firstaudio'] = $toc['firstaudio'];
  $record['trackoffsets'] = $toc['trackoffsets'];
  $record['crc32'] = $crc32;
  $record['trackcrcs'] = $trackcrcs;
  $record['confidence'] = $record3['confidence'];
  $record['parity'] = $paritysample;
  $record['artist'] = @$_POST['artist'];
  $record['title'] = @$_POST['title'];
  $record['tocid'] = $tocid;
  if ($parfile)
    $record['parfile'] = $parfile;

  if (phpCTDB::toc2tocid($record) != $tocid) die('tocid mismatch');

  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1", array($tocid))
    or die('Query failed: ' . pg_last_error());
  $rescount = pg_num_rows($result);
  while (TRUE == ($record2 = pg_fetch_array($result)))
    if ($record2['crc32'] == $crc32) {
	// TODO: conirm
	die("Duplicate entry");
  }
  pg_free_result($result);

  pg_insert($dbconn, 'submissions2', $record)
    or die('Query failed');
}

pg_insert($dbconn, 'submissions', $record3)
  or die('Query failed');

if ($parfile)
{
  @mkdir($target_path, 0777, true);
  move_uploaded_file($tmpname, $parfile)
    or die('error uploading file ' . $tmpname . ' to ' . $parfile);
}

if ($confirmid)
  printf("%s has been confirmed", $tocid);
else if ($rescount > 1)
  printf("%s has been updated", $tocid);
else
  printf("%s has been uploaded", $tocid);
?>
