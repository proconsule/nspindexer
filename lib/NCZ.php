<?php

require_once "NCA.php";

class NCZ
{
    function __construct($fh,$offset,$size, $keys = null)
	{
		$this->nczfile = new NCA($fh,$offset,$size,$keys);
		$this->offset = $offset;
		$this->keys = $keys;
		$this->fh = $fh;
		$this->useBlockCompression = false;
		$this->compressedSize = $size;
	}
	
	function readHeader()
	{
		$this->nczfile->readHeader();
	}
	
	function getOriginalSize()
	{
		$tmpsize = 0x4000;
		for($i=0;$i<count($this->sections);$i++){
			$tmpsize += $this->sections[$i]->size;
		}
		return $tmpsize;
	}
	
	function ReadNCZSECT()
	{
		fseek($this->fh,$this->offset+0x4000);
		$magic = fread($this->fh,8);
		if($magic != "NCZSECTN"){
			return false;
		}
		$sectionCount = unpack("P", fread($this->fh,8))[1];
		$this->sections = array();
		for($i=0;$i<$sectionCount;$i++){
			$tmpsection = new stdClass;
			$tmpsection->offset = unpack("P", fread($this->fh,8))[1];
			$tmpsection->size = unpack("P", fread($this->fh,8))[1];
			$tmpsection->cryptoType = unpack("P", fread($this->fh,8))[1];
			fread($this->fh,8);
			$tmpsection->cryptoKey =  fread($this->fh,16);
			$tmpsection->cryptoCounter =  fread($this->fh,16);
			$this->sections[] = $tmpsection;
		}
		$secpos = ftell($this->fh);
		$blockMagic =  fread($this->fh,8);
		if($blockMagic == "NCZBLOCK")$this->useBlockCompression = true;
		fseek($this->fh,$secpos);
		
	}
	
	function ReadNCZBLOCK(){
		$tmpblk = new StdClass;
		$tmpblk->magic = fread($this->fh,8);
		$tmpblk->version = fread($this->fh,1);
		$tmpblk->type = fread($this->fh,1);
		fread($this->fh,1);
		$tmpblk->blockSizeExponent = fread($this->fh,1);
		$tmpblk->numberOfBlocks  = unpack("V", fread($this->fh,4))[1];
		$tmpblk->decompressedSize = unpack("P", fread($this->fh,8))[1];
		$tmpblk->compressedBlockSizeList = array();
		for($i=0;$i<$tmpblk->numberOfBlocks;$i++){
			$tmpblk->compressedBlockSizeList[]  = unpack("V", fread($this->fh,4))[1];
		}
	}
	
	function Analyze(){
		$this->readHeader();
		$this->ReadNCZSECT();
		$this->nczfile->getFs();
		$ncafilesList = array();
		$retinfo = new stdClass;
		$retinfo->rsa1 = strtoupper($this->nczfile->rsa1);
		$retinfo->rsa2 = strtoupper($this->nczfile->rsa2);
		$retinfo->magic = $this->nczfile->magic;
		$retinfo->useBlockCompression = $this->useBlockCompression;
		$retinfo->compressedSize = $this->compressedSize;
		$retinfo->sigcheckrsa1 = $this->nczfile->sigcheck;
		$retinfo->sigcheckrsa2 = null;
		$retinfo->distributionType =  $this->nczfile->distributionType;
        $retinfo->contentType = $this->nczfile->contentType;
		$retinfo->contentSize =  $this->nczfile->contentSize;
        $retinfo->programId =  strtoupper($this->nczfile->programId);
		$retinfo->rightsId = strtoupper(strrev($this->nczfile->rightsId));
        $retinfo->contentIndex =  $this->nczfile->contentIndex;
		$retinfo->sdkArray = $this->nczfile->sdkArray;
		$retinfo->crypto_type = $this->nczfile->crypto_type;
		
		$retinfo->enckeyArea = $this->nczfile->enckeyArea;
		$retinfo->deckeyArea = $this->nczfile->deckeyArea;
		$retinfo->enctitlekey = $this->nczfile->enctitlekey;
		$retinfo->dectitlekey = $this->nczfile->dectitlekey;
		$retinfo->ncafilesList = $ncafilesList;
		$retinfo->pfs0idx =  $this->nczfile->pfs0idx;
		$retinfo->romfsidx =  $this->nczfile->romfsidx;
		$retinfo->pfs0Logoidx =  $this->nczfile->pfs0Logoidx;
		$retinfo->romfspatchidx =  $this->nczfile->romfspatchidx;
		$retinfo->sections = array(false,false,false,false);
		
		return $retinfo;
		
	}
}
	