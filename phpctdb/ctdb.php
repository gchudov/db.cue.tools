<?php

class phpCTDB{

	private $fp;
	private $fpstats;
	private $atoms;
	public $db;
	public $trackoffsets;
	public $audiotracks;
	public $trackcount;
	public $firstaudio;

	function __construct($target_file) {
		$this->fp = fopen($target_file, 'rb');
		$this->fpstats = fstat($this->fp);
		$this->atoms = $this->parse_container_atom(0, $this->fpstats['size']);
		$this->db = false;
 		foreach ($this->atoms as $entry) if($entry['name'] == 'CTDB') $this->db = $entry;
		$this->ParseTOC();
	}

	function __destruct() {
		fclose($this->fp);
	}

	function ParseTOC()
	{
		$disc = $this->db['discs'][0];
		$this->trackoffsets = '';
		$this->trackcount = 0;
		$this->firstaudio = 0;
		$this->audiotracks = 0;
		foreach ($disc['TOC ']['subatoms'] as $track)
		{
			if ($track['name']=='INFO') {
				$trackcount = phpCTDB::BigEndian2Int(substr($track['value'],0,4));
				$pregap = phpCTDB::BigEndian2Int(substr($track['value'],4,4));
				$pos = $pregap;
			}
			if ($track['name']=='TRAK') {
				$isaudio = phpCTDB::BigEndian2Int(substr($track['value'],0,4));
				$length = phpCTDB::BigEndian2Int(substr($track['value'],4,4));
				if ($isaudio == 0 && $this->trackcount!=0)
					$pos += 11400;
				$this->trackoffsets = sprintf('%s%d ', $this->trackoffsets, $pos);
				$pos += $length;
				$this->trackcount ++;
				if ($isaudio != 0) 
					$this->audiotracks ++;
				if ($isaudio != 0 && $this->firstaudio == 0) 
					$this->firstaudio = $this->trackcount;
			}
		}
		$this->trackoffsets = sprintf('%s%d', $this->trackoffsets, $pos);
		if ($trackcount != $this->trackcount || $this->audiotracks == 0)
			die('wrong trackcount');
	}

  static function query2json($conn, $query)
  {
    $result = @pg_query($conn, $query);
    if (!$result) {
      header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error");
      die(pg_last_error());
    }
    if (pg_num_rows($result) == 0)
      return ''; 
    $records = pg_fetch_all($result);
    pg_free_result($result);
    return phpCTDB::records2json($records);
  }

  static function records2json($records)
  {
    $json_entries = false;
    foreach($records as $record)
    {
      $trcnt = ($record['firstaudio'] > 1) ?
        (($record['firstaudio'] - 1) . '+' . $record['audiotracks']) :
        (($record['audiotracks'] < $record['trackcount'])
         ? ($record['audiotracks'] . '+1')
         : $record['audiotracks']);
      $json_entries[] = array('c' => array(
            array('v' => $record['artist']),
            array('v' => $record['title']),
            array('v' => $record['tocid']),
            array('v' => $trcnt),
            array('v' => (int)$record['id']),
            array('v' => (int)$record['confidence']),
            array('v' => (int)$record['crc32']),
            array('v' => phpCTDB::toc_toc2s($record)),
            ));
    }
    $json_entries_table = array('cols' => array(
          array('label' => 'Artist', 'type' => 'string'),
          array('label' => 'Album', 'type' => 'string'),
          array('label' => 'Disc Id', 'type' => 'string'),
          array('label' => 'Tracks', 'type' => 'string'),
          array('label' => 'CTDB Id', 'type' => 'number'),
          array('label' => 'AR', 'type' => 'number'),
          array('label' => 'CRC32', 'type' => 'number'),
          array('label' => 'MB Id', 'type' => 'string'),
          ), 'rows' => $json_entries);

    return json_encode($json_entries_table);
  }

