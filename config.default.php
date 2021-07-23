<?php

/*
 * You should copy this file to 'config.php' to customize it
 */

$scriptdir = "/var/www/html/switch/"; /* Absolute Script Path */
$gamedir = "/var/www/html/switch/data/games/"; /* Absolute Files Path */
$Host = "http://". $_SERVER['SERVER_ADDR'] ."/switch/"; /* Web Server URL */
$contentsurl = "http://". $_SERVER['SERVER_ADDR'] ."/switch/data/games/"; /* Files URL */
$allowedExtensions = array('nsp', 'xci', 'nsz', 'xcz');