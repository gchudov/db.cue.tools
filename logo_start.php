<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php
mb_internal_encoding("UTF-8");
include_once('auth.php');
$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
    or die('Could not connect: ' . pg_last_error());
$realm = 'ctdb';
$userinfo = getAuth($realm);
$isadmin = $userinfo && $userinfo['admin'];
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>CUETools DB</title>
<style type="text/css">
.style1 {
	font-size: x-small;
	font-family: Arial, Helvetica, sans-serif;
}
.style_fixed {
	#font-size: x-small;
	font-family: Courier;
}
.style_logo {
	font-size: x-large;
	font-family: Arial, Helvetica, sans-serif;
}
.td_status {
	font-size: x-small;
  text-align: center;
	font-family: sans-serif;
}
.td_artist {
  padding: 1px 10px;
}
.td_album {
  padding: 1px 10px;
}
.td_discid {
  padding: 1px 5px;
	font-family: Courier;
}
.td_ctdbid {
  padding: 1px 5px;
	font-family: Courier;
}
.td_ar {
  padding: 1px 5px;
  text-align: right;
	font-family: Courier;
}
.classy_table {
  border-style:none;
#  border-color-right: #D0D0D0
#  border-color-left: #D0D0D0
}
</style>
</head>
<body>
<table border=0 cellspacing=0 cellpadding=3 align=center>
	<tr>
		<td rowspan=3><img width=128 height=128 border=0 alt="" src=ctdb.png></td>
		<td class=td_status>
<?php
$dbresult = pg_query('SELECT count(id) FROM submissions2'); 
$rec = pg_fetch_array($dbresult);
printf("%d unique discs", $rec[0]);
pg_free_result($dbresult);
?>
		</td>
	</tr>
	<tr align=center height=34%>
		<td class=style_logo>CUETools Database</td>
	</tr>
	<tr align=center height=33%>
		<td>
			<?php include 'table_start.php'; ?>
			<table width=300 border=0 cellspacing=8 cellpadding=0 align=center>
				<tr align=center>
					<td><a href=/>Home</a></td>
					<td><a href=/top.php>Popular</a></td>
					<td><a href=/about.php>About</a></td>
					<td><a href=http://www.hydrogenaudio.org/forums/index.php?showtopic=79882>Forum</a></td>
					<td><a href=http://www.cuetools.net>CUETools</a></td>
<?php 
if ($isadmin) printf('<td><a href=/recent.php>Recent</a></td>');
if ($userinfo) printf('<td><a href=/?logout=1>Logout</a></td>');
?>
				</tr>
			</table>
			<?php include 'table_end.php'; ?>
		</td>
	</tr>
</table>
<br clear=all>
