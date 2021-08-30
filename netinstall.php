<?php

require 'config.default.php';
if (file_exists('config.php')) {
    require 'config.php';
}

if (!$netInstallSrc) {
    $netInstallSrc = $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'];
}

$dstAddr = $_POST["dstAddr"];
$dstPort = 2000;

$strPayload = "";
foreach ($_POST["listFiles"] as $key => $file) {
    $strPayload .= $netInstallSrc . $contentUrl . '/' . $file . "\n";
}

$strPayload = mb_convert_encoding($strPayload, 'ASCII');
$payload = pack("N", strlen($strPayload)) . $strPayload;

$status = new stdClass;
$status->int = -1;

$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    $status->msg = "Error Creating Socket";
} else if (@socket_connect($socket, $dstAddr, $dstPort) === false) {
    $status->msg = "Error Connecting to Socket";
} else if (@socket_write($socket, $payload, strlen($payload)) === false) {
    $status->msg = "Error Writing to Socket";
} else {
    $status->msg = "OK";
    $status->int = 0;
}

header("Content-Type: application/json");
echo json_encode($status);
