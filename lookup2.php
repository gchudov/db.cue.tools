<?php
require_once( 'phpctdb/ctdb.php' );
require_once( 'xml.php' );

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543") or die('Could not connect: ' . pg_last_error());
if (@$_GET['toc'])
{
  $toc_s = $_GET['toc'] or die('Invalid arguments');
  $dometa = @$_GET['musicbrainz'];
  $fuzzy = @$_GET['fuzzy'];
  $toc = phpCTDB::toc_s2toc($toc_s);
  $tocid = phpCTDB::toc2tocid($toc); 
  $query = "SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($tocid) . "'";
  if (!$fuzzy) $query = $query . " AND trackoffsets='" . pg_escape_string($toc['trackoffsets']) . "'";
  $result = pg_query($dbconn, $query) 
    or die('Query failed: ' . pg_last_error());
} else
{
  $tocid = @$_GET['tocid'] or die('No id');
  $dometa = true;
  $fuzzy = true;
  $result = pg_query($dbconn, "SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($tocid) . "';") 
    or die('Query failed: ' . pg_last_error());
}

$records = pg_fetch_all($result);
pg_free_result($result);
$mbids = false;
if (@$toc)
  $mbids[] = phpCTDB::toc2mbid($toc);
if ($records && $fuzzy)
  foreach($records as $record)
    $mbids[] = phpCTDB::toc2mbid($record);

$mbmetas = false;
if ($dometa)
foreach (array_unique($mbids) as $mbid)
{
  $mbmeta_t = phpCTDB::mblookupnew($mbid);
  if ($mbmeta_t)
    foreach ($mbmeta_t as $im)
      $mbmetas[] = $im;
}
if (!$records && !$mbmetas)
{
  ob_clean();
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
  header("Status: 404 Not Found");
  die('Not Found'); 
}

$xmlentry = false;
$i = 0;
if ($records)
foreach($records as $record)
{
  $xmlentry[$i] = false; 
  $xmlentry[$i . ' attr'] = array(
    'id' => $record['id'],
    'crc32' => sprintf("%08x", $record['crc32']),
    'confidence' => $record['confidence'], 
    'npar' => 8, 
    'stride' => 5880,
    'hasparity' => ($record['parfile'] ? "1" : "0"),
    'parity' => $record['parity'],
    'toc' => phpCTDB::toc_toc2s($record)
  );
  $i++;
}
$xmlmbmeta = false; 
$i = 0;
if ($mbmetas)
foreach ($mbmetas as $mbmeta)
{
  $tracks = false;
  $j = 0;
  foreach ($mbmeta['tracklist'] as $track) {
    if ($track['artist'] == $mbmeta['artistname'])
      $track['artist'] = false;
    $tracks[$j . ' attr'] = $track;
    $tracks[$j++] = false;
  }
  $labels = false;
  $j = 0;
  foreach ($mbmeta['label'] as $label) {
    $labels[$j . ' attr'] = $label;
    $labels[$j++] = false;
  }
  $xmlmbmeta[$i . ' attr'] = array(
    'release_gid' => $mbmeta['gid'],
    'artist' => $mbmeta['artistname'],
    'album' => $mbmeta['albumname'],
    'year' => $mbmeta['year'], 
    'releasedate' => $mbmeta['releasedate'], 
    'country' => $mbmeta['country'], 
    'discnumber' => $mbmeta['discnumber'], 
    'disccount' => $mbmeta['totaldiscs'], 
    'discname' => $mbmeta['discname'], 
    'coverarturl' => $mbmeta['coverarturl'], 
    'infourl' => $mbmeta['info_url'], 
    'barcode' => $mbmeta['barcode']
  );
  $xmlmbmetai = false;
  $xmlmbmetai['track'] = $tracks;
  if ($labels) $xmlmbmetai['label'] = $labels;
  $xmlmbmeta[$i++] = $xmlmbmetai;
}
$ctdbdata = false;
if ($xmlentry) $ctdbdata['entry'] = $xmlentry;
if ($xmlmbmeta) $ctdbdata['musicbrainz'] = $xmlmbmeta;
$data = array(
	'ctdb attr' => array('xmlns'=>"http://db.cuetools.net/ns/mmd-1.0#", 'xmlns:ext'=>"http://db.cuetools.net/ns/ext-1.0#"),
        'ctdb' => $ctdbdata
);
header('Content-type: text/xml; charset=UTF-8');
printf("%s", XML_serialize($data));
?>
