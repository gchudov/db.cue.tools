<?php
$retval = 0;
ob_start();
passthru('/opt/ctdb/www/ctdbweb/utils/ctdbconvert upconvert ' . (int)$_GET['id'], &$retval);
if ($retval != 0)
{
  ob_end_clean();
  header($_SERVER["SERVER_PROTOCOL"]." 501 Internal Server Error");
  header("Status: 501 Internal Server Error");
  exit();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=ctdbdump.bin');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . ob_get_length());
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
ob_end_flush();
exit();
?>
