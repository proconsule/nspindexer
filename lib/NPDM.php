<?php

class NPDM{
	
	function __construct($data,$size){
		$this->magic = substr($data,0,0x04);
		$this->AcidSignatureKeyGeneration = unpack("V", substr($data, 0x04, 0x04))[1];
		$this->flags = substr($data, 0x0c, 0x01);
		$this->MainThreadPriority = substr($data, 0x0e, 0x01);
		$this->MainThreadCoreNumber = substr($data, 0x0f, 0x01);
		$this->version = unpack("V", substr($data, 0x18, 0x04))[1];
		
		$this->AciOffset = unpack("V", substr($data, 0x70, 0x04))[1];
		$this->AciSize = unpack("V", substr($data, 0x74, 0x04))[1];
		$this->AcidOffset = unpack("V", substr($data, 0x78, 0x04))[1];
		$this->AcidSize = unpack("V", substr($data, 0x7c, 0x04))[1];
		$this->acid = new ACID(substr($data,$this->AcidOffset,$this->AcidSize),$this->AcidSize);
	}
	
	
	
}

class ACID{
	function __construct($data,$size){
		$this->rsa1 = bin2hex(substr($data, 0x00, 0x100));
		$this->rsa2 = bin2hex(substr($data, 0x100, 0x100));
		$this->magic = substr($data, 0x200, 0x04);
		$this->size = unpack("V", substr($data, 0x204, 0x04))[1];
	}
	
	
	
}