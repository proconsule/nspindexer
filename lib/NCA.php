<?php

include "AES.php";

abstract class ContentType {
	const Program = 0;
	const Meta = 1;
	const Control = 2;
	const Manual = 3;
    const Data = 4;
    const PublicData = 5;
}


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
		$this->rsa1 = substr($decHeader,0,0x100);
		$this->rsa2 = substr($decHeader,0x100,0x100);
		$this->magic = substr($decHeader,0x200,4);
		$this->distributionType  = ord(substr($decHeader,0x204,1));
		$this->contentType = ord(substr($decHeader,0x205,1));
		$this->keyGenerationOld = ord(substr($decHeader,0x206,1));
		$this->keyAreaEncryptionKeyIndex =  ord(substr($decHeader,0x207,1));
		$this->contentSize = unpack("Q", substr($decHeader,0x208,8))[1];
		$this->programId = bin2hex(strrev(substr($decHeader,0x210,0x08)));
		$this->contentIndex = unpack("V", substr($decHeader,0x218,4))[1];
		
		$sdkRevision = ord(substr($decHeader,0x21c,1));
		$sdkMicro = ord(substr($decHeader,0x21c+1,1));
		$sdkMinor = ord(substr($decHeader,0x21c+2,1));
		$sdkMajor = ord(substr($decHeader,0x21c+3,1));
		
		$this->keyGeneration = ord(substr($decHeader,0x220,1));
		$this->rightsId = bin2hex(strrev(substr($decHeader,0x230,0x10)));
		
		$this->sdkArray = array();
		
		$this->sdkArray[] = $sdkRevision;
		$this->sdkArray[] = $sdkMicro;
		$this->sdkArray[] = $sdkMinor;
		$this->sdkArray[] = $sdkMajor;
		
		
		
		$this->crypto_type = $this->keyGenerationOld;
		
		if ($this->keyGeneration > $this->crypto_type){
           $this->crypto_type = $this->keyGeneration;
		}
        if ($this->crypto_type){
          $this->crypto_type--; 
		}
		
		
		$keyAreakeyidxstring = "key_area_key_";
        if($this->keyAreaEncryptionKeyIndex == 0){
			$keyAreakeyidxstring .= "application_";
		}elseif($this->keyAreaEncryptionKeyIndex == 1){
			$keyAreakeyidxstring .= "ocean_";
		}elseif($this->keyAreaEncryptionKeyIndex == 2){
			$keyAreakeyidxstring .= "system_";
			
		}
		$keyAreakeyidxstring .= sprintf('%02x', $this->crypto_type);
		
		$this->keyAreakeyidxstring = $keyAreakeyidxstring;
		$enckeyArea = substr($decHeader,0x300,0x40);
		
        $keyareaAes = new AESECB(hex2bin($this->keys[$keyAreakeyidxstring]));
        $deckeyArea = $keyareaAes->decrypt($enckeyArea); 
		
		$this->enckeyArea = array();
		$this->deckeyArea = array();
		for($i=0;$i<4;$i++){
			$this->enckeyArea[] = bin2hex(substr($enckeyArea,0+($i*0x10),0x10));
			$this->deckeyArea[] = bin2hex(substr($deckeyArea,0+($i*0x10),0x10));
		}
		
		
		
		
		
		//$this->test = substr($decHeader,0x400,0x210);
		//echo $this->test;
		
		
		$this->fsEntrys = array();
		for($i=0;$i<4;$i++){
			$tmpFsEntry = new stdClass();
			$entrystartOffset = 0x240+($i*0x10);
            $tmpFsEntry->startOffset = unpack("V", substr($decHeader,$entrystartOffset,4))[1]*0x200;
			$tmpFsEntry->endOffset = unpack("V", substr($decHeader,$entrystartOffset+0x04,4))[1]*0x200;
			$this->fsEntrys[] = $tmpFsEntry;
			
		}
		
		$this->fsHeaders = array();
		for($i=0;$i<4;$i++){
			$tmpFsHeaderEntry = new stdClass();
			$entrystartOffset = 0x400+($i*0x200);
			$tmpFsHeaderEntry->version = unpack("v", substr($decHeader,$entrystartOffset,2))[1];
			$tmpFsHeaderEntry->fsType = ord(substr($decHeader,$entrystartOffset+0x02,1));
			$tmpFsHeaderEntry->hashType = ord(substr($decHeader,$entrystartOffset+0x03,1));
			$tmpFsHeaderEntry->encryptionType =  ord(substr($decHeader,$entrystartOffset+0x04,1));
			
			$tmpFsHeaderEntry->ctrLow = bin2hex(substr($decHeader,$entrystartOffset+0x140,4));
			$tmpFsHeaderEntry->ctrHig = bin2hex(substr($decHeader,$entrystartOffset+0x144,4));
			
			
			$this->fsHeaders[] = $tmpFsHeaderEntry;
		}
		
		
	}
	
	
	
}



/* Debug Example

$fh = fopen("pathtocryptednca.nca","r");
$mykeys = parse_ini_file("pathto prod.keys");

$test = new NCA($fh,0,1,$mykeys);
$test->readHeader();


var_dump($test);

*/