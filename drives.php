<?php
include 'logo_start.php';

function topercent(&$item, $key, $total)
{
    $item = max(1, round($item * 100 / $total));
}

function icolor($i)
{
  return ($i % 2) == 0 ? '4477dd' : '77dd44';
}

function dograph($dbconn, $dbname, $label, $where, $limit, $width, $height, $title, $offset)
{
  $query = "select " . $label . " as label, count(*) as cnt FROM " . $dbname . " " . $where . " GROUP BY label ORDER BY cnt DESC LIMIT $1";
  $params = array($limit);
  $result = pg_query_params($dbconn, $query, $params)
    or die('Query failed: ' . pg_last_error());
  $agents = pg_fetch_all_columns($result, 0);
  $counts = pg_fetch_all_columns($result, 1);
  pg_free_result($result);
  $result = pg_query($dbconn, "SELECT count(*) as total FROM " . $dbname . " " . $where)
    or die('Query failed: ' . pg_last_error());
  $total_row = pg_fetch_row($result);
  $total = $total_row[0];
  pg_free_result($result);
  $total1 = array_sum($counts);
  $counts = array_slice($counts, $offset);
  $agents = array_slice($agents, $offset);
  $total2 = array_sum($counts);
  $total = $total + $total2 - $total1;
  $other_count = $total - $total2;
  topercent($other_count, null, $total);
  array_walk($counts, 'topercent', $total);
  $colors = array_map('icolor', range(1, count($counts)));
  $agents_str = implode('|', $agents) . '|Other';
  $counts_str = implode(',', $counts) . ',' . $other_count;
  $colors_str = implode(',', $colors) . ',cccccc';
  printf('<img align=middle src="http://chart.apis.google.com/chart?cht=p3&chs=%sx%s&chco=%s&chl=%s&chd=t:%s&chtt=%s"><br>', $width, $height, $colors_str, $agents_str, $counts_str, $title);
}
//dograph($dbconn,"drivename","WHERE drivename IS NOT NULL", 20, 800, 240);
dograph($dbconn,"submissions2","substring(trackoffsets from '([^ ]*) ')", "WHERE substring(trackoffsets from '([^ ]*) ') != '0' AND int4(substring(trackoffsets from '([^ ]*) ')) < 450", 8, 800, 240, "Pregap values", 0);
dograph($dbconn,"submissions2","substring(trackoffsets from '([^ ]*) ')", "WHERE substring(trackoffsets from '([^ ]*) ') != '0' AND int4(substring(trackoffsets from '([^ ]*) ')) < 450", 32, 800, 240, "Pregap values except 32, 33, 37, 75, 30, 50, 1", 7);
?>
</center>
</body>
</html>
