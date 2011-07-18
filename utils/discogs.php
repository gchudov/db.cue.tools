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

function parseRelease($rel)
{
  //print_r( $rel);
  $discogs_id = $rel['id'];
  $title = escapeNode($rel->title);
  $status = escapeNode($rel['status']);
  $country = escapeNode($rel->country);
  $released = escapeNode($rel->released);
  $notes = escapeNode($rel->notes);
  $genres = escapeNodes($rel->genres);
  $styles = escapeNodes($rel->styles);

  echo "INSERT INTO release(discogs_id, title, status, country, released, notes, genres, styles) " . 
    "VALUES($discogs_id,$title,$status,$country,$released,$notes,$genres,$styles);\n";
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
