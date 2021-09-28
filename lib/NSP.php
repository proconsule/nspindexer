<?php

require_once "NCA.php";
require_once "NCZ.php";

class NSP
{
    function __construct($path, $keys = null)
    {
        $this->path = $path;
        $this->open();
        if ($keys == null) {
            $this->decryption = false;
        } else {
            $this->decryption = true;
            $this->keys = $keys;
        }
    }

    function open()
    {
        $this->fh = fopen($this->path, "r");
    }

    function close()
    {
        fclose($this->fh);
    }
	
	function getRealSize(){
		$finalsize = 0;
		for ($i = 0; $i < count($this->filesList); $i++) {
			$parts = explode('.', strtolower($this->filesList[$i]->name));
			if($parts[count($parts) - 1] == "ncz"){
				$finalsize += $this->nczfile->getOriginalSize();
			}else{
				$finalsize += $this->filesList[$i]->filesize;
			}
		}
		return $finalsize;
	}

    function getHeaderInfo()
    {
        $this->nspheader = fread($this->fh, 4);
        if ($this->nspheader != "PFS0") {
            return false;
        }

        $this->numFiles = unpack("V", fread($this->fh, 4))[1];
        $this->stringTableSize = unpack("V", fread($this->fh, 4))[1];
        $this->stringTableOffset = 0x10 + 0x18 * $this->numFiles;
        $this->fileBodyOffset = $this->stringTableOffset + $this->stringTableSize;
        fread($this->fh, 4);
        fseek($this->fh, 0x10);
        $this->HasTicketFile = false;
        $this->nspHasXmlFile = false;
        $this->ticket = new stdClass();
		$this->nspHasTicketFile = false;
		$this->ticket->titleKey = null;
		$this->nczfile = null;

        $this->filesList = [];
        for ($i = 0; $i < $this->numFiles; $i++) {
			$file = new stdClass();
			$file->dataFieldoffset = ftell($this->fh);
            $dataOffset = unpack("P", fread($this->fh, 8))[1];
            $dataSize = unpack("P", fread($this->fh, 8))[1];
            $stringOffset = unpack("V", fread($this->fh, 4))[1];
            fread($this->fh, 4);
            $storePos = ftell($this->fh);
            fseek($this->fh, $this->stringTableOffset + $stringOffset);
            $filename = "";
			$filenamestringoffset = ftell($this->fh);
            while (true) {
                $byte = unpack("C", fread($this->fh, 1))[1];
                if ($byte == 0x00) break;
                $filename = $filename . chr($byte);
            }
            $parts = explode('.', strtolower($filename));
            
            $file->name = $filename;
            $file->filesize = $dataSize;
            $file->fileoffset = $dataOffset;
			$file->sigcheck = false;
			
            if ($this->decryption) {
				if($parts[count($parts) - 1] == "ncz"){
					fseek($this->fh, $this->fileBodyOffset + $dataOffset);
                    $nczfile = new NCZ($this->fh, $this->fileBodyOffset + $dataOffset, $dataSize, $this->keys);
                    $nczfile->readHeader();
					$nczfile->ReadNCZSECT();
					$file->sigcheck = $nczfile->nczfile->sigcheck;
					$file->contentType = $nczfile->nczfile->contentType;
					$this->nczfile = $nczfile;
					$this->nczfileidx = $i;
					$this->nczfilestringoffset = $filenamestringoffset;
					
				}
				
                if ($parts[count($parts) - 1] == "nca") {
                    fseek($this->fh, $this->fileBodyOffset + $dataOffset);
                    $ncafile = new NCA($this->fh, $this->fileBodyOffset + $dataOffset, $dataSize, $this->keys);
                    $ncafile->readHeader();
					$file->sigcheck = $ncafile->sigcheck;
					$file->contentType = $ncafile->contentType;

                    if ($parts[count($parts) - 2] == "cnmt" && $parts[count($parts) - 1] == "nca") {
                        $cnmtncafile = new NCA($this->fh, $this->fileBodyOffset + $dataOffset, $dataSize, $this->keys);
                        $cnmtncafile->readHeader();
						$file->sigcheck = $ncafile->sigcheck;
                        $cnmtncafile->getFs();
						if($cnmtncafile->pfs0idx >-1){
							$cnmtncafile->getPFS0Enc($cnmtncafile->pfs0idx);
							$this->cnmtncafile = $cnmtncafile;
						}
                        
                    }

                    if ($ncafile->contentType == 2) {
                        $ncafile->getFs();
						if($ncafile->romfsidx >-1){
							$ncafile->getRomfs($ncafile->romfsidx);
							$this->ncafile = $ncafile;
						}
                    }
                }

            }
			$this->filesList[] = $file;
            if ($parts[count($parts) - 2] . "." . $parts[count($parts) - 1] == "cnmt.xml") {
                $this->nspHasXmlFile = true;
                fseek($this->fh, $this->fileBodyOffset + $dataOffset);
                $this->xmlFile = fread($this->fh, $dataSize);
            }

            if ($parts[count($parts) - 1] == "tik") {
                $this->nspHasTicketFile = true;
                fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x180);
                $titleKey = fread($this->fh, 0x10);
                fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x2a0);
                $titleRightsId = fread($this->fh, 0x10);
                $titleId = substr($titleRightsId, 0, 8);
                $this->ticket->titleKey = bin2hex($titleKey);
                $this->ticket->titleRightsId = bin2hex($titleRightsId);
                $this->ticket->titleId = bin2hex($titleId);
            }

