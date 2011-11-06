<?php
require_once( 'phpctdb/ctdb.php' );
require_once 'XML/Serializer.php';

//if ($_SERVER['HTTP_USER_AGENT'] != "CUETools 205")
//  fatal_error("user agent " . $_SERVER['HTTP_USER_AGENT'] . " is not allowed");

$options = array(
  XML_SERIALIZER_OPTION_INDENT        => '  ',
  XML_SERIALIZER_OPTION_RETURN_RESULT => true,
  XML_SERIALIZER_OPTION_SCALAR_AS_ATTRIBUTES => true,
  XML_SERIALIZER_OPTION_MODE          => XML_SERIALIZER_MODE_SIMPLEXML,
  XML_SERIALIZER_OPTION_IGNORE_NULL   => true,
  XML_SERIALIZER_OPTION_ROOT_NAME     => 'ctdb',
  XML_SERIALIZER_OPTION_ROOT_ATTRIBS  => array('xmlns'=>"http://db.cuetools.net/ns/mmd-1.0#", 'xmlns:ext'=>"http://db.cuetools.net/ns/ext-1.0#"),
  XML_SERIALIZER_OPTION_XML_ENCODING  => 'UTF-8'
  );
$serializer = &new XML_Serializer($options);

$version = isset($_POST['ctdb']) ? $_POST['ctdb'] : 2;
if ($version == 2) header('Content-type: text/xml; charset=UTF-8');

function report_success($reason) {
  global $version, $serializer;
  $response = array('status' => 'success', 'message' => $reason);
  die($version == 1 ? $reason : $serializer->serialize($response));
}

function fatal_error($reason) {
  global $version, $serializer;
  $response = array('status' => 'error', 'message' => $reason);
  die($version == 1 ? $reason : $serializer->serialize($response));
}

function parity_needed($npar) {
  global $version, $serializer;
  die($version == 1 ? 'parity needed' : $serializer->serialize(array('status' => 'parity needed', 'message' => 'parity needed', 'npar' => $npar)));
}

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
  or fatal_error('Could not connect: ' . pg_last_error());

$confirmid = @$_POST['confirmid'];

$toc_s = @$_POST['toc'];
if (!$toc_s) fatal_error('toc not specified');

$toc = phpCTDB::toc_s2toc($toc_s);
$tocid = phpCTDB::toc2tocid($toc);

$paritysample = @$_POST['parity'];
if (!$paritysample) fatal_error('parity not specified');
$syndromesample = @$_POST['syndrome'];

$crc32 = $_POST['crc32'];
if (!$crc32) fatal_error('crc32 not specified');

$trackcrcs = $_POST['trackcrcs'];
if (!$trackcrcs) fatal_error('trackcrcs not specified');

$confidence = $_POST['confidence'];
if (!$confidence) fatal_error('confidence not specified');

$quality = $_POST['quality'];

if (@$_POST['parityfile'])
{
  $file = $_FILES['parityfile'];
  if ($file['error'])
    fatal_error($file['error'] . "; upload_max_filesize " . ini_get('upload_max_filesize'));
  $tmpname = $file['tmp_name'];
  @file_exists($tmpname) or fatal_error("file doesn't exist");
  if (filesize($tmpname) == 0) fatal_error("file is empty");
  $parfile = true;
} else {
  $tmpname = false;
  $parfile = false;
}

$record3 = false;
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
  fatal_error($reason);
}

$needparfile = false;
if ($confirmid)
{ 
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array($confirmid))
    or fatal_error('Query failed: ' . pg_last_error());
  $oldrecord = pg_fetch_array($result)
    or fatal_error('Query failed: ' . pg_last_error());
  pg_free_result($result);
  if ($oldrecord['hasparity'] != 't') $needparfile = true;
  if ($oldrecord['syndrome'] == null && $syndromesample != null) $needparfile = true;
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
  parity_needed(16);

if ($confirmid) {
  $record3['entryid'] =  $confirmid;

  $result = pg_query_params($dbconn, "SELECT * FROM submissions WHERE entryid=$1 AND (userid=$2 OR ip=$3) AND drivename=$4", array($confirmid, $record3['userid'], $record3['ip'], $record3['drivename']))
    or fatal_error('Query failed: ' . pg_last_error());
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "already submitted");
  pg_free_result($result);

  if ($record3['drivename'] != null) {
    $result = pg_query_params($dbconn, "SELECT * FROM drives ds WHERE $1 ~* ('^'|| ds.name ||'.*-')", array($record3['drivename']));
    if (pg_num_rows($result) == 0) submit_error($dbconn, $record3, "unrecognized or virtual drive");
    pg_free_result($result);
  }

  if ($parfile)
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, subcount=subcount+1, s3=false, hasparity=true, parity=$1, syndrome=$6, crc32=$2, trackcrcs=$3 WHERE id=$4 AND tocid=$5", array($paritysample, $crc32, $trackcrcs, $confirmid, $tocid, $syndromesample));
  else
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, subcount=subcount+1 WHERE id=$1 AND tocid=$2", array($confirmid, $tocid));
  $result or fatal_error('Query failed: ' . pg_last_error());
  if (pg_affected_rows($result) > 1) submit_error($dbconn, $record3, "not unique");
  if (pg_affected_rows($result) < 1) submit_error($dbconn, $record3, "not found");
  pg_free_result($result);
  // if ($oldrecord['hasparity'] == 't' && $parfile) schedule deletion of old parfile from s3
} else
{
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1 AND crc32=$2 AND trackoffsets=$3", array($tocid, $crc32, $toc['trackoffsets']))
    or fatal_error('Query failed: ' . pg_last_error());
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "duplicate entry"); // or confirm?
  pg_free_result($result);

  $record = array();
  $record['trackcount'] = $toc['trackcount'];
  $record['audiotracks'] = $toc['audiotracks'];
  $record['firstaudio'] = $toc['firstaudio'];
  $record['trackoffsets'] = $toc['trackoffsets'];
  $record['crc32'] = $crc32;
  $record['trackcrcs'] = $trackcrcs;
  $record['confidence'] = $record3['confidence'];
  $record['parity'] = $paritysample;
  $record['syndrome'] = $syndromesample;
  $record['artist'] = @$_POST['artist'];
  $record['title'] = @$_POST['title'];
  $record['tocid'] = $tocid;
  if ($parfile) $record['hasparity'] = true;

  if (phpCTDB::toc2tocid($record) != $tocid) submit_error($dbconn, $record3, "tocid mismatch");

  $result = pg_query("SELECT nextval('submissions2_id_seq')");
  $record['id'] =  pg_fetch_result($result,0,0);
  pg_free_result($result);

  pg_insert($dbconn, 'submissions2', $record)
    or fatal_error('Query failed');

  $record3['entryid'] = $record['id'];
}

pg_insert($dbconn, 'submissions', $record3)
  or fatal_error('Query failed');

if ($parfile)
{
  //@mkdir($target_path, 0777, true);
  $parfilename = 'parity/' . $record3['entryid'];
  move_uploaded_file($tmpname, $parfilename)
    or fatal_error('error uploading file ' . $tmpname . ' to ' . $parfilename);
}

if ($confirmid)
  report_success(sprintf("%s has been confirmed", $tocid));
else if ($parfile)
  report_success(sprintf("%s has been uploaded", $tocid));
else
  report_success(sprintf("%s has been submitted", $tocid));
?>
