<?php
mb_internal_encoding("UTF-8");
$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=pgbouncer port=6432")
    or die('Could not connect to the database: ' . (error_get_last()['message'] ?? 'Unknown error'));
?>
