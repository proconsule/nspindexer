<?php

/*

NSP Indexer

https://github.com/proconsule/nspindexer

Fell free to use and/or modify

proconsule

Settings Section

*/

define("CACHE_DIR", './cache');

require 'config.default.php';
if (file_exists('config.php')) {
    require 'config.php';
}

$version = file_get_contents('./VERSION');

function getFileList($path)
{
    global $allowedExtensions;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    $arrFiles = array();
    foreach ($files as $file) {
        $parts = explode('.', $file);
        if (!$file->isDir() && in_array(strtolower(array_pop($parts)), $allowedExtensions)) {
            array_push($arrFiles, str_replace($path, '', $file->getPathname()));
        }
    }
    natcasesort($arrFiles);
    return array_values($arrFiles);
}

function matchTitleIds($files)
{
    $titles = [];
    // first round, get all Base TitleIds (0100XXXXXXXXY000, with Y being an even number)
    foreach ($files as $key => $file) {

        // Check if we have a Base TitleId
        if (preg_match('/(?<=\[)0100[0-9A-F]{8}[0,2,4,6,8,A,C,E]000(?=\])/', $file, $titleIdMatches) === 1) {
            $titleId = $titleIdMatches[0];
            $titles[$titleId] = array(
                "path" => $file,
                "updates" => array(),
                "dlc" => array()
            );
            unset($files[$key]);
        }
    }

    // second round, match Updates and DLC to Base TitleIds
    foreach ($files as $key => $file) {
        if (preg_match('/(?<=\[)0100[0-9A-F]{12}(?=\])/', $file, $titleIdMatches) === 0) {
            continue;
        }
        $titleId = $titleIdMatches[0];


        // find Updates (0100XXXXXXXXX800)
        if (preg_match('/^0100[0-9A-F]{9}800$/', $titleId) === 1) {

            if (preg_match('/(?<=\[v).+?(?=\])/', $file, $versionMatches) === 1) {
                $version = $versionMatches[0];
                $baseTitleId = substr_replace($titleId, "000", -3);
                if ($titles[$baseTitleId]) {
                    $titles[$baseTitleId]['updates'][$titleId] = array(
                        "path" => $file,
                        "version" => $version
                    );
                }
            }

        } else {
            // it's DLC, so find the Base TitleId (TitleId of the Base game with the fourth bit shifted by 1)
            $dlcBaseId = substr_replace($titleId, "000", -3);
            $offsetBit = hexdec(substr($dlcBaseId, 12, 1));
            $baseTitleBit = strtoupper(dechex($offsetBit - 1));
            $baseTitleId = substr_replace($dlcBaseId, $baseTitleBit, -4, 1);
            if ($titles[$baseTitleId]) {
                $titles[$baseTitleId]['dlc'][$titleId] = array(
                    "path" => $file
                );
            }

        }
    }
    return $titles;
}

function formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 1) . 'G';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 0) . 'M';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 0) . 'K';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

function getHumanFileSize($filename)
{
    $size = filesize($filename);
    if ($size === false) {
        $fp = fopen($filename, 'r');
        if (!$fp) {
            return false;
        }
        $offset = PHP_INT_MAX - 1;
        $size = (float)$offset;
        if (!fseek($fp, $offset)) {
            return false;
        }
        $chunksize = 8192;
        while (!feof($fp)) {
            $size += strlen(fread($fp, $chunksize));
        }
    } elseif ($size < 0) {
        $size = sprintf("%u", $size);
    }
    return $size;
}

function endsWith($haystack, $needle)
{
    return $needle === "" || (substr($haystack, -strlen($needle)) === $needle);
}

function getTitlesId($filesList)
{
    $titlesids = array();
    for ($i = 0; $i < count($filesList); $i++) {
        $listres = array();
        preg_match('/(?<=\[)[0-9A-F].+?(?=\])/', $filesList[$i], $titleidmatches);
        preg_match('/(?<=\[v).+?(?=\])/', $filesList[$i], $versionmatch);
        preg_match('/(\bDLC)/', $filesList[$i], $DLCmatch);


        $listres[] = $filesList[$i];
        $listres[] = $titleidmatches[0];
        if ($versionmatch == NULL) {
            $listres[] = "0";
        } else {
            $listres[] = $versionmatch[0];
        }
        if (endsWith($titleidmatches[0], "000")) {
            $listres[] = 0;
        } else if ($DLCmatch) {
            $listres[] = 2;
        } else {
            $listres[] = 1;
        }
        $titlesids[] = $listres;
    }

    $dlclist = array();
    $gamelist = array();
    for ($i = 0; $i < count($titlesids); $i++) {
        if ($titlesids[$i][3] == 0) {
            $titlesids[$i][] = array();
            $gamelist[$titlesids[$i][1]] = $titlesids[$i];
        }
        if ($titlesids[$i][3] == 2) {
            $dlclist[$titlesids[$i][1]] = $titlesids[$i];
        }


    }
    for ($i = 0; $i < count($titlesids); $i++) {
        if ($titlesids[$i][3] == 1) {
            $gamelist[substr($titlesids[$i][1], 0, -3) . "000"][4][] = $titlesids[$i];
        }


    }

    $returnlist = array();
    $returnlist[] = $gamelist;
    $returnlist[] = $dlclist;

    return $returnlist;
}

