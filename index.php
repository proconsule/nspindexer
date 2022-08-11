<?php

/*
 *
 * NSP Indexer, by proconsule and jangrewe
 * https://github.com/proconsule/nspindexer
 *
 */

require 'lib/NSP.php';
require 'lib/XCI.php';
require 'lib/Utils.php';
require 'config.default.php';

define('REGEX_TITLEID', '0100[0-9A-F]{12}');
define('REGEX_TITLEID_BASE', '0100[0-9A-F]{8}[02468ACE]000');
define('REGEX_TITLEID_UPDATE', '0100[0-9A-F]{9}800');

define("CACHE_DIR", './cache');
if (!file_exists(CACHE_DIR)) {
    if (!@mkdir(CACHE_DIR)) {
        echo "Could not create the cache directory '" . CACHE_DIR . "', please make sure your webserver has write permission to the local directory.";
        die();
    }
}

if (!function_exists('curl_version')) {
    echo "curl extension isn't installed please install it and refresh page";
    die();
}

if (file_exists('config.php')) {
    require 'config.php';
}

if (isset($_GET["tinfoil"])) {
    header("Content-Type: application/json");
    header('Content-Disposition: filename="main.json"');
    echo outputTinfoil();
    die();
} elseif (isset($_GET["DBI"])) {
    header("Content-Type: text/plain");
    echo outputDbi();
    die();
}

$enableDecryption = false;
if (!empty($keyFile) && file_exists($keyFile)) {
    $keyList = parse_ini_file($keyFile);
    if (count($keyList) > 0 && !is_32bit()) {
        $enableDecryption = true;
    }
}

if (!extension_loaded('openssl') && $enableDecryption == true) {
    echo "openssl insn't installed please install it and refresh page";
    die();
}

$version = trim(file_get_contents('./VERSION'));

function outputRomInfo($path)
{
    global $gameDir;
    if ($romInfo = romInfo($gameDir . '/' . $path)) {
        return json_encode($romInfo);
    } else {
        return json_encode(array('int' => -1));
    }
}

function getFileList($path)
{
    global $allowedExtensions;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    $arrFiles = array();
    foreach ($files as $file) {
        $parts = explode('.', $file);
        if (!$file->isDir() && in_array(strtolower(array_pop($parts)), $allowedExtensions)) {
            array_push($arrFiles, str_replace($path . '/', '', $file->getPathname()));
        }
    }
    natcasesort($arrFiles);
    return array_values($arrFiles);
}

function getTitleIdType($titleId)
{
    if (preg_match('/' . REGEX_TITLEID_BASE . '/', $titleId) === 1) {
        return 'base';
    } elseif (preg_match('/' . REGEX_TITLEID_UPDATE . '/', $titleId) === 1) {
        return 'update';
    } elseif (preg_match('/' . REGEX_TITLEID . '/', $titleId) === 1) {
        return 'dlc';
    }
    return false;
}

function getBaseTitleId($titleId)
{
    if (getTitleIdType($titleId) == 'update') {
        return substr_replace($titleId, "000", -3);
    } elseif (getTitleIdType($titleId) == 'dlc') {
        return dlcIdToBaseId($titleId);
    }
    return strtoupper($titleId);
}

function dlcIdToBaseId($titleId)
{
    // find the Base TitleId (TitleId of the Base game with the fourth bit shifted by 1)
    $dlcBaseId = substr_replace($titleId, "000", -3);
    $offsetBit = hexdec(substr($dlcBaseId, 12, 1));
    $baseTitleBit = strtoupper(dechex($offsetBit - 1));
    return substr_replace($dlcBaseId, $baseTitleBit, -4, 1);
}

function matchTitleIds($files)
{
    $titles = [];
    // first round, get all Base TitleIds
    foreach ($files as $key => $file) {
        // check if we have a Base TitleId (0100XXXXXXXXY000, with Y being an even number)
        if (preg_match('/(?<=\[)' . REGEX_TITLEID_BASE . '(?=])/', $file, $titleIdMatches) === 1) {
            $titleId = $titleIdMatches[0];
            $titles[$titleId] = array(
                "path" => $file,
                "updates" => array(),
                "dlc" => array()
            );
            unset($files[$key]);
        }
    }

    $unmatched = [];
    // second round, match Updates and DLC to Base TitleIds
    foreach ($files as $key => $file) {
        if (preg_match('/(?<=\[)' . REGEX_TITLEID . '(?=])/', $file, $titleIdMatches) === 0) {
            // file does not have any kind of TitleId, skip further checks
            array_push($unmatched, $file);
            continue;
        }
        $titleId = $titleIdMatches[0];

        // find Updates (0100XXXXXXXXX800)
        if (preg_match('/^' . REGEX_TITLEID_UPDATE . '$/', $titleId) === 1) {
            if (preg_match('/(?<=\[v).+?(?=])/', $file, $versionMatches) === 1) {
                $version = $versionMatches[0];
                $baseTitleId = getBaseTitleId($titleId);
                // add Update only if the Base TitleId for it exists
                if ($titles[$baseTitleId]) {
                    $titles[$baseTitleId]['updates'][$version] = array(
                        "path" => $file
                    );
                }
            }
        } else {
            $baseTitleId = getBaseTitleId($titleId);
            // add DLC only if the Base TitleId for it exists
            if ($titles[$baseTitleId]) {
                $titles[$baseTitleId]['dlc'][$titleId] = array(
                    "path" => $file
                );
            }
        }
    }
    return array(
        'titles' => $titles,
        'unmatched' => $unmatched
    );
}

