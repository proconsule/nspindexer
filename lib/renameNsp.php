<?php

function renameNsp($oldName, $preview = true)
{
    global $gameDir;
    $parseResult = json_decode(parseNsp(realpath($gameDir . '/' . $oldName)));
    $error = false;
    $newName = "";
    if ($parseResult->int === 0) {
        $titlesJson = getMetadata("titles");
        $titleId = strtoupper($parseResult->titleId);
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
        $newName = $baseTitleName . '/' . $baseTitleName . " " . $dlcNameNice . $typeTag . "[" . $titleId . "][v" . $parseResult->version . "].nsp";

        if (!$error && !$preview) {
            if (!file_exists($gameDir . '/' . $baseTitleName)) {
                mkdir($gameDir . '/' . $baseTitleName);
            }
            rename($gameDir .'/'. $oldName, $gameDir . '/' . $newName);
        }
    }

    return json_encode(array(
        "int" => $parseResult->int,
        "old" => $oldName,
        "new" => $newName,
    ));
}