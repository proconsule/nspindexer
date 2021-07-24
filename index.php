<?php

/*

NSP Indexer

https://github.com/proconsule/nspindexer

Fell free to use and/or modify

proconsule

Settings Section

*/

define("CACHE_DIR", './cache');
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR);
}

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
    // first round, get all Base TitleIds
    foreach ($files as $key => $file) {

        // check if we have a Base TitleId (0100XXXXXXXXY000, with Y being an even number)
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
            // file does not have any kind of TitleId, skip further checks
            continue;
        }
        $titleId = $titleIdMatches[0];


        // find Updates (0100XXXXXXXXX800)
        if (preg_match('/^0100[0-9A-F]{9}800$/', $titleId) === 1) {

            if (preg_match('/(?<=\[v).+?(?=\])/', $file, $versionMatches) === 1) {
                $version = $versionMatches[0];
                $baseTitleId = substr_replace($titleId, "000", -3);
                // add Update only if the Base TitleId for it exists
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
            // add DLC only if the Base TitleId for it exists
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
    if (file_exists(CACHE_DIR . "/games.json") && (filemtime(CACHE_DIR . "/games.json") > (time() - 60 * 5))) {
        $json = file_get_contents(CACHE_DIR . "/games.json");
    } else {
        global $gameDir;
        $output = array();
        foreach ($titles as $titleId => $title) {
            $latestVersion = $titlesJson[substr_replace($titleId, "800", -3)]["version"];
            $game = array(
                "path" => $title["path"],
                "name" => $titlesJson[$titleId]["name"],
                "thumb" => $titlesJson[$titleId]["iconUrl"],
                "banner" => $titlesJson[$titleId]["bannerUrl"],
                "intro" => $titlesJson[$titleId]["intro"],
                "latest_version" => $latestVersion,
                "latest_date" => $versionsJson[strtolower($titleId)][$latestVersion],
                "size" => $titlesJson[$titleId]["size"],
                "size_real" => filesize($gameDir . '/' . $title["path"])
            );
            $updates = array();
            foreach ($title["updates"] as $updateId => $update) {
                $updates[$updateId] = array(
                    "path" => $update["path"],
                    "version" => (int)$update["version"],
                    "date" => $versionsJson[strtolower($titleId)][$update["version"]],
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
        $json = json_encode($output);
        file_put_contents(CACHE_DIR . "/games.json", $json);
    }
    return $json;
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

function outputDbi()
{
    global $contentUrl;
    global $gameDir;
    $fileList = getFileList($gameDir);
    foreach ($fileList as $file) {
        echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $contentUrl . $file . "\n";
    }
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
} elseif (isset($_GET["DBI"])) {
    header("Content-Type: text/plain");
    outputDbi();
    die();
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>NSP Indexer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body>


<header>
    <div class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a href="#" class="navbar-brand d-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor"
                     class="bi bi-controller me-2" viewBox="0 0 16 16">
                    <path d="M11.5 6.027a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm-1.5 1.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1zm2.5-.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm-1.5 1.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1zm-6.5-3h1v1h1v1h-1v1h-1v-1h-1v-1h1v-1z"/>
                    <path d="M3.051 3.26a.5.5 0 0 1 .354-.613l1.932-.518a.5.5 0 0 1 .62.39c.655-.079 1.35-.117 2.043-.117.72 0 1.443.041 2.12.126a.5.5 0 0 1 .622-.399l1.932.518a.5.5 0 0 1 .306.729c.14.09.266.19.373.297.408.408.78 1.05 1.095 1.772.32.733.599 1.591.805 2.466.206.875.34 1.78.364 2.606.024.816-.059 1.602-.328 2.21a1.42 1.42 0 0 1-1.445.83c-.636-.067-1.115-.394-1.513-.773-.245-.232-.496-.526-.739-.808-.126-.148-.25-.292-.368-.423-.728-.804-1.597-1.527-3.224-1.527-1.627 0-2.496.723-3.224 1.527-.119.131-.242.275-.368.423-.243.282-.494.575-.739.808-.398.38-.877.706-1.513.773a1.42 1.42 0 0 1-1.445-.83c-.27-.608-.352-1.395-.329-2.21.024-.826.16-1.73.365-2.606.206-.875.486-1.733.805-2.466.315-.722.687-1.364 1.094-1.772a2.34 2.34 0 0 1 .433-.335.504.504 0 0 1-.028-.079zm2.036.412c-.877.185-1.469.443-1.733.708-.276.276-.587.783-.885 1.465a13.748 13.748 0 0 0-.748 2.295 12.351 12.351 0 0 0-.339 2.406c-.022.755.062 1.368.243 1.776a.42.42 0 0 0 .426.24c.327-.034.61-.199.929-.502.212-.202.4-.423.615-.674.133-.156.276-.323.44-.504C4.861 9.969 5.978 9.027 8 9.027s3.139.942 3.965 1.855c.164.181.307.348.44.504.214.251.403.472.615.674.318.303.601.468.929.503a.42.42 0 0 0 .426-.241c.18-.408.265-1.02.243-1.776a12.354 12.354 0 0 0-.339-2.406 13.753 13.753 0 0 0-.748-2.295c-.298-.682-.61-1.19-.885-1.465-.264-.265-.856-.523-1.733-.708-.85-.179-1.877-.27-2.913-.27-1.036 0-2.063.091-2.913.27z"/>
                </svg>
                <strong>NSP Indexer</strong>
            </a>
        </div>
    </div>
</header>

<main>
    <div class="album py-3 bg-light">
        <div class="container" id="titleList">
        </div>
    </div>
</main>

<footer class="text-muted py-5">
    <div class="container">
        <p class="float-end mb-1">
            <a href="#">Back to top</a>
        </p>
        <p class="mb-1"><?php echo "NSP Indexer v" . $version; ?></p>
    </div>
</footer>


<!---
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

--->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
<script src="js/nspindexer.js"></script>

</body>
</html>