            fseek($this->fh, $storePos);

        }
        return true;
    }

    function getInfo()
    {
        $infoobj = new stdClass();
        if ($this->decryption) {
			$infoobj->langs = $this->ncafile->romfs->nacp->langs;
            $infoobj->version = (int)$this->cnmtncafile->pfs0->cnmt->version;
            $infoobj->titleId = $this->cnmtncafile->pfs0->cnmt->id;
            $infoobj->mediaType = ord($this->cnmtncafile->pfs0->cnmt->mediaType);
			$infoobj->humanVersion = $this->ncafile->romfs->nacp->version;
            $infoobj->otherId = $this->cnmtncafile->pfs0->cnmt->otherId;
            $infoobj->sdk = $this->ncafile->sdkArray[3] . "." . $this->ncafile->sdkArray[2] . "." . $this->ncafile->sdkArray[1];
            $infoobj->compressedsize = getFileSize($this->path);
			$infoobj->originalsize = $this->getRealSize();
			if ($this->nspHasTicketFile) {
                $infoobj->titleKey = strtoupper($this->ticket->titleKey);
            } else {
                $infoobj->titleKey = "No TIK File found";
            }
			$infoobj->reqsysversion = (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 26) & 0x3F) . "." . (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 20) & 0x3F) . "." . (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 16) & 0x3F);
        
			$infoobj->filesList = $this->filesList;
			$infoobj->fwupdateversion = false;


        } elseif ($this->nspHasXmlFile) {
            $xml = simplexml_load_string($this->xmlFile);
            $infoobj->src = 'xml';
            $infoobj->titleId = substr($xml->Id, 2);
            $infoobj->version = (int)$xml->Version;
        } elseif ($this->nspHasTicketFile) {
            $infoobj->src = 'tik';
            $infoobj->titleId = $this->ticket->titleId;
            $infoobj->version = 'NOTFOUND';

        } else {
            return false;
        }
        return $infoobj;
    }
}


class NSZDecompress{
	
	function __construct($path, $keys = null)
    {
        $this->nszfile = new NSP($path,$keys);
		$this->nszfile->getHeaderInfo();
		$this->ncadecompressedsize = $this->nszfile->nczfile->getOriginalSize();
        
    }
	
	function HeaderChange(){
		$sizediff = $this->ncadecompressedsize-$this->nszfile->filesList[$this->nszfile->nczfileidx]->filesize;
		fseek($this->nszfile->fh,0);
		$srcHeader = fread($this->nszfile->fh,$this->nszfile->fileBodyOffset/*+$this->nszfile->filesList[0]->fileoffset*/);
		$dstHeader = $srcHeader;
		$dstOffset = 0;
		for($i=0;$i<count($this->nszfile->filesList);$i++){
			if($i>=$this->nszfile->nczfileidx){
				$dataOffset = $dstOffset;/*$this->nszfile->filesList[$i]->fileoffset+$sizediff;*/
				$dataSize = $this->nszfile->filesList[$i]->filesize;
				if($i== $this->nszfile->nczfileidx){
					$dataOffset = $dstOffset;
					$dataSize = $this->ncadecompressedsize;
				}
				$dstOffset = $dataSize+$dataOffset;
				$dstHeader = substr($dstHeader,0,$this->nszfile->filesList[$i]->dataFieldoffset). pack("P",$dataOffset).pack("P",$dataSize).substr($dstHeader,$this->nszfile->filesList[$i]->dataFieldoffset+16);
			}else{
				$dataOffset = $dstOffset;/*$this->nszfile->filesList[$i]->fileoffset+$sizediff;*/
				$dataSize = $this->nszfile->filesList[$i]->filesize;
				$dstOffset = $dataSize+$dataOffset;
				$dstHeader = substr($dstHeader,0,$this->nszfile->filesList[$i]->dataFieldoffset). pack("P",$dataOffset).pack("P",$dataSize).substr($dstHeader,$this->nszfile->filesList[$i]->dataFieldoffset+16);
			}
		}
		
		
		$dstHeader = substr_replace($dstHeader,"a",$this->nszfile->nczfilestringoffset+strlen($this->nszfile->filesList[$this->nszfile->nczfileidx]->name)-1,).substr($dstHeader,$this->nszfile->nczfilestringoffset+strlen($this->nszfile->filesList[$this->nszfile->nczfileidx]->name));
		print($dstHeader);
		for($i=0;$i<count($this->nszfile->filesList);$i++){
			fseek($this->nszfile->fh,$this->nszfile->fileBodyOffset + $this->nszfile->filesList[$i]->fileoffset);
			if($i== $this->nszfile->nczfileidx){
				$this->nszfile->nczfile->Decompress();
			}else{
				$n = 0;
				while($n<$this->nszfile->filesList[$i]->filesize){
				
					$chunkSz = 0x10000;
					if ($this->nszfile->filesList[$i]->filesize - $n > 0x10000){
						$chunkSz = 0x10000;
					}
					else{
						$chunkSz = $this->nszfile->filesList[$i]->filesize - $n;
					}
					print(fread($this->nszfile->fh,$chunkSz));
					$n += $chunkSz;	
				}
			}
			
		}
		
	}
	
}


#Debug Example
#use php NSP.php filepath;

/*

$mykeys = parse_ini_file("/root/.switch/prod.keys");
$nsp = new NSP($argv[1],$mykeys);
$nsp->getHeaderInfo();

var_dump($nsp);

*/
/*
$mykeys = parse_ini_file("/root/.switch/prod.keys");
$nsz = new NSZDecompress($argv[1],$mykeys);
$nsz->HeaderChange();
*/


