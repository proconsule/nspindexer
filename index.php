<?php

/*

NSP Indexer

https://github.com/proconsule/nspindexer

Fell free to use and/or modify

proconsule

Settings Section

*/


$scriptdir = "/var/www/html/switch/"; /* Absolute Script Path */
$gamedir = "/var/www/html/switch/data/games/"; /* Absolute Files Path */
$Host = "http://". $_SERVER['SERVER_ADDR'] ."/switch/"; /* Web Server URL */
$contentsurl = "http://". $_SERVER['SERVER_ADDR'] ."/switch/data/games/"; /* Files URL */


?>
<?php

$scriptversion = 0.1;

function mydirlist($path){
  $filelist = array();
  if (file_exists($path) && is_dir($path) ) {
    
      $scan_arr = scandir($path);
      $files_arr = array_diff($scan_arr, array('.','..') );
      foreach ($files_arr as $file) {
        $file_path = $path.$file;
        $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
        if ($file_ext=="nsp" || $file_ext=="xci" || $file_ext=="nsz" || $file_ext=="xcz") {
          $filelist[] = $file;
        }
        
      }
  }
  return $filelist;
}

function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 1) . 'G';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 0) . 'M';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 0) . 'K';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
}


function getTrueFileSize($filename) {
    $size = filesize($filename);
    if ($size === false) {
        $fp = fopen($filename, 'r');
        if (!$fp) {
            return false;
        }
        $offset = PHP_INT_MAX - 1;
        $size = (float) $offset;
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


if($_GET){
	if(isset($_GET["tinfoil"])){
		header("Content-Type: application/json");
	    header('Content-Disposition: filename="main.json"');
		$tinarray = array();
		$dirfilelist = mydirlist($gamedir);
		asort($dirfilelist);
        $tinarray["total"] = count($dirfilelist);
        $tinarray["files"] = array(); 		
		foreach ( $dirfilelist as $myfile ) {
			$myfilefullpath = $gamedir . $myfile;
			
			$tinarray["files"][] = [ 'url' => $contentsurl . $myfile ."#".urlencode(str_replace('#','',$myfile)), 'size' => getTrueFileSize($gamedir . $myfile)];
			
		}
		$tinarray['success'] = "NSP Indexer";
		echo json_encode($tinarray);
		die();
	}
	if(isset($_GET["DBI"])){
		echo "<html><head><title>Index of NSP Indexer</title></head><body><h1>Index of NSP Indexer</h1><table><tbody><tr><th valign='top'><img src='/icons/blank.gif' alt='[ICO]'></th><th><a href='?C=N;O=D'>Name</a></th><th><a href='?C=M;O=A'>Last modified</a></th><th><a href='?C=S;O=A'>Size</a></th><th><a href='?C=D;O=A'>Description</a></th></tr><tr><th colspan='5'><hr></th></tr>";
		echo "<tr><td valign='top'><img src='/icons/back.gif' alt='[PARENTDIR]'></td><td><a href=''>Parent Directory</a></td><td>&nbsp;</td><td align='right'>  - </td><td>&nbsp;</td></tr>";
		$dirfilelist = mydirlist($gamedir);
		asort($dirfilelist);
		foreach ( $dirfilelist as $myfile ) {
			echo "<tr><td valign='top'><img src='/icons/unknown.gif' alt='[   ]'></td><td><a href='".$contentsurl.$myfile."'>". str_replace($gamedir,"",$myfile). "</a></td><td>". date ("Y-d-m H:i", filemtime($gamedir . $myfile)) ."</td><td align='right'>".formatSizeUnits(getTrueFileSize($gamedir . $myfile)) ."</td><td></td>&nbsp;</tr>";
			
		}	
		echo "</tbody></table><address>NSP Indexer v". $scriptversion . " on " . $_SERVER['SERVER_ADDR']. "</address></body></html>";
		die();
	}
}


?>



<html>
<head>

<style>
body {
  font-family: "lato", sans-serif;
}

.container {
  max-width: 1000px;
  margin-left: auto;
  margin-right: auto;
  padding-left: 10px;
  padding-right: 10px;
}

h2 {
  font-size: 26px;
  margin: 20px 0;
  text-align: center;
}
h2 small {
  font-size: 0.5em;
}

.responsive-table li {
  border-radius: 3px;
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  margin-bottom: 25px;
}
.responsive-table .table-header {
  background-color: #95A5A6;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.responsive-table .table-row {
  background-color: #ffffff;
  box-shadow: 0px 0px 9px 0px rgba(0, 0, 0, 0.1);
}
.responsive-table .col-1 {
  flex-basis: 10%;
}
.responsive-table .col-2 {
  flex-basis: 20%;
  align-self: center;
}
.responsive-table .col-3 {
  flex-basis: 50%;
  align-self: center;

}
.responsive-table .col-4 {
  flex-basis: 10%;
  align-self: center;
}
.responsive-table .col-5 {
  flex-basis: 10%;
  align-self: center;
}

@media all and (max-width: 767px) {
  .responsive-table .table-header {
    display: none;
  }
  .responsive-table li {
    display: block;
  }
  .responsive-table .col {
    flex-basis: 100%;
  }
  .responsive-table .col {
    display: flex;
    padding: 10px 0;
  }
  .responsive-table .col:before {
    color: #6C7A89;
    padding-right: 10px;
    content: attr(data-label);
    flex-basis: 50%;
    text-align: right;
  }
}

IMG.displayed {
    position: relative;
	top: 10%;
    left: 10%;
    }
	

.zoom {
#  background-color: green;
  
  transition: transform .2s; 
  width: 100;
  height: 100px;
}

.zoom:hover {
  transform: scale(2.5);

}

.footer {
   position: fixed;
   left: 0;
   bottom: 0;
   width: 100%;
   background-color: black;
   color: white;
   text-align: center;
}

.col-3 .introdiv {
    display: none;
	font-size: 12px;
	position: realtive;
	
	
}

.linkdiv:hover {font-size:150%;}

.responsive-table .col-3:hover .introdiv{
	display: block;
}

.linkdiv{
	
}

.updatelink {
  
}

.updatelink:hover {
	
}

.updatelink .updatelinktext {
  visibility: hidden;
  width: 120px;
  background-color: #555;
  color: #fff;
  text-align: center;
  border-radius: 6px;
  position: absolute;
  z-index: 1;
  margin-left: -65px;
  margin-top: -25px;
  opacity: 0;
  transition: opacity 0.3s;
}



.updatelink:hover .updatelinktext {
  visibility: visible;
  opacity: 1;
}


</style>

</head>
<body>
<?php




function endsWith( $haystack, $needle ) {
    return $needle === "" || (substr($haystack, -strlen($needle)) === $needle);
}



function gettitlesid($filearray){
	$titlesids = array();
	for ($i = 0; $i < count($filearray); $i++) {
		$listres = array();
		preg_match('/(?<=\[)[0-9A-F].+?(?=\])/', $filearray[$i], $titleidmatches);
		preg_match('/(?<=\[v).+?(?=\])/', $filearray[$i], $versionmatch);
		preg_match('/(\bDLC)/', $filearray[$i], $DLCmatch);
		
		
		$listres[] = $filearray[$i];
		$listres[] = $titleidmatches[0];
		if($versionmatch == NULL){
			$listres[] = "0";
		}else{
			$listres[] = $versionmatch[0];
		}
		if(endsWith($titleidmatches[0],"000")){
			$listres[] = 0;
		}else if($DLCmatch){
			$listres[] = 2;
		}else{
			$listres[] = 1;
		}	
		$titlesids[] = $listres;
		
		
	}
	
	$dlclist = array();
	$gamelist = array();
    for ($i = 0; $i < count($titlesids); $i++) {
         if($titlesids[$i][3] == 0){
			$titlesids[$i][] = array();
			$gamelist[$titlesids[$i][1]] = $titlesids[$i];
         }
		 if($titlesids[$i][3] == 2){	
			$dlclist[$titlesids[$i][1]] = $titlesids[$i];
		 }		 
			 
		 
	}
	for ($i = 0; $i < count($titlesids); $i++) {
         if($titlesids[$i][3] == 1){
			$gamelist[substr($titlesids[$i][1],0,-3)."000"][4][] = $titlesids[$i];
         }		  
			 
		 
	}
	
	$returnlist = array();
	$returnlist[] = $gamelist;
	$returnlist[] = $dlclist;
	
	return $returnlist;
}



$versionsjsonstring = file_get_contents("./versions.json");
if ($versionsjsonstring === false) {
echo "Missing versions.json consider download <a href=\"https://tinfoil.media/repo/db/versions.json\">https://tinfoil.media/repo/db/versions.json</a><br>";

ob_flush();
flush();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://tinfoil.media/repo/db/versions.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ');
$out = curl_exec($ch);
curl_close($ch);


$versionsjsonstring = $out;
ob_flush();
flush();	
}


$titlesjsonstring = file_get_contents("./titles.json");
if ($titlesjsonstring === false) {
echo "Missing titles.json consider download <a href=\"https://tinfoil.media/repo/db/titles.json\">https://tinfoil.media/repo/db/titles.json</a><br>";

ob_flush();
flush();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://tinfoil.media/repo/db/titles.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ');
$out = curl_exec($ch);
curl_close($ch);


$titlesjsonstring = $out;
ob_flush();
flush();	
	
}

$versionsjson = json_decode($versionsjsonstring, true);
if ($versionsjson === null) {
    
}

$titlesjson = json_decode($titlesjsonstring, true);
if ($titlesjson === null) {
    
}


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

foreach(array_keys($mygamelist) as $key){

?>

<li class="table-row">
<div class="col col-1"><div class="zoom"><img class="displayed" src="<?php echo $titlesjson[$mygamelist[$key][1]]["iconUrl"] ?>" alt="" width="80" ></div></div>
<div class="col col-2"><?php echo $mygamelist[$key][1] ?></div>

<div class="col col-3"><div class="linkdiv"><a href="<?php echo $contentsurl . $mygamelist[$key][0]; ?>"><?php echo $titlesjson[$mygamelist[$key][1]]["name"] ?></a></div><div class="introdiv"><?php echo $titlesjson[$mygamelist[$key][1]]["intro"] ?></div></div>
<div class="col col-4">
<?php
foreach(array_keys($mygamelist[$key][4]) as $updatekey){
?>
<div class="updatelink">
<a href="<?php echo $contentsurl . $mygamelist[$key][4][$updatekey][0];?>"><?php echo intval($mygamelist[$key][4][$updatekey][2])/65536; ?></a>
<span class="updatelinktext"><?php echo "v". $mygamelist[$key][4][$updatekey][2]; ?></span>
</div>




<?php
}
?>
</div>
<div class="col col-5"><?php echo round(($titlesjson[$mygamelist[$key][1]]["size"]/1024/1024/1024),3) . " GB" ?></div>
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

foreach(array_keys($mydlclist) as $key){

?>

<li class="table-row">
<div class="col col-2"><?php echo $mydlclist[$key][1]; ?></div>
<div class="col col-3"><div class="linkdiv"><a href="<?php echo $contentsurl . $mydlclist[$key][0]; ?>"><?php echo $titlesjson[$mydlclist[$key][1]]["name"]; ?></a></div></div>
<div class="col col-4"><?php echo round(($titlesjson[$mydlclist[$key][1]]["size"]/1024/1024/1024),3) . " GB"; ?></div>


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


