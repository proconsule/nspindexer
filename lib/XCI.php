<?php

require_once "NCA.php";

class XCI
{
    function __construct($path, $keys)
    {
        if ($keys == null) {
            return false;
        }
        $this->path = $path;
        $this->keys = $keys;
        $this->open();
    }

    function open()
    {
        $this->fh = fopen($this->path, "r");
    }

    function close()
    {
        fclose($this->fh);
    }

    function getMasterPartitions()
    {
        fseek($this->fh, 0x100);
        $this->fileSignature = fread($this->fh, 4);
        if ($this->fileSignature != "HEAD") {
            return false;
        }
        fseek($this->fh, 0x130);
        $this->hfs0offset = unpack("P", fread($this->fh, 8))[1];
        $this->hfs0size = unpack("P", fread($this->fh, 8))[1];
        $this->masterpartition = new HFS0($this->fh, $this->hfs0offset, $this->hfs0size);
        $this->masterpartition->getHeaderInfo();
    }
	
	function getUpdatePartition()
    {
		if (!in_array("update", $this->masterpartition->filenames)) {
			$this->updatepartition = false;
            return false;
        }
		$this->update_index = array_search('update', $this->masterpartition->filenames);
		$this->updatepartition = new HFS0($this->fh, $this->masterpartition->rawdataoffset + $this->masterpartition->file_array[$this->update_index]->fileoffset, $this->masterpartition->file_array[$this->update_index]->filesize);
        $this->updatepartition->getHeaderInfo();
		$this->updatepartition->filesList = [];
		for ($i = 0; $i < count($this->updatepartition->filenames); $i++) {
			$file = new stdClass();
			$file->name = $this->updatepartition->filenames[$i];
            $file->filesize = $this->updatepartition->file_array[$i]->filesize;
            $file->offset = $this->updatepartition->rawdataoffset + $this->updatepartition->file_array[$i]->fileoffset;
            $this->updatepartition->filesList[] = $file;
		}
		for ($i = 0; $i < count($this->updatepartition->filesList); $i++) {
			$parts = explode('.', strtolower($this->updatepartition->filesList[$i]->name));
			if($parts[count($parts)-2] == "cnmt" && $parts[count($parts)-1] == "nca"){
					$ncafile = new NCA($this->fh, $this->updatepartition->filesList[$i]->offset,$this->updatepartition->filesList[$i]->filesize, $this->keys);
					$ncafile->readHeader();
					if($ncafile->programId == "0100000000000809"){
						$ncafile->getFs();
						$this->updatepartition->fwversion = (($ncafile->pfs0->cnmt->version  >> 26) & 0x3F) . "." . (($ncafile->pfs0->cnmt->version  >> 20) & 0x3F) . "." . (($ncafile->pfs0->cnmt->version  >> 16) & 0x3F);
						
						break;
					}
            
			}
			
		}
		return true;
		
	}

    function getSecurePartition()
    {
        if (!in_array("secure", $this->masterpartition->filenames)) {
            return false;
        }
        $this->secure_index = array_search('secure', $this->masterpartition->filenames);
        $this->securepartition = new HFS0($this->fh, $this->masterpartition->rawdataoffset + $this->masterpartition->file_array[$this->secure_index]->fileoffset, $this->masterpartition->file_array[$this->secure_index]->filesize);
        $this->securepartition->getHeaderInfo();
		$this->securepartition->filesList = [];
		

        for ($i = 0; $i < count($this->securepartition->filenames); $i++) {
			$file = new stdClass();
			$file->name = $this->securepartition->filenames[$i];
            $file->filesize = $this->securepartition->file_array[$i]->filesize;
            $file->offset = $this->securepartition->rawdataoffset + $this->securepartition->file_array[$i]->fileoffset;
            $parts = explode('.', strtolower($this->securepartition->filenames[$i]));
            $ncafile = new NCA($this->fh, $this->securepartition->rawdataoffset + $this->securepartition->file_array[$i]->fileoffset, $this->securepartition->file_array[$i]->filesize, $this->keys);
            $ncafile->readHeader();
			$file->contentType = $ncafile->contentType;
			$file->sigcheck = $ncafile->sigcheck;
			$this->securepartition->filesList[] = $file;
            if ($parts[count($parts) - 2] == "cnmt" && $parts[count($parts) - 1] == "nca") {
                $cnmtncafile = new NCA($this->fh, $this->securepartition->rawdataoffset + $this->securepartition->file_array[$i]->fileoffset, $this->securepartition->file_array[$i]->filesize, $this->keys);
                $cnmtncafile->readHeader();
                $cnmtncafile->getFs();
                $this->cnmtncafile = $cnmtncafile;
            }

            if ($ncafile->contentType == 2) {
                $ncafile->getFs();
                $ncafile->getRomfs(0);
                $this->ncafile = $ncafile;
            }
        }

    }

