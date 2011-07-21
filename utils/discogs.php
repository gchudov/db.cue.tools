<?php
function parseDuration($dur)
{
  if (!$dur || $dur == "") return "NULL";
  if (!preg_match( "/([0-9]*)[:'\.]([0-9]+)/", $dur, $match))
    return "NULL";
//    die("Invalid duration $dur");
  return ($match[1] == '' ? $match[2] : $match[1] * 60 + $match[2]);
}

function parsePosition($pos)
{
  if (!$pos || $pos == "") return "NULL";
  if (preg_match( "/([A-Za-z]+|[0-9]+\-)([0-9]+)/", $pos, $match))
    return $match[2];
  if (preg_match( "/[A-Za-z]+/", $pos, $match))
    return 'NULL';
  if (preg_match( "/[0-9]+/", $pos, $match))
    return $match[0];
  return 'NULL';
  //die("Invalid position $pos");
}

function parseDiscno($pos)
{
  if (!$pos || $pos == "") return "NULL";
  if (preg_match( "/([A-Za-z]+)([0-9]+)/", $pos, $match))
    return "NULL";
  if (preg_match( "/([0-9]+)\-([0-9]+)/", $pos, $match))
    return $match[1];
  if (preg_match( "/[A-Za-z]+/", $pos, $match))
    return "NULL";
  if (preg_match( "/[0-9]+/", $pos, $match))
    return '1';
  return 'NULL';
  //die("Invalid position $pos");
}

function printArray($items)
{
  if (!$items) return "";
  $res = "";
  foreach($items as $item)
    $res .= "," . $item;
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
  echo "INSERT INTO $table($keys) VALUES($vals);\n";
}

function escapeNode($node, $t = 'text')
{
  if (!$node || $node == '') return "NULL";
  if ($t == 'text') return "E'" . pg_escape_string($node) . "'";
  return "'" . pg_escape_string($node) . "'::$t";
}

function escapeNodes($nodes, $t = 'text')
{
  if (!$nodes) return "NULL";
  $res = array();
  foreach($nodes->children() as $node)
    $res[] = escapeNode($node, $t);
  return printArray($res);
}

$seqid_artistname = 1;
$seqid_image = 1;
$seqid_credit = 1;
$seqid_style = 1;
$known_names = array();
$known_images = array();
$known_styles = array();
$known_genres = array();
$known_formats = array();

function parseArtistName($name)
{
  if (!$name || $name=='')
    return 'NULL';
  global $seqid_artistname;
  global $known_names;
  if (@$known_names[(string)$name]) return $known_names[(string)$name];
  $name_id = $seqid_artistname++;
  printInsert('artist_name', array(
    'id' => $name_id,
    'name' => escapeNode($name)));
  $known_names[(string)$name] = $name_id;
  return $name_id;
}

function parseStyleName($name)
{
  if (!$name || $name=='')
    return '';
  global $seqid_style;
  global $known_styles;
  $key = (string)$name;
  if (@$known_styles[$key]) return $known_styles[$key];
  $name_id = $seqid_style++;
  printInsert('style', array(
    'id' => $name_id,
    'name' => escapeNode($name)));
  $known_styles[$key] = $name_id;
  return $name_id;
}

function parseStyles($styles)
{
  if (!$styles) return 'NULL';
  $res = array();
  foreach($styles->children() as $stl)
    $res[] = parseStyleName($stl);
  return printArray($res);
}

function parseImage($img)
{
  if (!$img)
    return '';
  global $seqid_image;
  global $known_images;
  $key = (string)$img['uri'];
  if (@$known_images[$key]) return $known_images[$key];
  $image_id = $seqid_image++;
  printInsert('image', array(
    'id' => $image_id,
    'uri' => escapeNode($img['uri']),
    'height' => $img['height'],
    'width' => $img['width'],
    'image_type' => escapeNode($img['type']),
    'uri150' => escapeNode($img['uri150'])));
  $known_images[$key] = $image_id;
  return $image_id;
}

function parseCredits($artists)
{
  if (!$artists)
    return 'NULL';
  global $seqid_credit;
  $artist_credit = $seqid_credit++;
  $artist_count = 0;
  $artist_name = '';
//  global $known_names;
//  $known_names = array();
  foreach($artists->children() as $art) {
    printInsert('artist_credit_name', array(
      'artist_credit' => $artist_credit,
      'position' => $artist_count,
      'name' => parseArtistName($art->name),
      'anv' => parseArtistName($art->anv),
      'join_verb' => escapeNode($art->join),
      'role' => escapeNode($art->role),
      'tracks' => escapeNode($art->tracks)));
    $artist_name .= ($art->anv != '' ? $art->anv : $art->name) . ($art->join != '' ? ' ' . $art->join . ' ' : '');
    $artist_count++;
  }
  printInsert('artist_credit', array(
    'id' => $artist_credit,
    'name' => parseArtistName($artist_name),
    'count' => $artist_count));
  return $artist_credit; 
}

function parseRelease($rel)
{
  global $known_genres;
  global $known_formats;

  if ($rel->genres)
    foreach ($rel->genres->children() as $key)
      $known_genres[escapeNode($key)] = true;
  if ($rel->formats)
    foreach ($rel->formats->children() as $key)
      $known_formats[escapeNode($key['name'])] = true;

  //print_r( $rel);
  printInsert('release', array(
    'discogs_id' => $rel['id'],
    'master_id' => $rel->master_id == '' ? 'NULL' : $rel->master_id,
    'artist_credit' => parseCredits($rel->artists),
    'title' => escapeNode($rel->title),
    'status' => escapeNode($rel['status']),
    'country' => escapeNode($rel->country),
    'released' => escapeNode($rel->released),
    'notes' => escapeNode($rel->notes),
    'genres' => escapeNodes($rel->genres,'genre_t'),
    'styles' => parseStyles($rel->styles)));
  if ($rel->labels)
  foreach($rel->labels->children() as $lbl) {
    printInsert('releases_labels', array(
      'discogs_id' => $rel['id'],
      'label' => escapeNode($lbl['name']),
      'catno' => escapeNode($lbl['catno'])));
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
  $toc = array();
  if ($rel->tracklist)
  foreach($rel->tracklist->children() as $trk) {
    $pos = parsePosition($trk->position);
    $dis = parseDiscno($trk->position);
    $dur = parseDuration($trk->duration);
    if ($dis != 'NULL') // && !Vinyl?
      $toc[$dis][$pos] = $dur;
    printInsert('track', array(
      'discogs_id' => $rel['id'],
      'artist_credit' => parseCredits($trk->artists),
      'title' => escapeNode($trk->title),
      'duration' => $dur,
      'position' => escapeNode($trk->position),
      'trno' => $pos,
      'discno' => $dis));
  }
/*
  foreach($toc as $dis => $trk) {
    if (!in_array('', $trk))
      printInsert('toc', array(
        'discogs_id' => $rel['id'],
        'disc' => $dis,
        'toc' => 'create_cube_from_toc(' . printArray($trk) . ')',
        'duration' => printArray($trk)));
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
    printInsert('releases_images', array(
      'release_id' => $rel['id'],
      'image_id' => parseImage($img)));
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

echo '-- CREATE TYPE genre_t AS ENUM (' . implode(',',array_keys($known_genres)) . ");\n";
echo '-- CREATE TYPE format_t AS ENUM (' . implode(',',array_keys($known_formats)) . ");\n";

