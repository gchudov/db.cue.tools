<?php

function finish($body, $maxage)
{
    $etag = md5($body);
    Header("Content-type: image/png");
    header("Cache-Control: max-age=" . $maxage);
    header("ETag:  " . $etag);
    if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
        exit;
    }
    die($body);
}

$maxage = 3600;
$apc_id = md5(getlastmod() + $_SERVER['REQUEST_URI']);
if (apc_exists($apc_id))
    finish(apc_fetch($apc_id), $maxage);
 
include "qrlib.php";
$address = empty($_GET['address'])?'':$_GET['address'];
ob_start();
QRcode::png($address);
$body = ob_get_contents();
ob_end_clean();
apc_store($apc_id, $body, $maxage);
finish($body, $maxage);
