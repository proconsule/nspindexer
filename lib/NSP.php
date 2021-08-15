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
		if ($this->decryption){
			$this->title = $this->ncafile->romfs->nacp->title;
			$this->publisher = $this->ncafile->romfs->nacp->publisher;
			$this->humanversion = $this->ncafile->romfs->nacp->version;
			$this->titleId = $this->ncafile->programId;
			
		}elseif ($this->nspHasXmlFile) {
            $xml = simplexml_load_string($this->xmlFile);
            $this->src = 'xml';
            $this->titleId = substr($xml->Id, 2);
            $this->version = (int)$xml->Version;
        } elseif ($this->nspHasTicketFile) {
            $this->src = 'tik';
            $this->titleId = $this->ticket->titleId;
            $this->version = 'NOTFOUND';			
        } else {
            return false;
        }
        return $this;
    }

}


#Debug Example
#use php NSP.php filepath;

/*
$mykeys = parse_ini_file("/root/.switch/prod.keys");
$nsp = new NSP($argv[1],$mykeys);
$nsp->getHeaderInfo();

var_dump($nsp->ncafile->romfs->nacp->title);
var_dump($nsp->ncafile->romfs->nacp->publisher);
var_dump($nsp->ncafile->romfs->nacp->version);
var_dump($nsp->ncafile->programId);
*/


