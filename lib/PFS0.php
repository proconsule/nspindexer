<?php

# Partial Implementation just to match our needs

class PFS0
{
    function __construct($data, $mydataOffset, $mydataSize)
    {
        $this->data = substr($data, $mydataOffset, $mydataSize);
		$this->dataSize = $mydataSize; 
    }

    function getHeader()
    {
        $this->pfs0header = substr($this->data, 0, 0x04);
        if ($this->pfs0header != "PFS0") {
            return false;
        }

        $this->numFiles = unpack("V", substr($this->data, 4, 0x04))[1];
		$this->stringTableSize = unpack("V", substr($this->data, 8, 0x04))[1];
        $this->stringTableOffset = 0x10 + 0x18 * $this->numFiles;
        $this->fileBodyOffset = $this->stringTableOffset + $this->stringTableSize;
		$this->filesList = [];
        for ($i = 0; $i < $this->numFiles; $i++) {
            $dataOffset = unpack("P", substr($this->data, 0x10 + (0x20 * $i), 0x08))[1];
            $dataSize = unpack("P", substr($this->data, 0x18 + (0x20 * $i), 0x08))[1];
            $stringOffset = unpack("V", substr($this->data, 0x1c + (0x20 * $i), 0x04))[1];
            $filename = "";
            $n = 0;
            while (true && $this->stringTableOffset + $stringOffset + $n < $this->dataSize-1) {
                $byte = unpack("C", substr($this->data, $this->stringTableOffset + $stringOffset + $n, 1))[1];
                if ($byte == 0x00) break;
                $filename = $filename . chr($byte);
                $n++;
            }
            $parts = explode('.', strtolower($filename));
            $file = new stdClass();
            $file->name = $filename;
            $file->size = $dataSize;
            $file->offset = $dataOffset;
            if ($parts[count($parts) - 1] == "cnmt") {
                $this->cnmt = new CNMT(substr($this->data, $this->fileBodyOffset + $dataOffset, $dataSize), $dataSize);
            }
            $this->filesList[] = $file;

        }

    }

}

# mediaType 0x80	Application (Base Game), 0x81 Patch Update , 0x82 AddOnContent (DLC)

class CNMT
{
    function __construct($data, $dataSize)
    {
        $data;
        $this->id = bin2hex(strrev(substr($data, 0, 0x8)));
        $this->version = unpack("V", (substr($data, 0x08, 0x4)))[1];
        $this->mediaType = substr($data, 0x0c, 0x1);
        $this->otherId = bin2hex(strrev(substr($data, 0x20, 0x08)));
        $this->reqsysversion = bin2hex(substr($data, 0x28, 0x04));

    }

}