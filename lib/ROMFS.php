<?php

# ROMFS very partial implementation, for use on small files only (we just need this)

class ROMFS{
	function __construct($fh,$offset,$size,$key,$ctr){
		$aesctr = new AESCTR(hex2bin(strtoupper($key)),hex2bin(strtoupper($ctr)),true);
		$this->fh = $fh;
		$this->offset = $offset;
		$this->size = $size;
		fseek($fh,$offset);
	    $this->decData =  $aesctr->decrypt(fread($fh,$size));
	}
    function getHeader(){
        $this->headerSize = unpack("P", substr($this->decData,0,8))[1];
        $this->dir_hash_offset = unpack("P", substr($this->decData,0x08,8))[1];
        $this->dir_hash_size = unpack("P", substr($this->decData,0x10,8))[1];
		$this->dir_meta_offset = unpack("P", substr($this->decData,0x18,8))[1];
		$this->dir_meta_size = unpack("P", substr($this->decData,0x20,8))[1];

		$this->file_hash_offset = unpack("P", substr($this->decData,0x28,8))[1];
        $this->file_hash_size = unpack("P", substr($this->decData,0x30,8))[1];
		$this->file_meta_offset = unpack("P", substr($this->decData,0x38,8))[1];
		$this->file_meta_size = unpack("P", substr($this->decData,0x40,8))[1];
		$this->data_offset = unpack("P", substr($this->decData,0x48,8))[1];

		$tmpfdata = substr($this->decData,$this->file_meta_offset,$this->file_meta_size);
		$this->Files = array();
		$lenred = 0;
		$idx = 0;
		while($lenred<=strlen($tmpfdata)){
			$tmpfentry = new stdClass();
			$tmpfentry->fileparent = unpack("V", substr($tmpfdata,$lenred,4))[1];
			$tmpfentry->sibiling = unpack("V", substr($tmpfdata,$lenred+4,4))[1];
			$tmpfentry->offset = unpack("P", substr($tmpfdata,$lenred+8,8))[1];
			$tmpfentry->size = unpack("P", substr($tmpfdata,$lenred+0x10,8))[1];
			$tmpfentry->hashfile = substr($tmpfdata,$lenred+0x18,4);
			$tmpfentry->name_size = unpack("V", substr($tmpfdata,$lenred+0x1c,4))[1];
			$tmpfentry->name= substr($tmpfdata,$lenred+0x20,$tmpfentry->name_size);
			$lenred = $tmpfentry->sibiling;
			$this->Files[] = $tmpfentry;
			$idx++;
		}
		
		for($i=0;$i<count($this->Files);$i++){
			$parts = explode('.', $this->Files[$i]->name);
			if($parts[1] == "nacp"){
				$this->nacp = new NACP($this->getFile($i));
			}
			
		}
		
		for($i=0;$i<count($this->Files);$i++){
			if(substr($this->Files[$i]->name,0,5) == "icon_"){
				$fileidx = $this->nacp->getLangIdx($this->Files[$i]->name);
				$this->nacp->langs[$fileidx]->gameIcon = base64_encode($this->getFile($i));
				$this->nacp->langs[$fileidx]->iconFilename = $this->Files[$i]->name;
				//break;
			}
		}
		
	
	}
	function getFile($i){
		$filecontents = substr($this->decData,$this->data_offset+$this->Files[$i]->offset,$this->Files[$i]->size);
		return $filecontents;
	}
}

class NACP{
	
	const langStrings = ["AmericanEnglish","BritishEnglish","Japanese","French","German","LatinAmericanSpanish","Spanish","Italian","Dutch","CanadianFrench","Portuguese","Russian","Korean","TraditionalChinese","SimplifiedChinese" ];
	
	function __construct($ncapcontents){
		$this->langs = array();
		
		
		for($i=0;$i<15;$i++){
			$langtmp = new stdClass();
			$langtmp->title = trim(substr($ncapcontents,0+(0x300*$i),0x200));
			$langtmp->publisher = trim(substr($ncapcontents,0x200+(0x300*$i),0x100));
			$langtmp->name = self::langStrings[$i];
			$langtmp->present = false;
			$this->langs[] = $langtmp;
		}
		$this->getLanguages(unpack("V",substr($ncapcontents,0x302C,4))[1]);
		$this->version = trim(substr($ncapcontents,0x3060,0x10));
	}

	function getLangIdx($iconfile){
		$iconlang = str_replace(".dat","",str_replace("icon_","",$iconfile));
		return array_search($iconlang,self::langStrings);
	}

	function getLanguages($suportedlanguages){
		for($i=0;$i<15;$i++){
			if( $suportedlanguages & (1 << $i) ) {
				$this->langs[$i]->present = true;
			}
		}
		
	}
}
