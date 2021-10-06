<?php

/*
 * You should copy this file to 'config.php' to customize it
 */

ini_set('memory_limit', '256M'); /* To prevent low memory errors anyway it may fail if php.ini have a limit set. if so edit php.ini with memory limit >= 256M */

$gameDir = "/var/www/html/switch/data/games"; /* Absolute Files Path, no trailing slash */
$contentUrl = "/switch/data/games"; /* Files URL, no trailing slash */
$allowedExtensions = array('nsp', 'xci', 'nsz', 'xcz');
$enableNetInstall = true; /* Enable Net Install feature */
$enableRename = true; /* Enable Rename feature */
$switchIp = "192.168.1.50"; /* Switch IP address for Net Install */
$netInstallSrc = false; /* Set to e.g. '192.168.0.1:80' to override source address for Net Install */
$showWarnings = true; /* Show configuration warnings on page load */

/* VARS FOR DOCKER USE */
if(getenv('NSPINDEXER_ABSPATH')){
$gameDir = getenv('NSPINDEXER_ABSPATH');
}
if(getenv('NSPINDEXER_WEBPATH')){
$contentUrl = getenv('NSPINDEXER_WEBPATH');
}

/*
 * Advanced Section (use only if you know what you are doing
 */

$keyFile = ""; /* Path to 'prod.keys', must be readable by the webserver/php, but KEEP IT SECURE via .htaccess or similar */
