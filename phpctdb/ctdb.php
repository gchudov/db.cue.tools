<?php

require_once 'ctdbcfg.php';

class phpCTDB{
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
            array('v' => (int)$record['subcount']),
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
          array('label' => 'Cf', 'type' => 'number'),
          array('label' => 'CRC32', 'type' => 'number'),
          array('label' => 'TOC', 'type' => 'string'),
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
            array('v' => $mbr['first_release_date_year'] == 0 ? null : (int)$mbr['first_release_date_year']),
            array('v' => $mbr['artistname']),
            array('v' => $mbr['albumname']),
            array('v' => ($mbr['totaldiscs'] ?: 1) != 1 || ($mbr['discnumber'] ?: 1) != 1 ? ($mbr['discnumber'] ?: '?') . '/' . ($mbr['totaldiscs'] ?: '?') . ($mbr['discname'] ? ': ' . $mbr['discname'] : '') : ''),
            array('v' => $mbr['country']),
            array('v' => $mbr['releasedate']),
            array('v' => $label),
            array('v' => $mbr['barcode']),
            array('v' => $mbr['id']),
            array('v' => $mbr['source']),
            array('v' => $mbr['relevance']),
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
          array('label' => 'Id', 'type' => 'string'),
          array('label' => 'Source', 'type' => 'string'),
          array('label' => 'Rel', 'type' => 'number'),
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

	static function tocs2mbid($toc_s)
	{
          $ids = explode(':', $toc_s);
          $trackcount = count($ids) - 1;
          $lastaudio = $trackcount;
          while ($lastaudio > 0 && $ids[$lastaudio - 1][0] == '-')
            $lastaudio --;
          for ($i = 0; $i < count($ids); $i++)
            if ($ids[$i][0] == '-')
              $ids[$i] = substr($ids[$i], 1);
          $mbtoc = sprintf('%02X%02X', 1, $lastaudio);
          if ($lastaudio == $trackcount) // Audio CD
            $mbtoc = sprintf('%s%08X', $mbtoc, $ids[$lastaudio] + 150);
          else // Enhanced CD
            $mbtoc = sprintf('%s%08X', $mbtoc, $ids[$lastaudio] + 150 - 11400);
          for ($tr = 0; $tr < $lastaudio; $tr++)
            $mbtoc = sprintf('%s%08X', $mbtoc, $ids[$tr] + 150);
//              echo $fulltoc . ':' . $mbtoc . '<br>';
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
                global $ctdbcfg_freedb_db;
		$freedbconn = pg_connect($ctdbcfg_freedb_db);
		if (!$freedbconn)
			return array();
		$ids = explode(':', $toc);
		$offsets = '';
		for ($tr = 0; $tr < count($ids) - 1; $tr++) {
			$offsets .= ',' . (abs($ids[$tr]) + 150);
		}
		if ($fuzzy == 0)
		  $result = pg_query_params($freedbconn,
		    'SELECT e.id, e.freedbid, e.category, e.year, e.title, e.extra, an.name as artist, gn.name as genre ' . 
		    'FROM entries e LEFT OUTER JOIN artist_names an ON an.id = e.artist LEFT OUTER JOIN genre_names gn ON gn.id = e.genre ' .
                    'WHERE array_to_string(offsets,\',\') = $1;', array(substr($offsets,1) . ',' . ((floor(abs($ids[count($ids) - 1]) / 75) + 2) * 75))); 
		else
                  $result = pg_query_params($freedbconn,
		    'SELECT e.id, e.freedbid, e.category, e.year, e.title, e.extra, an.name as artist, gn.name as genre ' . 
		    ', cube_distance(create_cube_from_toc(offsets), create_cube_from_toc($1)) as distance ' .
		    'FROM entries e LEFT OUTER JOIN artist_names an ON an.id = e.artist LEFT OUTER JOIN genre_names gn ON gn.id = e.genre ' .
                    'WHERE create_cube_from_toc(offsets) <@ create_bounding_cube($1, $2) AND array_upper(offsets, 1)=$3 ' . 
                    'ORDER BY distance LIMIT 30',
                    array('{' . substr($offsets,1) . ',' . ((floor(abs($ids[count($ids) - 1]) / 75) + 2) * 75) . '}', $fuzzy, count($ids)));
                    //array('{' . substr($offsets,1) . ',' . (abs($ids[count($ids) - 1]) + 150) . '}', $fuzzy, count($ids)));
		$meta = pg_fetch_all($result);
		pg_free_result($result);
		if (!$meta) return array();
		$res = array();
		foreach($meta as $r)
		{
		  $result = pg_query_params($freedbconn,
		    'SELECT t.number, t.title, t.extra, an.name AS artist ' . 
		    'FROM tracks t LEFT OUTER JOIN artist_names an ON an.id = t.artist ' .
		    'WHERE t.id = $1;', array($r['id']));
		  $tmeta = pg_fetch_all($result);
		  pg_free_result($result);
		  $tracklist = null;
		  if ($tmeta) {
		    for ($i = 0; $i < count($ids) - 1; $i++)
		      $tracklist[] = array('name' => null, 'artist' => null, 'extra' => null);
		    foreach ($tmeta as $t) {
		      $i = $t['number'] - 1;
		      $tracklist[$i]['name'] = $t['title'];
		      $tracklist[$i]['artist'] = $t['artist'];
		      $tracklist[$i]['extra'] = $t['extra'];
		    }
		  }
		  $res[] = array(
		    'source' => 'freedb',
		    'id' => sprintf('%s/%08x', $r['category'], $r['freedbid']),
		    'artistname' => $r['artist'],
		    'albumname' => $r['title'],
		    'first_release_date_year' => $r['year'],
		    'genre' => $r['genre'],
		    'extra' => $r['extra'],
		    'tracklist' => $tracklist,
		    'discnumber' => null,
		    'totaldiscs' => null,
		    'discname' => null,
		    'barcode' => null,
		    'coverarturl' => null,
		    'info_url' => null,
		    'releasedate' => null,
		    'country' => null,
		    'relevance' => (isset($r['distance']) ? (int)(exp(-$r['distance']/450)*100) : null),
		  );
		}
		return $res;
        }