  static function musicbrainz2json($mbmeta)
  {
    if (!$mbmeta)
      return json_encode(null);
    $json_releases = null;
    foreach ($mbmeta as $mbr)
    {
      $label = '';
      $labels_orig = @$mbr['label'];
      if ($labels_orig)
        foreach ($labels_orig as $l)
          $label = $label . ($label != '' ? ', ' : '') . $l['name'] . (@$l['catno'] ? ' ' . $l['catno'] : '');

      $json_releases[] = array(
          'c' => array(
            array('v' => (int)$mbr['first_release_date_year']),
            array('v' => $mbr['artistname']),
            array('v' => $mbr['albumname']),
            array('v' => ($mbr['totaldiscs'] ?: 1) != 1 || ($mbr['discnumber'] ?: 1) != 1 ? ($mbr['discnumber'] ?: '?') . '/' . ($mbr['totaldiscs'] ?: '?') . ($mbr['discname'] ? ': ' . $mbr['discname'] : '') : ''),
            array('v' => $mbr['country']),
            array('v' => $mbr['releasedate']),
            array('v' => $label),
            array('v' => $mbr['barcode']),
            array('v' => $mbr['gid']),
            ));
    }
    $json_releases_table = array(
        'cols' => array(
          array('label' => 'Year', 'type' => 'number'),
          array('label' => 'Artist', 'type' => 'string'),
          array('label' => 'Album', 'type' => 'string'),
          array('label' => 'Disc', 'type' => 'string'),
          array('label' => 'C', 'type' => 'string'),
          array('label' => 'Release', 'type' => 'string'),
          array('label' => 'Label', 'type' => 'string'),
          array('label' => 'Barcode', 'type' => 'string'),
          array('label' => 'Gid', 'type' => 'string'),
          ), 
        'rows' => $json_releases);
    return json_encode($json_releases_table);
  }

	static function toc2mbtoc($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
                $lastaudio = $record['firstaudio'] + $record['audiotracks'] - 1;
		$mbtoc = sprintf('%d %d', 1, $lastaudio);
		if ($lastaudio == $record['trackcount']) // Audio CD
			$mbtoc = sprintf('%s %d', $mbtoc, $ids[$lastaudio] + 150);
		else // Enhanced CD
			$mbtoc = sprintf('%s %d', $mbtoc, $ids[$lastaudio] + 150 - 11400);
		for ($tr = 0; $tr < $lastaudio; $tr++)
			$mbtoc = sprintf('%s %d', $mbtoc, $ids[$tr] + 150);
		return $mbtoc;
	}

	static function toc2tocid($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
		$tocid = '';
		$pregap = $ids[$record['firstaudio'] - 1];
		for ($tr = $record['firstaudio']; $tr < $record['firstaudio'] + $record['audiotracks'] - 1; $tr++)
			$tocid = sprintf('%s%08X', $tocid, $ids[$tr] - $pregap);
		$leadout = $ids[$record['firstaudio'] + $record['audiotracks'] - 1] -
			(($record['firstaudio'] == 1 && $record['audiotracks'] < $record['trackcount']) ? 11400 : 0); // Enhanced CD
		$tocid = sprintf('%s%08X', $tocid, $leadout - $pregap);
		//echo $tocid;
		$tocid = str_pad($tocid, 800, '0');
		$tocid = base64_encode(pack("H*" , sha1($tocid)));
		$tocid = str_replace('+', '.', str_replace('/', '_', str_replace('=', '-', $tocid)));
		return $tocid;
	}

	static function toc2mbid($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
                $lastaudio = $record['firstaudio'] + $record['audiotracks'] - 1;
		$mbtoc = sprintf('%02X%02X', 1, $lastaudio);
		if ($lastaudio == $record['trackcount']) // Audio CD
			$mbtoc = sprintf('%s%08X', $mbtoc, $ids[$lastaudio] + 150);
		else // Enhanced CD
			$mbtoc = sprintf('%s%08X', $mbtoc, $ids[$lastaudio] + 150 - 11400);
		for ($tr = 0; $tr < $lastaudio; $tr++)
			$mbtoc = sprintf('%s%08X', $mbtoc, $ids[$tr] + 150);
//		echo $fulltoc . ':' . $mbtoc . '<br>';
		$mbtoc = str_pad($mbtoc,804,'0');
		$mbid = str_replace('+', '.', str_replace('/', '_', str_replace('=', '-', base64_encode(pack("H*" , sha1($mbtoc))))));
		return $mbid;
	}

