<?php

$apc_id = md5($_SERVER['REQUEST_URI']);
if (apc_exists($apc_id)) {
    Header("Content-type: image/png");
    $image = apc_fetch($apc_id);
    die($image);
}
 
include "qrlib.php";
$address = empty($_GET['address'])?'':$_GET['address'];
ob_start();
QRcode::png($address);
apc_store($apc_id, ob_get_contents(), 3600);
ob_end_flush();
