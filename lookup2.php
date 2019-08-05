<?php
require_once 'phpctdb/ctdb.php';
require_once 'XML/Serializer.php';

$toc_s = $_GET['toc'] or die('Invalid arguments');
$dombz = $dombzfuzzy = $dombzraw = $dofreedb = $dofreedbfuzzy = $dodiscogs = $dodiscogsfuzzy = 0;
if(isset($_GET['metadata']))
{
  if ($_GET['metadata'] == 'fast') {
    $dombz = $dodiscogs = 1;
#    $dombzraw = 2;
    $dofreedb = 3;
  }
  if ($_GET['metadata'] == 'default') {
    $dombz = $dodiscogs = 1;
    $dombzfuzzy = $dodiscogsfuzzy = $dofreedb = 2;
    $dombzraw = 2;
    $dofreedbfuzzy = 4;
  }
  if ($_GET['metadata'] == 'extensive') {
    $dombz = $dodiscogs = $dombzfuzzy = $dodiscogsfuzzy = $dofreedbfuzzy = 1;
    $dombzraw = 1;
  }
}
if (isset($_GET['musicbrainz'])) $dombz = $_GET['musicbrainz'];
if ($dombz == 1 && $dombzfuzzy == 0) $dombzfuzzy = 2;
if (isset($_GET['musicbrainzfuzzy'])) $dombzfuzzy = $_GET['musicbrainzfuzzy'];
if (isset($_GET['musicbrainzraw'])) $dombzraw = $_GET['musicbrainzraw'];
if (isset($_GET['freedb'])) $dofreedb = $_GET['freedb'];
if (isset($_GET['freedbfuzzy'])) $dofreedbfuzzy = $_GET['freedbfuzzy'];
if (isset($_GET['discogs'])) $dodiscogs = $_GET['discogs'];
if (isset($_GET['discogsfuzzy'])) $dodiscogsfuzzy = $_GET['discogsfuzzy'];

$doctdb = isset($_GET['ctdb']) ? (int)$_GET['ctdb'] : 1;
$ctdbversion = isset($_GET['version']) ? (int)$_GET['version'] : 1;
$type = isset($_GET['type']) ? $_GET['type'] : 'xml';
$fuzzy = @$_GET['fuzzy'];
$toc = phpCTDB::toc_s2toc($toc_s);
$records = array();

if ($doctdb > 0)
{
  $dbconn = pg_connect("dbname=ctdb user=ctdb_user host=localhost port=6544") or die('Could not connect: ' . pg_last_error());
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
$ids_musicbrainz = array();
$ids_musicbrainz_raw = array();
for ($priority=1; $priority <= 7; $priority++)
{
  if ($dombz == $priority)
    $ids_musicbrainz = array_merge($ids_musicbrainz, phpCTDB::mbzlookupids($tocs, false)); 
  if ($dombzfuzzy == $priority)
    $ids_musicbrainz = array_merge($ids_musicbrainz, phpCTDB::mbzlookupids($tocs, true)); 
  if ($dombzraw == $priority)
    $ids_musicbrainz_raw = array_merge($ids_musicbrainz_raw, phpCTDB::mbzrawlookupids($tocs, false)); 
  if ($dodiscogsfuzzy == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(null, $toc_s));
  if ($dofreedbfuzzy == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 150)); 
  else if ($dofreedb == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 0)); 
  if ($mbmetas || $ids_musicbrainz) break;
}
if ($ids_musicbrainz) 
  $mbmetas = array_merge($mbmetas, phpCTDB::mbzlookup($ids_musicbrainz)); 
if ($ids_musicbrainz_raw) 
  $mbmetas = array_merge($mbmetas, phpCTDB::mbzrawlookup($ids_musicbrainz_raw));
if ($dodiscogs != 0)
  $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(phpCTDB::discogsids($mbmetas)));
usort($mbmetas, 'phpCTDB::metadataOrder');

