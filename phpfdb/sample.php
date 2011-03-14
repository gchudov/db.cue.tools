

function GetFreeDBSearchHits($words){

  $file = fopen("http://www.freedb.org/freedb_search.php?words=". urlencode($words) ."&allfields=NO&fields=artist&fields=title&allcats=YES&grouping=none", "r");
  while(!feof($file)){
    $data .= fgets($file, 4096);
  }
  fclose($file);

  $matches = array();
  $pattern = "(cat=(\w+)&id=(\w+)\">(.*) / (.*?)<)";
  $hits = preg_match_all($pattern, $data, $matches);
  $choices = array();
  for($x = 0; $x < sizeof($matches[1]); $x++){
    $cat = rtrim($matches[1][$x]);
    $discid = $matches[2][$x];
    $artist = $matches[3][$x];
    $title = rtrim($matches[4][$x]);
    $link = "http://www.freedb.org/freedb/$cat/$discid";
    $choices[sprintf("%s / %s<BR>", $title, $artist)] = $link;
  }

  return($choices);
}

$found = 1;
    $data = GetFreeDBEntry($_POST["freeDBHitRadio"]);
    $album = ParseFreeDB($data);
    $album->key = QueryKey($device, $slot);
    $album->device = $device;
    $album->slot = $slot;
    $wp->Append("<H2>FreeDB Entry</H2>");
