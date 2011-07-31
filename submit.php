<?php

require_once( 'phpctdb/ctdb.php' );

/**
 * Convert php.ini shorthands to byte
 *
 * @author <gilthans dot NO dot SPAM at gmail dot com>
 * @link   http://de3.php.net/manual/en/ini.core.php#79564
 */
function php_to_byte($v){
    $l = substr($v, -1);
    $ret = substr($v, 0, -1);
    switch(strtoupper($l)){
        case 'P':
            $ret *= 1024;
        case 'T':
            $ret *= 1024;
        case 'G':
            $ret *= 1024;
        case 'M':
            $ret *= 1024;
        case 'K':
            $ret *= 1024;
        break;
    }
    return $ret;
}

// Return the human readable size of a file
// @param int $size a file size
// @param int $dec a number of decimal places

function filesize_h($size, $dec = 1)
{
    $sizes = array('byte(s)', 'kb', 'mb', 'gb');
    $count = count($sizes);
    $i = 0;

    while ($size >= 1024 && ($i < $count - 1)) {
        $size /= 1024;
        $i++;
    }

    return round($size, $dec) . ' ' . $sizes[$i];
}

$file = $_FILES['uploadedfile'];

//echo $file['name'], ini_get('upload_max_filesize');

    // give info on PHP catched upload errors
    if($file['error']) switch($file['error']){
        case 1:
        case 2:
            echo sprintf($lang['uploadsize'],
                filesize_h(php_to_byte(ini_get('upload_max_filesize'))));
            echo "Error ", $file['error'];
            return;
        default:
            echo $lang['uploadfail'];
            echo "Error ", $file['error'];
    }

//if ($_SERVER['HTTP_USER_AGENT'] != "CUETools 205") {
//  echo "user agent ", $_SERVER['HTTP_USER_AGENT'], " is not allowed";
//  return;
//}

$tmpname = $file['tmp_name'];
$size = (@file_exists($tmpname)) ? filesize($tmpname) : 0;
if ($size == 0) {
  echo "no file uploaded";
  return;
}

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
    or die('Could not connect: ' . pg_last_error());

$ctdb = new phpCTDB($tmpname);
$record = $ctdb->ctdb2pg();
unset($ctdb);

//$tocidsafe = str_replace('.','+',$record['tocid']);
//$target_path = sprintf("parity/%s/%s", substr($tocidsafe, 0, 1), substr($tocidsafe, 1, 1));
$parityfile = sprintf("parity/%s%08x",  str_replace('.','+',$record['tocid']), $record['crc32']);
$record['hasparity'] = true;

if ($_SERVER['HTTP_USER_AGENT']=='CUETools 205')
	die ('outdated client version');

$result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE tocid=$1", array($record['tocid']))
  or die('Query failed: ' . pg_last_error());
$rescount = pg_num_rows($result);
while (TRUE == ($record2 = pg_fetch_array($result)))
  if ($record2['crc32'] == $record['crc32']) {
		// TODO: conirm
		die("Duplicate entry");
	}
pg_free_result($result);

$subres = pg_insert($dbconn, 'submissions2', $record);
$result= pg_query("SELECT currval('submissions2_id_seq')");

$record3 = false;
$record3['entryid'] = pg_fetch_result($result,0,0);
$record3['confidence'] = $record['confidence'];
$record3['userid'] = @$_POST['userid'];
$record3['agent'] = $_SERVER['HTTP_USER_AGENT'];
$record3['time'] = gmdate ("Y-m-d H:i:s");
$record3['ip'] = $_SERVER["REMOTE_ADDR"];
pg_insert($dbconn,'submissions',$record3);

//@mkdir($target_path, 0777, true);
if(!move_uploaded_file($tmpname, $parityfile))
  die('error uploading file ' . $tmpname . ' to ' . $parityfile);

if ($rescount > 1)
  printf("%s has been updated", $record['tocid']);
else
  printf("%s has been uploaded", $record['tocid']);
?>
