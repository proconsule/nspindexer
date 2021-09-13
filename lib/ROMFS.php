<?php

# ROMFS very partial implementation, for use on small files only (we just need this)

class ROMFS{
	function __construct($fh,$encoffset,$encsize,$romfsoffset,$romfssize,$key,$ctr){
		$this->aesctr = new AESCTR(hex2bin(strtoupper($key)),hex2bin(strtoupper($ctr)),true);
		$this->fh = $fh;
		$this->startctr = hex2bin($ctr);
		$this->encoffset = $encoffset;
		$this->encsize = $encsize;
		$this->romfsoffset = $romfsoffset;
		$this->romfssize = $romfssize;
		fseek($fh,$romfsoffset); 
	    $this->decData =  $this->aesctr->decrypt(fread($fh,0x50),$this->getCTROffset($romfsoffset-$encoffset));
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
		
		$this->Files = array();
		$this->Directorys = array();
		$lenred = 0;
		$idx = 0;
		$this->parsedir(0);
		
		for($i=0;$i<count($this->Files);$i++){
			$parts = explode('.', $this->Files[$i]["name"]);
			if($parts[count($parts)-1] == "nacp"){
				$this->nacp = new NACP($this->getFile($i));
			}
			
		}
		
		for($i=0;$i<count($this->Files);$i++){
			if(substr($this->Files[$i]["name"],0,6) == "/icon_"){
				$fileidx = $this->nacp->getLangIdx($this->Files[$i]["name"]);
				$this->nacp->langs[$fileidx]->gameIcon = base64_encode($this->getFile($i));
				$this->nacp->langs[$fileidx]->iconFilename = $this->Files[$i]["name"];
			}
		}
		
	
	}
	
	function parsefile($off, $path = ''){
		$filearray = array();
		$filearray["sibling"] = 0;
		while ($filearray["sibling"] != 0xFFFFFFFF){
			$subber = ($this->file_meta_offset+$off)%16;	
			fseek($this->fh,$this->romfsoffset+$this->file_meta_offset+$off-$subber);
			$mydata =  $this->aesctr->decrypt(fread($this->fh,0x20+$subber),$this->getCTROffset($this->romfsoffset-$this->encoffset + $this->file_meta_offset+$off-$subber));
			$tmpfdata = substr($mydata,0+$subber,0x20);
			$filearray = unpack("Vparent/Vsibling/Pofs/Psize/Vhsh/Vnamelen",$tmpfdata);
			
			$subber = ($this->file_meta_offset + $off +0x20)%16;
			fseek($this->fh,$this->romfsoffset+$this->file_meta_offset + $off+0x20-$subber);
			$tmpfilename = $this->aesctr->decrypt(fread($this->fh,$filearray['namelen']+$subber),$this->getCTROffset($this->romfsoffset-$this->encoffset + $this->file_meta_offset+$off+0x20-$subber));
			$tmpfilename = substr($tmpfilename,0+$subber,$filearray['namelen']);
			$filearray["name"] = $path.$tmpfilename;
			$off = $filearray["sibling"];
			$this->Files[] = $filearray;
			//var_dump( $filearray);
			//die();
		}
		//echo "FILE";
		//var_dump( $filearray);
		
	}
	
	
	function parsedir($off, $path = ''){
		$dirsubber = ($this->dir_meta_offset + $off)%16;	
		fseek($this->fh,$this->romfsoffset+$this->dir_meta_offset + $off-$dirsubber);
		$mydata = $this->aesctr->decrypt(fread($this->fh,0x18+$dirsubber),$this->getCTROffset($this->romfsoffset-$this->encoffset + $this->dir_meta_offset+$off-$dirsubber));
		$mydata = substr($mydata,0+$dirsubber,0x18);
		$dirarray = unpack("Vparent/Vsibling/Vchild/Vfile/Vhsh/Vnamelen",$mydata);
		if($dirarray["namelen"] != 0xFFFFFFFF){
			$dirsubber = ($this->dir_meta_offset + $off +0x18)%16;
			fseek($this->fh,$this->romfsoffset+$this->dir_meta_offset + $off+0x18-$dirsubber);
			$tmpdirname = $this->aesctr->decrypt(fread($this->fh,$dirarray['namelen']+$dirsubber),$this->getCTROffset($this->romfsoffset-$this->encoffset + $this->dir_meta_offset+$off+0x18-$dirsubber));
			$tmpdirname = substr($tmpdirname,0+$dirsubber,$dirarray['namelen']);
			$dirarray["name"] = $tmpdirname;
		}else{
			$dirarray["name"]= "";
		}
		 $newpath = "";
		 if($path != ''){
            $newpath = $path. $dirarray["name"]."/";
		 }else{
			$newpath = $dirarray["name"]."/";
		 }
		
		if($dirarray["file"] != 0xFFFFFFFF){
			$this->parsefile($dirarray["file"], $newpath);
		}
		
		if($dirarray["sibling"] != 0xFFFFFFFF){
			$this->parsedir($dirarray["sibling"], $path);
		}
		if ($dirarray["child"] != 0xFFFFFFFF){
			$this->parsedir($dirarray["child"], $newpath);
		}
		
		//echo "DIR";
		//var_dump( $dirarray);
		
		
	}
	
