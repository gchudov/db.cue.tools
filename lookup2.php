<?php
require_once 'phpctdb/ctdb.php';
require_once 'XML/Serializer.php';

$toc_s = $_GET['toc'] or die('Invalid arguments');
$dometa = @$_GET['musicbrainz'];
$dofreedb = isset($_GET['freedb']) ? $_GET['freedb'] : false; // $dometa;
$dofreedbfuzzy = isset($_GET['freedbfuzzy']) ? $_GET['freedbfuzzy'] : false; // $dometa;
$dodiscogs = isset($_GET['discogs']) ? $_GET['discogs'] : false; // $dometa;
$dodiscogsfuzzy = isset($_GET['discogsfuzzy']) ? $_GET['discogsfuzzy'] : false; // $dometa;
$doctdb = isset($_GET['ctdb']) ? $_GET['ctdb'] : 1;
$type = isset($_GET['type']) ? $_GET['type'] : 'xml';
$fuzzy = @$_GET['fuzzy'];
$toc = phpCTDB::toc_s2toc($toc_s);
$records = array();
if ($doctdb)
{
  $dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543") or die('Could not connect: ' . pg_last_error());
  $tocid = phpCTDB::toc2tocid($toc); 
  $query = "SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($tocid) . "'";
  if (!$fuzzy) $query = $query . " AND trackoffsets='" . pg_escape_string($toc['trackoffsets']) . "'";
  $result = pg_query($dbconn, $query) 
    or die('Query failed: ' . pg_last_error());
  $records = pg_fetch_all($result);
  pg_free_result($result);
}

$mbids = array(phpCTDB::toc2mbid($toc));
if ($records && $fuzzy)
  foreach($records as $record)
    $mbids[] = phpCTDB::toc2mbid($record);

$mbmetas = array();
for ($priority=1; $priority <= 7; $priority++)
{
  if (($dometa & 7) == $priority)
    foreach (array_unique($mbids) as $mbid)
      $mbmetas = array_merge($mbmetas, phpCTDB::mblookup($mbid)); 
  if (($dodiscogs & 7) == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(phpCTDB::discogsids($mbmetas))); 
  if (($dodiscogsfuzzy & 7) == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::discogslookup(phpCTDB::discogsfuzzylookup($toc_s))); 
  if (($dofreedbfuzzy & 7) == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 150)); 
  else if (($dofreedb & 7) == $priority)
    $mbmetas = array_merge($mbmetas, phpCTDB::freedblookup($toc_s, 0)); 
  if ($mbmetas) break;
}

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
  foreach($records as $record)
  {
    $xmlentry[] = array(
      'id' => $record['id'],
      'crc32' => sprintf("%08x", $record['crc32']),
      'confidence' => $record['confidence'], 
      'npar' => 8, 
      'stride' => 5880,
      'hasparity' => ($record['s3'] == 't' ? sprintf("http://p.cuetools.net/%s%08x", str_replace('.','%2B',$record['tocid']), $record['crc32']) :  ($record['parfile'] ? "/" . $record['parfile'] : false)),
      'parity' => $record['parity'],
      'toc' => phpCTDB::toc_toc2s($record)
    );
  }
  $xmlmbmeta = null; 
  foreach ($mbmetas as $mbmeta)
  {
    $tracks = null;
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
      'genre' => @$mbmeta['genre'],
      'extra' => @$mbmeta['extra'],
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
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*5) . ' GMT');
header("ETag:  " . $etag);
if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
  header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
  exit;
}
die($body);
