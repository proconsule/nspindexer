<?php

class IVFC
{

    function __construct($superBlock)
    {
        $this->magic = substr($superBlock, 0, 4);
        $this->magincnum = unpack("V", substr($superBlock, 0x4, 4))[1];
        $this->levels = array();
        $this->sboffset = unpack("P", substr($superBlock, 0x88, 8))[1];
		for ($i = 0; $i < 6; $i++) {
            $sofs = 0x10 + ($i * 0x18);
            $tmplevel = new stdClass();
			$tmplevel->offset = unpack("P", substr($superBlock, $sofs, 0x08))[1];
            $tmplevel->size = unpack("P", substr($superBlock, $sofs + 0x08, 0x08))[1];
            $tmplevel->blockSize = 1 << unpack("V", substr($superBlock, $sofs + 0x08 + 0x08, 4))[1];
            $this->levels[] = $tmplevel;
        }
    }
}