	static function toc2cddbid($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
		$tocid = '';
		for ($tr = 0; $tr < $record['trackcount']; $tr++)
			$tocid = $tocid . (floor($ids[$tr] / 75) + 2);
		$id0 = 0;
    for ($i = 0; $i < strlen($tocid); $i++)
			$id0 += ord($tocid{$i}) - ord('0');
    return 
			sprintf('%02X', $id0 % 255) . 
			sprintf('%04X', floor($ids[$record['trackcount']] / 75) - floor($ids[0] / 75)) .
			sprintf('%02X', $record['trackcount']);
	}

	static function toc2arid($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
		$discId1 = 0;
		$discId2 = 0;
		for ($tr = $record['firstaudio']; $tr < $record['firstaudio'] + $record['audiotracks']; $tr++)
		{
			$discId1 += $ids[$tr - 1];
			$discId2 += max(1,$ids[$tr - 1]) * ($tr - $record['firstaudio'] + 1);
		}
		$discId1 += $ids[$record['trackcount']];
		$discId2 += max(1,$ids[$record['trackcount']]) * ($record['audiotracks'] + 1);
    return sprintf('%08x-%08x-%s', $discId1, $discId2, strtolower(phpCTDB::toc2cddbid($record)));
	}

	static function toc_s2toc($toc_s)
	{
	  $ids = explode(':', $toc_s);
	  $firstaudio = 1;
	  $audiotracks = 0;
	  $trackcount = count($ids) - 1;
	  while ($firstaudio < count($ids) && $ids[$firstaudio - 1][0] == '-')
	    $firstaudio ++;
	  while ($firstaudio + $audiotracks < count($ids) && $ids[$firstaudio + $audiotracks - 1][0] != '-')
	    $audiotracks ++;
	  for ($i = 0; $i < count($ids); $i++)
	    if ($ids[$i][0] == '-')
	      $ids[$i] = substr($ids[$i], 1);
	  $toc = false;
	  $toc['firstaudio'] = $firstaudio;
	  $toc['audiotracks'] = $audiotracks;
	  $toc['trackcount'] = $trackcount;
	  $toc['trackoffsets'] = implode(' ', $ids);
	  return $toc;
	}

	static function toc_toc2s($toc)
	{
	  $ids = explode(' ', $toc['trackoffsets']);
	  for ($i = 0; $i < count($ids) - 1; $i++)
	    if ($i + 1 < $toc['firstaudio'] || $i + 1 >= $toc['firstaudio'] + $toc['audiotracks'])
	      $ids[$i] = '-' . $ids[$i];
	  return implode(':', $ids);
	}

	static function pg_array_indexes($args)
	{
          $i = 1;
	  $res = '';
	  foreach($args as $arg)
	    $res = $res . ',$' . $i++;
	  return '(' . substr($res,1) . ')';  
	}

	static function pg_array_parse( $text, &$output, $limit = false, $offset = 1 )
	{
	  if( false === $limit )
	  {
	    $limit = strlen( $text )-1;
	    $output = array();
	  }
	  if( '{}' != $text )
	    do
	    {
	      if( '{' != $text{$offset} )
	      {
	        preg_match( "/(\\{?\"([^\"\\\\]|\\\\.)*\"|[^,{}]+)+([,}]+)/", $text, $match, 0, $offset );
	        $offset += strlen( $match[0] );
	        $output[] = $match[1] == 'NULL' ? false : ( '"' != $match[1]{0} ? $match[1] : stripcslashes( substr( $match[1], 1, -1 ) ) );
	        if( '},' == $match[3] ) return $offset;
	      }
	      else  $offset = phpCTDB::pg_array_parse( $text, $output[], $limit, $offset+1 );
	    }
	    while( $limit > $offset );
	  return $output;
	}

