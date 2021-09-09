<?php

require_once "PFS0.php";
require_once "NSP.php";
require_once "XCI.php";
require_once "TAR.php";

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
			fseek($fh, 0x10);
			$isnsz = false;
			for ($i = 0; $i < $numFiles; $i++) {
				$dataOffset = unpack("P", fread($fh, 8))[1];
				$dataSize = unpack("P", fread($fh, 8))[1];
				$stringOffset = unpack("V", fread($fh, 4))[1];
				fread($fh, 4);
				$storePos = ftell($fh);
				fseek($fh, $stringTableOffset + $stringOffset);
				$filename = "";
				while (true) {
					$byte = unpack("C", fread($fh, 1))[1];
					if ($byte == 0x00) break;
					$filename = $filename . chr($byte);
				}
				$parts = explode('.', strtolower($filename));
				$file = new stdClass();
				$file->name = $filename;
				$file->filesize = $dataSize;
				$file->fileoffset = $dataOffset;
				if ($parts[count($parts) - 1] == "ncz") {
                    $isnsz = true;
                }
				fseek($fh, $storePos);
			}
			
            if ($isnsz) return "NSZ";
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
    if ((!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')) {
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
		$haveupdatepartition = $xci->getUpdatePartition();
        $ret = $xci->getInfo();
        $ret->fileType = $fileType;
        return $ret;
    }
    return false;
}

function romFileListContents($romfilename,$romfile){
	global $keyList;
	global $gameDir;
	
	$keyList = parse_ini_file("/root/.switch/prod.keys");
	
	if(guessFileType($romfilename) == "NSP"){	
		$nsp = new NSP(realpath($romfilename), $keyList);
		$nsp->getHeaderInfo();
		$fileidx = -1;
		for($i=0;$i<count($nsp->filesList);$i++){
			if($nsp->filesList[$i]->name == $romfile){
				$fileidx = $i;
				break;
			}
		}
		if($fileidx == -1){
			die();
		}
		
		fseek($nsp->fh, $nsp->fileBodyOffset + $nsp->filesList[$fileidx]->fileoffset);
		$ncafile = new NCA($nsp->fh, $nsp->fileBodyOffset + $nsp->filesList[$fileidx]->fileoffset, $nsp->filesList[$fileidx]->filesize, $keyList,$nsp->ticket->titleKey);
		
		$ncafile->readHeader();
		$ncafile->getFs();
		
		$ncafilesList = array();
		
		if($ncafile->pfs0){
			$ncafilesList["pfs0"] = $ncafile->pfs0->filesList;
		}
		if($ncafile->romfsidx>-1){
			$ncafile->getRomfs($ncafile->romfsidx);
			$ncafilesList["romfs"] = $ncafile->romfs->Files;
		}
		return ncafilesList;
		
	}
	return false;
}

function romFile($romfilename,$romfile){
	global $keyList;
	global $gameDir;
	
	if(guessFileType($romfilename) == "NSP" || guessFileType($romfilename) =="NSZ"){	
		$nsp = new NSP(realpath($romfilename), $keyList);
		$nsp->getHeaderInfo();
		$fileidx = -1;
		for($i=0;$i<count($nsp->filesList);$i++){
			if($nsp->filesList[$i]->name == $romfile){
				$fileidx = $i;
				break;
			}
		}
		if($fileidx == -1){
			die();
		}
		$size = $nsp->filesList[$fileidx]->filesize;
		$chunksize = 5 * (1024 * 1024);
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.$size);
		header('Content-Disposition: attachment;filename="'.$romfile.'"');
		$tmpchunksize = $size;
		fseek($nsp->fh, $nsp->fileBodyOffset + $nsp->filesList[$fileidx]->fileoffset);
		if($size > $chunksize)
		{ 
			while ($tmpchunksize>$chunksize)
			{ 
				echo(fread($nsp->fh, $chunksize));
				$tmpchunksize -=$chunksize;
				ob_flush();
				flush();
			}
			if($tmpchunksize>0){
			echo(fread($nsp->fh, $tmpchunksize));
			ob_flush();
			flush();
			}
         
		}
		if($size < $chunksize){
		  echo(fread($nsp->fh, $tmpchunksize));
          ob_flush();
          flush();
		}
		fclose($nsp->fh);
		die();
	}
	if(guessFileType($romfilename) == "XCI" || guessFileType($romfilename) =="XCZ"){
		$xci = new XCI(realpath($romfilename), $keyList);
		
		
		$xci->getMasterPartitions();
		$xci->getSecurePartition();
		$fileidx = -1;
		for($i=0;$i<count($xci->securepartition->filesList);$i++){
			if($xci->securepartition->filesList[$i]->name == $romfile){
				$fileidx = $i;
				break;
			}
		}
		if($fileidx == -1){
			die();
		}
		$size = $xci->securepartition->filesList[$fileidx]->filesize;
		$chunksize = 5 * (1024 * 1024);
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.$size);
		header('Content-Disposition: attachment;filename="'.$romfile.'"');
		$tmpchunksize = $size;
		fseek($xci->fh, $xci->securepartition->rawdataoffset + $xci->securepartition->file_array[$fileidx]->fileoffset);
		if($size > $chunksize)
		{ 
			while ($tmpchunksize>$chunksize)
			{ 
				echo(fread($xci->fh, $chunksize));
				$tmpchunksize -=$chunksize;
				ob_flush();
				flush();
			}
			if($tmpchunksize>0){
			echo(fread($xci->fh, $tmpchunksize));
			ob_flush();
			flush();
			}
         
		}
		if($size < $chunksize){
		  echo(fread($xci->fh, $tmpchunksize));
          ob_flush();
          flush();
		}
		fclose($xci->fh);
		die();
		
		
	}
	
	
	
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

function XCIUpdatePartition($xcifilename,$tarfilename){
	
	global $gameDir, $enableDecryption, $keyList;
	
	$xci = new XCI(realpath($xcifilename),$keyList);
	
	$xci->getMasterPartitions();
	$xci->getSecurePartition();
	$xci->GetUpdatePartition();
	
	$tar = new TAR();
	
	$tarfinalsize = 0;
	for($i=0;$i<count($xci->updatepartition->filesList);$i++){
		$tarentry = $tar->getTarHeaderFooter($xci->updatepartition->filesList[$i]->name,$xci->updatepartition->filesList[$i]->filesize,0);
		$tarfinalsize += strlen($tarentry[0]);
		$tarfinalsize += $xci->updatepartition->filesList[$i]->filesize;
		$tarfinalsize += strlen($tarentry[1]);
	}
	header( 'Content-type: archive/tar' );
	header( 'Content-Disposition: attachment; filename="' . basename( $tarfilename ) . '"'  );
	header( 'Content-Transfer-Encoding: binary' );
	header( 'Content-Length: ' . $tarfinalsize  );
	
	
	for($i=0;$i<count($xci->updatepartition->filesList);$i++){
		$tar->AddFile($xci->updatepartition->filesList[$i]->name,$xci->fh,$xci->updatepartition->filesList[$i]->offset,$xci->updatepartition->filesList[$i]->filesize);
	}
	die();
	
	
}

