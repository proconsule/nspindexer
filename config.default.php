<?php

/*
 * You should copy this file to 'config.php' to customize it
 */

$gameDir = "/var/www/html/switch/data/games"; /* Absolute Files Path, no trailing slash */
$contentUrl = "/switch/data/games"; /* Files URL, no trailing slash */
$allowedExtensions = array('nsp', 'xci', 'nsz', 'xcz');
$enableNetInstall = true; /* Enable Net Install feature */
$enableRename = true; /* Enable Rename feature */
$switchIp = "192.168.1.50"; /* Switch IP address for Net Install */
$netInstallSrc = false; /* Set to e.g. '192.168.0.1:80' to override source address for Net Install */
/*
 * Advanced Section (use only if you know what you are doing
 */

$useKeyFile = false; /* Set to true to use NCA Decryption , it enables advanced features */
$keyfile = ""; /* Path to a prod.keys must be readable by webserver php but KEEP IT SECURE via .htaccess or similar */