	static function freedblookup($toc, $fuzzy = 0)
        {
		$freedbconn = pg_connect("dbname=freedb user=freedb_user port=6543");
		if (!$freedbconn)
			return false;
		$ids = explode(':', $toc);
		$tocid = '';
		$offsets = '';
		for ($tr = 0; $tr < count($ids) - 1; $tr++) {
			$offsets .= ',' . (abs($ids[$tr]) + 150);
			$tocid = $tocid . (floor(abs($ids[$tr]) / 75) + 2);
		}
		$id0 = 0;
    		for ($i = 0; $i < strlen($tocid); $i++)
			$id0 += ord($tocid{$i}) - ord('0');
		$length = floor(abs($ids[count($ids) - 1]) / 75) - floor(abs($ids[0]) / 75);
    		$hexid =
			sprintf('%02X', $id0 % 255) . 
			sprintf('%04X', $length) .
			sprintf('%02X', count($ids) - 1);
		$result = pg_query_params($freedbconn,
		  'SELECT * FROM entries WHERE id = $1 AND length = $2 AND offsets = $3;', array((int)phpCTDB::Hex2Int($hexid, false), $length + 2, '{' . substr($offsets,1) . '}')); 
		$meta = pg_fetch_all($result);
		pg_free_result($result);
		if (!$meta && $fuzzy > 0) 
                {
                  $result = pg_query_params($freedbconn,
                    'SELECT en.* FROM toc_index ti INNER JOIN entries en ON en.id = ti.id AND en.category = ti.category ' .
                    'WHERE ti.toc <@ create_bounding_cube($1, $2) AND array_upper(en.offsets, 1)=$3 ' . 
                    'ORDER BY cube_distance(toc, create_cube_from_toc($1)) LIMIT 5',
                    array('{' . substr($offsets,1) . ',' . (abs($ids[count($ids) - 1]) + 150) . '}', $fuzzy, count($ids) - 1));
		  $meta = pg_fetch_all($result);
		  pg_free_result($result);
		}
		if (!$meta) return array();
		$res = array();
		foreach($meta as $r)
		{
		  $tracklist = null;
		  $track_title = null;
		  phpCTDB::pg_array_parse($r['track_title'], $track_title);
		  foreach($track_title as $tt)
		    $tracklist[] = array('name' => $tt, 'artist' => null);
		  $res[] = array(
		    'artistname' => $r['artist'],
		    'albumname' => $r['title'],
		    'first_release_date_year' => $r['year'],
		    'genre' => $r['genre'],
		    'tracklist' => $tracklist,
		    'discnumber' => null,
		    'totaldiscs' => null,
		    'discname' => null,
		    'gid' => null,
		    'barcode' => null,
		    'coverarturl' => null,
		    'info_url' => null,
		    'releasedate' => null,
		    'country' => null,
		  );
		}
		return $res;
        }

