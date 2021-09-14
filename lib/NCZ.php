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
		
	}
}
	