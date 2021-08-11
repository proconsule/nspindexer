<?php

class XCI
{
    function __construct($path)
    {
        $this->path = $path;
        $this->open();
    }

    function open()
    {
        $this->fh = fopen($this->path, "r");
    }

    function close()
    {
        fclose($this->fh);
    }

    function getMasterPartitions()
    {
        fseek($this->fh, 0x100);
        $this->fileSignature = fread($this->fh, 4);

        if ($this->fileSignature != "HEAD") {

            return false;
        }
        fseek($this->fh, 0x130);
        $this->hfs0offset = unpack("Q", fread($this->fh, 8))[1];
        $this->hfs0size = unpack("Q", fread($this->fh, 8))[1];
        $this->masterpartition = new HFS0($this->fh, $this->hfs0offset, $this->hfs0size);
        $this->masterpartition->getHeaderInfo();
    }

    function getSecurePartition()
    {
        if (!in_array("secure", $this->masterpartition->filenames)) {
            return false;
        }
        $this->secure_index = array_search('secure', $this->masterpartition->filenames);
        $this->securepartition = new HFS0($this->fh, $this->masterpartition->rawdataoffset + $this->masterpartition->file_array[$this->secure_index]->fileoffset, $this->masterpartition->file_array[$this->secure_index]->filesize);
        $this->securepartition->getHeaderInfo();
    }

}


class HFS0
{
    function __construct($fh, $offset, $size)
    {
        $this->fh = $fh;
        $this->offset = $offset;
        $this->size = $size;
    }

    function getHeaderInfo()
    {
        fseek($this->fh, $this->offset);
        $this->HF0Signature = fread($this->fh, 4);
        if ($this->HF0Signature != "HFS0") {
            return false;
        }
        $this->numfiles = unpack("V", fread($this->fh, 4))[1];
        $this->stringtable_size = unpack("V", fread($this->fh, 4))[1];
        fseek($this->fh, $this->offset + 0x10);
        $this->file_array = array();
        for ($i = 0; $i < $this->numfiles; $i++) {
            $tmpfileobj = new stdClass();
            $tmpfileobj->fileoffset = unpack("Q", fread($this->fh, 8))[1];
            $tmpfileobj->filesize = unpack("Q", fread($this->fh, 8))[1];
            $tmpfileobj->stringoffset = unpack("V", fread($this->fh, 4))[1];
            $dummy = fread($this->fh, 0x2C);
            $this->file_array[] = $tmpfileobj;
        }
        $this->rawdataoffset = ftell($this->fh) + $this->stringtable_size;
        $this->filenames = array();
        $tmpfilename = "";
        for ($i = 0; $i < $this->stringtable_size; $i++) {
            $tmpchar = unpack("C", fread($this->fh, 1))[1];
            if ($tmpchar == 0x00) {
                $this->filenames[] = $tmpfilename;
                $tmpfilename = "";
            } else {
                $tmpfilename = $tmpfilename . chr($tmpchar);
            }
            if (count($this->filenames) >= $this->numfiles) break;
        }
        return true;
    }

}
