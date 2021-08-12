<?php

include "AES.php";

class NCA{
	
	function __construct($fh,$fileOffset,$fileSize,$keys) {
		$this->fh = $fh;
		$this->fileOffset = $fileOffset;
		$this->fileSize = $fileSize;
		$this->keys = $keys;
	}
	
	function readHeader(){
		fseek($this->fh,$this->fileOffset);
		$encHeader = fread($this->fh,0xc00);
		$k1 = substr($this->keys["header_key"],0,0x20);
		$k2 = substr($this->keys["header_key"],0x20,0x20);
		
		
		$aes = new AESXTSN([hex2bin($k1),hex2bin($k2)]);

		
		$decHeader = $aes->decrypt($encHeader);
		$this->magic = substr($decHeader,0x200,4);
		$this->dtype = substr($decHeader,0x204,1);
		$this->ctype = substr($decHeader,0x205,1);
		$this->ProgramId = bin2hex(strrev(substr($decHeader,0x210,0x08)));
		$this->RightsId = bin2hex(strrev(substr($decHeader,0x230,0x10)));
		
		
		//echo $this->magic;
		
		
	}
	
	
	
}


/* Debug Example

$fh = fopen("pathtocryptednca.nca","r");
$mykeys = parse_ini_file("pathto prod.keys");

$test = new NCA($fh,0,1,$mykeys);
$test->readHeader();


var_dump($test);