function getJson($type)
{
    if (!file_exists(CACHE_DIR)) {
        mkdir(CACHE_DIR);
    }
    if (file_exists(CACHE_DIR . "/" . $type . ".json") && (filemtime(CACHE_DIR . "/" . $type . ".json") > (time() - 60 * 60 * 24))) {
        $json = file_get_contents(CACHE_DIR . "/" . $type . ".json");
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://tinfoil.media/repo/db/" . $type . ".json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ');
        $json = curl_exec($ch);
        curl_close($ch);
        file_put_contents(CACHE_DIR . "/" . $type . ".json", $json);
    }
    return json_decode($json, true);
}

function outputJson($titlesJson, $versionsJson, $titles)
{
    global $gameDir;
    $output = array();
    foreach ($titles as $titleId => $title) {
        $game = array(
            "path" => $title["path"],
            "name" => $titlesJson[$titleId]["name"],
            "thumb" => $titlesJson[$titleId]["iconUrl"],
            "intro" => $titlesJson[$titleId]["intro"],
            "latest" => $titlesJson[substr_replace($titleId, "800", -3)]["version"],
            "size" => $titlesJson[$titleId]["size"],
            "size_real" => filesize($gameDir . '/' . $title["path"])
        );
        $updates = array();
        foreach ($title["updates"] as $updateId => $update) {
            $updates[$updateId] = array(
                "path" => $update["path"],
                "version" => (int)$update["version"],
                "size" => filesize($gameDir . '/' . $update["path"])
            );
        }
        $game['updates'] = $updates;
        $dlcs = array();
        foreach ($title["dlc"] as $dlcId => $d) {
            $dlcs[$dlcId] = array(
                "path" => $d["path"],
                "name" => $titlesJson[$dlcId]["name"],
                "size" => $titlesJson[$dlcId]["size"],
                "size_real" => filesize($gameDir . '/' . $d["path"])
            );
        }
        $game['dlc'] = $dlcs;
        $output[$titleId] = $game;
    }
    return json_encode($output);
}

