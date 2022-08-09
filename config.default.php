<?php

/*
 * You should copy this file to 'config.php' to customize it
 */

ini_set('memory_limit', getenv("PHP_MEMORY_LIMIT")?:'256M'); /* To prevent low memory errors anyway it may fail if php.ini have a limit set. if so edit php.ini with memory limit >= 256M */


$gameDir = getenv("NSPINDEXER_GAMES_DIR")?:"/var/www/html/switch/data/games"; /* Absolute Files Path, no trailing slash */
$contentUrl = getenv("NSPINDEXER_CONTENT_URL")?:"/switch/data/games"; /* Files URL, no trailing slash */
$allowedExtensions = explode(",", getenv("NSPINDEXER_EXTENSIONS"))?:array('nsp', 'xci', 'nsz', 'xcz');
$enableNetInstall = getenv("NSPINDEXER_ENABLE_NETINSTALL")?:true; /* Enable Net Install feature */
$enableRename = getenv("NSPINDEXER_ENABLE_RENAME")?:true; /* Enable Rename feature */
$switchIp = getenv("NSPINDEXER_SWITCH_IP")?:"192.168.1.50"; /* Switch IP address for Net Install */
$netInstallSrc = getenv("NSPINDEXER_NETINSTALL_SRC")?:false; /* Set to e.g. '192.168.0.1:80' to override source address for Net Install */

/*
 * Advanced Section (use only if you know what you are doing
 */

$keyFile = getenv("NSPINDEXER_KEYFILE")?:""; /* Path to 'prod.keys', must be readable by the webserver/php, but KEEP IT SECURE via .htaccess or similar */
