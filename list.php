<table class="ctdbbox"><tr><td>
<table class=classy_table cellpadding=3 cellspacing=0><tr bgcolor=#D0D0D0><th>Artist</th><th>Album</th><th>Disc Id</th><th>Tracks</th><th>CTDB Id</th><th>AR</th></tr>
<?php
while(true == ($record = pg_fetch_array($result)))
{
  printf('<tr><td class=td_artist><a href=?artist=%s>%s</a></td><td class=td_album>%s</td><td class=td_discid><a href="index.php?tocid=%s">%s</a></td><td class=td_ar>%s</td><td class=td_ctdbid><a href="show.php?id=%d">%08x</a></td><td class=td_ar>%d</td></tr>' . "\n", urlencode($record['artist']), mb_substr($record['artist'],0,60), mb_substr($record['title'],0,60), $record['tocid'], $record['tocid'], ($record['firstaudio'] > 1) ? ('1+' . $record['audiotracks']) : (($record['audiotracks'] < $record['trackcount']) ? ($record['audiotracks'] . '+1') : $record['audiotracks']), $record['id'], $record['crc32'], $record['confidence']);
}
if (pg_num_rows($result) == $count) 
  printf('<tr><td colspan=6 align=right><a class=style1 href="?start=%d%s">More</a></td></tr>', $start + $count, $url);
?>
</table
</td></tr></table>;
