<?
function escapeNode($node)
{
  return $node ? "'" . pg_escape_string($node) . "'" : "";
}

function escapeNodes($nodes)
{
  if (!$nodes) return "";
  $res = "";
  foreach($nodes->children() as $node)
    $res .= ",'" . pg_escape_string($node) . "'";
  return "ARRAY[" . substr($res,1) . "]";
}

function printInsert($table, $record)
{
  $keys = '';
  $vals = '';
  foreach ($record as $key => $val) {
    $keys .= ",$key";
    $vals .= ",$val";
  }
  $keys = substr($keys,1);
  $vals = substr($vals,1);
  echo "INERT INTO $table($keys) VALUES($vals);\n";
}

function parseRelease($rel)
{
  //print_r( $rel);
  printInsert('release', array(
    'discogs_id' => $rel['id'],
    'title' => escapeNode($rel->title),
    'status' => escapeNode($rel['status']),
    'country' => escapeNode($rel->country),
    'released' => escapeNode($rel->released),
    'notes' => escapeNode($rel->notes),
    'genres' => escapeNodes($rel->genres),
    'styles' => escapeNodes($rel->styles)));
  if ($rel->labels)
  foreach($rel->labels->children() as $lbl) {
    printInsert('releases_labels', array(
      'discogs_id' => $rel['id'],
      'label' => escapeNode($lbl['name']),
      'catno' => escapeNode($lbl['catno'])));
  }
  $seq = 0;
  if ($rel->artists)
  foreach($rel->artists->children() as $art) {
    printInsert('releases_artists', array(
      'discogs_id' => $rel['id'],
      'seq' => $seq++,
      'name' => escapeNode($art->name),
      'anv' => escapeNode($art->anv),
      'join' => escapeNode($art->join),
      'role' => escapeNode($art->role),
      'tracks' => escapeNode($art->tracks)));
  }
/*
  $seq = 0;
  if ($rel->extraartists)
  foreach($rel->extraartists->children() as $art) {
    printInsert('releases_extraartists', array(
      'discogs_id' => $rel['id'],
      'seq' => $seq++,
      'name' => escapeNode($art->name),
      'anv' => escapeNode($art->anv),
      'join' => escapeNode($art->join),
      'role' => escapeNode($art->role),
      'tracks' => escapeNode($art->tracks)));
  }
*/
  if ($rel->formats)
  foreach($rel->formats->children() as $fmt) {
    printInsert('releases_formats', array(
      'discogs_id' => $rel['id'],
      'format_name' => escapeNode($fmt['name']),
      'qty' => $fmt['qty'],
      'descriptions' => escapeNodes($fmt->descriptions)));
  }
  if ($rel->images)
  foreach($rel->images->children() as $img) {
    printInsert('image', array(
      'uri' => escapeNode($img['uri']),
      'height' => $img['height'],
      'width' => $img['width'],
      'type' => escapeNode($img['type']),
      'uri150' => escapeNode($img['uri150'])));
    printInsert('releases_images', array(
      'discogs_id' => $rel['id'],
      'image_uri' => escapeNode($img['uri'])));
  }
}

$xml = new XMLReader();
$xml->open('php://stdin'); 
while ($xml->read() && ($xml->nodeType != XMLReader::ELEMENT || $xml->name != 'release'))
  ;
while(1)
{
  $rel = $xml->readOuterXML();
  if (!$rel) break;
  $rel = new SimpleXMLElement($rel);
  parseRelease($rel);
  while ($xml->next() && ($xml->nodeType != XMLReader::ELEMENT || $xml->name != 'release'))
    ;
}
$xml->close();
