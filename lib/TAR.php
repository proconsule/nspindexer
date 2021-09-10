<?php

class TAR
{
    function __construct()
    {
        
    }
	
	function  AddFile($filename,$fh,$offset,$size)
	{
		$chunksize = 5*(1024*1024);
		$entry = $this->getTarHeaderFooter($filename,$size,0);
		print($entry[0]);
		$tmpsize = $size;
		
		fseek($fh,$offset);
		
		while($tmpsize>0){
			if($tmpsize > $chunksize){
				print(fread($fh,$chunksize));
				$tmpsize -= $chunksize;
				flush();
				ob_flush();
			}else
			{
				print(fread($fh,$tmpsize));
				$tmpsize=0;
				flush();
				ob_flush();
			}
			
		}
		print($entry[1]);
		flush();
		ob_flush();
		
	}
	
	function getTarHeaderFooter($filename, $filesize, $filemtime)
	{
		$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct($filesize), decoct($filemtime));
		$checksum = 8*32; // space for checksum itself
		for ($i=0; $i < strlen($return); $i++) {
			$checksum += ord($return{$i});
		}
		$return .= sprintf("%06o", $checksum) . "\0 ";
		return array(
			$return . str_repeat("\0", 512 - strlen($return)),
			str_repeat("\0", 511 - ($filesize + 511) % 512)
		);
	}
	
}



