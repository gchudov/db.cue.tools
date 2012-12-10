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
$serializer = new XML_Serializer($options);

$version = isset($_POST['ctdb']) ? $_POST['ctdb'] : 1;
if ($version == 2) header('Content-type: text/xml; charset=UTF-8');

function report_success($reason) {
  global $version, $serializer;
  $response = array('status' => 'success', 'message' => $reason);
#  if (substr($_SERVER['HTTP_USER_AGENT'],0,strlen('EACv1.0b3 CTDB 2.1.4')) == 'EACv1.0b3 CTDB 2.1.4') {
#    $response['updateurl'] = 'http://s3.cuetools.net/CUETools.CTDB.EACPlugin.Installer.msi';
#    $response['updatemsg'] = 'Version 2.1.4 adds support for coverart.';
#  }
  die($version == 1 ? $reason : $serializer->serialize($response));
}

function fatal_error($reason) {
  global $version, $serializer;
  $response = array('status' => 'error', 'message' => $reason);
#  if (substr($_SERVER['HTTP_USER_AGENT'],0,strlen('EACv1.0b3 CTDB 2.1.4')) == 'EACv1.0b3 CTDB 2.1.4') {
#    $response['updateurl'] = 'http://s3.cuetools.net/CUETools.CTDB.EACPlugin.Installer.msi';
#    $response['updatemsg'] = 'Version 2.1.4 adds support for coverart.';
#  }
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

$trackoffsets = explode(' ', $toc['trackoffsets']);
if ($version == 1 && $trackoffsets[0] != 0)
  fatal_error('discs with pregaps not supported in this protocol version');

$paritysample = @$_POST['parity'];
if (!$paritysample) fatal_error('parity not specified');
$syndromesample = isset($_POST['syndrome']) ? base64_decode($_POST['syndrome']) : null;

$crc32 = $_POST['crc32'];
if (!$crc32) fatal_error('crc32 not specified');

$track_crcs_s = $_POST['trackcrcs'];
if (!$track_crcs_s) fatal_error('trackcrcs not specified');

$track_crcs = explode(' ', $track_crcs_s);
foreach($track_crcs as &$track_crc) $track_crc = phpCTDB::Hex2Int($track_crc, true);
$track_crcs_a = '{' . implode(',', $track_crcs) . '}';

$confidence = $_POST['confidence'];
if (!$confidence) fatal_error('confidence not specified');

$quality = $_POST['quality'];

$maxid = isset($_POST['maxid']) ? $_POST['maxid'] : 0;

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
$neednpar = 8;
if ($confirmid)
{ 
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1", array($confirmid))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  $oldrecord = pg_fetch_array($result)
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  pg_free_result($result);
  $oldsyn = phpCTDB::bytea_to_string(@$oldrecord['syndrome']);
  if ($oldrecord['hasparity'] != 't') $needparfile = true;
  if ($oldrecord['track_crcs'] == null) $needparfile = true;
  if ($version > 1 && $oldrecord['syndrome'] == null) $needparfile = true;
  if ($version > 1 && $oldrecord['subcount'] + 1 >= 5 && ($oldsyn == null || strlen($oldsyn) < 16)) {
    $needparfile = true;
    $neednpar = 16;
  }

  if ($version > 1) {
    if ($crc32 != $oldrecord['crc32'])
      submit_error($dbconn, $record3, "crc32 mismatch");
    $old_track_crcs = null;
    if (@$oldrecord['track_crcs'])
      phpCTDB::pg_array_parse($oldrecord['track_crcs'], $old_track_crcs);
    if ($old_track_crcs != null && $track_crcs != $old_track_crcs)
      submit_error($dbconn, $record3, "trackcrcs mismatch");
    if ($paritysample != $oldrecord['parity'])
      submit_error($dbconn, $record3, "parity mismatch");
    if ($oldsyn != null) {
      $synlen = min(strlen($syndromesample), strlen($oldsyn));
      if (substr($syndromesample, 0, $synlen) != substr($oldsyn, 0, $synlen))
        submit_error($dbconn, $record3, "syndrome mismatch");
    }
  }
}
else
{
  if ($quality < 50) submit_error($dbconn, $record3, "insufficient quality");
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1 AND trackoffsets = $2 AND subcount > 1", array($tocid, $toc['trackoffsets']))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  $different_entry_confirmed = pg_num_rows($result) > 0;
  pg_free_result($result);
  if (($quality > 95 && !$different_entry_confirmed) || $confidence > 1)
    $needparfile = true;
}

if ($parfile && !$needparfile)
  submit_error($dbconn, $record3, "parity not needed"); // parfile = false?

#error_log(print_r($_POST,true));
#error_log('v=' . $version);
#error_log('maxid=' . $maxid);
if ($version != 1) {
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1 AND trackoffsets = $2 AND id > $3", array($tocid, $toc['trackoffsets'], $maxid))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "client is not aware of recent entries"); // or confirm?
  pg_free_result($result);
}

if ($confirmid) {
  $result = pg_query_params($dbconn, "SELECT * FROM submissions WHERE entryid=$1 AND (userid=$2 OR ip=$3) AND drivename=$4", array($confirmid, $record3['userid'], $record3['ip'], $record3['drivename']))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "already submitted");
  pg_free_result($result);
} else {
  $result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1 AND crc32=$2 AND trackoffsets=$3", array($tocid, $crc32, $toc['trackoffsets']))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));
  if (pg_num_rows($result) > 0) submit_error($dbconn, $record3, "duplicate submission"); // or confirm?
  pg_free_result($result);
}

