<?php
class NSP{
   function __construct($path) {
      $this->path = $path;
      $this->open();
   }

   function open(){
      $this->fh = fopen($this->path, "r");
   }

   function close(){
      fclose($this->fh);
   }
   
   function getHeaderInfo(){
        $this->nspheader = fread($this->fh, 4);
        if ($this->nspheader != "PFS0") {
         return false;
        }

        $this->numFiles = unpack("V", fread($this->fh, 4))[1];
        $this->stringTableSize = unpack("V", fread($this->fh, 4))[1];
        $this->stringTableOffset = 0x10 + 0x18 * $this->numFiles;
        $this->fileBodyOffset = $this->stringTableOffset + $this->stringTableSize;
        fread($this->fh, 4);
        fseek($this->fh, 0x10);
        $this->HasTicketFile = false;
        $this->nspHasXmlFile = false;
        $this->ticket = new stdClass();

        $this->filesList = [];
        for ($i = 0; $i < $this->numFiles; $i++) {
           $dataOffset = unpack("Q", fread($this->fh, 8))[1];
           $dataSize = unpack("Q", fread($this->fh, 8))[1];
           $stringOffset = unpack("V", fread($this->fh, 4))[1];
           fread($this->fh, 4);
           $storePos = ftell($this->fh);
           fseek($this->fh, $this->stringTableOffset + $stringOffset);
           $filename = "";
           while (true) {
            $byte = unpack("C", fread($this->fh, 1))[1];
            if ($byte == 0x00) break;
            $filename = $filename . chr($byte);
           }
           $parts = explode('.', strtolower($filename));
           $file = new stdClass();
           $file->name = $filename;
           $file->size = $dataSize;
           $file->offset = $dataOffset;
           $this->filesList[] = $file;
           if ($parts[1].".".$parts[2] == "cnmt.xml") {
             $this->nspHasXmlFile = true;
             fseek($this->fh, $this->fileBodyOffset + $dataOffset);
             $this->xmlFile = fread($this->fh, $dataSize);
           }
           
           if ($parts[1] == "tik") {
            $this->nspHasTicketFile = true;
            fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x180);
            $titleKey = fread($this->fh, 0x10);
            fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x2a0);
            $titleRightsId = fread($this->fh, 0x10);
            fseek($this->fh, $this->fileBodyOffset + $dataOffset + 0x2a0);
            $titleId = fread($this->fh, 8);

            $this->ticket->titleKey = bin2hex($titleKey);
            $this->ticket->titleRightsId = bin2hex($titleRightsId);
            $this->ticket->titleId = bin2hex($titleId);
           }

           fseek($this->fh, $storePos);

        }
        return true;
        
   }



}




?>
