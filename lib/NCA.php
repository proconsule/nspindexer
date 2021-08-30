<?php

include_once "AES.php";
include_once "ROMFS.php";
include_once "IVFC.php";
include_once "PFS0.php";

class NCA
{
	const NCAHeaderSignature = [ /* Fixed RSA key used to validate NCA signature 0. */
        0xBF, 0xBE, 0x40, 0x6C, 0xF4, 0xA7, 0x80, 0xE9, 0xF0, 0x7D, 0x0C, 0x99, 0x61, 0x1D, 0x77, 0x2F,
        0x96, 0xBC, 0x4B, 0x9E, 0x58, 0x38, 0x1B, 0x03, 0xAB, 0xB1, 0x75, 0x49, 0x9F, 0x2B, 0x4D, 0x58,
        0x34, 0xB0, 0x05, 0xA3, 0x75, 0x22, 0xBE, 0x1A, 0x3F, 0x03, 0x73, 0xAC, 0x70, 0x68, 0xD1, 0x16,
        0xB9, 0x04, 0x46, 0x5E, 0xB7, 0x07, 0x91, 0x2F, 0x07, 0x8B, 0x26, 0xDE, 0xF6, 0x00, 0x07, 0xB2,
        0xB4, 0x51, 0xF8, 0x0D, 0x0A, 0x5E, 0x58, 0xAD, 0xEB, 0xBC, 0x9A, 0xD6, 0x49, 0xB9, 0x64, 0xEF,
        0xA7, 0x82, 0xB5, 0xCF, 0x6D, 0x70, 0x13, 0xB0, 0x0F, 0x85, 0xF6, 0xA9, 0x08, 0xAA, 0x4D, 0x67,
        0x66, 0x87, 0xFA, 0x89, 0xFF, 0x75, 0x90, 0x18, 0x1E, 0x6B, 0x3D, 0xE9, 0x8A, 0x68, 0xC9, 0x26,
        0x04, 0xD9, 0x80, 0xCE, 0x3F, 0x5E, 0x92, 0xCE, 0x01, 0xFF, 0x06, 0x3B, 0xF2, 0xC1, 0xA9, 0x0C,
        0xCE, 0x02, 0x6F, 0x16, 0xBC, 0x92, 0x42, 0x0A, 0x41, 0x64, 0xCD, 0x52, 0xB6, 0x34, 0x4D, 0xAE,
        0xC0, 0x2E, 0xDE, 0xA4, 0xDF, 0x27, 0x68, 0x3C, 0xC1, 0xA0, 0x60, 0xAD, 0x43, 0xF3, 0xFC, 0x86,
        0xC1, 0x3E, 0x6C, 0x46, 0xF7, 0x7C, 0x29, 0x9F, 0xFA, 0xFD, 0xF0, 0xE3, 0xCE, 0x64, 0xE7, 0x35,
        0xF2, 0xF6, 0x56, 0x56, 0x6F, 0x6D, 0xF1, 0xE2, 0x42, 0xB0, 0x83, 0x40, 0xA5, 0xC3, 0x20, 0x2B,
        0xCC, 0x9A, 0xAE, 0xCA, 0xED, 0x4D, 0x70, 0x30, 0xA8, 0x70, 0x1C, 0x70, 0xFD, 0x13, 0x63, 0x29,
        0x02, 0x79, 0xEA, 0xD2, 0xA7, 0xAF, 0x35, 0x28, 0x32, 0x1C, 0x7B, 0xE6, 0x2F, 0x1A, 0xAA, 0x40,
        0x7E, 0x32, 0x8C, 0x27, 0x42, 0xFE, 0x82, 0x78, 0xEC, 0x0D, 0xEB, 0xE6, 0x83, 0x4B, 0x6D, 0x81,
        0x04, 0x40, 0x1A, 0x9E, 0x9A, 0x67, 0xF6, 0x72, 0x29, 0xFA, 0x04, 0xF0, 0x9D, 0xE4, 0xF4, 0x03    
    ];

    function __construct($fh, $fileOffset, $fileSize, $keys)
    {
        $this->fh = $fh;
        $this->fileOffset = $fileOffset;
        $this->fileSize = $fileSize;
        $this->keys = $keys;
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
                $this->fsEntrys[$i]->romfsoffset = $this->fsEntrys[$i]->startOffset + $ivfc->sboffset;
                fseek($this->fh, $this->fsEntrys[$i]->startOffset + $this->fileOffset);
                $this->fsEntrys[$i]->encData = fread($this->fh, $this->fsEntrys[$i]->endOffset - $this->fsEntrys[$i]->startOffset);
            }
            if ($this->fsHeaders[$i]->hashType == 2) {
                $shahash = substr($this->fsHeaders[$i]->superBlock, 0, 0x20)[1];
                $blocksize = unpack("V", substr($this->fsHeaders[$i]->superBlock, 0x20, 4))[1];
                $pfs0offset = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x38, 8))[1];
                $pfs0size = unpack("P", substr($this->fsHeaders[$i]->superBlock, 0x40, 8))[1];
				$this->fsEntrys[$i]->pfs0offset = $this->fsEntrys[$i]->startOffset + $pfs0offset;
                fseek($this->fh, $this->fsEntrys[$i]->startOffset + $this->fileOffset);
                $this->fsEntrys[$i]->encData = fread($this->fh, $this->fsEntrys[$i]->endOffset - $this->fsEntrys[$i]->startOffset);
                $aesctr = new AESCTR(hex2bin(strtoupper($this->deckeyArea[2])), hex2bin(strtoupper($this->fsHeaders[$i]->ctr)), true);
                $this->fsEntrys[$i]->decData = $aesctr->decrypt($this->fsEntrys[$i]->encData);
                $pfs0 = new PFS0($this->fsEntrys[$i]->decData, $pfs0offset, $pfs0size);
				$pfs0->getHeader();
                $this->pfs0 = $pfs0;
            }
        }
    }

    function getRomfs($idx)
    {
        $this->romfs = new ROMFS($this->fsEntrys[$idx]->encData, $this->deckeyArea[2], $this->fsHeaders[$idx]->ctr);
        $this->romfs->decData = substr($this->romfs->decData, $this->fsEntrys[$idx]->romfsoffset - $this->fsEntrys[$idx]->startOffset, $this->fsEntrys[$idx]->endOffset);
        $this->romfs->getHeader();
    }
}