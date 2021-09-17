<?php

include_once "AES.php";
include_once "ROMFS.php";
include_once "IVFC.php";
include_once "PFS0.php";
include_once "N-RSA.php";


class NCA
{
 
	function __construct($fh, $fileOffset, $fileSize, $keys , $enctitlekey = "")
    {
        $this->fh = $fh;
        $this->fileOffset = $fileOffset;
        $this->fileSize = $fileSize;
        $this->keys = $keys;
		$this->enctitlekey = $enctitlekey;
		$this->romfsidx = -1;
		$this->pfs0idx = -1;
		$this->pfs0Logoidx = -1;
    }

    function readHeader()
    {
        fseek($this->fh, $this->fileOffset);
        $encHeader = fread($this->fh, 0xc00);
        $k1 = substr($this->keys["header_key"], 0, 0x20);
        $k2 = substr($this->keys["header_key"], 0x20, 0x20);
        $aes = new AESXTSN([hex2bin($k1), hex2bin($k2)]);
        $decHeader = $aes->decrypt($encHeader);
		$this->decHeader = $decHeader;
		$this->rsa1 = bin2hex(substr($decHeader, 0, 0x100));
        $this->rsa2 = bin2hex(substr($decHeader, 0x100, 0x100));
        $this->magic = substr($decHeader, 0x200, 4);
		if($this->magic != "NCA3")return false;
		
		$rsapss = new NCARSAPSS(substr($this->decHeader,0x200,0x200),hex2bin($this->rsa1));
		$this->sigcheck = $rsapss->verify();
        $this->distributionType = ord(substr($decHeader, 0x204, 1));
        $this->contentType = ord(substr($decHeader, 0x205, 1));
        $this->keyGenerationOld = ord(substr($decHeader, 0x206, 1));
        $this->keyAreaEncryptionKeyIndex = ord(substr($decHeader, 0x207, 1));
        $this->contentSize = unpack("P", substr($decHeader, 0x208, 8))[1];
        $this->programId = bin2hex(strrev(substr($decHeader, 0x210, 0x08)));
        $this->contentIndex = unpack("V", substr($decHeader, 0x218, 4))[1];
        $sdkRevision = ord(substr($decHeader, 0x21c, 1));
        $sdkMicro = ord(substr($decHeader, 0x21c + 1, 1));
        $sdkMinor = ord(substr($decHeader, 0x21c + 2, 1));
        $sdkMajor = ord(substr($decHeader, 0x21c + 3, 1));
        $this->keyGeneration = ord(substr($decHeader, 0x220, 1));
        $this->rightsId = bin2hex(strrev(substr($decHeader, 0x230, 0x10)));
        $this->sdkArray = array();
        $this->sdkArray[] = $sdkRevision;
        $this->sdkArray[] = $sdkMicro;
        $this->sdkArray[] = $sdkMinor;
        $this->sdkArray[] = $sdkMajor;
        $this->crypto_type = $this->keyGenerationOld;

        if ($this->keyGeneration > $this->crypto_type) {
            $this->crypto_type = $this->keyGeneration;
        }
        if ($this->crypto_type) {
            $this->crypto_type--;
        }
        $keyAreakeyidxstring = "key_area_key_";
        if ($this->keyAreaEncryptionKeyIndex == 0) {
            $keyAreakeyidxstring .= "application_";
        } elseif ($this->keyAreaEncryptionKeyIndex == 1) {
            $keyAreakeyidxstring .= "ocean_";
        } elseif ($this->keyAreaEncryptionKeyIndex == 2) {
            $keyAreakeyidxstring .= "system_";

        }
		$this->enckeyArea = array("00000000000000000000000000000000","00000000000000000000000000000000","00000000000000000000000000000000","00000000000000000000000000000000");
		$this->deckeyArea = array("00000000000000000000000000000000","00000000000000000000000000000000","00000000000000000000000000000000","00000000000000000000000000000000");
		$this->dectitlekey = "";
		
		if($this->rightsId == "00000000000000000000000000000000"){
			$keyAreakeyidxstring .= sprintf('%02x', $this->crypto_type);
			$this->keyAreakeyidxstring = $keyAreakeyidxstring;
			$enckeyArea = substr($decHeader, 0x300, 0x40);
			$keyareaAes = new AESECB(hex2bin($this->keys[$keyAreakeyidxstring]));
			$deckeyArea = $keyareaAes->decrypt($enckeyArea);
			for ($i = 0; $i < 4; $i++) {
				$this->enckeyArea[$i] = bin2hex(substr($enckeyArea, 0 + ($i * 0x10), 0x10));
				$this->deckeyArea[$i] = bin2hex(substr($deckeyArea, 0 + ($i * 0x10), 0x10));
			}
		}else{
			$this->keyidstring =  "titlekek_" . sprintf('%02x', $this->crypto_type);
			$keytitleAes = new AESECB(hex2bin($this->keys[$this->keyidstring]));
			$this->dectitlekey = bin2hex($keytitleAes->decrypt(hex2bin($this->enctitlekey))); 
		}
		return true;
    }

