<?php

include "NCA.php";

class NSP
{
    function __construct($path,$keys = null)
    {
        $this->path = $path;
        $this->open();
		if($keys == null){
			$this->decryption = false;
		}else{
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

        $this->filesList = [];
        for ($i = 0; $i < $this->numFiles; $i++) {
            $dataOffset = unpack("Q", fread($this->fh, 8))[1];
            $dataSize = unpack("Q", fread($this->fh, 8))[1];
            $stringOffset = unpack("V", fread($this->fh, 4))[1];
            fread($this->fh, 4);
            $storePos = ftell($this->fh);
            fseek($this->fh, $this->stringTableOffset + $stringOffset);
            $filename = "";
            while (true) {
                $byte = unpack("C", fread($this->fh, 1))[1];
                if ($byte == 0x00) break;
                $filename = $filename . chr($byte);
            }
            $parts = explode('.', strtolower($filename));
            $file = new stdClass();
            $file->name = $filename;
            $file->size = $dataSize;
            $file->offset = $dataOffset;
            $this->filesList[] = $file;
			if($this->decryption){
				
				
				
				if ($parts[count($parts)-1] == "nca"){
					fseek($this->fh, $this->fileBodyOffset + $dataOffset);
					$ncafile = new NCA($this->fh,$this->fileBodyOffset+$dataOffset,$dataSize,$this->keys);
					$ncafile->readHeader();
					
					if ($parts[count($parts)-2] == "cnmt" && $parts[count($parts)-1] == "nca"){
						$cnmtncafile = new NCA($this->fh,$this->fileBodyOffset+$dataOffset,$dataSize,$this->keys);
						$cnmtncafile->readHeader();
						$cnmtncafile->getFs();
						$this->cnmtncafile = $cnmtncafile;
				    }
					
					
					if($ncafile->contentType == 2){
						$ncafile->getFs();
						$ncafile->getRomfs(0);
						$this->ncafile = $ncafile;
					}
				}
			}
            if ($parts[count($parts)-2] . "." . $parts[count($parts)-1] == "cnmt.xml") {
                $this->nspHasXmlFile = true;
                fseek($this->fh, $this->fileBodyOffset + $dataOffset);
                $this->xmlFile = fread($this->fh, $dataSize);
            }

            if ($parts[count($parts)-1] == "tik") {
                $this->nspHasTicketFile = true;
                fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x180);
                $titleKey = fread($this->fh, 0x10);
                fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x2a0);
                $titleRightsId = fread($this->fh, 0x10);
                $titleId = substr($titleRightsId,0,8);
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
		if ($this->decryption){
			$infoobj->title = $this->ncafile->romfs->nacp->title;
			$infoobj->publisher = $this->ncafile->romfs->nacp->publisher;
			$infoobj->version = (int)$this->cnmtncafile->pfs0->cnmt->version;
			$infoobj->humanVersion = $this->ncafile->romfs->nacp->version;
			$infoobj->titleId = $this->cnmtncafile->pfs0->cnmt->id;
			$infoobj->mediaType = ord($this->cnmtncafile->pfs0->cnmt->mediaType);
			$infoobj->otherId = $this->cnmtncafile->pfs0->cnmt->otherId;
			$infoobj->sdk = $this->ncafile->sdkArray[3]. "." . $this->ncafile->sdkArray[2].".".$this->ncafile->sdkArray[1];
			$infoobj->gameIcon = $this->ncafile->romfs->gameIcon;
			if($this->nspHasTicketFile){
				$infoobj->titleKey = strtoupper($this->ticket->titleKey);
			}else{
				$infoobj->titleKey = "No TIK File found";
			}
			
			
		}elseif ($this->nspHasXmlFile) {
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


#Debug Example
#use php NSP.php filepath;

/*
$mykeys = parse_ini_file("/root/.switch/prod.keys");
$nsp = new NSP($argv[1],$mykeys);
$nsp->getHeaderInfo();

var_dump($nsp->getInfo());
*/