	static function discogsids($mbmetas)
        {
	    $dids = null;
	    foreach($mbmetas as $m) {
	      $d = @$m['discogs_id'];
	      if ($d != null) {
	        $clone = false;
	        foreach($mbmetas as $r)
		  $clone |= $r['source'] == 'discogs' && $r['id'] == $d;
		if (!$clone)
		  $dids[] = $d . '/' . @$m['discnumber'] . '/' . @$m['relevance'];
	      }
	    }
	    return $dids;
	}

	static function discogslookup($dids, $fuzzy = null)
	{
                global $ctdbcfg_discogs_db;
		$conn = pg_connect($ctdbcfg_discogs_db);
		if (!$conn)
		  return array();
		$discogs_countries = array(
'Afghanistan' => 'AF','Africa' => '','Albania' => 'AL','Algeria' => 'DZ','American Samoa' => 'AS','Andorra' => 'AD',
'Angola' => 'AO','Antarctica' => 'AQ','Antigua & Barbuda' => 'AG','Argentina' => 'AR','Armenia' => 'AM','Aruba' => 'AW',
'Asia' => '','Australasia' => '','Australia' => 'AU','Australia & New Zealand' => '','Austria' => 'AT','Azerbaijan' => 'AZ',
'Bahamas, The' => 'BS','Bahrain' => 'BH','Bangladesh' => 'BD','Barbados' => 'BB','Belarus' => 'BY','Belgium' => 'BE',
'Belize' => 'BZ','Benelux' => '','Benin' => 'BJ','Bermuda' => 'BM','Bhutan' => 'BT','Bolivia' => 'BO','Bosnia & Herzegovina' => 'BA',
'Botswana' => 'BW','Brazil' => 'BR','Bulgaria' => 'BG','Burkina Faso' => 'BF','Burma' => '','Cambodia' => 'KH','Cameroon' => 'CM',
'Canada' => 'CA','Cape Verde' => 'CV','Cayman Islands' => 'KY','Central America' => '','Chile' => 'CL','China' => 'CN',
'Cocos (Keeling) Islands' => '','Colombia' => 'CO','Congo, Democratic Republic of the' => 'CD','Congo, Republic of the' => 'CG',
'Cook Islands' => 'CK','Costa Rica' => 'CR','Croatia' => 'HR','Cuba' => 'CU','Cyprus' => 'CY','Czechoslovakia' => 'XC',
'Czech Republic' => 'CZ','Denmark' => 'DK','Dominica' => 'DM','Dominican Republic' => 'DO','East Timor' => '','Ecuador' => 'EC',
'Egypt' => 'EG','El Salvador' => 'SV','Estonia' => 'EE','Ethiopia' => 'ET','Europa Island' => '','Europe' => 'XE',
'Faroe Islands' => 'FO','Fiji' => 'FJ','Finland' => 'FI','France' => 'FR','France & Benelux' => '','French Guiana' => 'GF',
'French Polynesia' => 'PF','French Southern & Antarctic Lands' => 'TF','Gabon' => 'GA','Georgia' => 'GE',
'German Democratic Republic (GDR)' => 'XG','Germany' => 'DE','Germany, Austria, & Switzerland' => '','Germany & Switzerland' => '',
'Ghana' => 'GH','Greece' => 'GR','Greenland' => 'GL','Grenada' => 'GD','Guadeloupe' => 'GP','Guam' => 'GU','Guatemala' => 'GT',
'Guinea' => 'GN','Gulf Cooperation Council' => '','Guyana' => 'GY','Haiti' => 'HT','Honduras' => 'HN','Hong Kong' => 'HK',
'Hungary' => 'HU','Iceland' => 'IS','India' => 'IN','Indonesia' => 'ID','Iran' => 'IR','Iraq' => 'IQ','Ireland' => 'IE',
'Israel' => 'IL','Italy' => 'IT','Ivory Coast' => '','Jamaica' => 'JM','Japan' => 'JP','Jordan' => 'JO','Kazakhstan' => 'KZ',
'Kenya' => 'KE','Korea' => 'KR','Korea, North' => 'KP','Kuwait' => 'KW','Kyrgyzstan' => 'KG','Latvia' => 'LV','Lebanon' => 'LB',
'Lesotho' => 'LS','Liechtenstein' => 'LI','Lithuania' => 'LT','Luxembourg' => 'LU','Macedonia' => 'MK','Madagascar' => 'MG',
'Malawi' => 'MW','Malaysia' => 'MY','Maldives' => 'MV','Mali' => 'ML','Malta' => 'MT','Marshall Islands' => 'MH',
'Martinique' => 'MQ','Mauritius' => 'MU','Mexico' => 'MX','Moldova' => 'MD','Monaco' => 'MC','Mongolia' => 'MN',
'Montenegro' => 'ME','Morocco' => 'MA','Mozambique' => 'MZ','Namibia' => 'NA','Nepal' => 'NP','Netherlands' => 'NL',
'Netherlands Antilles' => 'AN','New Caledonia' => 'NC','New Zealand' => 'NZ','Nicaragua' => 'NI','Nigeria' => 'NG',
'North America (inc Mexico)' => '','Northern Mariana Islands' => 'MP','North Korea' => 'KP','Norway' => 'NO','Oman' => 'OM',
'Pakistan' => 'PK','Panama' => 'PA','Papua New Guinea' => 'PG','Paraguay' => 'PY','Peru' => 'PE','Philippines' => 'PH',
'Pitcairn Islands' => 'PN','Poland' => 'PL','Portugal' => 'PT','Puerto Rico' => 'PR','Reunion' => 'RE','Romania' => 'RO',
'Russia' => 'RU','Saint Kitts and Nevis' => 'KN','Saint Vincent and the Grenadines' => 'VC','San Marino' => 'SM',
'Saudi Arabia' => 'SA','Scandinavia' => '','Senegal' => 'SN','Serbia' => 'RS','Serbia and Montenegro' => 'CS',
'Seychelles' => 'SC','Sierra Leone' => 'SL','Singapore' => 'SG','Slovakia' => 'SK','Slovenia' => 'SI','South Africa' => 'ZA',
'South America' => '','South Korea' => 'KR','Spain' => 'ES','Sri Lanka' => 'LK','Sudan' => 'SD','Suriname' => 'SR',
'Svalbard' => 'SJ','Swaziland' => 'SZ','Sweden' => 'SE','Switzerland' => 'CH','Syria' => 'SY','Taiwan' => 'TW',
'Tajikistan' => 'TJ','Tanzania' => 'TZ','Thailand' => 'TH','Togo' => 'TG','Trinidad & Tobago' => 'TT','Tunisia' => 'TN',
'Turkey' => 'TR','Turks and Caicos Islands' => 'TC','Tuvalu' => 'TV','Uganda' => 'UG','UK' => 'GB','UK & Europe' => 'XE',
'UK, Europe & US' => 'XW','UK & Ireland' => '','Ukraine' => 'UA','UK & US' => '','United Arab Emirates' => 'AE',
'Uruguay' => 'UY','US' => 'US','USA & Canada' => '','USA, Canada & UK' => '','USSR' => 'SU','Uzbekistan' => 'UZ',
'Vatican City' => 'VA','Venezuela' => 'VE','Vietnam' => 'VN','Virgin Islands' => 'VI','Wake Island' => '',
'Wallis and Futuna' => 'WF','Yugoslavia' => 'YU','Zambia' => 'ZM','Zimbabwe' => 'ZW'
);
		$ids = array();
		if (!$fuzzy) {
		  if (!$dids)
		    return array();
		  foreach($dids as $did) {
		    $iddno = explode('/', $did);
		    $ids[] = $iddno[0];
		  }
		  $result = pg_query_params($conn,
		    'SELECT ' . 
		    '  r.discogs_id, ' . 
		    '  r.title, ' . 
		    '  r.country, ' . 
		    '  r.released, ' .
		    '  r.artist_credit, ' .
		    '  (SELECT max(rf.qty) FROM releases_formats rf WHERE rf.release_id = r.discogs_id AND rf.format_name = \'CD\') as totaldiscs, ' .
		    '  (SELECT min(substring(rr.released,1,4)::integer) FROM release rr WHERE rr.master_id = r.master_id AND rr.released IS NOT NULL) as year ' .
		    'FROM release r ' .
		    'WHERE r.discogs_id IN ' . phpCTDB::pg_array_indexes($ids), $ids);
		} else {
	          $toff = explode(':', $fuzzy);
	          $offsets = '';
	          for ($tr = 1; $tr < count($toff); $tr++)
	            $offsets .= ',' . round((abs($toff[$tr]) - abs($toff[$tr-1])) / 75);
		  $result = pg_query_params($conn,
		    'SELECT ' .
		    '  cube_distance(create_cube_from_toc(t.duration), create_cube_from_toc($1)) as distance, ' . 
		    '  t.disc, ' . 
                    '  r.discogs_id, ' .
                    '  r.title, ' .
                    '  r.country, ' .
                    '  r.released, ' .
                    '  r.artist_credit, ' .
                    '  (SELECT max(rf.qty) FROM releases_formats rf WHERE rf.release_id = r.discogs_id AND rf.format_name = \'CD\') as totaldiscs, ' .
                    '  (SELECT min(substring(rr.released,1,4)::integer) FROM release rr WHERE rr.master_id = r.master_id AND rr.released IS NOT NULL) as year ' .
		    'FROM toc t ' .
		    'INNER JOIN release r ON r.discogs_id = t.discogs_id ' .
		    'WHERE create_cube_from_toc(t.duration) <@ create_bounding_cube($1,3) AND array_upper(t.duration, 1)=$2 '.
	            'ORDER BY distance LIMIT 30',
		    array('{' . substr($offsets,1) . '}', count($toff) - 1));
		}
		$meta = pg_fetch_all($result);
		pg_free_result($result);
		if (!$meta) return array();
		if (!$fuzzy) {
		  $dno = array();
		  $dis = array();
		  foreach($dids as $did) {
		    $iddno = explode('/', $did);
		    $dno[$iddno[0]] = $iddno[1];
		    if (@$iddno[2])
		    $dis[$iddno[0]] = $iddno[2];
		  }
		  for($i = 0; $i < count($meta); $i++) {
		    $id = $meta[$i]['discogs_id'];
		    $meta[$i]['disc'] = $dno[$id];
		    $meta[$i]['relevance'] = @$dis[$id] ;
		  }
		} else {
		  for($i = 0; $i < count($meta); $i++) {
		    $ids[] = $meta[$i]['discogs_id'];
		    $meta[$i]['relevance'] = (int)(exp(-$meta[$i]['distance']/6)*100);
		  }
		}
		$result = pg_query_params($conn,
		  'SELECT rl.release_id, rl.catno, l.name ' . 
		  'FROM releases_labels rl ' .
		  'INNER JOIN label l ON l.id = rl.label_id ' .
		  'WHERE rl.release_id IN ' . phpCTDB::pg_array_indexes($ids), $ids);
		$labelmeta = pg_fetch_all($result);
		pg_free_result($result);
                $result = pg_query_params($conn,
                  'SELECT t.release_id, t.artist_credit, t.discno, tt.name ' .
                  'FROM track t ' .
                  'LEFT OUTER JOIN track_title tt ON t.title = tt.id ' .
                  'WHERE t.release_id IN ' . phpCTDB::pg_array_indexes($ids) . ' ' . 
                  'AND t.discno IS NOT NULL AND t.trno IS NOT NULL ' .
		  'ORDER BY t.release_id, t.index', $ids);
                $trackmeta = pg_fetch_all($result);
                pg_free_result($result);
                $result = pg_query_params($conn,
		  'SELECT release_id, image_type, uri, height, width ' . 
		  'FROM releases_images ri ' . 
                  'WHERE ri.release_id IN ' . phpCTDB::pg_array_indexes($ids), $ids);
		$images =  pg_fetch_all($result);
                pg_free_result($result);
	        $result = pg_query_params($conn,
		  'SELECT release_id, id_type, id_value ' . 
		  'FROM releases_identifiers ri ' . 
                  'WHERE ri.release_id IN ' . phpCTDB::pg_array_indexes($ids) . ' AND id_type = \'Barcode\'::idtype_t', $ids);
		$relids =  pg_fetch_all($result);
                pg_free_result($result);
		$result = pg_query_params($conn,
		  'SELECT rv.release_id, v.src ' . 
		  'FROM releases_videos rv ' .
		  'INNER JOIN video v ON v.id = rv.video_id ' .
		  'WHERE rv.release_id IN ' . phpCTDB::pg_array_indexes($ids), $ids);
		$videos = pg_fetch_all($result);
		pg_free_result($result);
		$artist_credit = array();
		foreach($meta as $r)
		  if ($r['artist_credit'] != null)
		    $artist_credit[$r['artist_credit']] = null;
		if ($trackmeta) foreach($trackmeta as $t)
		  if ($t['artist_credit'] != null)
		    $artist_credit[$t['artist_credit']] = null;
		$acs = array_keys($artist_credit);
                $result = pg_query_params($conn,
                  'SELECT ' .
                  'ac.id, an.name ' .
                  'FROM artist_credit ac ' .
                  'INNER JOIN artist_name an ON an.id = ac.name ' .
                  'WHERE ac.id IN ' . phpCTDB::pg_array_indexes($acs), $acs);
                $acmeta = pg_fetch_all($result);
                pg_free_result($result);
		foreach($acmeta as $ac)
		  $artist_credit[$ac['id']] = $ac['name'];
		$res = array();
		foreach($meta as $r)
		{
		  $tracklist = array();
		  $label = null;
		  $country_iso = @$discogs_countries[$r['country']];
		  if ($country_iso == '')
		    $country_iso = null;
		  foreach($labelmeta as $l)
		    if ($l['release_id'] == $r['discogs_id'])
		      $label[] = array('catno' => $l['catno'], 'name' => $l['name']);
		  if ($trackmeta) foreach($trackmeta as $t)
		    if ($t['release_id'] == $r['discogs_id'] && $t['discno'] == $r['disc'])
		      $tracklist[] = array('name' => $t['name'], 'artist' => @$artist_credit[$t['artist_credit']]);
		  $barcode = null;
		  if ($relids)
		  foreach ($relids as &$relid)
		    if ($relid['release_id'] == $r['discogs_id'])
		      $barcode = strtr($relid['id_value'], array(' ' => '', '-' => ''));
		  $rvideos = array();
		  if ($videos)
		  foreach ($videos as &$video)
		    if ($video['release_id'] == $r['discogs_id'])
		      $rvideos[] = array('uri' => 'http://www.youtube.com/watch?v=' . $video['src']);
		  $rimages = array();
#		  error_log(print_r($images,true));
                  if ($images)
		  foreach ($images as &$image)
		    if ($image['release_id'] == $r['discogs_id'])
		      $rimages[] = array('uri' => 'http://api.discogs.com/image/R-' . $image['uri'], 'uri150' => 'http://api.discogs.com/image/R-150-' . $image['uri'], 'width' => $image['width'], 'height' => $image['height'], 'primary' => $image['image_type'] == 'primary' ? 1 : 0);
		  $res[] = array(
		    'source' => 'discogs',
		    'id' => $r['discogs_id'],
		    'artistname' => @$artist_credit[$r['artist_credit']],
		    'albumname' => $r['title'],
		    'first_release_date_year' => ($r['year'] != null ? $r['year'] : substr($r['released'],0,4)),
		    'genre' => null, //$r['genre'],
		    'extra' => null, //$r['extra'],
		    'tracklist' => $tracklist,
		    'label' => $label,
		    'discnumber' => $r['disc'],
		    'totaldiscs' => $r['totaldiscs'],
		    'discname' => null,
		    'barcode' => $barcode,
		    'coverart' => $rimages ? $rimages : null,
		    'videos' => $rvideos ? $rvideos : null,
		    'info_url' => null,
		    'releasedate' => $r['released'],
		    'country' => $country_iso,
		    'relevance' => @$r['relevance'],
		  );
		}
		return $res;
	}

