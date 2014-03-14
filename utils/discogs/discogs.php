<?php
function parseDuration($dur)
{
  if ($dur == null || $dur == "") return "\\N";
  if (!preg_match( "/([0-9]*)[:'\.]([0-9]+)/", $dur, $match))
    return "\\N";
  if ($match[1] >= 2147483647 || $match[1] < -2147483648 ||
      $match[2] >= 2147483647 || $match[2] < -2147483648)
    return "\\N";
//    die("Invalid duration $dur");
  return ($match[1] == '' ? $match[2] : $match[1] * 60 + $match[2]);
}

function parseDiscno($pos, &$tr)
{
  if ($pos == null || $pos == "") return "\\N";
  if (preg_match( "/^([0-9]+)[\-\.]([0-9]+)$/", $pos, $match)) {
    $tr = (int)$match[2];
    return (int)$match[1]; 
  }
  if (preg_match( "/^CD([0-9]+)[\-\.]([0-9]+)$/", $pos, $match)) {
    $tr = (int)$match[2];
    return (int)$match[1]; 
  }
  if (preg_match( "/^[0-9]+$/", $pos, $match)) {
    $tr = (int)$match[0];
    return '1';
  }
  return "\\N";
  //die("Invalid position $pos");
}

function printArray($items)
{
  if (!$items) return "";
  $res = "";
  foreach($items as $item)
    $res .= "," . (preg_match("/[\"\\\\{, ]/", $item) ? '"' . str_replace("\"", "\\\\\"", str_replace("\\\\", "\\\\\\\\", $item)) . '"' : $item);
  return "{" . substr($res,1) . "}";
}

$fps = array();
function printInsert($table, $record)
{
  global $fps;
  $fp = @$fps[$table];
  if (!$fp) {
    $fp = gzopen("discogs_" . $table . "_sql.gz", "wb5");
    $keys = implode(', ',array_keys($record));
    gzwrite($fp, "COPY $table ($keys) FROM stdin;\n");
    $fps[$table] = $fp;
  }
  gzwrite($fp, implode("\t", $record) . "\n");
}

function escapeNode($node, $t = 'text')
{
  if (!$node || $node == '') return "\\N";
  return addcslashes($node, "\\\t\r\n");
}

function escapeNodes($nodes, $t = 'text')
{
  if (!$nodes) return "\\N";
  $res = array();
  foreach($nodes->children() as $node)
    $res[] = escapeNode($node, $t);
  return printArray($res);
}

$seqid_artistname = 1;
$seqid_image = 1;
$seqid_credit = 1;
$seqid_label = 1;
$seqid_title = 1;
$seqid_video = 1;
$known_names = array();
$known_images = array();
$known_labels = array();
$known_titles = array();
$known_credits = array();
$known_styles = array();
$known_genres = array();
$known_idtypes = array();
$known_formats = array();
$known_descriptions = array();
$known_videos = array();

