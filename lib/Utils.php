<?php

require_once "PFS0.php" ;

require_once dirname(__FILE__) . '/../config.default.php';
if (file_exists(dirname(__FILE__) . '/../config.php')) {
    require_once dirname(__FILE__) .'/../config.php';
}


function guessFileType($path, $internalcheck = false)
{
    if ($internalcheck == true) {
        $fh = fopen($path, "r");
        $magicdata = fread($fh, 0x104);
        fclose($fh);
        if (substr($magicdata, 0, 4) == "PFS0") {
			$pfs0 = new PFS0($magicdata,0,0x104);
			$pfs0->getHeader();
			$isnsz = false;
			for ($i = 0; $i < count($pfs0->filesList); $i++) {
				$parts = explode('.', strtolower($pfs0->filesList[$i]->name));
				if ($parts[count($parts)-1] == "ncz"){
					$isnsz = true;
				}
			}
			if($isnsz)return "NSZ";
            return "NSP";
        }
        if (substr($magicdata, 0x100, 4) == "HEAD") {
            return "XCI";
        }
    } else {
        $parts = explode('.', strtolower($path));
        if ($parts[count($parts) - 1] == "nsp") {
            return "NSP";
        }
        if ($parts[count($parts) - 1] == "xci") {
            return "XCI";
        }
		 if ($parts[count($parts) - 1] == "nsz") {
            return "NSZ";
        }
    }
    return "UNKNOWN";
}

function is_32bit(){
  return PHP_INT_SIZE === 4;
}

function getFileSize($filename)
{
    if(!is_32bit()){
       $size = filesize($filename);
       return $size;
    }
    return getFileSize32bit($filename);
}

/* Hack for get filesize on big files > 4GB  on 32bit Systems */
function getFileSize32bit($filename){
	global $contentUrl;
	global $gameDir;
	$urlfilename = str_replace($gameDir,$contentUrl,$filename);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, getURLSchema() . '://' . "127.0.0.1" . implode('/', array_map('rawurlencode', explode('/',$urlfilename))));
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	$data = curl_exec($ch);
	curl_close($ch);
	if ($data !== false && preg_match('/Content-Length: (\d+)/', $data, $matches)) {
		return $matches[1];
	}
	
}

function getURLSchema()
{
    $server_request_scheme = "http";
    if ((!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')) {
        $server_request_scheme = 'https';
    }
    return $server_request_scheme;
}