function getMetadata($type, $refresh = false)
{
    if (!$refresh && file_exists(CACHE_DIR . "/" . $type . ".json")) {
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

function refreshMetadata()
{
    $refreshed = array();
    foreach (array('versions', 'titles') as $type) {
        if (!file_exists(CACHE_DIR . "/" . $type . ".json") || filemtime(CACHE_DIR . "/" . $type . ".json") < (time() - 60 * 5)) {
            getMetadata($type, true);
            array_push($refreshed, $type);
        }
    }
    return json_encode(array(
        "int" => count($refreshed),
        "msg" => "Metadata " . ((count($refreshed) > 0) ? "updated: " . join(", ", $refreshed) : "not updated")
    ));
}

function outputConfig()
{
    global $contentUrl, $version, $enableNetInstall, $switchIp, $enableDecryption, $enableRename;
    return json_encode(array(
        "contentUrl" => $contentUrl,
        "version" => $version,
        "enableNetInstall" => $enableNetInstall,
        "enableRename" => $enableRename,
        "enableRomInfo" => $enableDecryption,
        "switchIp" => $switchIp
    ));
}

function outputTitles($forceUpdate = false)
{
    if (!$forceUpdate && file_exists(CACHE_DIR . "/games.json") && (filemtime(CACHE_DIR . "/games.json") > (time() - 60 * 5))) {
        $json = file_get_contents(CACHE_DIR . "/games.json");
    } else {
        global $gameDir;
        $versionsJson = getMetadata("versions");
        $titlesJson = getMetadata("titles");
        $titles = matchTitleIds(getFileList($gameDir));
        $output = array();
        foreach ($titles['titles'] as $titleId => $title) {
            $latestVersion = 0;
            $updateTitleId = substr_replace($titleId, "800", -3);
            if (array_key_exists($updateTitleId, $titlesJson)) {
                if ($titlesJson[$updateTitleId]["version"] != null) {
                    $latestVersion = $titlesJson[$updateTitleId]["version"];
                }
            }
            $realeaseDate = DateTime::createFromFormat('Ynd', $titlesJson[$titleId]["releaseDate"]);
            $latestVersionDate = $realeaseDate->format('Y-m-d');
            if (array_key_exists(strtolower($titleId), $versionsJson)) {
                $latestVersionDate = $versionsJson[strtolower($titleId)][$latestVersion];
            }
            $game = array(
                "path" => $title["path"],
                "fileType" => guessFileType($gameDir . "/" . $title["path"]),
                "name" => $titlesJson[$titleId]["name"],
                "thumb" => $titlesJson[$titleId]["iconUrl"],
                "banner" => $titlesJson[$titleId]["bannerUrl"],
                "intro" => $titlesJson[$titleId]["intro"],
                "latest_version" => $latestVersion,
                "latest_date" => $latestVersionDate,
                "size" => $titlesJson[$titleId]["size"],
                "size_real" => getFileSize($gameDir . "/" . $title["path"])
            );
            $updates = array();
            foreach ($title["updates"] as $updateVersion => $update) {
                $updates[(int)$updateVersion] = array(
                    "path" => $update["path"],
                    "date" => $versionsJson[strtolower($titleId)][$updateVersion],
                    "size_real" => getFileSize($gameDir . "/" . $update["path"])
                );
            }
            $game['updates'] = $updates;
            $dlcs = array();
            foreach ($title["dlc"] as $dlcId => $d) {
                $dlcs[$dlcId] = array(
                    "path" => $d["path"],
                    "name" => $titlesJson[$dlcId]["name"],
                    "size" => $titlesJson[$dlcId]["size"],
                    "size_real" => getFileSize($gameDir . "/" . $d["path"])
                );
            }
            $game['dlc'] = $dlcs;
            $output[$titleId] = $game;
        }
        $json = json_encode(array(
            'titles' => $output,
            'unmatched' => $titles['unmatched']
        ));
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
    $urlSchema = getURLSchema();
    foreach ($fileList as $file) {
        if (!is_32bit()) {
            $output["files"][] = ['url' => $urlSchema . '://' . $_SERVER['SERVER_NAME'] . implode('/', array_map('rawurlencode', explode('/', $contentUrl . '/' . $file))), 'size' => getFileSize($gameDir . '/' . $file)];
        } else {
            $output["files"][] = ['url' => $urlSchema . '://' . $_SERVER['SERVER_NAME'] . implode('/', array_map('rawurlencode', explode('/', $contentUrl . '/' . $file))), 'size' => floatval(getFileSize($gameDir . '/' . $file))];
        }
    }
    $output['success'] = "NSP Indexer";
    return json_encode($output);
}

function outputDbi()
{
    global $contentUrl;
    global $gameDir;
    $urlSchema = getURLSchema();
    $fileList = getFileList($gameDir);
    $output = "";
    foreach ($fileList as $file) {
        $output .= $urlSchema . '://' . $_SERVER['SERVER_NAME'] . implode('/', array_map('rawurlencode', explode('/', $contentUrl . '/' . $file))) . "\n";
    }
    return $output;
}

if (isset($_GET["config"])) {
    header("Content-Type: application/json");
    echo outputConfig();
    die();
} elseif (isset($_GET["titles"])) {
    header("Content-Type: application/json");
    echo outputTitles(isset($_GET["force"]));
    die();
} elseif (isset($_GET['metadata'])) {
    header("Content-Type: application/json");
    echo refreshMetadata();
    die();
} elseif (!empty($_GET['rename'])) {
    header("Content-Type: application/json");
    echo renameRom(rawurldecode($_GET['rename']), isset($_GET['preview']));
    die();
} elseif (!empty($_GET['rominfo'])) {
    header("Content-Type: application/json");
    echo outputRomInfo(rawurldecode($_GET['rominfo']));
    die();
}

require 'page.html';
