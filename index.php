<?php

/*

NSP Indexer

https://github.com/proconsule/nspindexer

Fell free to use and/or modify

proconsule

Settings Section

*/


$scriptdir = "/var/www/html/switch/"; /* Absolute Script Path */
$gamedir = "/var/www/html/switch/data/games"; /* Absolute Files Path */
$Host = "http://". $_SERVER['SERVER_ADDR'] ."/switch/"; /* Web Server URL */
$contentsurl = "http://". $_SERVER['SERVER_ADDR'] ."/switch/data/games/"; /* Files URL */

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

</style>

</head>
<body>
<?php

$scriptversion = 0.1;


function endsWith( $haystack, $needle ) {
    return $needle === "" || (substr($haystack, -strlen($needle)) === $needle);
}



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


function gettitlesid($filearray){
	$titlesids = array();
	for ($i = 0; $i < count($filearray); $i++) {
		$listres = array();
		preg_match('/(?<=\[)[0-9A-F].+?(?=\])/', $filearray[$i], $titleidmatches);
		preg_match('/(?<=\[v).+?(?=\])/', $filearray[$i], $versionmatch);
		preg_match('/(\bDLC)/', $filearray[$i], $DLCmatch);
		
		
		$listres[] = $filearray[$i];
		$listres[] = $titleidmatches[0];
		if($versionmatch[0] == NULL)$versionmatch[0] = "0";
		$listres[] = $versionmatch[0];
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
<div class="linkdiv">
<a href="<?php echo $contentsurl . $mygamelist[$key][4][$updatekey][0];?>"><?php echo intval($mygamelist[$key][4][$updatekey][2])/65536; ?></a>
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