	static function metadataOrder($a, $b)
	{
	  $sourceOrder = array('musicbrainz' => 0, 'discogs' => 1, 'freedb' => 2);
	  if ($a['source'] != $b['source'])
	    return $sourceOrder[$a['source']] - $sourceOrder[$b['source']];
	  $aRel = $a['relevance'] ?: 101;
	  $bRel = $b['relevance'] ?: 101;
	  if ($aRel != $bRel)
	    return $bRel - $aRel;
//          'ORDER BY rgm.first_release_date_year NULLS LAST, r.date_year NULLS LAST, r.date_month NULLS LAST, r.date_day NULLS LAST', $mbids);
//            'text(r.date_year) || COALESCE(\'-\' || r.date_month || COALESCE(\'-\' || r.date_day, \'\'),\'\') as releasedate, ' .
	  $aFR = isset($a['first_release_date_year']) ? $a['first_release_date_year'] : 9999;
	  $bFR = isset($b['first_release_date_year']) ? $b['first_release_date_year'] : 9999;
	  if ($aFR != $bFR)
	    return $aFR - $bFR;
	  $aRD = isset($a['releasedate']) ? strtotime(strpos($a['releasedate'],'-') ? $a['releasedate'] : $a['releasedate'] . '-01-01') : 0;
	  $bRD = isset($b['releasedate']) ? strtotime(strpos($b['releasedate'],'-') ? $b['releasedate'] : $b['releasedate'] . '-01-01') : 0;
	  if ($aRD != $bRD)
	    return $aRD - $bRD;
	  if ($a['id'] != $b['id'])
	    return $a['id'] < $b['id'] ? -1 : 1;
	  return 0;
	}
/*
	static function uniqueids($ids)
	{
	  $ret = array();
	  foreach($ids as $id)
	    if ($id['relevance'] == null)
	      $ret[] = $id;
	  foreach($ids as $id)
	    if ($id['relevance'] != null) {
	      $found = false;
	      foreach ($ret as $id2)
		$found |= $id2['id'] == $id['id'];
	      if (!$found)
		$ret[] = $id;
	    }
	  return $ids;
	}
*/
	static function mbzlookupids($tocs, $fuzzy = false, $mbconn = null)
	{
	  if (!$tocs) return array();
          if (!$mbconn) {
            global $ctdbcfg_musicbrainz_db;
	    $mbconn = pg_connect($ctdbcfg_musicbrainz_db);
	    if (!$mbconn) return array();
            $result = pg_query($mbconn, 'SET search_path TO musicbrainz');
            pg_free_result($result);
          }
	  if ($fuzzy) {
	    $ids = explode(':', $tocs[0]);
	    $dur = array();
	    for($i = 1; $i < count($ids); $i++)
	      if ($ids[$i-1][0] != '-')
		$dur[] = round((abs($ids[$i]) - $ids[$i-1]) * 1000 / 75);
//	    die('{' . implode(',', $dur) . '}');
	    $mbresult = pg_query_params($mbconn,
	      'SELECT ' .
	      'cube_distance(ti.toc, create_cube_from_durations($1)) AS distance, ' . 
              'm.id as id ' .
	      'FROM tracklist_index ti ' . 
	      'JOIN tracklist t ON t.id = ti.tracklist ' . 
	      'JOIN medium m ON m.tracklist = ti.tracklist ' . 
	      'WHERE ti.toc <@ create_bounding_cube($1, 3000) ' . 
	      'AND t.track_count = array_upper($1, 1) ' . 
	      'AND (m.format = 1 OR m.format IS NULL) ' .
	      'LIMIT 30', array('{' . implode(',', $dur) . '}'));
	  } else {
	    $mbids = array();
	    foreach($tocs as $toc)
	      $mbids[] = phpCTDB::tocs2mbid($toc);
	    $mbids = array_unique($mbids);
	    $mbresult = pg_query_params($mbconn,
	      'SELECT DISTINCT ' . 
	      'mc.medium AS id ' .
              'FROM cdtoc c ' .
	      'INNER JOIN medium_cdtoc mc on mc.cdtoc = c.id ' .
              'WHERE c.discid IN ' . phpCTDB::pg_array_indexes($mbids), $mbids);
	  }
	  $mbmeta = pg_fetch_all($mbresult);
	  pg_free_result($mbresult);
	  return $mbmeta ? $mbmeta : array();
        }

