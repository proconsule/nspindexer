<?php
require 'config.default.php';

if (file_exists('config.php')) {
    require 'config.php';
}
require_once(__DIR__ . '/lib/extlibs/Flow/Autoloader.php');

Flow\Autoloader::register();

if (1 == mt_rand(1, 100)) {
    \Flow\Uploader::pruneChunks(__DIR__ . '/cache/tmp_upload');
}

$config = new Flow\Config(array(
   'tempDir' => __DIR__ . '/cache/tmp_upload'
));
$request = new Flow\Request();
if (\Flow\Basic::save($gameDir. DIRECTORY_SEPARATOR .$request->getFileName(), $config, $request)) {
  
}

?>