function parseArtistName($name)
{
  if ($name == null || $name=='')
    return "\\N";
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

function parseLabel($name)
{
  if ($name == null || $name=='')
    return "\\N";
  global $seqid_label;
  global $known_labels;
  $key = (string)$name;
  if (@$known_labels[$key]) return $known_labels[$key];
  $name_id = $seqid_label++;
  printInsert('label', array(
    'id' => $name_id,
    'name' => escapeNode($name)));
  $known_labels[$key] = $name_id;
  return $name_id;
}

function parseTitle($name)
{
  if ($name == null || $name=='')
    return "\\N";
  global $seqid_title;
  global $known_titles;
  $key = (string)$name;
  if (@$known_titles[$key]) return $known_titles[$key];
  $name_id = $seqid_title++;
  printInsert('track_title', array(
    'id' => $name_id,
    'name' => escapeNode($name)));
  $known_titles[$key] = $name_id;
  return $name_id;
}

function parseImage($rid, $img)
{
  if ($img == null || $img['uri'] == '')
    return "\\N";
#  global $seqid_image;
#  global $known_images;
  $key = (string)$img['uri'];
  $key = escapeNode(substr($key,0,31)=='http://api.discogs.com/image/R-' ? substr($key,31) : $key);
  //if (@$known_images[$key]) return $known_images[$key];
#  $image_id = $seqid_image++;
#  printInsert('image', array(
#    'id' => $image_id,
  printInsert('releases_images', array(
    'release_id' => $rid,
    'uri' => escapeNode($key),
    'height' => $img['height'],
    'width' => $img['width'],
    'image_type' => escapeNode($img['type'])));
  //$known_images[$key] = $image_id;
#  return $image_id;
}

function parseVideo($vid)
{
  if ($vid == null || $vid['src'] == '')
    return "\\N";
  global $seqid_video;
  global $known_videos;
  $src = (string)$vid['src'];
  $src = escapeNode(substr($src,0,31)=='http://www.youtube.com/watch?v=' ? substr($src,31) : $src);
  if (@$known_videos[$src]) return $known_videos[$src];
  $video_id = $seqid_video++;
  printInsert('video', array(
    'id' => $video_id,
    'src' => escapeNode($src),
    'title' => escapeNode((string)$vid->title),
    'duration' => $vid['duration']));
  $known_videos[$src] = $video_id;
  return $video_id;
}

function parseCredits($artists)
{
  if (!$artists)
    return "\\N";
  $artist_name = '';
//  global $known_names;
//  $known_names = array();
  $ac = array();
  foreach($artists->children() as $art) {
    $ac[] = array(
      'name' => parseArtistName($art->name),
      'anv' => parseArtistName($art->anv),
      'join_verb' => escapeNode($art->join),
      'role' => escapeNode($art->role),
      'tracks' => escapeNode($art->tracks));
    $artist_name .= ($art->anv != '' ? $art->anv : $art->name) . ($art->join != '' ? ' ' . $art->join . ' ' : '');
  }
  $artist_name_id = parseArtistName($artist_name);
  if ($artist_name_id == "\\N") return "\\N";
  global $seqid_credit;
  global $known_credits;
  $key = '';
  foreach($ac as $acn)
    $key .= $acn['name'] . "\t" . $acn['anv'] . "\t" . $acn['join_verb'] . "\t" . $acn['role'] . "\t" . $acn['tracks'] . "\t";
  if (@$known_credits[$key]) return $known_credits[$key];
  $artist_count = 0;
  $artist_credit = $seqid_credit++;
  printInsert('artist_credit', array(
    'id' => $artist_credit,
    'name' => $artist_name_id,
    'count' => count($ac)));
  foreach($ac as $acn) {
    $acn['artist_credit'] = $artist_credit;
    $acn['position'] = $artist_count++;
    printInsert('artist_credit_name', $acn);
  }
  $known_credits[$key] = $artist_credit;
  return $artist_credit; 
}

function parseRelease($rel)
{
  global $known_genres;
  global $known_styles;
  global $known_formats;
  global $known_descriptions;
  global $known_idtypes;

  if ($rel->genres)
    foreach ($rel->genres->children() as $key)
      $known_genres[(string)$key] = true;
  if ($rel->styles)
    foreach ($rel->styles->children() as $key)
      $known_styles[(string)$key] = true;

  //print_r( $rel);
  printInsert('release', array(
    'discogs_id' => $rel['id'],
    'master_id' => $rel->master_id == '' ? '\N' : $rel->master_id,
    'artist_credit' => parseCredits($rel->artists),
//    'extra_artists' => parseCredits($rel->extraartists),
    'title' => escapeNode($rel->title),
    'status' => escapeNode($rel['status']),
    'country' => escapeNode($rel->country),
    'released' => escapeNode($rel->released),
    'notes' => escapeNode($rel->notes),
    'genres' => escapeNodes($rel->genres,'genre_t'),
    'styles' => escapeNodes($rel->styles,'style_t')));
  if ($rel->labels)
  foreach($rel->labels->children() as $lbl) {
    printInsert('releases_labels', array(
      'release_id' => $rel['id'],
      'label_id' => parseLabel($lbl['name']),
      'catno' => escapeNode($lbl['catno'])));
  }
  if ($rel->identifiers)
  foreach($rel->identifiers->children() as $id) {
    $known_idtypes[escapeNode($id['type'])] = true;
    if ($id['value'] != null && $id['value'] != '')
    printInsert('releases_identifiers', array(
      'release_id' => $rel['id'],
      'id_type' => escapeNode($id['type']),
      'id_value' => escapeNode($id['value'])));
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
  $seq = 0;
  if ($rel->tracklist)
  foreach($rel->tracklist->children() as $trk) {
    $pos = "\\N";
    $dis = parseDiscno($trk->position, $pos);
    $dur = parseDuration($trk->duration);
    if ($dis != "\\N" && $pos != "\\N" && $dur != "\\N") // && !Vinyl?
      $toc[$dis][$pos] = $dur;
    printInsert('track', array(
      'release_id' => $rel['id'],
      'index' => ++$seq,
      'discno' => $dis,
      'trno' => $pos,
      'position' => escapeNode($trk->position),
      'duration' => $dur,
      'artist_credit' => parseCredits($trk->artists),
//      'extra_artists' => parseCredits($trk->extraartists),
      'title' => parseTitle($trk->title)));
  }
  $iscd = false;
  if ($rel->formats)
  foreach($rel->formats->children() as $fmt) {
    $iscd |= $fmt['name'] == 'CD';
    $known_formats[escapeNode($fmt['name'])] = true;
    if ($fmt->descriptions)
      foreach ($fmt->descriptions->children() as $des)
        $known_descriptions[(string)$des] = true;
    printInsert('releases_formats', array(
      'release_id' => $rel['id'] + 0,
      'format_name' => escapeNode($fmt['name']),
      'qty' => $fmt['qty'] + 0,
      'descriptions' => escapeNodes($fmt->descriptions, 'description_t')));
  }
  if ($iscd && end(array_keys($toc)) == count($toc))
  foreach($toc as $dis => $trk) {
    if (count($trk) < 100 && end(array_keys($trk)) == count($trk))
      printInsert('toc', array(
        'discogs_id' => $rel['id'],
        'disc' => $dis,
        //'toc' => 'create_cube_from_toc(' . printArray($trk) . ')',
        'duration' => printArray($trk)));
  }
  if ($rel->images)
  foreach($rel->images->children() as $img) {
    parseImage($rel['id'], $img);
#    printInsert('releases_images', array(
#      'release_id' => $rel['id'],
#      'image_id' => parseImage($img)));
  }
  if ($rel->videos)
  foreach($rel->videos->children() as $vid) {
    printInsert('releases_videos', array(
      'release_id' => $rel['id'],
      'video_id' => parseVideo($vid)));
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

array_walk($known_styles, function(&$val, $key) { $val = "E'" . pg_escape_string($key) . "'"; }); 
array_walk($known_genres, function(&$val, $key) { $val = "E'" . pg_escape_string($key) . "'"; }); 
array_walk($known_descriptions, function(&$val, $key) { $val = "E'" . pg_escape_string($key) . "'"; }); 
array_walk($known_formats, function(&$val, $key) { $val = "E'" . pg_escape_string($key) . "'"; }); 
array_walk($known_idtypes, function(&$val, $key) { $val = "E'" . pg_escape_string($key) . "'"; }); 
echo "CREATE TYPE style_t AS ENUM (\n    " . implode(",\n    ",$known_styles) . "\n);\n";
echo "CREATE TYPE genre_t AS ENUM (\n    " . implode(",\n    ",$known_genres) . "\n);\n";
echo "CREATE TYPE description_t AS ENUM (\n    " . implode(",\n    ",$known_descriptions) . "\n);\n";
echo "CREATE TYPE format_t AS ENUM (\n    " . implode(",\n    ",$known_formats) . "\n);\n";
echo "CREATE TYPE idtype_t AS ENUM (\n    " . implode(",\n    ",$known_idtypes) . "\n);\n";
foreach($fps as $fp) {
  gzwrite($fp, "\\.\n");
  gzclose($fp);
}
