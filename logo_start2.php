<?php
$dbresult = pg_query($dbconn,"SELECT reltuples FROM pg_class WHERE oid = 'submissions2'::regclass"); 
$rec = pg_fetch_array($dbresult);
$cntunique = $rec[0];
pg_free_result($dbresult);
?>
<title>CUETools DB</title>
<link rel="stylesheet" type="text/css" href="/ctdb.css" />
</head>
<body>
<ul id="nav">
  <li><img width=64 height=64 border=0 alt="" src=ctdb64.png></li>
  <li id="nav-1"><a href="/">Home</a></li>
  <li id="nav-2"><a href="/top.php">Popular</a></li>
  <li id="nav-3"><a href="/about.php">About</a></li>
	<li id="nav-4"><a href="http://www.hydrogenaudio.org/forums/index.php?showtopic=79882" target="_blank">Forum</a></li>
	<?php if ($isadmin) { ?><li id="nav-6"><a href="/recent.php">Recent</a></li><?php }?>
	<?php if ($isadmin) { ?><li id="nav-7"><a href="/?logout=1">Logout</a></li><?php }?>
	<li id="nav-8"><a href="/downloads/CUETools.CTDB.EACPlugin.rar" target="_blank">EAC Plugin</a></li>
	<li id="nav-0"><a href="http://www.cuetools.net" target="_blank">CUETools</a></li>
	<li id="nav-11"><a><?php echo 'CUETools DB: ' . $cntunique . ' unique discs'?></a></li>
</ul>
<br clear=all>
