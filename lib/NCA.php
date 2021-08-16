<?php

include "AES.php";
include "ROMFS.php";
include "IVFC.php";
include "PFS0.php";


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
		$this->decHeader = $decHeader;
		$this->rsa1 = bin2hex(substr($decHeader,0,0x100));
		$this->rsa2 = bin2hex(substr($decHeader,0x100,0x100));
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
	}
	
	function getFs(){
		$decHeader = $this->decHeader;
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
			if($this->fsEntrys[$i]->startOffset == 0)continue;
			$tmpFsHeaderEntry = new stdClass();
			$entrystartOffset = 0x400+($i*0x200);
			$tmpFsHeaderEntry->version = unpack("v", substr($decHeader,$entrystartOffset,2))[1];
			$tmpFsHeaderEntry->fsType = ord(substr($decHeader,$entrystartOffset+0x02,1));
			$tmpFsHeaderEntry->hashType = ord(substr($decHeader,$entrystartOffset+0x03,1));
			$tmpFsHeaderEntry->encryptionType =  ord(substr($decHeader,$entrystartOffset+0x04,1));
			$tmpFsHeaderEntry->superBlock = substr($decHeader,$entrystartOffset+0x08,0x138);
			if($tmpFsHeaderEntry->hashType == 3){
				$tmpFsHeaderEntry->superBlockHash = bin2hex(substr($tmpFsHeaderEntry->superBlock,0xc0,0x20));
			}
			$tmpFsHeaderEntry->section_ctr = substr($decHeader,$entrystartOffset+0x140,0x08);
			$ofs = $this->fsEntrys[$i]->startOffset >> 4;
			$tmpFsHeaderEntry->ctr = "0000000000000000";
            for ($j = 0; $j < 0x8; $j++) {
                $tmpFsHeaderEntry->ctr[$j] = $tmpFsHeaderEntry->section_ctr[0x8-$j-1];
                $tmpFsHeaderEntry->ctr[0x10-$j-1] = chr(($ofs & 0xFF));
                $ofs >>= 8;
            }
			$tmpFsHeaderEntry->ctr = bin2hex($tmpFsHeaderEntry->ctr);
			$this->fsHeaders[] = $tmpFsHeaderEntry;
		}
		
		for($i=0;$i<4;$i++){
			if($this->fsEntrys[$i]->startOffset == 0)continue;
			if($this->fsHeaders[$i]->hashType  == 3){
			   $ivfc = new IVFC($this->fsHeaders[$i]->superBlock);
			   $this->fsEntrys[$i]->romfsoffset = $this->fsEntrys[$i]->startOffset+$ivfc->sboffset;
			   fseek($this->fh,$this->fsEntrys[$i]->startOffset+$this->fileOffset);
			   $this->fsEntrys[$i]->encData = fread($this->fh,$this->fsEntrys[$i]->endOffset-$this->fsEntrys[$i]->startOffset);
			}
			if($this->fsHeaders[$i]->hashType  == 2){
				$shahash = substr($this->fsHeaders[$i]->superBlock,0,0x20)[1];
				$blocksize = unpack("V", substr($this->fsHeaders[$i]->superBlock,0x20,4))[1];
				$pfs0offset = unpack("Q", substr($this->fsHeaders[$i]->superBlock,0x38,8))[1];
				$pfs0size = unpack("Q", substr($this->fsHeaders[$i]->superBlock,0x40,8))[1];
				$this->fsEntrys[$i]->pfs0offset = $this->fsEntrys[$i]->startOffset+$pfs0offset;
				fseek($this->fh,$this->fsEntrys[$i]->startOffset+$this->fileOffset);
				$this->fsEntrys[$i]->encData = fread($this->fh,$this->fsEntrys[$i]->endOffset-$this->fsEntrys[$i]->startOffset);
			    $aesctr = new AESCTR(hex2bin(strtoupper($this->deckeyArea[2])),hex2bin(strtoupper($this->fsHeaders[$i]->ctr)),true);
				$this->fsEntrys[$i]->decData = $aesctr->decrypt($this->fsEntrys[$i]->encData);
				$pfs0 = new PFS0($this->fsEntrys[$i]->decData,$pfs0offset,$pfs0size);
				$pfs0->getHeader();
				$this->pfs0 = $pfs0;
			}
		}
	}
	
	function getRomfs($idx){
		$this->romfs = new ROMFS($this->fsEntrys[$idx]->encData,$this->deckeyArea[2],$this->fsHeaders[$idx]->ctr);
		$this->romfs->decData = substr($this->romfs->decData,$this->fsEntrys[$idx]->romfsoffset-$this->fsEntrys[$idx]->startOffset,$this->fsEntrys[$idx]->endOffset);
		$this->romfs->getHeader();
	}
}
