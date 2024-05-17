<?php
/* WIP, buggy. But it werks as a gallery. */


$configFile = 'config.php';

if (!file_exists($configFile)) {
    die("Error: Configuration file <i>$configFile</i> is missing.");
}
$conf = require_once $configFile;
unset($configFile);
if(!file_exists($conf['logFile'])) die($conf['logFile']. " is missing. Please create it.");

/* draw functions */
function drawHeader(){
    global $conf;

    echo '
    <html>
    <head>
    <META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
    <meta name="Berry" content="no">
    <meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
    <meta http-equiv="cache-control" content="max-age=0">
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="0">
    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
    <meta http-equiv="pragma" content="no-cache">
    <style>
        a:link    {color:#0000ee;}
	a:visited {color:#0000ee;}
	tr {min-width: 100%; background-color:#eeaa88;}
	table {border-collapse: collapse; max-width: 100%; }
	.entry {
		position: absolute;
		overflow-y: hidden;
		vertical-align: top;
		padding: 0.5em 0;
	}
	.entryImage {
		max-height: 200px;
		max-width: 300px;
	}
	.catalog {
	#temporary empty
	}
    </style>
    <title>'.$conf['boardTitle'].'</title>
    </head>
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
        <table width="100%">
            <tr><td bgcolor="#eeaa88">
                <strong><font size="4">'.$conf['boardTitle'].'</font></strong>
            </td></tr>
        </table>
        <tt><br>
        <br>
        '.$conf['boardSubTitle'].'<br>
        <br>
	<br>
	[<a href="'.$conf['mainScript'].'">Back</a>]<br>
        </tt>';
}
function drawFooter(){
    echo '
    <br>
    <h5 align="right">
        <a href="https://github.com/Heyuri/Uploader/">Heyuri</a> + <a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
    </h5>
    </body>
    </html>';
}


function createDataFromString($str){
    return explode("<>",$str);
}

function getID($postData){
    return $postData[0];    
}

function getFileExtension($postData){
    return $postData[1];
}

function getComment($postData){
    return $postData[2];
}

function getMimeType($postData){
    return $postData[6];
}

function getTotalLogLines(){
    global $conf;
    $lineCount = 0; 
    $fileHandle = fopen($conf['logFile'], 'r'); 

    while (!feof($fileHandle)) {
        $line = fgets($fileHandle);
        if ($line !== false && trim($line) !== '') {
            $lineCount++;
        }
    }
    fclose($fileHandle); 

    return $lineCount;
}


function drawPageingBar($page=1){
    global $conf;
    
    $fileCount = getTotalLogLines();
    $pages = ceil($fileCount / $conf['filesPerListing']) + 1;

    if($page === "all"){
        echo '[<a href="'.$conf['home'].'">Home</a>] [<b>ALL</b>] [<a href="'.$_SERVER['PHP_SELF'].'?page=1">1</a>]';
        return;
    }

    echo '[<a href="'.$conf['home'].'">Home</a>] [<a href="'.$_SERVER['PHP_SELF'].'?page=all">ALL</a>]';

    for($i = 1; $i < $pages; $i++) {
        if($i == $page){
            echo '[<b>'.$i.'</b>]'; 
        }else{
            echo '[<a href="'.$_SERVER['PHP_SELF'].'?page='.$i.'">'.$i.'</a>]'; 
        }
    }
}

function drawCatalogListing($page=1){
    global $conf;
    $count = $conf['filesPerListing'];
    if($page == "all"){
        $count = getTotalLogLines();
        $page = 0;
    }else{
        $page = $page - 1;
    }

    $lineOffset = $count * $page;
    $currentLine = 0;

    $fileHandle = fopen($conf['logFile'], 'r');
    //go to the offest
    while ($currentLine < $lineOffset  && !feof($fileHandle)) {
        fgets($fileHandle);
        $currentLine++;
    }
    
   $lineOffset = $currentLine + $count;
    
    
    echo  '<hr><table align="CENTER" cellpadding="0" cellspacing="20" style="font-size:10pt; width:100%;" class="catalog"><tr>'; //init <table>

    while ($currentLine < $lineOffset && !feof($fileHandle)) {
        $line = fgets($fileHandle);
        if ($line == false || trim($line) == '') {
            continue;
	}
	//display thumbnail
	$data = createDataFromString($line);
	$fileName = $conf['prefix'] . getID($data) .'.'. getFileExtension($data);
        $thumbName = $conf['prefix'] . getID($data) .'_thumb.'. getFileExtension($data);
	
	$path = $conf['uploadDir'] . $fileName;
	$thumbPath = $conf['thumbDir'] . $thumbName;

	if(!file_exists($thumbPath)) $thumbPath = $path;
	if(preg_match('/audio/i', getMimeType($data))) $thumbPath = 'static/images/audio_overlay.png'; //if file is an audio it will use a default image 
	
	if(preg_match('/video/i', getMimeType($data))) $thumbPath = $conf['thumbDir'].$conf['prefix'].getID($data).'_thumb.jpg'; //if file is a video it will use a default image 
	if(preg_match('/video/i', getMimeType($data)) && !file_exists($thumbPath)) $thumbPath = 'static/images/video_overlay.png';

	if(preg_match('/application/i', getMimeType($data))) $thumbPath = 'static/images/application_overlay.png'; //if file isn't media it will use a default image 

	echo  '<div class="entry"><td style="display: inline-block; margin: 10px;"><a href="'.$conf['uploadDir'].$fileName.'"><center><img class="entryImage" src="'.$thumbPath.'"></center> </a><br><center>'.getComment($data).'</center></div></td>';

	$currentLine = $currentLine + 1;
    }
    
	echo '</tr></table>';

}

if(isset($_GET['page'])){
    $page = $_GET['page'];
    drawHeader();
    drawPageingBar($page);
    drawCatalogListing($page);
    drawFooter();
    die();
}



drawHeader();
drawPageingBar(1);
drawCatalogListing(1);
drawFooter();
