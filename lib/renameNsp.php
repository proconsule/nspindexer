<?php

function renameNsp($oldName, $preview = true)
{
    global $gameDir,$useKeyFile,$keylist;
    $nsp = "";
	if($useKeyFile){
		$nsp = new NSP(realpath($gameDir . '/' . $oldName),$keylist);
	}else{
		$nsp = new NSP(realpath($gameDir . '/' . $oldName));
	}
    $error = false;
    $newName = "";
    if ($nsp->getHeaderInfo()) {
        $nspInfo = $nsp->getInfo();
        $titlesJson = getMetadata("titles");
        $titleId = strtoupper($nspInfo->titleId);
        $titleIdType = getTitleIdType($titleId);
        $baseTitleId = $titleId;
        $typeTag = "";
        if ($titleIdType == 'update' || $titleIdType == 'dlc') {
            $baseTitleId = getBaseTitleId($titleId);
            //$typeTag = "[" . (($titleIdType == 'update') ? "UPD" : "DLC") . "]";
        }
        $baseTitleName = "";
        if (array_key_exists($baseTitleId, $titlesJson)) {
            $baseTitleName = preg_replace("/[^[:alnum:][:space:]_-]/u", '', $titlesJson[$baseTitleId]['name']);
        } else {
            $error = true;
        }
        $dlcNameNice = "";
        if ($titleIdType == 'dlc') {
            if (array_key_exists($titleId, $titlesJson)) {
                $dlcName = preg_replace("/[^[:alnum:][:space:]_-]/u", '', $titlesJson[$titleId]['name']);
                $dlcNameNice = "(" . trim(str_replace($baseTitleName, '', $dlcName)) . ") ";
            } else {
                $error = true;
            }
        }
        $newName = $baseTitleName . '/' . $baseTitleName . " " . $dlcNameNice . $typeTag . "[" . $titleId . "][v" . $nspInfo->version . "].nsp";

        if (!$error && !$preview) {
            if (!file_exists($gameDir . '/' . $baseTitleName)) {
                mkdir($gameDir . '/' . $baseTitleName);
            }
            rename($gameDir . '/' . $oldName, $gameDir . '/' . $newName);
        }
    } else {
        $error = true;
    }

    $nsp->close();

    return json_encode(array(
        "int" => $error ? -1 : 0,
        "old" => $oldName,
        "new" => $newName,
    ));
}