if ($record3['drivename'] != null) {
  $result = pg_query_params($dbconn, "SELECT * FROM drives ds WHERE $1 ~* ('^'|| ds.name ||'.*-')", array($record3['drivename']));
  if (pg_num_rows($result) == 0) submit_error($dbconn, $record3, "unrecognized or virtual drive");
  pg_free_result($result);
}

if (!$parfile && $needparfile)
  parity_needed($neednpar);

if ($confirmid) {
  $record3['entryid'] =  $confirmid;
  if ($parfile)
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, subcount=subcount+1, s3=false, hasparity=true, parity=$1, syndrome=decode($6,'base64'), crc32=$2, track_crcs=$3 WHERE id=$4 AND tocid=$5", array($paritysample, $crc32, $track_crcs_a, $confirmid, $tocid, $syndromesample == null ? null : base64_encode($syndromesample)));
  else
    $result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1, subcount=subcount+1 WHERE id=$1 AND tocid=$2", array($confirmid, $tocid));
  $result or fatal_error('Query failed: ' . pg_last_error($dbconn));
  if (pg_affected_rows($result) > 1) submit_error($dbconn, $record3, "not unique");
  if (pg_affected_rows($result) < 1) submit_error($dbconn, $record3, "not found");
  pg_free_result($result);
  // if ($oldrecord['hasparity'] == 't' && $parfile) schedule deletion of old parfile from s3
} else
{
  $result = pg_query("SELECT nextval('submissions2_id_seq')");
  $record_id =  pg_fetch_result($result,0,0);
  pg_free_result($result);

  $result = pg_query_params($dbconn, "INSERT INTO submissions2 (id,trackcount,audiotracks,firstaudio,trackoffsets,crc32,track_crcs,confidence,parity,syndrome,artist,title,tocid,hasparity) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, decode($10,'base64'), $11, $12, $13, $14)", array($record_id, $toc['trackcount'],$toc['audiotracks'],$toc['firstaudio'],$toc['trackoffsets'],$crc32,$track_crcs_a,$record3['confidence'],$paritysample,$syndromesample == null ? null : base64_encode($syndromesample),@$_POST['artist'],@$_POST['title'],$tocid,(int)$parfile))
    or fatal_error('Query failed: ' . pg_last_error($dbconn));

  $record3['entryid'] = $record_id;
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
