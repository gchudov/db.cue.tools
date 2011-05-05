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

	static function toc2mbtoc($record)
	{
		$ids = explode(' ', $record['trackoffsets']);
		$mbtoc = sprintf('%d %d', 1, $record['audiotracks']);
		if ($record['audiotracks'] == $record['trackcount']) // Audio CD
			$mbtoc = sprintf('%s %d', $mbtoc, $ids[$record['audiotracks']] + 150);
		else // Enhanced CD
			$mbtoc = sprintf('%s %d', $mbtoc, $ids[$record['audiotracks']] + 150 - 11400);
		for ($tr = 0; $tr < $record['audiotracks']; $tr++)
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
		$mbtoc = sprintf('%02X%02X', 1, $record['audiotracks']);
		if ($record['audiotracks'] == $record['trackcount']) // Audio CD
			$mbtoc = sprintf('%s%08X', $mbtoc, $ids[$record['audiotracks']] + 150);
		else // Enhanced CD
			$mbtoc = sprintf('%s%08X', $mbtoc, $ids[$record['audiotracks']] + 150 - 11400);
		for ($tr = 0; $tr < $record['audiotracks']; $tr++)
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

	static function mblookup($mbid)
	{
		$mbconn = pg_connect("dbname=musicbrainz_db user=musicbrainz_user");
		if (!$mbconn)
			return false;
		$mbresult = pg_query_params('SELECT DISTINCT album'
		. ' FROM album_cdtoc, cdtoc'
		. ' WHERE album_cdtoc.cdtoc = cdtoc.id'
		. ' AND cdtoc.discid = $1',
		array($mbid)
		);
		$mbmeta = false;
		while(true == ($mbrecord = pg_fetch_array($mbresult)))
		{
			$mbresult2 = pg_query_params('SELECT a.name as albumname, ar.name as artistname, coverarturl'
			. ' FROM album a INNER JOIN albummeta m ON m.id = a.id, artist ar'
			. ' WHERE a.id = $1'
			. ' AND ar.id = a.artist', 
			array($mbrecord[0]));
			$mbmeta[] = pg_fetch_array($mbresult2);
			pg_free_result($mbresult2);
		}
		pg_free_result($mbresult);
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
		$record['crc32'] = $disc['CRC ']['int'];
		$record['confidence'] = $disc['CONF']['int'];
		$record['parity'] = base64_encode($this->read($disc['PAR ']['offset'], 16));
		$record['artist'] = @$disc['ART ']['value'];
		$record['title'] = @$disc['nam ']['value'];
		$record['tocid'] = phpCTDB::toc2tocid($record);
		$discid = sprintf('%03d-%s', $record['trackcount'], phpCTDB::toc2arid($record));
		$record['parfile'] = sprintf("%s/%08x.bin", phpCTDB::discid2path($discid), $record['crc32']);
		return $record;
	}

	static function pg2ctdb($dbconn, $tocid)
	{
		$result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1", array($tocid))
			or die('Query failed: ' . pg_last_error());
		$rescount = pg_num_rows($result);
		if ($rescount < 1) die('not found');

		$totalconf = 0;
		$newctdb = false;
		$newctdb['name'] = 'CTDB';
			$newhead = false;
			$newhead['name'] = 'HEAD';
				$newtotal = false;
				$newtotal['name'] = 'TOTL';
				$newtotal['value'] =	phpCTDB::BigEndian2String($totalconf,4);
			$newhead['subatoms'][] = $newtotal;
		$newctdb['subatoms'][] = $newhead;

		while (TRUE == ($record = pg_fetch_array($result)))
		{
			$totalconf += $record['confidence'];
				$newdisc = false;
				$newdisc['name'] = 'DISC';

					$newatom = false;
					$newatom['name'] = 'CRC ';
					$newatom['value'] = phpCTDB::Hex2String(sprintf('%08x',$record['crc32']));
				$newdisc['subatoms'][] = $newatom;

					$newatom = false;
					$newatom['name'] = 'NPAR';
					$newatom['value'] =	phpCTDB::BigEndian2String(8,4);
				$newdisc['subatoms'][] = $newatom;

					$newatom = false;
					$newatom['name'] = 'CONF';
					$newatom['value'] = phpCTDB::BigEndian2String((int)($record['confidence']),4);
				$newdisc['subatoms'][] = $newatom;

					$newatom = false;
					$newatom['name'] = 'PAR ';
					$newatom['value'] = base64_decode($record['parity']);
				$newdisc['subatoms'][] = $newatom;

			$target_path = phpCTDB::discid2path(sprintf('%03d-%s', $record['trackcount'], phpCTDB::toc2arid($record)));
			// can be different!!!

			$newctdb['subatoms'][] = $newdisc;
		}

		pg_free_result($result);

		$newctdb['subatoms'][0]['subatoms'][0]['value'] = phpCTDB::BigEndian2String($totalconf,4);
		
		$ftyp=false;	
		$ftyp['name'] = 'ftyp';
		$ftyp['value'] = 'CTDB';

		@mkdir($target_path, 0777, true);
		$tname = sprintf("%s/ctdb.tmp", $target_path);
		$tfp = fopen($tname, 'wb');
		phpCTDB::unparse_atom($tfp,$ftyp);
		phpCTDB::unparse_atom($tfp,$newctdb);
		fclose($tfp);
		if(!rename($tname,sprintf("%s/ctdb.bin", $target_path)))
			die('error uploading file ' . $target_path);
		return $rescount;
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

	static function discid2path($id)
	{
		$err = sscanf($id, "%03d-%04x%04x-%04x%04x-%04x%04x", $tracks, $id1a, $id1b, $id2a, $id2b, $cddbida, $cddbidb);
		$parsedid = sprintf("%03d-%04x%04x-%04x%04x-%04x%04x", $tracks, $id1a, $id1b, $id2a, $id2b, $cddbida, $cddbidb);
		if ($id != $parsedid)
			die("bad id ". $id);
		return sprintf("parity/%x/%x/%x/%s", $id1b & 15, ($id1b >> 4) & 15, ($id1b >> 8) & 15, $parsedid);
	}

	static function ctdbid2path($discid, $ctdbid)
	{
		$path = phpCTDB::discid2path($discid);
		sscanf($ctdbid, "%04x%04x", $ctdbida, $ctdbidb);
		$parsedctdbid = sprintf("%04x%04x", $ctdbida, $ctdbidb);
		if ($ctdbid != $parsedctdbid)
			die("bad id ". $ctdbid);
		return sprintf("%s/%s.bin", $path, $ctdbid);
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