function outputTinfoil()
{
    global $gameDir, $contentUrl;
    $output = array();
    $fileList = getFileList($gameDir);
    asort($fileList);
    $output["total"] = count($fileList);
    $output["files"] = array();
    foreach ($fileList as $file) {
        $output["files"][] = ['url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $contentUrl . $file . "#" . urlencode(str_replace('#', '', $file)), 'size' => getHumanFileSize($gameDir . $file)];
    }
    $output['success'] = "NSP Indexer";
    return json_encode($output);
}

function outputDirIndex()
{
    global $version;
    global $contentUrl;
    global $gameDir;
    echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 3.2 Final//EN\">\r\n";
    echo "<html>\r\n";
    echo " <head>\r\n";
    echo "  <title>Index of NSP Indexer</title>\r\n";
    echo " </head>\r\n";
    echo " <body>\r\n";
    echo "<h1>Index of NSP Indexer</h1>\r\n";
    echo "  <table>\r\n";
    echo "   <tr><th valign=\"top\"><img src=\"/icons/blank.gif\" alt=\"[ICO]\"></th><th><a href=\"?C=N;O=D\">Name</a></th><th><a href=\"?C=M;O=A\">Last modified</a></th><th><a href=\"?C=S;O=A\">Size</a></th><th><a href=\"?C=D;O=A\">Description</a></th></tr>\r\n";
    echo "   <tr><th colspan=\"5\"><hr></th></tr>\r\n";
    echo "<tr><td valign=\"top\"><img src=\"/icons/back.gif\" alt=\"[PARENTDIR]\"></td><td><a href=\"\">Parent Directory</a></td><td>&nbsp;</td><td align=\"right\">  - </td><td>&nbsp;</td></tr>\r\n";
    $fileList = getFileList($gameDir);
    foreach ($fileList as $file) {
        echo "<tr><td valign=\"top\"><img src=\"/icons/unknown.gif\" alt=\"[   ]\"></td>"
            . "<td><a href=\"" . $contentUrl . rawurlencode($file) . "\">" . str_replace($gameDir, "", $file) . "</a></td>"
            . "<td>" . date("Y-d-m H:i", filemtime($gameDir . $file)) . "</td>"
            . "<td align=\"right\">" . formatSizeUnits(getHumanFileSize($gameDir . $file)) . "</td>"
            . "<td>&nbsp;</td></tr>\r\n";
    }
    echo "   <tr><th colspan=\"5\"><hr></th></tr>\r\n";
    echo "</table>\r\n";
    echo "<address>NSP Indexer v" . $version . " on " . $_SERVER['SERVER_ADDR'] . "</address>\r\n</body></html>";
}

$versionsJson = getJson("versions");
$titlesJson = getJson("titles");

$titlesList = getTitlesId(getFileList($gameDir));

$gameList = $titlesList[0];
$dlcList = $titlesList[1];

if (isset($_GET["json"])) {
    header("Content-Type: application/json");
    $matchedTitles = matchTitleIds(getFileList($gameDir));
    echo outputJson($titlesJson, $versionsJson, $matchedTitles);
    die();
} elseif (isset($_GET["tinfoil"])) {
    header("Content-Type: application/json");
    header('Content-Disposition: filename="main.json"');
    echo outputTinfoil();
    die();

} elseif (array_key_exists("DBI/", $_GET)) {
    outputDirIndex();
    die();
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>NSP Indexer</title>
    <link href="css/styles.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <ul class="responsive-table">
        <li class="table-header">
            <div class="col col-1"></div>
            <div class="col col-2">ID</div>
            <div class="col col-3">Title</div>
            <div class="col col-4">Update</div>
            <div class="col col-5">Size</div>
        </li>
        <?php
        foreach (array_keys($gameList) as $key) {
            ?>
            <li class="table-row">
                <div class="col col-1">
                    <div class="zoom"><img class="displayed"
                                           src="<?php echo $titlesJson[$gameList[$key][1]]["iconUrl"] ?>" alt=""
                                           width="80"></div>
                </div>
                <div class="col col-2"><?php echo $gameList[$key][1] ?></div>

                <div class="col col-3">
                    <div class="linkdiv"><a
                                href="<?php echo $contentUrl . $gameList[$key][0]; ?>"><?php echo $titlesJson[$gameList[$key][1]]["name"] ?></a>
                    </div>
                    <div class="introdiv"><?php echo $titlesJson[$gameList[$key][1]]["intro"] ?></div>
                </div>
                <div class="col col-4">
                    <?php
                    foreach (array_keys($gameList[$key][4]) as $updatekey) {
                        ?>
                        <div class="updatelink">
                            <a href="<?php echo $contentUrl . $gameList[$key][4][$updatekey][0]; ?>"><?php echo intval($gameList[$key][4][$updatekey][2]) / 65536; ?></a>
                            <span class="updatelinktext"><?php echo "v" . $gameList[$key][4][$updatekey][2]; ?></span>
                        </div>
                        <?php
                    }
                    ?>
                    <?php
                    if (array_key_last($versionsJson[strtolower($gameList[$key][1])]) != end($gameList[$key][4])[2]) {
                        ?>
                        <div class="newupdatediv">
                            Last: <?php echo array_key_last($versionsJson[strtolower($gameList[$key][1])]) / 65536; ?>
                            <span class="newupdatedivtext"><?php echo "v" . array_key_last($versionsJson[strtolower($gameList[$key][1])]); ?></span>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="col col-5"><?php echo round(($titlesJson[$gameList[$key][1]]["size"] / 1024 / 1024 / 1024), 3) . " GB" ?></div>
            </li>

            <?php
        }
        ?>
    </ul>
</div>
<h2>DLC</h2>
<div class="container">
    <ul class="responsive-table">
        <li class="table-header">
            <div class="col col-2">ID</div>
            <div class="col col-3">Title</div>
            <div class="col col-4">Size</div>
        </li>
        <?php

        foreach (array_keys($dlcList) as $key) {

            ?>

            <li class="table-row">
                <div class="col col-2"><?php echo $dlcList[$key][1]; ?></div>
                <div class="col col-3">
                    <div class="linkdiv"><a
                                href="<?php echo $contentUrl . $dlcList[$key][0]; ?>"><?php echo $titlesJson[$dlcList[$key][1]]["name"]; ?></a>
                    </div>
                </div>
                <div class="col col-4"><?php echo round(($titlesJson[$dlcList[$key][1]]["size"] / 1024 / 1024 / 1024), 3) . " GB"; ?></div>


            </li>
            <?php

        }

        ?>

    </ul>
</div>

<div class="footer">
    <?php echo "NSP Indexer v" . $version; ?>
</div>

</body>
</html>