	static function mbzlookup($ids)
	{
	  if (!$ids) return array();
          global $ctdbcfg_musicbrainz_db;
	  $mbconn = pg_connect($ctdbcfg_musicbrainz_db);
	  if (!$mbconn) return array();
          $result = pg_query($mbconn, 'SET search_path TO musicbrainz');
          pg_free_result($result);
          $mediumids = array();
          foreach($ids as $id)
	    $mediumids[] = $id['id'];
	  $mediumids = array_unique($mediumids);
	  $mbresult = pg_query_params($mbconn,
	    'SELECT ' .
            'm.id AS mediumid, ' .
            'rgm.first_release_date_year, ' .
            '(select cn.iso_code FROM country cn WHERE cn.id = r.country) as country, ' .
            'm.tracklist as tracklistno, ' .
//            '(select array_agg(tn.name ORDER BY t.position) FROM track t INNER JOIN track_name tn ON t.name = tn.id WHERE t.tracklist = m.tracklist) as tracklist, ' .
            'rca.cover_art_url as coverarturl, ' .
            'rm.info_url, ' .
            'r.gid as id, ' .
            'r.artist_credit, ' .
//            'array_to_string((select array_agg(an.name || COALESCE(acn.join_phrase,\'\')) FROM artist_credit_name acn INNER JOIN artist_name an ON an.id = acn.name WHERE acn.artist_credit = r.artist_credit), \'\') as artistname, ' .
            'rn.name as albumname, ' .
//            'rg.gid as group_id, ' .
            'm.position as discnumber, ' .
            'm.name as discname, ' .
            '(select count(*) from medium where release = r.id) as totaldiscs, ' .
            '(select min(substring(u.url,32)) from l_release_url rurl INNER JOIN url u ON rurl.entity1 = u.id WHERE rurl.entity0 = r.id AND u.url ilike \'http://www.discogs.com/release/%\') as discogs_id, ' .
            '(select array_agg(rl.catalog_number) from release_label rl where rl.release = r.id) as catno, ' .
            '(select array_agg(ln.name) from release_label rl inner join label l ON l.id = rl.label inner join label_name ln ON ln.id = l.name where rl.release = r.id) as label, ' .
//            'r.date_year as year, ' .
            'text(r.date_year) || COALESCE(\'-\' || r.date_month || COALESCE(\'-\' || r.date_day, \'\'),\'\') as releasedate, ' .
            'r.barcode ' .

	    'FROM medium m ' . 
            'INNER JOIN release r on r.id = m.release ' .
            'INNER JOIN release_name rn on rn.id = r.name ' .
//            'INNER JOIN release_group rg on rg.id = r.release_group ' .
//            'INNER JOIN artist_credit ac ON ac.id = rg.artist_credit ' .
            'LEFT OUTER JOIN release_coverart rca ON rca.id = r.id ' .
            'LEFT OUTER JOIN release_meta rm ON rm.id = r.id ' .
            'LEFT OUTER JOIN release_group_meta rgm ON rgm.id = r.release_group ' .
	    'WHERE m.id IN ' . phpCTDB::pg_array_indexes($mediumids), $mediumids); 
//          'ORDER BY rgm.first_release_date_year NULLS LAST, r.date_year NULLS LAST, r.date_month NULLS LAST, r.date_day NULLS LAST', $mbids);
	  $mbmeta = pg_fetch_all($mbresult);
	  pg_free_result($mbresult);
	  if (!$mbmeta) return array();

		$tracklists = null;
		$artistcredits = null;

		foreach($mbmeta as $r)
		  $tracklists[] = $r['tracklistno'];
		$tracklists = array_unique($tracklists);
		$trackliststonames = null;
		$trackliststocredits = null;
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
		  'ac.id as artist_credit, ' .
		  'an.name as artistname ' .
		  'FROM artist_credit ac ' .
		  'INNER JOIN artist_name an ON an.id = ac.name ' .
		  'WHERE ac.id IN ' . phpCTDB::pg_array_indexes($artistcredits),
		  $artistcredits);
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
		  $rel = 0;
		  foreach($ids as $id)
		    if ($id['id'] == $r['mediumid'])
		      $rel = max($rel, isset($id['distance']) ? (int)(exp(-$id['distance']/6000)*100) : 101);
		  $r['relevance'] = $rel != 101 ? $rel : null;
		  $r['artistname'] = $artistcreditstonames[$r['artist_credit']];
		  $r['tracklist'] = $tltl[$r['tracklistno']];
		  $r['source'] = 'musicbrainz';
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

