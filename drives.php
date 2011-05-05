<?php
include 'logo_start.php';

function topercent(&$item, $key, $total)
{
    $item = $item * 100 / $total;
}

function dograph($dbconn, $label, $where, $limit, $width, $height)
{
  $query = "select " . $label . " as label, count(*) as cnt FROM submissions " . $where . " GROUP BY label ORDER BY cnt DESC LIMIT $1";
  $params = array($limit);
  $result = pg_query_params($dbconn, $query, $params)
    or die('Query failed: ' . pg_last_error());
  $agents = pg_fetch_all_columns($result, 0);
  $counts = pg_fetch_all_columns($result, 1);
  pg_free_result($result);
  $result = pg_query($dbconn, "SELECT count(*) as total FROM submissions " . $where)
    or die('Query failed: ' . pg_last_error());
  $total_row = pg_fetch_row($result);
  $total = $total_row[0];
  pg_free_result($result);
  $total2 = array_sum($counts);
  array_walk($counts, 'topercent', $total);
  $agents_str = implode('|', $agents) . '|Other';
  $counts_str = implode(',', $counts) . ',' . (($total - $total2) * 100 / $total);
  printf('<img align=middle src="http://chart.apis.google.com/chart?cht=p3&chs=%sx%s&chco=4477DD&chl=%s&chd=t:%s"><br>', $width, $height, $agents_str, $counts_str);
}
//dograph($dbconn,"drivename","WHERE drivename IS NOT NULL", 20, 800, 240);
dograph($dbconn,"substring(drivename from '([^ ]*) ')","WHERE drivename IS NOT NULL", 17, 800, 240);
dograph($dbconn,"substring(agent from '([^:(]*)( [(]|:|$)')", "WHERE agent IS NOT NULL", 10, 800, 240);
?>
</center>
</body>
</html>
