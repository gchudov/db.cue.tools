<?php
include 'logo_start.php'; 

if ((@$_GET['login'] && !$userinfo) || (@$_GET['logout'] && $userinfo)) makeAuth1($realm, 'Login requested');

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

$start = @$_GET['start'] == '' ? 0 : @$_GET['start'];
$query = $query . " ORDER BY id DESC OFFSET " . pg_escape_string($start) . " LIMIT " . pg_escape_string($count);
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) == 0)
  die('nothing found');
printf("<center><h3>Recent additions:</h3>");
include 'list.php';
pg_free_result($result);
if ($where_discid != '' && $isadmin) {
  printf('<br>');
  include 'table_start.php';
  printf('<table width=100%% class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Date</th><th>Agent</th><th>User</th><th>Ip</th><th>CTDB Id</th><th>AR</th></tr>');

  $result = pg_query_params($dbconn, "SELECT time, agent, userid, ip, s.entryid as entryid, s.confidence as confidence, crc32 FROM submissions s INNER JOIN submissions2 e ON e.id = s.entryid WHERE e.tocid = $1 ORDER by entryid DESC", array($where_discid))
    or die('Query failed: ' . pg_last_error());
  while (TRUE == ($record3 = pg_fetch_array($result)))
    printf('<tr><td class=td_ar>%s</td><td class=td_ar><a href="/recent.php?agent=%s">%.15s</a></td><td class=td_ar><a href="/recent.php?uid=%s">%s</a></td><td class=td_ar>%s</td><td class=td_ar>%08x</td><td class=td_ar>%d</td></tr>',
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
