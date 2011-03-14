<?php
/* Set internal character encoding to UTF-8 */
mb_internal_encoding("UTF-8");
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );
include_once('auth.php');

$realm = 'Restricted area';
$isadmin = ('admin' == getAuth($realm));
if (!$isadmin || @$_GET['logout']) makeAuth($realm);


printf("<center><h3>Recent additions:</h3>");
include 'table_start.php';
printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>Artist</th><th>Title</th><th>TOC Id</th><th>Tr.#</th><th>CTDB Id</th><th>AR</th></tr>');

$query = "";
$params = false;

$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
	$params[] = $where_discid;
  $query = 'e.tocid=$' . count($params);
}

$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
	$params[] = '%' . $where_artist . '%';
  $query = 'e.artist ilike $' . count($params);
}

$where_agent=@$_GET['agent'];
if ($where_agent != '')
{
	$params[] = $where_agent . '%';
  $query = 's.agent ilike $' . count($params);
}

$where_uid=@$_GET['uid'];
if ($where_uid != '')
{
	$params[] = $where_uid;
  $query = 's.userid=$' . count($params);
}

$where_ip=@$_GET['ip'];
if ($where_ip != '')
{
	$params[] = $where_ip;
  $query = 's.ip=$' . count($params);
}

$show_date = true;
if ($query == '')
//if ($query == '' || $query == 'ip=$1')
{
	$params[] ='24:00';
	$query = ($query == '' ? '' : $query . ' AND ') . 'now() - s.time < $' . (count($params));
	$show_date = false;
}

$result = pg_query_params($dbconn, "SELECT s.time as time, s.agent as agent, s.userid as userid, ip, s.entryid as entryid, s.confidence as confidence, e.confidence as confidence2, crc32, tocid, artist, title, firstaudio, audiotracks, trackcount FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid WHERE " . $query . " ORDER by s.subid DESC LIMIT 50", $params)
  or die('Query failed: ' . pg_last_error());
while (TRUE == ($record3 = pg_fetch_array($result)))
  printf('<tr><td class=td_discid>%s</td><td class=td_artist><a href="?agent=%s">%s</a></td><td class=td_discid><a href="?uid=%s">%.5s</a></td><td class=td_discid><a href="?ip=%s">%s</a></td><td class=td_artist><a href="?artist=%s">%s</a></td><td class=td_album>%s</td><td class=td_discid><a href="?tocid=%s">%.5s</a></td><td class=td_ar>%s</td><td class=td_ctdbid><a href="show.php?tocid=%s&id=%d">%08x</td><td class=td_ar>%s</td></tr>',
    $show_date ? $record3['time'] : substr($record3['time'],10),
    $record3['agent'], $record3['agent'],
    $record3['userid'],
    $record3['userid'],
		@$record3['ip'],
		@$record3['ip'],
		urlencode($record3['artist']),
		mb_substr($record3['artist'],0,22),
		mb_substr($record3['title'],0,32),
		$record3['tocid'],
		$record3['tocid'],
		($record3['firstaudio'] > 1) ? ('1+' . $record3['audiotracks']) : (($record3['audiotracks'] < $record3['trackcount']) ? ($record3['audiotracks'] . '+1') : $record3['audiotracks']),
		$record3['tocid'],
		$record3['entryid'],
		$record3['crc32'],
		$record3['confidence'] == $record3['confidence2'] ? $record3['confidence'] : sprintf('%d/%d', $record3['confidence'], $record3['confidence2'])
	);
pg_free_result($result);
printf("</table>");
include 'table_end.php';
printf("</center>");
?>
</body>
</html>
