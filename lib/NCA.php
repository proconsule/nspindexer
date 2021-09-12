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
		
		if($this->rightsId == "00000000000000000000000000000000"){
			$keyAreakeyidxstring .= sprintf('%02x', $this->crypto_type);
			$this->keyAreakeyidxstring = $keyAreakeyidxstring;
			$enckeyArea = substr($decHeader, 0x300, 0x40);
			$keyareaAes = new AESECB(hex2bin($this->keys[$keyAreakeyidxstring]));
			$deckeyArea = $keyareaAes->decrypt($enckeyArea);
			$this->enckeyArea = array();
			$this->deckeyArea = array();
			for ($i = 0; $i < 4; $i++) {
				$this->enckeyArea[] = bin2hex(substr($enckeyArea, 0 + ($i * 0x10), 0x10));
				$this->deckeyArea[] = bin2hex(substr($deckeyArea, 0 + ($i * 0x10), 0x10));
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
            $tmpFsHeaderEntry->section_ctr = substr($decHeader, $entrystartOffset + 0x140, 0x08);
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
				$this->romfsidx = $i;
                $this->fsEntrys[$i]->romfsoffset = $this->fsEntrys[$i]->startOffset + $ivfc->sboffset;
            }
			if ($this->fsHeaders[$i]->hashType == 2) {
                $shahash = substr($this->fsHeaders[$i]->superBlock, 0, 0x20)[1];
                $blocksize = unpack("V", substr($this->fsHeaders[$i]->superBlock, 0x20, 4))[1];
                $pfs0offset = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x38, 8))[1];
                $pfs0size = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x40, 8))[1];
				$this->pfs0idx = $i;
				if($this->rightsId == "00000000000000000000000000000000"){
					$pfs0 = new PFS0Encrypted($this->fh,$this->fsEntrys[$i]->startOffset + $this->fileOffset,$this->fsEntrys[$i]->endOffset - $this->fsEntrys[$i]->startOffset,$pfs0offset,$pfs0size,$this->deckeyArea[2],$this->fsHeaders[$i]->ctr);
					$pfs0->getHeader();
					if(property_exists($pfs0,"filesList")){
						$this->pfs0 = $pfs0;
					}
				}else{
					$pfs0 = new PFS0Encrypted($this->fh,$this->fsEntrys[$i]->startOffset + $this->fileOffset,$this->fsEntrys[$i]->endOffset - $this->fsEntrys[$i]->startOffset,$pfs0offset,$pfs0size,$this->dectitlekey,$this->fsHeaders[$i]->ctr);
					$pfs0->getHeader();
					if(property_exists($pfs0,"filesList")){
						$this->pfs0 = $pfs0;
					}
					
				}
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
}

/*
$mykeys = parse_ini_file("/root/.switch/prod.keys");
$fh = fopen($argv[1],'r');
$test = new NCA($fh,0,1,$mykeys,$argv[2]);
$test->readHeader();
$test->getFs();
var_dump( $test->pfs0->filesList);
if($test->romfsidx>-1){
	$test->getRomfs($test->romfsidx);
	var_dump($test->romfs->Files);
	var_dump($test->romfs->nacp);
}
*/