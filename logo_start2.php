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
    document.getElementById('totals_span').innerHTML = 'CUETools DB:<br><span style="font-family: Consolas,\'Lucida Console\',\'DejaVu Sans Mono\',monospace;">' + totals_val.unique_tocs + '</span> discs<br><span style="font-family: Consolas,\'Lucida Console\',\'DejaVu Sans Mono\',monospace;">' + totals_val.submissions + '</span> rips';
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
<link rel="stylesheet" type="text/css" href="http://s3.cuetools.net/ctdb12.css" />
</head>
<body style="background: black;">
<!-- <ul id="nav">
  <li><img width=64 height=64 border=0 alt="" src="http://s3.cuetools.net/ctdb64.png"></li>
  <li id="nav-1"><a href="/">Home</a></li>
  <li id="nav-2"><a href="/top.php">Popular</a></li>
  <li id="nav-6"><a href="/stats.php">Stats</a></li>
  <li id="nav-3"><a href="http://www.cuetools.net/wiki/CUETools_Database" target="_blank">About</a></li>
	<li id="nav-4"><a href="http://www.hydrogenaudio.org/forums/index.php?showtopic=79882" target="_blank">Forum</a></li>
	<?php if ($isadmin) { ?><li id="nav-6"><a href="/recent.php">Recent</a></li><?php }?>
	<?php if ($isadmin) { ?><li id="nav-7"><a href="/?logout=1">Logout</a></li><?php }?>
	<li id="nav-8"><a href="http://www.cuetools.net/wiki/CTDB_EAC_Plugin" target="_blank">EAC Plugin</a></li>
	<li id="nav-0"><a href="http://www.cuetools.net/wiki/CUETools" target="_blank">CUETools</a></li>
	<li id="nav-11"><a><span id=totals_span></span></a></li>
</ul>-->
<br clear=all>
<center><h1><a style="color: red;" href="https://www.google.com/landing/takeaction/">Tell Congress: Don’t censor the Web</a></h1></center>
