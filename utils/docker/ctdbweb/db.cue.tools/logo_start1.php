<?php
mb_internal_encoding("UTF-8");
include_once('auth.php');
$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=pgbouncer port=6432")
    or die('Could not connect: ' . pg_last_error());
$realm = 'ctdb';
$userinfo = getAuth($realm);
$isadmin = $userinfo && $userinfo['admin'];
?>
