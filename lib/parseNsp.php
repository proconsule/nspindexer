<?php

include "NSP.php";


function parseNsp($path)
{
    
	$nsp = new NSP($path);
	$nsp->getHeaderInfo();
	$nsp->close();

    $output = array();
    if($nsp->nspHasXmlFile) {
        $xml = simplexml_load_string($nsp->xmlFile);
        $output["int"] = 0;
        $output["src"] = "xml";
        $output["titleId"] = substr($xml->Id, 2);
        $output["version"] = (int)$xml->Version;
        $output["msg"] = "success";
    } elseif ($nsp->nspHasTicketFile){
        $output["int"] = 0;
        $output["src"] = "ticket";
        $output["titleId"] = $nsp->ticket->titleId;
        $output["version"] = null;
        $output["msg"] = "success";
    } else {
        $output["int"] = -1;
        $output["src"] = null;
        $output["msg"] = "Could not parse NSP file.";
    }

    return json_encode($output);
}