    function getInfo()
    {
        $infoobj = new stdClass();
        $infoobj->title = $this->ncafile->romfs->nacp->title;
        $infoobj->publisher = $this->ncafile->romfs->nacp->publisher;
        $infoobj->version = (int)$this->cnmtncafile->pfs0->cnmt->version;
        $infoobj->humanVersion = $this->ncafile->romfs->nacp->version;
        $infoobj->titleId = $this->cnmtncafile->pfs0->cnmt->id;
        $infoobj->mediaType = ord($this->cnmtncafile->pfs0->cnmt->mediaType);
        $infoobj->otherId = $this->cnmtncafile->pfs0->cnmt->otherId;
        $infoobj->sdk = $this->ncafile->sdkArray[3] . "." . $this->ncafile->sdkArray[2] . "." . $this->ncafile->sdkArray[1];
        $infoobj->gameIcon = $this->ncafile->romfs->gameIcon;
		$infoobj->filesList = $this->securepartition->filesList;
		if($this->updatepartition){
			$infoobj->fwupdateversion = $this->updatepartition->fwversion;
		}else{
			$infoobj->fwupdateversion = false;
		}
		$infoobj->reqsysversion = (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 26) & 0x3F) . "." . (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 20) & 0x3F) . "." . (($this->cnmtncafile->pfs0->cnmt->reqsysversion  >> 16) & 0x3F);
        return $infoobj;
    }

}


class HFS0
{
    function __construct($fh, $offset, $size)
    {
        $this->fh = $fh;
        $this->offset = $offset;
        $this->size = $size;
    }

    function getHeaderInfo()
    {
        fseek($this->fh, $this->offset);
        $this->HF0Signature = fread($this->fh, 4);
        if ($this->HF0Signature != "HFS0") {
            return false;
        }
        $this->numfiles = unpack("V", fread($this->fh, 4))[1];
        $this->stringtable_size = unpack("V", fread($this->fh, 4))[1];
        fseek($this->fh, $this->offset + 0x10);
        $this->file_array = array();
        for ($i = 0; $i < $this->numfiles; $i++) {
            $tmpfileobj = new stdClass();
            $tmpfileobj->fileoffset = unpack("P", fread($this->fh, 8))[1];
            $tmpfileobj->filesize = unpack("P", fread($this->fh, 8))[1];
            $tmpfileobj->stringoffset = unpack("V", fread($this->fh, 4))[1];
            $dummy = fread($this->fh, 0x2C);
            $this->file_array[] = $tmpfileobj;
        }
        $this->rawdataoffset = ftell($this->fh) + $this->stringtable_size;
        $this->filenames = array();
        $tmpfilename = "";
        for ($i = 0; $i < $this->stringtable_size; $i++) {
            $tmpchar = unpack("C", fread($this->fh, 1))[1];
            if ($tmpchar == 0x00) {
                $this->filenames[] = $tmpfilename;
                $tmpfilename = "";
            } else {
                $tmpfilename = $tmpfilename . chr($tmpchar);
            }
            if (count($this->filenames) >= $this->numfiles) break;
        }
        return true;
    }

}



# Debug Example
# use php XCI.php filepath;

/*
$mykeys = parse_ini_file("/root/.switch/prod.keys");


$xci = new XCI($argv[1],$mykeys);
$xci->getMasterPartitions();
$xci->getSecurePartition();
$xci->GetUpdatePartition();
var_dump($xci->updatepartition->filesList);
*/
//var_dump($xci->getInfo());

