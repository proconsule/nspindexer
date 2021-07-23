<?php

define("CACHE_DIR", './cache');

/*

NSP Indexer

https://github.com/proconsule/nspindexer

Fell free to use and/or modify

proconsule

Settings Section

*/

require 'config.default.php';

if (file_exists('config.php'))
    require 'config.php';


$scriptversion = 0.1;

function mydirlist($path)
{
    $filelist = array();
    if (file_exists($path) && is_dir($path)) {

        $scan_arr = scandir($path);
        $files_arr = array_diff($scan_arr, array('.', '..'));
        foreach ($files_arr as $file) {
            $file_path = $path . $file;
            $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
            if ($file_ext == "nsp" || $file_ext == "xci" || $file_ext == "nsz" || $file_ext == "xcz") {
                $filelist[] = $file;
            }

        }
    }
    return $filelist;
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


function getTrueFileSize($filename)
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

function genDirList()
{
    global $scriptversion;
    global $scriptdir;
    global $gamedir;
    global $Host;
    global $contentsurl;

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
    $dirfilelist = mydirlist($gamedir);
    asort($dirfilelist);
    foreach ($dirfilelist as $myfile) {
        echo "<tr><td valign=\"top\"><img src=\"/icons/unknown.gif\" alt=\"[   ]\"></td><td><a href=\"" . str_replace($scriptdir, "", $gamedir) . rawurlencode($myfile) . "\">" . str_replace($gamedir, "", $myfile) . "</a></td><td>" . date("Y-d-m H:i", filemtime($gamedir . $myfile)) . "</td><td align=\"right\">" . formatSizeUnits(getTrueFileSize($gamedir . $myfile)) . "</td><td></td>&nbsp;</tr>\r\n";

    }
    echo "   <tr><th colspan=\"5\"><hr></th></tr>\r\n";
    echo "</table>\r\n";
    echo "<address>NSP Indexer v" . $scriptversion . " on " . $_SERVER['SERVER_ADDR'] . "</address>\r\n</body></html>";


}


if ($_GET) {
    if (isset($_GET["tinfoil"])) {
        header("Content-Type: application/json");
        header('Content-Disposition: filename="main.json"');
        $tinarray = array();
        $dirfilelist = mydirlist($gamedir);
        asort($dirfilelist);
        $tinarray["total"] = count($dirfilelist);
        $tinarray["files"] = array();
        foreach ($dirfilelist as $myfile) {
            $myfilefullpath = $gamedir . $myfile;

            $tinarray["files"][] = ['url' => $contentsurl . $myfile . "#" . urlencode(str_replace('#', '', $myfile)), 'size' => getTrueFileSize($gamedir . $myfile)];

        }
        $tinarray['success'] = "NSP Indexer";
        echo json_encode($tinarray);
        die();
    }
    if (array_key_exists("DBI/", $_GET)) {
        genDirList();
        die();
    }
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
</head>
<body>
<?php

function endsWith($haystack, $needle)
{
    return $needle === "" || (substr($haystack, -strlen($needle)) === $needle);
}


function gettitlesid($filearray)
{
    $titlesids = array();
    for ($i = 0; $i < count($filearray); $i++) {
        $listres = array();
        preg_match('/(?<=\[)[0-9A-F].+?(?=\])/', $filearray[$i], $titleidmatches);
        preg_match('/(?<=\[v).+?(?=\])/', $filearray[$i], $versionmatch);
        preg_match('/(\bDLC)/', $filearray[$i], $DLCmatch);


        $listres[] = $filearray[$i];
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


function getJsonString($type)
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


$versionsjson = getJsonString("versions");
$titlesjson = getJsonString("titles");


$myfilelist = gettitlesid(mydirlist($gamedir));

$mygamelist = $myfilelist[0];
$mydlclist = $myfilelist[1];


?>


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

        foreach (array_keys($mygamelist) as $key) {

            ?>

            <li class="table-row">
                <div class="col col-1">
                    <div class="zoom"><img class="displayed"
                                           src="<?php echo $titlesjson[$mygamelist[$key][1]]["iconUrl"] ?>" alt=""
                                           width="80"></div>
                </div>
                <div class="col col-2"><?php echo $mygamelist[$key][1] ?></div>

                <div class="col col-3">
                    <div class="linkdiv"><a
                                href="<?php echo $contentsurl . $mygamelist[$key][0]; ?>"><?php echo $titlesjson[$mygamelist[$key][1]]["name"] ?></a>
                    </div>
                    <div class="introdiv"><?php echo $titlesjson[$mygamelist[$key][1]]["intro"] ?></div>
                </div>
                <div class="col col-4">
                    <?php
                    foreach (array_keys($mygamelist[$key][4]) as $updatekey) {
                        ?>
                        <div class="updatelink">
                            <a href="<?php echo $contentsurl . $mygamelist[$key][4][$updatekey][0]; ?>"><?php echo intval($mygamelist[$key][4][$updatekey][2]) / 65536; ?></a>
                            <span class="updatelinktext"><?php echo "v" . $mygamelist[$key][4][$updatekey][2]; ?></span>
                        </div>


                        <?php
                    }
                    ?>
                    <?php
                    if (array_key_last($versionsjson[strtolower($mygamelist[$key][1])]) != end($mygamelist[$key][4])[2]) {
                        ?>
                        <div class="newupdatediv">
                            Last: <?php echo array_key_last($versionsjson[strtolower($mygamelist[$key][1])]) / 65536; ?>
                            <span class="newupdatedivtext"><?php echo "v" . array_key_last($versionsjson[strtolower($mygamelist[$key][1])]); ?></span>
                        </div>

                        <?php
                    }
                    ?>
                </div>
                <div class="col col-5"><?php echo round(($titlesjson[$mygamelist[$key][1]]["size"] / 1024 / 1024 / 1024), 3) . " GB" ?></div>
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

        foreach (array_keys($mydlclist) as $key) {

            ?>

            <li class="table-row">
                <div class="col col-2"><?php echo $mydlclist[$key][1]; ?></div>
                <div class="col col-3">
                    <div class="linkdiv"><a
                                href="<?php echo $contentsurl . $mydlclist[$key][0]; ?>"><?php echo $titlesjson[$mydlclist[$key][1]]["name"]; ?></a>
                    </div>
                </div>
                <div class="col col-4"><?php echo round(($titlesjson[$mydlclist[$key][1]]["size"] / 1024 / 1024 / 1024), 3) . " GB"; ?></div>


            </li>
            <?php

        }

        ?>

    </ul>
</div>

<div class="footer">
    <?php echo "NSP Indexer v" . $scriptversion; ?>
</div>

</body>
</html>