	static function mblookup($mbid)
	{
		$mbconn = pg_connect("dbname=musicbrainz_db user=musicbrainz port=6543");
		if (!$mbconn)
			return false;
		// first get cttoc ids; then select where WHERE mc.cdtoc IN $1;
		$mbresult = pg_query_params($mbconn,
		  'SELECT ' . 
		  'rgm.first_release_date_year, ' .
		  '(select cn.iso_code FROM country cn WHERE cn.id = r.country) as country, ' .
		  'c.leadout_offset, ' .
		  'c.track_offset, ' .
		  'm.tracklist as tracklistno, ' .
//                  '(select array_agg(tn.name ORDER BY t.position) FROM track t INNER JOIN track_name tn ON t.name = tn.id WHERE t.tracklist = m.tracklist) as tracklist, ' . 
                  'rca.cover_art_url as coverarturl, ' . 
                  'rm.info_url, ' . 
                  'r.gid, ' .
		  'r.artist_credit, ' .
//                  'array_to_string((select array_agg(an.name || COALESCE(acn.join_phrase,\'\')) FROM artist_credit_name acn INNER JOIN artist_name an ON an.id = acn.name WHERE acn.artist_credit = r.artist_credit), \'\') as artistname, ' .
                  'rn.name as albumname, ' .
                  'm.position as discnumber, ' .
                  'm.name as discname, ' .
                  '(select count(*) from medium where release = r.id) as totaldiscs, ' .
                  '(select array_agg(rl.catalog_number) from release_label rl where rl.release = r.id) as catno, ' .
                  '(select array_agg(ln.name) from release_label rl inner join label l ON l.id = rl.label inner join label_name ln ON ln.id = l.name where rl.release = r.id) as label, ' .
//                  'r.date_year as year, ' .
                  'text(r.date_year) || COALESCE(\'-\' || r.date_month || COALESCE(\'-\' || r.date_day, \'\'),\'\') as releasedate, ' .
                  'r.barcode ' .
                  'FROM cdtoc c ' .
		  'INNER JOIN medium_cdtoc mc on mc.cdtoc = c.id ' .
                  'INNER JOIN medium m on m.id = mc.medium ' .
                  'INNER JOIN release r on r.id = m.release ' .
                  'INNER JOIN release_name rn on rn.id = r.name ' .
//                  'INNER JOIN release_group rg on rg.id = r.release_group ' .
//                  'INNER JOIN artist_credit ac ON ac.id = rg.artist_credit ' .
                  'LEFT OUTER JOIN release_coverart rca ON rca.id = r.id ' .
                  'LEFT OUTER JOIN release_meta rm ON rm.id = r.id ' .
		  'LEFT OUTER JOIN release_group_meta rgm ON rgm.id = r.release_group ' .
                  'WHERE c.discid = $1 ' .
                  'ORDER BY rgm.first_release_date_year NULLS LAST, r.date_year NULLS LAST, r.date_month NULLS LAST, r.date_day NULLS LAST', array($mbid));
		$mbmeta = pg_fetch_all($mbresult);
		pg_free_result($mbresult);

		if (!$mbmeta)
		  return array();

		$tracklists = false;
		$artistcredits = false;

		foreach($mbmeta as $r)
		  $tracklists[] = $r['tracklistno'];
		$tracklists = array_unique($tracklists);
		$trackliststonames = false;
		$trackliststocredits = false;
		foreach($tracklists as $tr) {
                  $mbresult = pg_query_params('
                    SELECT t.artist_credit, tn.name 
                    FROM track t 
                    INNER JOIN track_name tn ON tn.id = t.name 
                    WHERE t.tracklist = $1
                    ORDER BY t.position', array($tr));
		  $trartistcredits = pg_fetch_all_columns($mbresult, 0);
		  $tracknames = pg_fetch_all_columns($mbresult, 1);
		  pg_free_result($mbresult);
		  $trackliststonames[$tr] = $tracknames;
		  $trackliststocredits[$tr] = $trartistcredits;
		  foreach($trartistcredits as $trcr)
		    $artistcredits[] = $trcr;
		}

		foreach($mbmeta as $r)
		  $artistcredits[] = $r['artist_credit'];
		$artistcredits = array_unique($artistcredits);
		$mbresult = pg_query_params($mbconn,
		  'SELECT ' .
		  'acn.artist_credit, ' .
		  'array_to_string(array_agg(an.name || COALESCE(acn.join_phrase,\'\')),\'\') as artistname ' .
		  'FROM artist_credit_name acn ' .
		  'INNER JOIN artist_name an ON an.id = acn.name ' .
		  'WHERE acn.artist_credit IN ' . phpCTDB::pg_array_indexes($artistcredits) . ' ' .
		  'GROUP BY acn.artist_credit', $artistcredits);
		$artistcredits = pg_fetch_all($mbresult);
		pg_free_result($mbresult);
		$artistcreditstonames = false;
		foreach($artistcredits as $cr)
		  $artistcreditstonames[$cr['artist_credit']] = $cr['artistname'];

		$tltl = false;
		foreach($tracklists as $tr) {
		  $tl = false;
		  $tlnames = $trackliststonames[$tr];
		  $tlart = $trackliststocredits[$tr];
		  for ($trno = 0; $trno < count($tlnames); $trno++)
		    $tl[] = array('name' => $tlnames[$trno], 'artist' => $artistcreditstonames[$tlart[$trno]]);
		  $tltl[$tr] = $tl;
		}

		foreach($mbmeta as &$r) {
		  $r['artistname'] = $artistcreditstonames[$r['artist_credit']];
		  $r['tracklist'] = $tltl[$r['tracklistno']];
		  $catno = false;
		  $label = false;
		  $labelcat = false;
		  if (@$r['catno']) {
		    phpCTDB::pg_array_parse($r['catno'], $catno);
		    phpCTDB::pg_array_parse($r['label'], $label);
        for($i = 0; $i < count($catno); $i++)
          $labelcat[] = array('name' => $label[$i], 'catno' => $catno[$i]);
/*        if (count($catno) != count($label)) die($label[232]);
		    for($i = 0; $i < count($catno); $i++)
		      $labelcat[$i]['catno'] = $catno[$i];
		    for($i = 0; $i < count($label); $i++)
		      $labelcat[$i]['name'] = $label[$i];*/
      }
		  $r['label'] = $labelcat;
		}
		return $mbmeta;
	}

	function ctdb2pg()
	{
		$disc = $this->db['discs'][0];
		$record = false;
		$record['trackcount'] = $this->trackcount;
		$record['audiotracks'] = $this->audiotracks;
		$record['firstaudio'] = $this->firstaudio;
		$record['trackoffsets'] = $this->trackoffsets;
		$record['crc32'] = $disc['CRC ']['int'] & 0xffffffff;
		$record['confidence'] = $disc['CONF']['int'];
		$record['parity'] = base64_encode($this->read($disc['PAR ']['offset'], 16));
		$record['artist'] = @$disc['ART ']['value'];
		$record['title'] = @$disc['nam ']['value'];
		$record['tocid'] = phpCTDB::toc2tocid($record);
		return $record;
	}

	static function Hex2Int($hex_word, $signed = false)
	{
		$int_value = 0;
		$byte_wordlen = strlen($hex_word);
		for ($i = 0; $i < $byte_wordlen; $i++) {
			sscanf($hex_word{$i}, "%x", $digit);
			$int_value += $digit * pow(16, ($byte_wordlen - 1 - $i));
		}
		if ($signed) {
				$sign_mask_bit = 0x80 << 24;
				if ($int_value & $sign_mask_bit) {
						$int_value = 0 - ($int_value & ($sign_mask_bit - 1));
				}
		}
		return $int_value;
	}

	static function BigEndian2Int($byte_word, $signed = false) {

		$int_value = 0;
		$byte_wordlen = strlen($byte_word);

		for ($i = 0; $i < $byte_wordlen; $i++) {
				$int_value += ord($byte_word{$i}) * pow(256, ($byte_wordlen - 1 - $i));
		}

		if ($signed) {
				$sign_mask_bit = 0x80 << (8 * ($byte_wordlen - 1));
				if ($int_value & $sign_mask_bit) {
						$int_value = 0 - ($int_value & ($sign_mask_bit - 1));
				}
		}

		return $int_value;
	}

	static function Hex2String($number)
	{
		$intstring = '';
		$hex_word = str_pad($number, 8, '0', STR_PAD_LEFT);
		for ($i = 0; $i < 4; $i++) {
			sscanf(substr($hex_word, $i*2, 2), "%x", $number);
			$intstring = $intstring.chr($number);
		}
		return $intstring;
	}

	static function LittleEndian2String($number, $minbytes=1, $synchsafe=false) {
		$intstring = '';
		while ($number > 0) {
				if ($synchsafe) {
						$intstring = $intstring.chr($number & 127);
						$number >>= 7;
				} else {
						$intstring = $intstring.chr($number & 255);
						$number >>= 8;
				}
		}
		return str_pad($intstring, $minbytes, "\x00", STR_PAD_RIGHT);
	}

	static function BigEndian2String($number, $minbytes=1, $synchsafe=false) {
		return strrev(phpCTDB::LittleEndian2String($number, $minbytes, $synchsafe));
	}

	static function unparse_atom($fp, $atom)
	{
//		printf('unparse_atom(%s)<br>', $atom['name']);
		$offset = ftell($fp);
		fwrite($fp, phpCTDB::BigEndian2String(0, 4));
		fwrite($fp, $atom['name']);
		if (@$atom['subatoms'])
			foreach ($atom['subatoms'] as $subatom)
				phpCTDB::unparse_atom($fp, $subatom);
		else if ($atom['value'])
			fwrite($fp, $atom['value']);
		else
			die(sprintf("couldn't write long atom %s: size %d", $atom['name'], $atom['size']));
		$pos = ftell($fp);
		fseek($fp, $offset, SEEK_SET);
		fwrite($fp, phpCTDB::BigEndian2String($pos - $offset, 4));
		fseek($fp, $pos, SEEK_SET);
	}

	function read($offset, $len)
	{
			fseek($this->fp, $offset, SEEK_SET);
			return fread($this->fp, $len);
	}

	function parse_container_atom($offset, $len)
	{
//		printf('parse_container_atom(%d, %d)<br>', $offset, $len);
		$atoms = false;
		$fin = $offset + $len;
		while ($offset < $fin) {
			fseek($this->fp, $offset, SEEK_SET);
			$atom_header = fread($this->fp, 8);
			$atom_size = phpCTDB::BigEndian2Int(substr($atom_header, 0, 4));
			$atom_name = substr($atom_header, 4, 4);
			$atom['name'] = $atom_name;
			$atom['size'] = $atom_size - 8;
			$atom['offset'] = $offset + 8;
			if ($atom_size - 8 <= 256)
				$atom['value'] = fread($this->fp, $atom_size - 8);
			else
				$atom['value'] = false;
//		echo $len, ':',	$offset, ":", $atom_size, ":", $atom_name, '<br>';
			if ($atom_name == 'CTDB' || $atom_name == 'DISC' || $atom_name == 'TOC ' || ($atom_name == 'HEAD' && ($atom_size != 28 || 256 != phpCTDB::BigEndian2Int(substr($atom['value'],0,4)))))
 		 {
				$atom['subatoms'] = $this->parse_container_atom($offset + 8, $atom_size - 8);
				foreach ($atom['subatoms'] as $param)
					switch ($param['name']) {
						case 'HEAD':
						case 'TOC ':
						case 'CRC ':
						case 'USER':
						case 'TOOL':
						case 'MBID':
						case 'ART ':
						case 'nam ':
						case 'NPAR':
						case 'CONF':
						case 'TOTL':
						case 'PAR ':
							$atom[$param['name']] = $param;
						break;
				case 'DISC':
					$atom['discs'][] = $param; 
					break;
				}
			} else
				$atom['subatoms'] = false;
			switch ($atom_name)
			{
				case 'CRC ':
				case 'NPAR':
				case 'CONF':
				case 'TOTL':
					$atom['int'] = phpCTDB::BigEndian2Int($atom['value']);
					break;
			}
			$offset += $atom_size;
			$atoms[] = $atom;
		}
		if ($offset > $fin)
			die(printf("bad atom: offset=%d, fin=%d", $offset, $fin));
		return $atoms;
	}
}
?>
