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
  //$tocidsafe = str_replace('.','+',$tocid); 
  //$target_path = sprintf("parity/%s/%s", substr($tocidsafe, 0, 1), substr($tocidsafe, 1, 1));
  $parfile = sprintf("parity/%s%08x",  str_replace('.','+', $tocid), $crc32);
} else {
  $tmpname = false;
  $parfile = false;
}

$record3 = false;
$record3['entryid'] = $sub2_id;
$record3['confidence'] = $confidence;
$record3['quality'] = $quality;
$record3['userid'] = @$_POST['userid'];
$record3['drivename'] = @$_POST['drivename'];
$record3['agent'] = $_SERVER['HTTP_USER_AGENT'];
$record3['time'] = gmdate ("Y-m-d H:i:s");
$record3['ip'] = $_SERVER["REMOTE_ADDR"];
if (isset($_POST['barcode'])) $record3['barcode'] = $_POST['barcode'];

function submit_error($dbconn, $submission, $reason) {
  $submission['reason'] = $reason;
  pg_insert($dbconn, 'failed_submissions', $submission);
  die($reason);
}

$needparfile = false;
if ($confirmid)
{ 
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array($confirmid))
    or die('Query failed: ' . pg_last_error());
  $oldrecord = pg_fetch_array($result)
    or die('Query failed: ' . pg_last_error());
  pg_free_result($result);
  if ($oldrecord['hasparity'] != 't') $needparfile = true;
  @$oldrecord['trackcrcs'] or $needparfile = true;
}
else
{
  if ($quality < 50) submit_error($dbconn, $record3, "insufficient quality");
  if ($quality > 95 || $confidence > 1) 
    $needparfile = true;
}

if ($parfile && !$needparfile)
  submit_error($dbconn, $record3, "parity not needed"); // parfile = false?
if (!$parfile && $needparfile)
  die ('parity needed');

if ($confirmid) {
  $result = pg_query_params($dbconn, "SELECT * FROM submissions WHERE entryid=$1 AND userid=$2 AND drivename=$3", array($sub2_id, $record3['userid'], $record3['drivename']))
    or die('Query failed: ' . pg_last_error());
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "already submitted");
  pg_free_result($result);

  if ($record3['drivename'] != null) {
    $result = pg_query_params($dbconn, "SELECT * FROM drives ds WHERE $1 ~* ('^'|| ds.name ||'.*-')", array($record3['drivename']));
    if (pg_num_rows($result) == 0) submit_error($dbconn, $record3, "unrecognized or virtual drive");
    pg_free_result($result);
  }

  if ($parfile)
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, s3=false, hasparity=true, parity=$1, crc32=$2, trackcrcs=$3 WHERE id=$4 AND tocid=$5", array($paritysample, $crc32, $trackcrcs, $sub2_id, $tocid));
  else
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1 WHERE id=$1 AND tocid=$2", array($sub2_id, $tocid));
  $result or die('Query failed: ' . pg_last_error());
  if (pg_affected_rows($result) > 1) submit_error($dbconn, $record3, "not unique");
  if (pg_affected_rows($result) < 1) submit_error($dbconn, $record3, "not found");
  pg_free_result($result);
  // if ($oldrecord['hasparity'] == 't' && $parfile) schedule deletion of old parfile from s3
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
  if ($parfile) $record['hasparity'] = true;

  if (phpCTDB::toc2tocid($record) != $tocid) { 
    $record3['reason'] = "tocid mismatch";
    pg_insert($dbconn, 'failed_submissions', $record3);
    die($record3['reason']);
  }

  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1 AND crc32=$2", array($tocid, $crc32))
    or die('Query failed: ' . pg_last_error());
  $rescount = pg_num_rows($result);
  pg_free_result($result);
  if ($rescount > 0) submit_error($dbconn, $record3, "duplicate entry"); // or confirm?

  pg_insert($dbconn, 'submissions2', $record)
    or die('Query failed');
}

pg_insert($dbconn, 'submissions', $record3)
  or die('Query failed');

if ($parfile)
{
  //@mkdir($target_path, 0777, true);
  move_uploaded_file($tmpname, $parfile)
    or die('error uploading file ' . $tmpname . ' to ' . $parfile);
}

if ($confirmid)
  printf("%s has been confirmed", $tocid);
else if ($parfile)
  printf("%s has been uploaded", $tocid);
else
  printf("%s has been submitted", $tocid);
?>
