<?php

require_once "PFS0.php";

require_once dirname(__FILE__) . '/../config.default.php';
if (file_exists(dirname(__FILE__) . '/../config.php')) {
    require_once dirname(__FILE__) . '/../config.php';
}


function guessFileType($path, $internalcheck = false)
{
    if ($internalcheck == true) {
        $fh = fopen($path, "r");
        $magicdata = fread($fh, 0x104);
        if (substr($magicdata, 0, 4) == "PFS0") {
            $numFiles = unpack("V", substr($magicdata, 4, 0x04))[1];
            $stringTableSize = unpack("V", substr($magicdata, 8, 0x04))[1];
            $stringTableOffset = 0x10 + 0x18 * $numFiles;
            fseek($fh, 0);
            $magicdata = fread($fh, $stringTableOffset + $stringTableSize);
            $pfs0 = new PFS0($magicdata, 0, $stringTableOffset + $stringTableSize);
            $pfs0->getHeader();
            $isnsz = false;
            for ($i = 0; $i < count($pfs0->filesList); $i++) {
                $parts = explode('.', strtolower($pfs0->filesList[$i]->name));
                if ($parts[count($parts) - 1] == "ncz") {
                    $isnsz = true;
                }
            }
            if ($isnsz) {
                return "NSZ";
            }
            return "NSP";
        }
        if (substr($magicdata, 0x100, 4) == "HEAD") {
            return "XCI";
        }
        fclose($fh);
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

function is_32bit()
{
    return PHP_INT_SIZE === 4;
}

function getFileSize($filename)
{
    if (!is_32bit()) {
        $size = filesize($filename);
        return $size;
    }
    return getFileSize32bit($filename);
}

/* Hack for get filesize on big files > 4GB  on 32bit Systems */
function getFileSize32bit($filename)
{
    global $contentUrl;
    global $gameDir;
    $urlfilename = str_replace($gameDir, $contentUrl, $filename);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, getURLSchema() . '://' . "127.0.0.1" . implode('/', array_map('rawurlencode', explode('/', $urlfilename))));
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
    if (
        (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')
    ) {
        $server_request_scheme = 'https';
    }
    return $server_request_scheme;
}

function romInfo($path)
{
    global $keyList;
    $filePath = realpath($path);
    $fileType = guessFileType($filePath, true);
    if ($fileType == "NSP" || $fileType == "NSZ") {
        $nsp = new NSP($filePath, $keyList);
        $nsp->getHeaderInfo();
        $ret = $nsp->getInfo();
        $ret->fileType = $fileType;
        return $ret;
    } elseif ($fileType == "XCI") {
        $xci = new XCI($filePath, $keyList);
        $xci->getMasterPartitions();
        $xci->getSecurePartition();
        $ret = $xci->getInfo();
        $ret->fileType = $fileType;
        return $ret;
    }
    return false;
}

function renameRom($oldName, $preview = true)
{
    global $gameDir, $enableDecryption, $keyList;

    $error = false;
    $newName = "";
    if ($romInfo = romInfo(realpath($gameDir . '/' . $oldName))) {
        $titlesJson = getMetadata("titles");
        $titleId = strtoupper($romInfo->titleId);
        $titleIdType = getTitleIdType($titleId);
        $baseTitleId = $titleId;
        $typeTag = "";
        if ($titleIdType == 'update' || $titleIdType == 'dlc') {
            $baseTitleId = getBaseTitleId($titleId);
            //$typeTag = "[" . (($titleIdType == 'update') ? "UPD" : "DLC") . "]";
        }
        $baseTitleName = "";
        if (array_key_exists($baseTitleId, $titlesJson)) {
            $baseTitleName = preg_replace("/[^[:alnum:][:space:]_-]/u", '', $titlesJson[$baseTitleId]['name']);
        } else {
            $error = true;
        }
        $dlcNameNice = "";
        if ($titleIdType == 'dlc') {
            if (array_key_exists($titleId, $titlesJson)) {
                $dlcName = preg_replace("/[^[:alnum:][:space:]_-]/u", '', $titlesJson[$titleId]['name']);
                $dlcNameNice = "(" . trim(str_replace($baseTitleName, '', $dlcName)) . ") ";
            } else {
                $error = true;
            }
        }
        $newName = $baseTitleName . '/' . $baseTitleName . " " . $dlcNameNice . $typeTag . "[" . $titleId . "][v" . $romInfo->version . "]." . strtolower($romInfo->fileType);

        if (!$error && !$preview) {
            if (!file_exists($gameDir . '/' . $baseTitleName)) {
                mkdir($gameDir . '/' . $baseTitleName);
            }
            rename($gameDir . '/' . $oldName, $gameDir . '/' . $newName);
        }
    } else {
        $error = true;
    }

    return json_encode(array(
        "int" => $error ? -1 : 0,
        "old" => $oldName,
        "new" => $newName,
    ));
}
