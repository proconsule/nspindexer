<?php

/*
 *
 * NSP Indexer, by proconsule and jangrewe
 * https://github.com/proconsule/nspindexer
 *
 */

define("CACHE_DIR", './cache');
if (!file_exists(CACHE_DIR)) {
    if (!@mkdir(CACHE_DIR)) {
        echo "Could not create the cache directory '" . CACHE_DIR . "', please make sure your webserver has write permission to the local directory.";
        die();
    }
}

require 'config.default.php';
if (file_exists('config.php')) {
    require 'config.php';
}

$version = file_get_contents('./VERSION');

function getURLSchema()
{
    $server_request_scheme = "http";
    if ((!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')) {
        $server_request_scheme = 'https';
    }
    return $server_request_scheme;
}

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

// this is a workaround for 32bit systems and files >2GB
function getFileSize($filename)
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

function getMetadata($type)
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

function outputJson($titlesJson, $versionsJson)
{
    if (file_exists(CACHE_DIR . "/games.json") && (filemtime(CACHE_DIR . "/games.json") > (time() - 60 * 5))) {
        $json = file_get_contents(CACHE_DIR . "/games.json");
    } else {
        global $gameDir;
        $titles = matchTitleIds(getFileList($gameDir));
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
                "size_real" => getFileSize($gameDir . $title["path"])
            );
            $updates = array();
            foreach ($title["updates"] as $updateId => $update) {
                $updates[$updateId] = array(
                    "path" => $update["path"],
                    "version" => (int)$update["version"],
                    "date" => $versionsJson[strtolower($titleId)][$update["version"]],
                    "size_real" => getFileSize($gameDir . $update["path"])
                );
            }
            $game['updates'] = $updates;
            $dlcs = array();
            foreach ($title["dlc"] as $dlcId => $d) {
                $dlcs[$dlcId] = array(
                    "path" => $d["path"],
                    "name" => $titlesJson[$dlcId]["name"],
                    "size" => $titlesJson[$dlcId]["size"],
                    "size_real" => getFileSize($gameDir . $d["path"])
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
    $urlSchema = getURLSchema();
    foreach ($fileList as $file) {
        $output["files"][] = ['url' => $urlSchema . '://' . $_SERVER['SERVER_NAME'] . $contentUrl . $file . "#" . urlencode(str_replace('#', '', $file)), 'size' => getFileSize($gameDir . $file)];
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
    foreach ($fileList as $file) {
        echo $urlSchema . '://' . $_SERVER['SERVER_NAME'] . implode('/', array_map('rawurlencode', explode('/', $contentUrl . $file))) . "\n";
    }
}

$versionsJson = getMetadata("versions");
$titlesJson = getMetadata("titles");

if (isset($_GET["json"])) {
    header("Content-Type: application/json");
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
    <link rel="icon" type="image/svg+xml" href="img/favicon.svg">
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/nspindexer.css" rel="stylesheet">
    <script>
        var contentUrl = '<?=$contentUrl?>';
    </script>
</head>
<body>

<header>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi-controller brandLogo "></i>&nbsp;<strong>NSP Indexer</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarContent" aria-controls="navbarContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end mt-2 mt-md-0" id="navbarContent">
                <form>
                    <div class="input-group">
                        <input class="form-control" id="keyword" type="text" placeholder="Search Titles..."
                               aria-label="Search">
                        <span class="input-group-text" id="keywordClear"><i class="bi-x"></i></span>
                    </div>
                </form>
            </div>
        </div>
    </nav>
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

<div class="modal fade" id="modalNetInstall" tabindex="-1" aria-labelledby="modalNetInstallLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNetInstallLabel">Net Install</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Switch IP</span>
                        <input type="text" class="form-control" id="netInstalldstAddr" placeholder="x.x.x.x">
                    </div>
                </div>
                <div class="row">
                    <div id="listNetInstall"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="startNetInstall">Start</button>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-3.6.0.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery.lazy.min.js"></script>
<script src="js/nspindexer.js"></script>

<script id="cardTemplate" type="text/x-template">
    <div class="row gx-2 mb-4">
        <div class="col col-2 d-none d-md-block">
            <div class="card px-0 shadow-sm fill cardThumb">
                <img data-src="<%=thumbUrl%>" class="img-fluid lazy"/>
            </div>
        </div>
        <div class="col col-12 col-md-10">
            <div class="card shadow-sm">
                <div class="cardBanner fill rounded-3">
                    <img data-src="<%=bannerUrl%>" class="img-fluid h-100 lazy">
                </div>
                <div class="card-body rounded cardBody">
                    <h5 class="card-title"><strong><%=name%></strong>
                        <button type="button" class="btn btn-sm btn-primary float-end btnNetInstall"
                                data-title-id="<%=titleId%>">
                            <i class="bi-cloud-arrow-up-fill"></i>
                        </button>
                    </h5>
                    <div class="card-text">
                        <p class="small noWrap"><%=intro%></p>
                        <p class="small"><strong>Latest Version:</strong> v<%=latestVersion%> (<%=latestDate%>)
                            <%=updateStatus%></p>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <p class="my-1">
                                    <strong>Base Game:</strong> <%=fileName%>
                                    <span class="float-end">
                                        <span class="badge bg-secondary"><%=fileSize%></span>
                                        <a href="<%=fileUrl%>" class="btn btn-sm bg-primary text-light">
                                            <i class="bi-cloud-arrow-down-fill"></i>
                                        </a>
                                    </span>
                                </p>
                            </li>
                            <li class="list-group-item <%=hideUpdates%>">
                                <p class="my-1 contentListTrigger">
                                    <strong>Updates</strong>
                                    <span class="float-end">
                                    <span class="badge bg-success"><%=countUpdates%></span>
                                    <i class="listChevron bi-chevron-down text-dark"></i>
                                </span>
                                </p>
                                <ul class="list-group my-2 contentList">
                                    <%=listUpdates%>
                                </ul>
                            </li>
                            <li class="list-group-item <%=hideDlc%>">
                                <p class="my-1 contentListTrigger">
                                    <strong>DLC</strong>
                                    <span class="float-end">
                                    <span class="badge bg-success"><%=countDlc%></span>
                                    <i class="listChevron bi-chevron-down text-dark"></i>
                                </span>
                                </p>
                                <ul class="list-group my-2 contentList">
                                    <%=listDlc%>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>

<script id="updateTemplate" type="text/x-template">
    <li class="list-group-item"><strong>#<%=revision%> / v<%=version%>:</strong> <%=name%>
        <span class="float-end">
            <span class="badge bg-secondary"><%=size%></span>
            <a href="<%=url%>" class="btn btn-sm bg-primary text-light">
                <i class="bi-cloud-arrow-down-fill"></i>
            </a>
        </span>
    </li>
</script>

<script id="dlcTemplate" type="text/x-template">
    <li class="list-group-item"><%=name%>
        <span class="float-end">
            <span class="badge bg-secondary"><%=size%></span>
            <a href="<%=url%>" class="btn btn-sm bg-primary text-light">
                <i class="bi-cloud-arrow-down-fill"></i>
            </a>
        </span>
    </li>
</script>

<script id="netInstallTemplate" type="text/x-template">
    <ul class="list-group">
        <li class="list-group-item">
            <div class="row">
                <p class="my-1">
                    <strong>Base Game</strong>
                </p>
            </div>
            <div class="form-check">
                <input class="form-check-input netInstallCheckbox" type="checkbox" id="netinstallbase" data-path="<%=path%>">
                <label class="form-check-label" for="netinstallbase" class="noWrap"><%=name%></label>
            </div>
        </li>
        <li class="list-group-item <%=hideUpdates%>">
            <div class="row">
                <p class="my-1">
                    <strong>Updates</strong>
                </p>
            </div>
            <ul class="list-group">
                <%=listUpdates%>
            </ul>
        </li>
        <li class="list-group-item <%=hideDlc%>">
            <div class="row">
                <p class="my-1">
                    <strong>DLC</strong>
                </p>
            </div>
            <ul class="list-group">
                <%=listDlc%>
            </ul>
        </li>
    </ul>
</script>

<script id="netInstallContentTemplate" type="text/x-template">
    <div class="form-check">
        <input class="form-check-input netInstallCheckbox" type="checkbox" id="netinstall<%=type%>_<%=idx%>" data-path="<%=path%>">
        <label class="form-check-label small" for="netinstall<%=type%>_<%=idx%>" class="noWrap"><%=name%></label>
    </div>
</script>

</body>
</html>
