<?php

require_once( 'phpctdb/ctdb.php' );

//if ($_SERVER['HTTP_USER_AGENT'] != "CUETools 205") {
//  echo "user agent ", $_SERVER['HTTP_USER_AGENT'], " is not allowed";
//  return;
//}
if (!@$_GET['id'])
  die('outdated client version');

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
	or die('Could not connect: ' . pg_last_error());

$id = $_GET['id'];
$tocid = $_GET['tocid'];

$result = pg_query_params($dbconn, "UPDATE submissions2 SET confidence=confidence+1 WHERE id=$1 AND tocid=$2", array($id, $tocid))
 	or die('Query failed: ' . pg_last_error());
if (pg_affected_rows($result) < 1) die('not found');
if (pg_affected_rows($result) > 1) die('not unique');
pg_free_result($result);

$record3 = false;
$record3['entryid'] = $id;
$record3['confidence'] = 1;
$record3['userid'] = @$_GET['userid'];
$record3['agent'] = $_SERVER['HTTP_USER_AGENT'];
$record3['time'] = gmdate ("Y-m-d H:i:s");
$record3['ip'] = $_SERVER["REMOTE_ADDR"];
pg_insert($dbconn,'submissions',$record3);

printf("entry #%s has been confirmed", $id);
?>
