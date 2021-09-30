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
	
	
	function getCTROffset($startctr,$offset){
		$ctr = new CTRCOUNTER_GMP($startctr);
		$adder = $offset/16;
		$ctr->add($adder);
		return $ctr->getCtr();
	}
	
	
	function Decompress(){
		fseek($this->fh,$this->offset);
		print(fread($this->fh,0x4000));
		$this->ReadNCZSECT();
		if($this->useBlockCompression){
			return false;
		}
		$start = ftell($this->fh);
		$yazstd = new yazstd_decompress();
		$sectionbuffer = "";
		
		foreach ($this->sections as $section) {
			$crypto = new AESCTR($section->cryptoKey,$section->cryptoCounter,true);
			$end = $section->offset+$section->size;
			$i = $section->offset;
			
			$startctr = $section->cryptoCounter;
			while($i<$end){

				$chunkSz = 0x10000;
				if ($end - $i > 0x10000){
					$chunkSz = 0x10000;
				}
				else{
					$chunkSz = $end - $i;
				}
				
				$compressedChunk = fread($this->fh,$chunkSz);
				
				$inputChunk  = $yazstd->decompress($compressedChunk);
				
				if(strlen($sectionbuffer)>0){
					$inputChunk = $sectionbuffer.$inputChunk;
					$sectionbuffer= "";
				}
				
				if($i+strlen($inputChunk)>$section->size){
					$outsectionlen = $i+strlen($inputChunk)-$end;
					$sectionbuffer = substr($inputChunk,strlen($inputChunk)-$outsectionlen);
					$inputChunk = substr($inputChunk,0,strlen($inputChunk)-$outsectionlen);
					
				}
				
				if($section->cryptoType == 3 || $section->cryptoType == 4){
					
					$ctr = $this->getCTROffset($startctr,$i);
		
					$inputChunk = $crypto->encrypt($inputChunk,$ctr);
				}
				
				print($inputChunk);
				$i+=strlen($inputChunk);
				//ob_flush();
				flush();
			}
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


/*

$fh = fopen($argv[1],"r");
$keys = parse_ini_file("/root/.switch/prod.keys");

$test = new NCZ($fh,0,filesize($argv[1]),$keys,$argv[2]);
//$test->readHeader();


$test->Decompress();

//var_dump($test);
*/
	