<?php

function parseNsp($path)
{
    $fh = fopen($path, "r");

    $fileSignature = fread($fh, 4);
    if ($fileSignature != "PFS0") {
        fclose($fh);
        return json_encode(array(
            "int" => -1,
            "msg" => "Wrong file signature, not a NSP file",
        ));
    }

    $numFiles = unpack("V", fread($fh, 4))[1];
    $stringTableSize = unpack("V", fread($fh, 4))[1];
    $stringTableOffset = 0x10 + 0x18 * $numFiles;
    $fileBodyOffset = $stringTableOffset + $stringTableSize;

    fread($fh, 4);
    fseek($fh, 0x10);

    $nspHasTicketFile = false;
    $nspHasXmlFile = false;

    $filesList = [];
    $ticket = new stdClass();
    $xmlFile = "";

    for ($i = 0; $i < $numFiles; $i++) {
        $dataOffset = unpack("Q", fread($fh, 8))[1];
        $dataSize = unpack("Q", fread($fh, 8))[1];
        $stringOffset = unpack("V", fread($fh, 4))[1];
        fread($fh, 4);
        $storePos = ftell($fh);
        fseek($fh, $stringTableOffset + $stringOffset);
        $filename = "";
        while (true) {
            $byte = unpack("C", fread($fh, 1))[1];
            if ($byte == 0x00) break;
            $filename = $filename . chr($byte);
        }
        $parts = explode('.', strtolower($filename));
        $file = new stdClass();
        $file->name = $filename;
        $file->size = $dataSize;
        $filesList[] = $file;

        if ($parts[1].".".$parts[2] == "cnmt.xml") {
            $nspHasXmlFile = true;
            fseek($fh, $fileBodyOffset + $dataOffset);
            $xmlFile = fread($fh, $dataSize);
        }

        if ($parts[1] == "tik") {
            $nspHasTicketFile = true;
            fseek($fh, $fileBodyOffset + $dataOffset + 0x180);
            $titleKey = fread($fh, 0x10);
            fseek($fh, $fileBodyOffset + $dataOffset + 0x2a0);
            $titleRightsId = fread($fh, 0x10);
            fseek($fh, $fileBodyOffset + $dataOffset + 0x2a0);
            $titleId = fread($fh, 8);

            $ticket->titleKey = bin2hex($titleKey);
            $ticket->titleRightsId = bin2hex($titleRightsId);
            $ticket->titleId = bin2hex($titleId);
        }
        fseek($fh, $storePos);
    }
    fclose($fh);

    /*
    echo "<pre>";
    echo "== files ==\n";
    var_dump($filesList);
    echo "\n== ticket ==\n";
    var_dump($ticket);
    echo "\n== xml ==\n";
    var_dump($xmlFile);
    echo "</pre>";
    die();
    */

    $output = array();
    if($nspHasXmlFile) {
        $xml = simplexml_load_string($xmlFile);
        $output["int"] = 0;
        $output["src"] = "xml";
        $output["titleId"] = substr($xml->Id, 2);
        $output["version"] = (int)$xml->Version;
        $output["msg"] = "success";
    } elseif ($nspHasTicketFile){
        $output["int"] = 0;
        $output["src"] = "ticket";
        $output["titleId"] = $ticket->titleId;
        $output["version"] = null;
        $output["msg"] = "success";
    } else {
        $output["int"] = -1;
        $output["src"] = null;
        $output["msg"] = "Could not parse NSP file.";
    }

    return json_encode($output);
}