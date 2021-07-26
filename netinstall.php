<?php

require 'config.default.php';
if (file_exists('config.php')) {
    require 'config.php';
}

$hostip =$_SERVER['SERVER_ADDR'];
$hostport = $_SERVER['SERVER_PORT'];



$service_port = 2000;


$address = $_POST["switchaddress"];
$filelist = json_decode($_POST["urllist"]);

$listpayload = "";

foreach ($filelist as $myfile){
  
    $listpayload = $listpayload. $hostip .":". $hostport . implode('/', array_map('rawurlencode', explode('/', $contentUrl . $myfile))) . "\n";
}

$listpayload = mb_convert_encoding($listpayload,'ASCII');


$finalpayload = pack("N",strlen($listpayload)).$listpayload;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die();
} else {
  
}

$result = socket_connect($socket, $address, $service_port);
if ($result === false) {
    die();
} else {
  
}

socket_write($socket,$finalpayload,strlen($finalpayload));

?>