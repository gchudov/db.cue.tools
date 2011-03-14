<?php
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=ctdbdump.tar');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
passthru('tar c parity/');
?>