if (isset($_GET['jsonp']))
{
  $body = $_GET['jsonp'] . '(' . phpCTDB::musicbrainz2json($mbmetas) . ')';
  header('Content-type: text/javascript; charset=UTF-8');
}
else if ($type == 'json')
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
    $record['syndrome'] = phpCTDB::bytea_to_string($record['syndrome']);
    if ($record['hasparity'] == 't') {
      if ($record['syndrome'] != null && $ctdbversion == 1)
        $parityurl = $record['s3'] == 't' ? "/tov1.php?id=" . $record['id'] : null;
      else if ($record['syndrome'] == null && $ctdbversion > 1)
        $parityurl = $record['s3'] == 't' ? "/tov2.php?id=" . $record['id'] : null;
      else
        $parityurl = sprintf("%s/%d", $record['s3'] == 't' ? "http://p.cuetools.net" : "/parity", $record['id']);
    }
    $track_crcs_s = null;
    if ($record['track_crcs'] != null && $ctdbversion > 1) {
      $track_crcs = null;
      phpCTDB::pg_array_parse($record['track_crcs'], $track_crcs);
      foreach($track_crcs as &$track_crc) $track_crc = sprintf("%08x", $track_crc & 0xffffffff);
      $track_crcs_s = implode(' ', $track_crcs);
    }
    $xmlentry[] = array(
      'id' => $record['id'],
      'crc32' => sprintf("%08x", $record['crc32'] & 0xffffffff),
      'confidence' => $record['subcount'], 
      'npar' => $record['syndrome'] == null || $ctdbversion == 1 ? 8 : strlen($record['syndrome'])/2, 
      'stride' => 5880,
      'hasparity' => $parityurl,
      'parity' => $record['syndrome'] == null || $ctdbversion == 1 ? $record['parity'] : null,
      'syndrome' => $record['syndrome'] == null || $ctdbversion == 1 ? null : base64_encode($record['syndrome']),
      'trackcrcs' => $track_crcs_s,
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
      'releasedate' => ($ctdbversion > 2 || !$mbmeta['release']) ? null : $mbmeta['release'][0]['date'], 
      'country' => ($ctdbversion > 2 || !$mbmeta['release']) ? null : $mbmeta['release'][0]['country'], 
      'discnumber' => $mbmeta['discnumber'], 
      'disccount' => $mbmeta['totaldiscs'], 
      'discname' => $mbmeta['discname'], 
      'infourl' => $mbmeta['info_url'], 
      'barcode' => $mbmeta['barcode'],
      'discogs_id' => @$mbmeta['discogs_id'],
      'group_id' => @$mbmeta['group_id'],
      'genre' => @$mbmeta['genre'],
      'extra' => @$mbmeta['extra'],
      'relevance' => $mbmeta['relevance'],
      'track' => $tracks,
      'label' => @$mbmeta['label'],
      'release' => $ctdbversion <= 2 ? null : @$mbmeta['release'], 
      'coverart' => @$mbmeta['coverart'], 
    );
  }
  if ($ctdbversion > 1)
    $ctdbdata = array('entry' => $xmlentry, 'metadata' => $xmlmbmeta);
  else
    $ctdbdata = array('entry' => $xmlentry, 'musicbrainz' => $xmlmbmeta);
  $options = array(
    XML_SERIALIZER_OPTION_INDENT        => ' ',
    XML_SERIALIZER_OPTION_RETURN_RESULT => true,
    XML_SERIALIZER_OPTION_SCALAR_AS_ATTRIBUTES => $ctdbversion > 1 ?
      array(
        "entry" => array("id", "crc32", "confidence", "npar", "stride", "hasparity", "parity", "syndrome", "trackcrcs", "toc"),
        "metadata" => array("source", "id", "artist", "album", "year", "releasedate", "country", "discnumber", "disccount", "discname", "infourl", "barcode", "discogs_id", "genre", "relevance"),
        "track" => array("name", "artist"),
        "label" => array("name", "catno"),
        "release" => array("country", "date"),
        "coverart" => array("uri", "uri150", "width", "height", "primary"),
      ) : true,
    XML_SERIALIZER_OPTION_MODE          => XML_SERIALIZER_MODE_SIMPLEXML,
#    XML_SERIALIZER_OPTION_ENTITIES      => XML_SERIALIZER_ENTITIES_NONE,
    XML_SERIALIZER_OPTION_ENTITIES      => XML_SERIALIZER_ENTITIES_XML,
    XML_SERIALIZER_OPTION_IGNORE_NULL   => true,
    XML_SERIALIZER_OPTION_ROOT_NAME     => 'ctdb',
    XML_SERIALIZER_OPTION_ROOT_ATTRIBS  => array('xmlns'=>"http://db.cuetools.net/ns/mmd-1.0#", 'xmlns:ext'=>"http://db.cuetools.net/ns/ext-1.0#"),
    XML_SERIALIZER_OPTION_XML_ENCODING  => 'UTF-8'
    );
  $serializer = new XML_Serializer($options);
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