	function getCTROffset($offset){
		$ctr = new CTRCOUNTER_GMP($this->startctr);
		$adder = $offset/16;
		$ctr->add($adder);
		return $ctr->getCtr();
	}
	
#In memory extraction use on small file only	
	function getFile($i){
		fseek($this->fh,$this->romfsoffset + $this->data_offset+$this->Files[$i]["ofs"]);
		$filecontents = $this->aesctr->decrypt(fread($this->fh,$this->Files[$i]["size"]),$this->getCTROffset(($this->romfsoffset-$this->encoffset)+$this->data_offset+$this->Files[$i]["ofs"]));
		return $filecontents;
	}
	

	function extractFile($idx){
		
		$subber = ($this->romfsoffset + $this->data_offset+$this->Files[$idx]["ofs"])%16;
		
		fseek($this->fh, $this->romfsoffset + $this->data_offset+$this->Files[$idx]["ofs"]-$subber);
		
		$size = $this->Files[$idx]["size"];
		$tmpchunksize = $size+$subber;
		$parts = explode('/', $this->Files[$idx]["name"]);
		$chunksize = 5 * (1024 * 1024);
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.$size);
		header('Content-Disposition: attachment;filename="'.$parts[count($parts)-1].'"');
		$tmpchunksize = $size+$subber;
		$tmpchunkdone = 0;
		while ($tmpchunksize>$chunksize)
		{ 
			$ctr = $this->getCTROffset(($this->romfsoffset-$this->encoffset)+$this->data_offset+$this->Files[$idx]["ofs"]-$subber+($chunksize*$tmpchunkdone));
			$outdata =  $this->aesctr->decrypt(fread($this->fh,$chunksize),$ctr);
			if($tmpchunkdone == 0){
				print(substr($outdata,$subber));
			}else{
				print($outdata);
			}
            $tmpchunksize -=$chunksize;
			$tmpchunkdone += 1;
				
			ob_flush();
			flush();
		}
			
		if($tmpchunksize<=$chunksize){
			$ctr = $this->getCTROffset(($this->romfsoffset-$this->encoffset)+$this->data_offset+$this->Files[$idx]["ofs"]-$subber+($chunksize*$tmpchunkdone));
			$outdata = $this->aesctr->decrypt(fread($this->fh,$tmpchunksize),$ctr);
			if($tmpchunkdone == 0){
				print(substr($outdata,$subber));
			}else{
				print($outdata);	
			}
			ob_flush();
			flush();
		}
		
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
		$iconlang = str_replace(".dat","",str_replace("/icon_","",$iconfile));
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
