<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );
include_once('auth.php');

$realm = 'Restricted area';
$isadmin = ('admin' == getAuth($realm));
if ((@$_GET['login'] && !$isadmin) || @$_GET['logout']) makeAuth($realm);

$count = 20;
$query = 'SELECT * FROM submissions2';
$term = ' WHERE ';
$url = '';
$where_discid=@$_GET['tocid'];
if ($where_discid != '')
{
  $query = $query . $term . "tocid='" . pg_escape_string($where_discid) . "'";
  $term = ' AND ';
  $url = $url . '&tocid=' . urlencode($where_discid);
}
$where_artist=@$_GET['artist'];
if ($where_artist != '')
{
  $query = $query . $term . "artist ilike '%" . pg_escape_string($where_artist) . "%'";
  $term = ' AND ';
  $url = $url . '&artist=' . urlencode($where_artist);
}
$where_uid=@$_GET['uid'];
if ($where_uid != '')
{
  $query = $query . $term . "userid='" . pg_escape_string($where_uid) . "'";
  $term = ' AND ';
  $url = $url . '&uid=' . urlencode($where_uid);
}
$where_agent=@$_GET['agent'];
if ($where_agent != '')
{
  $query = $query . $term . "agent ilike '%" . pg_escape_string($where_agent) . "%'";
  $term = ' AND ';
  $url = $url . '&agent=' . urlencode($where_agent);
}
$query = $query . " ORDER BY id";
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
$start = @$_GET['start'];
if (pg_num_rows($result) == 0)
  die('nothing found');
if ($count > pg_num_rows($result))
	$count = pg_num_rows($result);
if ($start == '') $start = pg_num_rows($result) - $count;

printf("<center><h3>Recent additions:</h3>");
include 'list.php';
pg_free_result($result);
if ($where_discid != '' && $isadmin) {
  printf('<br>');
  include 'table_start.php';
  printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>CTDB Id</th><th>AR</th></tr>');

  $result = pg_query_params($dbconn, "SELECT s.time as time, s.agent as agent, s.userid as userid, ip, s.entryid as entryid, s.confidence as confidence, crc32 FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid WHERE e.tocid = $1 ORDER by entryid DESC", array($where_discid))
    or die('Query failed: ' . pg_last_error());
  while (TRUE == ($record3 = pg_fetch_array($result)))
    printf('<tr><td class=td_ar>%s</td><td class=td_ar><a href="/?agent=%s">%.15s</a></td><td class=td_ar><a href="/?uid=%s">%s</a></td><td class=td_ar>%s</td><td class=td_ar>%08x</td><td class=td_ar>%d</td></tr>',
      $record3['time'],
      $record3['agent'], $record3['agent'],
      $record3['userid'], '*',
      @$record3['ip'],
			$record3['crc32'],
			$record3['confidence']);
  pg_free_result($result);
  printf("</table>");
  include 'table_end.php';
}
printf("</center>");
?>
</body>
</html>
