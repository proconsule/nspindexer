<?php

function guessFileType($path, $internalcheck = false)
{
    if ($internalcheck == true) {
        $fh = fopen($path, "r");
        $magicdata = fread($fh, 0x104);
        fclose($fh);
        if (substr($magicdata, 0, 4) == "PFS0") {
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
    }
    return "UNKNOWN";
}

// this is a workaround for 32bit systems and files >2GB
function getFileSize($filename)
{

    $size = filesize($filename);
    if ($size === false) {
        $fp = fopen($filename, 'r');
        if (!$fp) {
            return false;
        }
        $offset = PHP_INT_MAX - 1;
        $size = (float)$offset;
        if (!fseek($fp, $offset)) {
            return false;
        }
        $chunksize = 8192;
        while (!feof($fp)) {
            $size += strlen(fread($fp, $chunksize));
        }
    } elseif ($size < 0) {
        $size = sprintf("%u", $size);
    }
    return $size;
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
