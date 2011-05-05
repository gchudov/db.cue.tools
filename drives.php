<?php
include 'logo_start.php';

function topercent(&$item, $key, $total)
{
    $item = $item * 100 / $total;
}

function dograph($dbconn, $query, $params, $width, $height)
{
  $result = pg_query_params($dbconn, $query, $params)
    or die('Query failed: ' . pg_last_error());
  $agents = pg_fetch_all_columns($result, 0);
  $counts = pg_fetch_all_columns($result, 1);
  $total = array_sum($counts);
  array_walk($counts, 'topercent', $total);
  pg_free_result($result);
  $agents_str = implode('|', $agents);
  $counts_str = implode(',', $counts);
  printf('<img align=middle src="http://chart.apis.google.com/chart?cht=p3&chs=%sx%s&chco=4477DD&chl=%s&chd=t:%s"><br>', $width, $height, $agents_str, $counts_str);
}
$query="select drivename, count(*) FROM submissions WHERE drivename IS NOT NULL GROUP BY drivename ORDER BY count(*) DESC LIMIT $1";
dograph($dbconn,$query,array(15),800,240);
$query="select substring(agent from '([^:(]*)( [(]|:|$)') as ag, count(*) FROM submissions WHERE agent IS NOT NULL GROUP BY ag ORDER BY count(*) DESC LIMIT $1";
dograph($dbconn,$query,array(15),800,240);
?>
</center>
</body>
</html>
