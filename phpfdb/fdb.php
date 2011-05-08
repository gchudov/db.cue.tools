<?

require_once("lib/album.php");

function GetFreeDBEntry($url){
  $data = "";
  $file = fopen($url, "r");
  while(!feof($file)){
    $data .= fread($file, 4096);
  }
  fclose($file);

  return($data);
}

function ParseFreeDB($data){
  $album = new Album();
  $trackLengths = array();
  $matches = array();

  $lines = explode("\n", $data);

  for($x = 0; $x < sizeof($lines); $x++){
    $num = preg_match("(#\s+(\d+))", $lines[$x], $matches);
    if($num > 0 && $matches[1] > 300){
      $trackLengths[] = $matches[1] - $prevFrame;
      $prevFrame = $matches[1];
      continue;
    }else if($num > 0){
      $prevFrame = $matches[1];
      continue;
    }

    $num = preg_match("(# Disc length: (\d+))", $lines[$x], $matches);
    if($num > 0){
      $album->length = $matches[1];
      $trackLengths[] = $matches[1] * 75 - $prevFrame;
      continue;
    }

    $num = preg_match("(DISCID=(.+))", $lines[$x], $matches);
    if($num > 0){
      $album->discid = $matches[1];
      continue;
    }

    $num = preg_match("(DTITLE=(.+) / (.+))", $lines[$x], $matches);
    if($num > 0){
      $album->artist = $matches[1];
      $album->name = $matches[2];
      continue;
    }

    $num = preg_match("(DYEAR=(\d+))", $lines[$x], $matches);
    if($num > 0){
      $album->year = $matches[1];
      continue;
    }

    $num = preg_match("(DGENRE=(.+))", $lines[$x], $matches);
    if($num > 0){
      $album->freeDBGenre = $matches[1];
      continue;
    }

    $num = preg_match("(TTITLE(\d+)=(.+))", $lines[$x], $matches);
    if($num > 0){
      if($album->tracks[sizeof($album->tracks) - 1]->num == ($matches[1] + 1)){
    $album->tracks[sizeof($album->tracks) - 1]->name = $album->tracks[sizeof($album->tracks) - 1]->name . $matches[2];

      }else{
    $track = new Track();
    $track->num = $matches[1] + 1;
    $track->name = $matches[2];
    $time = floor($trackLengths[$matches[1]] / 75);
    $track->length = $time;
    $album->tracks[] = $track;
      }
      continue;
    }

    if(preg_match("(EXTD=(.+))", $lines[$x], $matches)){
      if(preg_match("(YEAR: (\d+))", $lines[$x], $yearMatches)){
    $album->year = $yearMathces[1];
      }
      continue;
    }

    /* XXX do I really want to ignore this?
    $num = preg_match("(EXTT(\d+)=(.+))", $lines[$x], $matches);
    if($num > 0){
      $album->tracks[$matches[1]]->extd = $matches[2];
      continue;
    }
    */
  }

  for($x = 0; $x < sizeof($album->tracks); $x++){
    $track = $album->tracks[$x];
    $matches = array();
    $num = preg_match("((.+?)\s*[/\-]\s*(.+))", $track->name, $matches);
    if($num > 0){
      $album->tracks[$x]->artist = $matches[1];
      $album->tracks[$x]->name = $matches[2];
    }
  }

  return($album);
}

?>
