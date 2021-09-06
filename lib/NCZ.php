<?php

require_once "NCA.php";

class NCZ
{
    function __construct($fh,$offset,$size, $keys = null)
	{
		$this->nczfile = new NCA($fh,$offset,$size,$keys);
		$this->keys = $keys;
	}
	
	function readHeader()
	{
		$this->nczfile->readHeader();
	}
	
	function getOriginalSize()
	{
		return $this->nczfile->contentSize;
	}
	
}
	