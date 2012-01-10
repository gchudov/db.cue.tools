<?php
require_once 'phpctdb/ctdb.php';
require_once 'XML/Serializer.php';

$toc_s = $_GET['toc'] or die('Invalid arguments');
$dombz = $dombzfuzzy = $dofreedb = $dofreedbfuzzy = $dodiscogs = $dodiscogsfuzzy = 0;
if(isset($_GET['metadata']))
{
  if ($_GET['metadata'] == 'fast') {
    $dombz = $dodiscogs = 1;
    $dofreedb = 2;
  }
  if ($_GET['metadata'] == 'default') {
    $dombz = $dodiscogs = 1;
    $dombzfuzzy = $dodiscogsfuzzy = $dofreedb = 2;
    $dofreedbfuzzy = 3;
  }
  if ($_GET['metadata'] == 'extensive') {
    $dombz = $dodiscogs = $dombzfuzzy = $dodiscogsfuzzy = $dofreedbfuzzy = 1;
  }
}
if (isset($_GET['musicbrainz'])) $dombz = $_GET['musicbrainz'];
if ($dombz == 1 && $dombzfuzzy == 0) $dombzfuzzy = 2;
if (isset($_GET['musicbrainzfuzzy'])) $dombzfuzzy = $_GET['musicbrainzfuzzy'];
if (isset($_GET['freedb'])) $dofreedb = $_GET['freedb'];
if (isset($_GET['freedbfuzzy'])) $dofreedbfuzzy = $_GET['freedbfuzzy'];
if (isset($_GET['discogs'])) $dodiscogs = $_GET['discogs'];
if (isset($_GET['discogsfuzzy'])) $dodiscogsfuzzy = $_GET['discogsfuzzy'];

$ctdbversion = isset($_GET['ctdb']) ? (int)$_GET['ctdb'] : 1;
$type = isset($_GET['type']) ? $_GET['type'] : 'xml';
$fuzzy = @$_GET['fuzzy'];
$toc = phpCTDB::toc_s2toc($toc_s);
$records = array();

if ($ctdbversion > 0)
{
  $dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543") or die('Could not connect: ' . pg_last_error());
  $tocid = phpCTDB::toc2tocid($toc); 
  $query = "SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($tocid) . "'";
  if (!$fuzzy) $query = $query . " AND trackoffsets='" . pg_escape_string($toc['trackoffsets']) . "'";
  $query = $query . " ORDER BY id";
  $result = pg_query($dbconn, $query) 
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
}

$tocs = array($toc_s);
if ($records && $fuzzy)
  foreach($records as $record)
    $tocs[] = phpCTDB::toc_toc2s($record);

$mbmetas = array();
for ($priority=1; $priority <= 7; $priority++)
{
  if ($dombzfuzzy == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::mbzlookup($tocs, true)); 
  if ($dombz == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::mbzlookup($tocs)); 
  if ($dodiscogsfuzzy == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(null, $toc_s));
  if ($dofreedbfuzzy == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 150)); 
  else if ($dofreedb == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 0)); 
  if ($mbmetas) break;
}
if ($dodiscogs != 0)
  $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(phpCTDB::discogsids($mbmetas))); 

if ($type == 'json')
{
  $body = phpCTDB::musicbrainz2json($mbmetas);
  header('Content-type: application/json; charset=UTF-8');
}
else if ($type == 'xml')
{
  if (!$records && !$mbmetas)
  {
    ob_clean();
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
    header("Status: 404 Not Found");
    die('Not Found'); 
  }

  $xmlentry = null;
  if ($records)
  foreach($records as &$record)
  {
    $parityurl = null;
    if ($record['syndrome'] != null) $record['syndrome'] = stripcslashes($record['syndrome']);
    if ($record['hasparity'] == 't') {
      if ($record['syndrome'] != null && $ctdbversion == 1)
        $parityurl = $record['s3'] == 't' ? "/tov1.php?id=" . $record['id'] : null;
      else if ($record['syndrome'] == null && $ctdbversion == 2)
        $parityurl = $record['s3'] == 't' ? "/tov2.php?id=" . $record['id'] : null;
      else
        $parityurl = sprintf("%s/%d", $record['s3'] == 't' ? "http://p.cuetools.net" : "/parity", $record['id']);
    }
    $xmlentry[] = array(
      'id' => $record['id'],
      'crc32' => sprintf("%08x", $record['crc32']),
      'confidence' => $ctdbversion == 1 ? $record['confidence'] : $record['subcount'], 
      'npar' => $record['syndrome'] == null ? 8 : strlen($record['syndrome'])/2, 
      'stride' => 5880,
      'hasparity' => $parityurl,
      'parity' => $record['syndrome'] == null || $ctdbversion == 1 ? $record['parity'] : null,
      'syndrome' => $record['syndrome'] == null || $ctdbversion == 1 ? null : base64_encode($record['syndrome']),
      'trackcrcs' => $ctdbversion == 1 ? null : $record['trackcrcs'],
      'toc' => phpCTDB::toc_toc2s($record)
    );
  }
  $xmlmbmeta = null; 
  foreach ($mbmetas as $mbmeta)
  {
    $tracks = array();
    foreach ($mbmeta['tracklist'] as $track) {
      if ($track['artist'] == $mbmeta['artistname'])
        $track['artist'] = null;
      $tracks[] = $track;
    }
    $xmlmbmeta[] = array(
      'source' => $mbmeta['source'],
      'id' => $mbmeta['id'],
      'artist' => $mbmeta['artistname'],
      'album' => $mbmeta['albumname'],
      'year' => $mbmeta['first_release_date_year'], 
      'releasedate' => $mbmeta['releasedate'], 
      'country' => $mbmeta['country'], 
      'discnumber' => $mbmeta['discnumber'], 
      'disccount' => $mbmeta['totaldiscs'], 
      'discname' => $mbmeta['discname'], 
      'coverarturl' => $mbmeta['coverarturl'], 
      'infourl' => $mbmeta['info_url'], 
      'barcode' => $mbmeta['barcode'],
      'track' => $tracks,
      'label' => @$mbmeta['label'],
      'discogs_id' => @$mbmeta['discogs_id'],
      'group_id' => @$mbmeta['group_id'],
      'genre' => @$mbmeta['genre'],
      'extra' => @$mbmeta['extra'],
      'relevance' => $mbmeta['relevance'],
    );
  }
  $ctdbdata = array('entry' => $xmlentry, 'musicbrainz' => $xmlmbmeta);
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
  $body = $serializer->serialize($ctdbdata);
  header('Content-type: text/xml; charset=UTF-8');
}
else
{
  die('Invalid type');
}

$etag = md5($body);
header("Cache-Control: max-age=10");
header("ETag:  " . $etag);
if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
  header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
  exit;
}
die($body);