    function getFs()
    {
        $decHeader = $this->decHeader;
        $this->fsEntrys = array();
        for ($i = 0; $i < 4; $i++) {
            $tmpFsEntry = new stdClass();
            $entrystartOffset = 0x240 + ($i * 0x10);
            $tmpFsEntry->startOffset = unpack("V", substr($decHeader, $entrystartOffset, 4))[1] * 0x200;
            $tmpFsEntry->endOffset = unpack("V", substr($decHeader, $entrystartOffset + 0x04, 4))[1] * 0x200;
			$tmpFsEntry->Offset0x8 = unpack("V", substr($decHeader, $entrystartOffset + 0x08, 4))[1] * 0x200;
			$tmpFsEntry->Offset0xc = unpack("V", substr($decHeader, $entrystartOffset + 0x0c, 4))[1] * 0x200;
            $this->fsEntrys[] = $tmpFsEntry;
        }
        $this->fsHeaders = array();
        for ($i = 0; $i < 4; $i++) {
            if ($this->fsEntrys[$i]->startOffset == 0) continue;
            $tmpFsHeaderEntry = new stdClass();
            $entrystartOffset = 0x400 + ($i * 0x200);
            $tmpFsHeaderEntry->version = unpack("v", substr($decHeader, $entrystartOffset, 2))[1];
            $tmpFsHeaderEntry->fsType = ord(substr($decHeader, $entrystartOffset + 0x02, 1));
            $tmpFsHeaderEntry->hashType = ord(substr($decHeader, $entrystartOffset + 0x03, 1));
            $tmpFsHeaderEntry->encryptionType = ord(substr($decHeader, $entrystartOffset + 0x04, 1));
            $tmpFsHeaderEntry->superBlock = substr($decHeader, $entrystartOffset + 0x08, 0x138);
            if ($tmpFsHeaderEntry->hashType == 3) {
                $tmpFsHeaderEntry->superBlockHash = bin2hex(substr($tmpFsHeaderEntry->superBlock, 0xc0, 0x20));
            }
			$tmpFsHeaderEntry->sparseInfo = substr($decHeader, $entrystartOffset + 0x148, 0x30);
			$tmpFsHeaderEntry->sparseInfooffset = unpack("P", substr($tmpFsHeaderEntry->sparseInfo, 0x0, 8))[1];
			$tmpFsHeaderEntry->sparseInfosize = unpack("P", substr($tmpFsHeaderEntry->sparseInfo, 0x08, 8))[1];
            $tmpFsHeaderEntry->section_ctr = substr($decHeader, $entrystartOffset + 0x140, 0x08);
            if(intval(bin2hex($tmpFsHeaderEntry->sparseInfo))!= 0){
				return false; //Not implemented needs info not documented
			}
			$ofs = $this->fsEntrys[$i]->startOffset >> 4;
            $tmpFsHeaderEntry->ctr = "0000000000000000";
            for ($j = 0; $j < 0x8; $j++) {
                $tmpFsHeaderEntry->ctr[$j] = $tmpFsHeaderEntry->section_ctr[0x8 - $j - 1];
                $tmpFsHeaderEntry->ctr[0x10 - $j - 1] = chr(($ofs & 0xFF));
                $ofs >>= 8;
            }
            $tmpFsHeaderEntry->ctr = bin2hex($tmpFsHeaderEntry->ctr);
            $this->fsHeaders[] = $tmpFsHeaderEntry;
        }

        for ($i = 0; $i < 4; $i++) {
            if ($this->fsEntrys[$i]->startOffset == 0) continue;
            if ($this->fsHeaders[$i]->hashType == 3) {
				$ivfc = new IVFC($this->fsHeaders[$i]->superBlock);
				$this->fsHeaders[$i]->ivfc = $ivfc;
				$this->romfsidx = $i;
				$this->fsEntrys[$i]->romfsoffset = $this->fsEntrys[$i]->startOffset + $ivfc->sboffset;
            }
			
			/* PFS0 without encryption is Logo Partition */
			if ($this->fsHeaders[$i]->hashType == 2 && $this->fsHeaders[$i]->encryptionType == 1) {
				
				$shahash = substr($this->fsHeaders[$i]->superBlock, 0, 0x20);
                $blocksize = unpack("V", substr($this->fsHeaders[$i]->superBlock, 0x20, 4))[1];
                $pfs0offset = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x38, 8))[1];
                $pfs0size = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x40, 8))[1];
				$this->fsHeaders[$i]->shahash = $shahash;
				$this->fsHeaders[$i]->blocksize = $blocksize;
				$this->fsHeaders[$i]->pfs0offset = $pfs0offset;
				$this->fsHeaders[$i]->pfs0size = $pfs0size;
				$this->pfs0Logoidx = $i;
				$pfs0 = new PFS0($this->fh,$this->fsEntrys[$i]->startOffset + $this->fileOffset,$this->fsEntrys[$i]->endOffset - $this->fsEntrys[$i]->startOffset,$pfs0offset,$pfs0size);
				$pfs0->getHeader();
				if(property_exists($pfs0,"filesList")){
					$this->pfs0Logo = $pfs0;
				}
			}
			
			if ($this->fsHeaders[$i]->hashType == 2 && $this->fsHeaders[$i]->encryptionType != 1) {
                $shahash = substr($this->fsHeaders[$i]->superBlock, 0, 0x20);
                $blocksize = unpack("V", substr($this->fsHeaders[$i]->superBlock, 0x20, 4))[1];
                $pfs0offset = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x38, 8))[1];
                $pfs0size = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x40, 8))[1];
				
				$this->fsHeaders[$i]->shahash = $shahash;
				$this->fsHeaders[$i]->blocksize = $blocksize;
				$this->fsHeaders[$i]->pfs0offset = $pfs0offset;
				$this->fsHeaders[$i]->pfs0size = $pfs0size;
				$this->pfs0idx = $i;
				
            }
        }
		return true;
    }
	
	function getPFS0Enc($idx)
    {
		if($this->rightsId == "00000000000000000000000000000000"){
			$pfs0 = new PFS0Encrypted($this->fh,$this->fsEntrys[$idx]->startOffset + $this->fileOffset,$this->fsEntrys[$idx]->endOffset - $this->fsEntrys[$idx]->startOffset,$this->fsHeaders[$idx]->pfs0offset,$this->fsHeaders[$idx]->pfs0size,$this->deckeyArea[2],$this->fsHeaders[$idx]->ctr);
			$pfs0->getHeader();
			if(property_exists($pfs0,"filesList")){
				$this->pfs0 = $pfs0;
			}
		}else{
			$pfs0 = new PFS0Encrypted($this->fh,$this->fsEntrys[$idx]->startOffset + $this->fileOffset,$this->fsEntrys[$idx]->endOffset - $this->fsEntrys[$idx]->startOffset,$this->fsHeaders[$idx]->pfs0offset,$this->fsHeaders[$idx]->pfs0size,$this->dectitlekey,$this->fsHeaders[$idx]->ctr);
			$pfs0->getHeader();
			$this->pfs0 = $pfs0;
			if(property_exists($pfs0,"filesList")){
				$this->pfs0 = $pfs0;
			}
			
		}
	}

    function getRomfs($idx)
    {
		if($this->rightsId == "00000000000000000000000000000000"){
			$this->romfs = new ROMFS($this->fh,$this->fsEntrys[$idx]->startOffset + $this->fileOffset,$this->fsEntrys[$idx]->endOffset - $this->fsEntrys[$idx]->startOffset,$this->fsEntrys[$idx]->romfsoffset+$this->fileOffset, $this->fsEntrys[$idx]->endOffset ,$this->deckeyArea[2], $this->fsHeaders[$idx]->ctr);
			$this->romfs->getHeader();
		}else{
			$this->romfs = new ROMFS($this->fh,$this->fsEntrys[$idx]->startOffset + $this->fileOffset,$this->fsEntrys[$idx]->endOffset - $this->fsEntrys[$idx]->startOffset,$this->fsEntrys[$idx]->romfsoffset+$this->fileOffset, $this->fsEntrys[$idx]->endOffset ,$this->dectitlekey, $this->fsHeaders[$idx]->ctr);
			$this->romfs->getHeader();
		}
    }
	
	function Analyze(){
		
		$this->readHeader();
		$this->getFs();
		$ncafilesList = array();
		if($this->pfs0idx >-1){
			$this->getPFS0Enc($this->pfs0idx);
			$ncafilesList["pfs0"] = $this->pfs0->filesList;
		}
		if($this->romfsidx>-1){
			$this->getRomfs($this->romfsidx);
			$ncafilesList["romfs"] = $this->romfs->Files;
		}
		
		if($this->pfs0Logoidx>-1){
			$ncafilesList["pfs0Logo"] = $this->pfs0Logo->filesList;
		}
		
		$retinfo = new stdClass;
		$retinfo->rsa1 = strtoupper($this->rsa1);
		$retinfo->rsa2 = strtoupper($this->rsa2);
		$retinfo->magic = $this->magic;
		$retinfo->sigcheckrsa1 = $this->sigcheck;
		$retinfo->sigcheckrsa2 = null;
		$retinfo->distributionType =  $this->distributionType;
        $retinfo->contentType = $this->contentType;
		$retinfo->contentSize =  $this->contentSize;
        $retinfo->programId =  strtoupper($this->programId);
		$retinfo->rightsId = strtoupper(strrev($this->rightsId));
        $retinfo->contentIndex =  $this->contentIndex;
		$retinfo->sdkArray = $this->sdkArray;
		$retinfo->crypto_type = $this->crypto_type;
		
		$retinfo->enckeyArea = $this->enckeyArea;
		$retinfo->deckeyArea = $this->deckeyArea;
		$retinfo->enctitlekey = $this->enctitlekey;
		$retinfo->dectitlekey = $this->dectitlekey;
		$retinfo->ncafilesList = $ncafilesList;
		$retinfo->pfs0idx =  $this->pfs0idx;
		$retinfo->romfsidx =  $this->romfsidx;
		$retinfo->pfs0Logoidx =  $this->pfs0Logoidx;
		
		$retinfo->exefs = false;
		
		if($this->pfs0idx > -1){
			if($this->pfs0->isexefs){
				$retinfo->exefs = true;
				$rsapss = new NCARSAPSS(substr($this->decHeader,0x200,0x200),hex2bin($this->rsa2),hex2bin($this->pfs0->npdm->acid->rsa2));
				$retinfo->sigcheckrsa2 = $rsapss->verify();
			}
		}
		
		$retinfo->sections = array(false,false,false,false);
		if($this->pfs0idx > -1){
			$tmpsectionobj = new stdClass;
			$tmpsectionobj->offset = $this->fsEntrys[$this->pfs0idx]->startOffset;
			$tmpsectionobj->size = $this->fsEntrys[$this->pfs0idx]->endOffset-$this->fsEntrys[$this->pfs0idx]->startOffset;
			$tmpsectionobj->partitionType = "PFS0";
			if($this->pfs0->isexefs){
				$tmpsectionobj->partitionType = "ExeFS";
			}
			$tmpsectionobj->ctr = $this->fsHeaders[$this->pfs0idx]->ctr;
			$tmpsectionobj->shahash = strtoupper(bin2hex($this->fsHeaders[$this->pfs0idx]->shahash));
			$tmpsectionobj->pfs0offset = $this->fsHeaders[$this->pfs0idx]->pfs0offset;
			$tmpsectionobj->pfs0size = $this->fsHeaders[$this->pfs0idx]->pfs0size;
			$retinfo->sections[$this->pfs0idx] = $tmpsectionobj;
			
		}
		
		if($this->romfsidx > -1){
			$tmpsectionobj = new stdClass;
			$tmpsectionobj->partitionType = "ROMFS";
			$tmpsectionobj->offset = $this->fsEntrys[$this->romfsidx]->startOffset;
			$tmpsectionobj->ctr = $this->fsHeaders[$this->romfsidx]->ctr;
			//$tmpsectionobj->shahash = strtoupper(bin2hex($this->fsHeaders[$this->romfsidx]->shahash));
			$tmpsectionobj->size = $this->fsEntrys[$this->romfsidx]->endOffset-$this->fsEntrys[$this->romfsidx]->startOffset;
			$tmpsectionobj->ivfc = $this->fsHeaders[$this->romfsidx]->ivfc;
			$tmpsectionobj->ivfc->shahash = strtoupper(bin2hex($tmpsectionobj->ivfc->shahash));
			$tmpsectionobj->shahash = $tmpsectionobj->ivfc->shahash;
			$retinfo->sections[$this->romfsidx] = $tmpsectionobj;
		}
		
		if($this->pfs0Logoidx > -1){
			$tmpsectionobj = new stdClass;
			$tmpsectionobj->offset = $this->fsEntrys[$this->pfs0Logoidx]->startOffset;
			$tmpsectionobj->size = $this->fsEntrys[$this->pfs0Logoidx]->endOffset-$this->fsEntrys[$this->pfs0Logoidx]->startOffset;
			$tmpsectionobj->partitionType = "PFS0";
			$tmpsectionobj->ctr = $this->fsHeaders[$this->pfs0Logoidx]->ctr;
			$tmpsectionobj->shahash = strtoupper(bin2hex($this->fsHeaders[$this->pfs0Logoidx]->shahash));
			$tmpsectionobj->pfs0offset = $this->fsHeaders[$this->pfs0Logoidx]->pfs0offset;
			$tmpsectionobj->pfs0size = $this->fsHeaders[$this->pfs0Logoidx]->pfs0size;
			$retinfo->sections[$this->pfs0Logoidx] = $tmpsectionobj;
			
		}
		
		
		return $retinfo;
		
	}
	/* DUMP FUNCTION FOR DEBUG!
	function DumpDec(){
		fseek($this->fh,$this->fsEntrys[1]->startOffset + $this->fileOffset);
		$ctr = new CTRCOUNTER_GMP(hex2bin(strtoupper($this->fsHeaders[1]->ctr)));
		$aesctr = new AESCTR(hex2bin(strtoupper($this->dectitlekey)),$ctr->getCtr(),true);
		while(!feof($this->fh)){
			print($aesctr->decrypt(fread($this->fh,0x3E80),$ctr->getCtr()));
			flush();
			$ctr->add(1000);
		}
		
	}
	*/
}

/*
$fh = fopen($argv[1],"r");
$keys = parse_ini_file("/root/.switch/prod.keys");

$test = new NCA($fh,0,filesize($argv[1]),$keys,$argv[2]);
$test->readHeader();
$ret = $test->getFs();
//$test->getRomfs($test->romfsidx);
//echo $ret;
//$test->DumpDec();

var_dump($test);
*/
