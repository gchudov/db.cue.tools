<?php
require_once( 'phpctdb/ctdb.php' );
require_once( 'xml.php' );

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
	or die('Could not connect: ' . pg_last_error());
$tocid = @$_GET['tocid']
	or die('No id');
$result = pg_query("SELECT * FROM submissions2 WHERE tocid='" . pg_escape_string($tocid) . "';") 
	or die('Query failed: ' . pg_last_error());
$records = pg_fetch_all($result);
pg_free_result($result);
if (!count($records))
{
  ob_clean();
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
  header("Status: 404 Not Found");
  die('Not Found'); 
}
$xmlentry = false;
$i = 0;
$mbids = false;
foreach($records as $record)
{
  $mbids[] = phpCTDB::toc2mbid($record);
  $xmlentry[$i] = array('parity' => $record['parity'], 'toc' => $record['trackoffsets'], 'toc attr' => array(
    'trackcount' => $record['trackcount'], 'audiotracks' => $record['audiotracks'], 'firstaudio' => $record['firstaudio']
  ));
  $xmlentry[$i . ' attr'] = array(
    'id' => $record['id'],
    'crc32' => sprintf("%08x", $record['crc32']),
    'confidence' => $record['confidence'], 
    'npar' => 8, 
    'stride' => 5880,
    'hasparity' => ($record['parfile'] ? "1" : "0")
  );
  $i++;
}
$mbmetas = false;
foreach (array_unique($mbids) as $mbid)
{
  foreach (phpCTDB::mblookupnew($mbid) as $im)
    $mbmetas[] = $im;
}
$xmlmbmeta = false; 
$i = 0;
foreach ($mbmetas as $mbmeta)
{
  $tracks = false;
  $j = 0;
  foreach ($mbmeta['tracklist'] as $track) {
    $tracks[$j . ' attr'] = $track;
    $tracks[$j++] = false;
  }
  $xmlmbmeta[$i . ' attr'] = array(
    'musicbrainz_release_gid' => $mbmeta['gid'],
    'artist' => $mbmeta['artistname'],
    'album' => $mbmeta['albumname'],
    'year' => $mbmeta['year'], 
    'discnumber' => $mbmeta['discnumber'], 
    'disccount' => $mbmeta['totaldiscs'], 
    'discname' => $mbmeta['discname'], 
    'coverarturl' => $mbmeta['coverarturl'], 
    'infourl' => $mbmeta['info_url'], 
    'barcode' => $mbmeta['barcode']
  );
  $xmlmbmeta[$i++] = array('track' => $tracks, 'label' => $mbmeta['catno']);
}
$data = array(
	'ctdb attr' => array('xmlns'=>"http://db.cuetools.net/ns/mmd-1.0#", 'xmlns:ext'=>"http://db.cuetools.net/ns/ext-1.0#"),
        'ctdb' => array('entry' => $xmlentry, 'meta' => $xmlmbmeta)
);
header('Content-type: text/xml; charset=UTF-8');
printf("%s", XML_serialize($data));
?>
