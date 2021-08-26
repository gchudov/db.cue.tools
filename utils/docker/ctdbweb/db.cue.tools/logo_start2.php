<?php require_once 'ctdbcfg.php';?>
<script>

window.onblur = function() { window.ctdb_blurred = true; };
window.onfocus = function() { window.ctdb_blurred = false; };

var totals_http = new XMLHttpRequest();
var totals_timer;
totals_http.onreadystatechange=function() {
  if (totals_http.readyState != 4) return;
  if (totals_http.status == 200)
  {
    var totals_val = JSON.parse(totals_http.responseText);
    document.getElementById('ctdbtotals').innerHTML = 'CUETools DB:<br><span style="font-family: Consolas,\'Lucida Console\',\'DejaVu Sans Mono\',monospace;">' + totals_val.unique_tocs + '</span> discs<br><span style="font-family: Consolas,\'Lucida Console\',\'DejaVu Sans Mono\',monospace;">' + totals_val.submissions + '</span> rips';
  }
  totals_timer = setTimeout("updateTotals()",5000);
};
function updateTotals()
{
  if (window.ctdb_blurred)
  {
    totals_timer = setTimeout("updateTotals()",1000);
  }
  else
  {
    totals_http.open("GET", '/statsjson.php?type=totals', true);
    totals_http.send(null);
  }
}
google.setOnLoadCallback(updateTotals());
</script>
<title>CUETools DB</title>
<link rel="shortcut icon" href="<?php echo $ctdbcfg_s3?>/favicon.ico" type="image/x-icon">
<link rel="stylesheet" type="text/css" href="<?php echo $ctdbcfg_s3?>/ctdb.css?id=<?php echo $ctdbcfg_s3_id?>" />
<!--link rel="stylesheet" type="text/css" href="http://s3.cuetools.net/ctdb12.css" /-->
</head>
<body>
<div id="ctdbheader">
<span id="ctdbtotals"></span>
<span id="ctdbsponsor" style="float:right;">
<iframe src="https://github.com/sponsors/gchudov/button" title="Sponsor gchudov" height="35" width="116" style="border: 0;"></iframe>
</span>
<div id="ctdbmenu">
<a class="ctdbmenu01" href="/">Home</a>
<a class="ctdbmenu02" href="/top.php">Popular</a>
<a class="ctdbmenu03" href="/stats.php">Stats</a>
<a class="ctdbmenu04" href="http://cue.tools/wiki/CUETools_Database" target="_blank">About</a>
<a class="ctdbmenu05" href="http://www.hydrogenaudio.org/forums/index.php?showtopic=79882" target="_blank">Forum</a>
<?php if ($isadmin) { ?><a class="ctdbmenu06" href="/recent.php">Recent</a><?php }?>
<?php if ($isadmin) { ?><a class="ctdbmenu07" href="/?logout=1">Logout</a><?php }?>
<a class="ctdbmenu08" href="http://cue.tools/wiki/CTDB_EAC_Plugin" target="_blank">EAC Plugin</a>
<a id="ctdbmenu09" href="http://cue.tools/wiki/CUETools" target="_blank">CUETools</a>
</div>
<div id="ctdbtitle"><?php echo $ctdb_page_title;?></div>
</div>
<br clear=all>