                  $coverart = array();
                  if (!isset($r['coverarturl']) || $r['coverarturl'] == '')
                  if (isset($r['info_url']) && $r['info_url'] != '')
                  if (0 < preg_match("/(http\:\/\/www\.amazon\.)([^\/]*)\/gp\/product\/(.*)/", $r['info_url'], $match))
                  {
                      $cc = null;
                      switch($match[2])
                      {
                        case 'com' : $cc = 1; break;
                        case 'co.uk' : $cc = 2; break;
                        case 'co.jp' : $cc = 9; break;
                        case 'de' : $cc = 3; break;
                        case 'fr' : $cc = 8; break;
                        case 'jp' : $cc = 9; break;
                      }
                      if ($cc != null)
                        $coverart[] = array(
                          'primary' => true,
                          'uri' => sprintf('http://images.amazon.com/images/P/%s.0%d.LZZZZZZZ.jpg', $match[3], $cc),
                          'uri150' => sprintf('http://images.amazon.com/images/P/%s.0%d._SL150_.jpg', $match[3], $cc));
                  }
                  if ($coverart)
                    $r['coverart'] = $coverart;
		}
		return $mbmeta;
	}

	static function Hex2Int($hex, $signed = false)
	{
          $dec = hexdec($hex);
	  if ($signed) {
            $max = pow(2, 32);
            $_dec = $max - $dec;
            return (int)($dec > $_dec ? -$_dec : $dec);
	  }
	  return $dec;
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
}
?>
