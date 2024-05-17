<?php

if(file_exists("debug.php")){
    require_once("debug.php");
}

// Define config file here. This all you need to do in this file.
$configFile = 'config.php';

/***************************************************************************
  PHPぁぷろだ by ToR(http://php.s3.to)
  source by ずるぽん(http://zurubon.virtualave.net/)
  English translation & various modifications by Heyuri (https://www.heyuri.net/)

  Heyuri updates (edition 20240517)
    This uploader is a custom version of PHPぁぷろだ.
    Many thanks to ずるぼん-sama for the original source and レッツPHP-sama for the PHP conversion.
    The last update before Heyuri took over was Yakuba modifications (edition 20090922)

■Terms and Conditions
  ・We give no guarantees on its operation. Don’t cry if anything bad happens!
  ・Commercial use is allowed, but do not use it for illegal purposes.
  ・You are free to redistribute and modify. However, you can not remove the links.
  ・These rules are in accordance with レッツPHP-sama's standards...

■History
  2001/08/30
  2001/09/04 v1.1 Cookies enabled for preferences, FTP transfer (deletion not yet works)
  2002/06/12 v1.2 Changed to move_uploaded_file (line 215)
  2002/07/23 v1.3 Some CSS measures for deletion (line 147)
  2002/08/06 v2.0 Slight changes in specifications (about allowed extensions, original file name display)
  2004/10/10 v2.2 Various fixes
  2005/01/10 v2.3 Removed line breaks
  2009/09/20 Revision   Major modifications commented by Yakuba
    ・Check if the log files etc exist.
    ・Display total size of the board
    ・Total capacity limit (cannot post if the limit is exceeded)
    ・Slightly adjusted the layout to resemble SnUploader
    ・Fixed a problem in certain environments where the log file 
      disappears when the uploaded file is deleted and the log is empty
  2009/09/22 Revision   Fixed a bug about forced extension conversions and F5'ing
    ・Forced conversion of specified extensions during upload.
    ・When the extension of a file is converted, display its original extension in its comment.
    ・Fixed a bug where the same operation was repeated if F5 was pressed immediately after
      the operation, such as uploading duplicate files.
  2020/06/?? Nakura from Heyuri has partially translated it to English
  2024/04/20 v3.0 The software is uploaded to github and shared with Hachikuji and Penman, who started working on it to make major changes
  2024/05/17 Revision   Major changes were made to the Uploader's code
    ・Changed all deprecated PHP codes into modern ones
    ・English translation is completed
    ・It displays total board and file sizes in the proper storage units now
    ・Fixed the bug where it didn't check if the board's file size limit was exceeded
    ・Thumbnails implemented. Files larger than 1MB will get thumbnailed. Can be enabled from settings
    ・Brought back sam.php as images.php
    ・User boards (user/) are now an "extra" part of the software. People can create their own boards
    ・Fixed an issue where the server was getting into an error loop if log file didn't exist
    ・Configurations are now in a separate file. Main script doesn't need to be edited by default anymore (unless path of config.php is changed)
    ・User boards are now an "extra" part of the software. Users can create their own boards. They can have custom CSS for their boards too
    ・Configurable cooldown added against flooding
    ・It's now anonymous by default, but can have a setting to log IPs of uploaders
    ・If logging IPs, there are other settings to block IPs from viewing the board & uploading files
    ・Some default CSS fixes
    ・If user didn't enter any password for a file, only the administrator can delete the file

■Installation
  ・Clone repo into web directory (or unzip it there)
  ・cd into the directory and do this: "chmod +x prepare.sh", then run it with "./prepare.sh"
  ・Alternatively create the log file (default: souko.log), the count file (default: count.log), source dir. (src/) and thumb dir (thmb/) yourself
  ・If you change their names, you need to change them from configuration file too
  ・Set owner of all files in the directory to web user by "sudo chown -R webuser:webuser /path/to/Uploader"

■Cautions (it is recommended to check these)
  ・These variables in php.ini may need to be changed if you want to allow files larger than 2MBs to get uploaded:
      「upload_max_filesize」「post_max_size」「memory_limit」「max_execution_time」
  ・And these variables in php.ini may be related to uploading process itself:
      「file_uploads」「upload_tmp_dir」
  ・You can check your server's PHP settings with <?php phpinfo(); ?> (Some servers may not allow this)
  ・Make sure uploaded .php files (and other potentially dangerous extensions) are properly converted to .txt
  ・Hide the log files from displaying from internet with .htaccess, or change their default names so users don't know
**************************************************************************/

if (!file_exists($configFile)) {
    die("Error: Configuration file <i>$configFile</i> is missing.");
}
$conf = require_once $configFile;
unset($configFile);

date_default_timezone_set($conf['timeZone']);

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
        a:hover   {color:#5555ee;}
        a:visited {color:#0000ee;}
        tr:nth-child(odd) {background-color: #f7efea;}
        tr:hover {background-color: #f0e0d6;}
        table {border-collapse: collapse;}
	.previewContainer {
		height: 100px;
	}
	.imagePreview {
	 	max-height: 95px;
	 	max-width: 200px;
	 	padding-right: 5px;
		padding-top: 2px;
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
        </tt>';
}
function drawPageingBar($page=1){
    global $conf;
    
    $fileCount = getTotalLogLines();
    $pages = ceil($fileCount / $conf['filesPerListing']) + 1;

    if($page === "all"){
        echo '[<a href="'.$conf['home'].'">Home</a>]　[<b>ALL</b>] [<a href="'.$_SERVER['PHP_SELF'].'?page=1">1</a>]';
        return;
    }

    echo '[<a href="'.$conf['home'].'">Home</a>]　[<a href="'.$_SERVER['PHP_SELF'].'?page=all">ALL</a>]';

    for($i = 1; $i < $pages; $i++) {
        if($i == $page){
            echo '[<b>'.$i.'</b>]'; 
        }else{
            echo '[<a href="'.$_SERVER['PHP_SELF'].'?page='.$i.'">'.$i.'</a>]'; 
        }
    }
}
function drawFileListing($page=1){
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
    
    $cookie = getSplitCookie();
    // Main header (please adjust the width if you change the display items)
    echo                                    '<hr><table width="100%" style="font-size:10pt;"><tr>';
    if($cookie['showDeleteButton']) echo    '<td width="4%"><tt><b>DEL</b></tt></td>';
    echo                                    '<td width="8%"><tt><b>NAME</b></tt></td>';
    if($cookie['showComment'])  echo        '<td width="58%"><tt><b>COMMENT</b></tt></td>';
    if($cookie['showFileSize']) echo        '<td width="7%"><tt><b>SIZE</b></tt></td>';
    if($cookie['showMimeType']) echo        '<td><tt><b>MIME</b></tt></td>';
    echo                                    '</tr>';

    $lineOffset = $currentLine + $count;
    while ($currentLine < $lineOffset && !feof($fileHandle)) {
        $line = fgets($fileHandle);
        if ($line == false || trim($line) == '') {
            continue;
            //empty line
        }
        $data = createDataFromString($line);
	
        $fileName = $conf['prefix'] . getID($data) .'.'. getFileExtension($data);
	$thumbName = $conf['prefix'] . getID($data) .'_thumb.'. getFileExtension($data);
	
	$path = $conf['uploadDir'] . $fileName;
	$thumbPath = $conf['thumbDir'].$thumbName;
	
	if(preg_match('/audio/i', getMimeType($data))) $thumbPath = 'static/images/audio_overlay.png'; //if file is an audio it will use a default image 

	if(preg_match('/video/i', getMimeType($data))) $thumbPath = $conf['thumbDir'].$conf['prefix'].getID($data).'_thumb.jpg'; //if file is a video it will use a default imag 
	if(preg_match('/video/i', getMimeType($data)) && !file_exists($thumbPath)) $thumbPath = 'static/images/video_overlay.png';

	if(!file_exists($thumbPath)) $thumbPath = $path;
	
	if($cookie['showDeleteButton']) echo    '<td><small><a href='. $_SERVER['PHP_SELF'] .'?deleteFileID='.getID($data).'>■</a></small></td>';
	if($cookie['showPreviewImage']) echo    '<td class="previewContainer"><a href="'. $path .'"><img class="imagePreview" src="'.$thumbPath.'"><br>'.$fileName.'</a></td>'; else echo '<td> <a href="'. $path .'">'.$fileName.'</td>';
	if($cookie['showComment'])	echo	'<td><font size=2>'. getComment($data) .'</font></td>';
	if($cookie['showFileSize'])	echo	'<td><font size=2>'. bytesToHumanReadable(getSizeInBytes($data)) .'</font></td>';
        if($cookie['showMimeType'])	echo	'<td><font size=2 color=888888>'. getMimeType($data) .'</font></td>';
        echo                                    '</tr>';

        $currentLine = $currentLine + 1;
    }
    
    echo "</table><hr>";
    echo 'Used '. bytesToHumanReadable(getTotalUsageInBytes()).'/ '. bytesToHumanReadable($conf['maxTotalSize']).'<br>';
    echo 'Used '.getTotalLogLines().' Files/ '. $conf['maxAmountOfFiles'].' Files<br>';
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
function drawErrorPageAndExit($mes1,$mes2=""){
    global $base_php;
    drawHeader();
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center> 
    [<a href="'.$base_php.'">Back</a>]';
    drawFooter();
    exit;
}
function drawMessageAndRedirectHome($mes1,$mes2=""){ 
    drawHeader();
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center>
    [<a href="'.$_SERVER['PHP_SELF'].'">Back</a>]
    <script type="text/javascript">setTimeout(location.href="'.$_SERVER['PHP_SELF'].'",0)</script>';
    drawFooter();
    exit;
}
function drawUploadForm(){
    // Post form header (Yakuba modification)
    // Check if the overall filesize limit for the board has been exceeded
    global $conf;
    if(getTotalUsageInBytes() >= $conf['maxTotalSize']){
        echo '
        The total capacity has exceeded the limit and is currently under posting restriction.<br>
        Please notify the administrator.<br>
        <br>';
    }
    else{
        echo '
        <form method="post" enctype="multipart/form-data" action="'. $_SERVER['PHP_SELF'] .'">
        <input type="hidden" name="MAX_FILE_SIZE" value="'. $conf['maxUploadSize'] .'">
            MAX UPLOAD SIZE: '. bytesToHumanReadable($conf['maxUploadSize']) .'<br>
            <input type=file name="upfile"> 

            DELETION KEY: <input type=password size="10" name="password" maxlength="10"><br>
            COMMENT<i><small>(※If no comment is entered, the page will be reloaded / URL will be auto-linked.)</small></i><br>
            <input type="text" size="45" value="'.$conf['defaultComment'].'" name="comment">
            <input type=submit value="Up/Reload">
            <input type=reset value="Cancel"><br>
            <small><details> <summary>Allowed extensions</summary>Allowed extensions: '.  implode(", ", $conf['allowedExtensions']) .'</summary></details></small>
        </form>
        ';
    }
}
function drawDeletionForm($fielID){
    echo'
    <form action='.$_SERVER['PHP_SELF'].' method="post">
        <input type=hidden name=deleteFileID value="'.$fielID.'">
        Enter your password: <input type=password size=12 name=password>
        <input type=submit value="Delete">
    </form>';
}
function drawSettingsForm(){
    $cookie = getSplitCookie();
    echo '
    <hr>
    <strong>client Settings</strong><br>
    <form method=POST action="'.$_SERVER['PHP_SELF'].'">
    <input type=hidden name=action value="setUserSettings">
    <ul>
        <li><strong>display</strong>
        <ul>
            <input type=checkbox name=showDeleteButton value=checked '.$cookie['showDeleteButton'].'>show delete button<br>
            <input type=checkbox name=showComment  value=checked '.$cookie['showComment'].'>show comments<br>
	    <input type=checkbox name=showPreviewImage value=checked '.$cookie['showPreviewImage'].'>show preview<br>
	    <input type=checkbox name=showFileSize value=checked '.$cookie['showFileSize'].'>show file size<br>
            <input type=checkbox name=showMimeType value=checked '.$cookie['showMimeType'].'>show MIME types<br>
        </ul>
    <ul><br>
    <br><br>

    <input type=submit value="save">
    <input type=reset value="clear">
    </form>
    <a href="'.$_SERVER['PHP_SELF'].'">[Back]</a>';
}
function drawActionLinks(){
    echo '
    <HR size=1>
    <small>
        <a href="'.$_SERVER['PHP_SELF'].'?goingto=settings">settings</a> | <a href="'.$_SERVER['PHP_SELF'].'">reload</a> | <a href="images.php">image list</a>
    </small>
    <HR size=1>';
}

/* data getters */
function getLastID(){
    global $conf;
    $logFile = $conf['logFile'];
    $openFile = fopen($logFile,"r");
    
    $firstLine = fgets($openFile);
    $array = explode("<>",$firstLine);
    fclose($openFile);
    return getID($array) ?? 1;
}
function getDataByID($id){
    global $conf;
    $logFile = $conf['logFile'];
    $openFile = fopen($logFile,"r");
    $data = null;

    while(!feof($openFile)){ 
        $line = fgets($openFile);
        $array = explode("<>",$line);
        if($array[0] == $id){
            $data = $array;
            break;
        }
    } 
    fclose($openFile);

    return $data;
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
function getHost($postData){
    return $postData[3];
}
function getDateUploaded($postData){
    return $postData[4];
}
function getSizeInBytes($postData){
    return $postData[5];
}
function getMimeType($postData){
    return $postData[6];
}
function getPassword($postData){
    return $postData[7];
}
function getOriginalFileName($postData){
    return $postData[8];
}
function createData($id,$fileExtension,$comment,$ip,$time,$size,$mimeType,$password,$orignalFileName){
    return array($id,$fileExtension,$comment,$ip,$time,$size,$mimeType,$password,$orignalFileName);
}
function createDataFromString($str){
    return explode("<>",$str);
}
function isDataEmpty($data) {
    if(count($data) < 8){
        return true;
    }
    return false;
}
/* helper libs */

//generate thumbnail
function thumbnailImage($imagePath, $thumbPath, $w, $h) {
	global $conf;
    try {
    	$img = new Imagick(realpath($imagePath));
    	$img->setbackgroundcolor('rgb(64, 64, 64)');
    	$img->thumbnailImage($w, $h, true);
    
	$img->writeImage($thumbPath);
    } catch (Exception $e) {
    	drawErrorPageAndExit("There was an error with thumbnailImage() in ".$conf['mainScript'].". Please contact the administrator.", $e->getMessage());
    }
}
function thumbnailVideo($videoPath, $thumbPath, $w, $h) {
	global $conf;
    try {
	shell_exec("ffmpeg -i $videoPath -ss 00:00:01.000 -vframes 1 -s ".$w.'x'.$h." $thumbPath");
    } catch (Exception $e) {
    	drawErrorPageAndExit("There was an error with thumbnailVideo() in ".$conf['mainScript'].". Please contact the administrator.", $e->getMessage());
    }

}

function writeDataToLogs($data){
    global $conf;

    $stringData = implode("<>", $data) . "\n";

    $fileHandle = fopen($conf['logFile'], "c+");

    if ($fileHandle === false) {
        // Handle error when file cannot be opened
        echo "Failed to open log file.";
        return false;
    }

    // Acquire an exclusive lock
    if (!flock($fileHandle, LOCK_EX)) {
        echo "Could not lock log file.";
        fclose($fileHandle);
        return false;
    }

    // Read the existing contents to prepend new data
    $existingData = stream_get_contents($fileHandle);

    // Rewind the file pointer to the beginning of the file
    rewind($fileHandle);

    // Prepend new data and write the existing data back
    if (fwrite($fileHandle, $stringData . $existingData) === false) {
        echo "Failed to write to log file.";
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);
        return false;
    }

    // Unlock the file and close
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);

    return true;
}
function getTotalUsageInBytes(){
    // Total file size calculation
    global $conf;
    $logFile = $conf['logFile'];
    $totalSize=0;
    $openFile = fopen($logFile,"r");

    //id<>fileExtension<>comment<>host<>dateUploaded<>sizeInBytes<>mimeType<>Password<>orginalFileName
    while(!feof($openFile)){ 
        $line = fgets($openFile);
        if ($line == false && trim($line) == '') {
            continue;
        }
        $array = explode("<>",$line);
        $size = getSizeInBytes($array);
        $totalSize = $totalSize + $size;
    } 
    fclose($openFile);
    return $totalSize;
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
function deleteFileByData($data){
    global $conf;
    $path = $conf['uploadDir'] . $conf['prefix'] . getID($data) . '.' . getFileExtension($data);
    unlink($path);
}
function removeLastData(){
    global $conf;
    $fileHandle = fopen($conf['logFile'], 'r+'); 
    flock($fileHandle, LOCK_EX);

    if (!$fileHandle) {
        return [false, ""]; // Return false and an empty string if the file cannot be opened
    }

    $lastLine = '';
    $len = 0; // To track the length of the last line

    // Move to the end of the file
    fseek($fileHandle, 0, SEEK_END);
    $fileSize = ftell($fileHandle); // Get the size of the file

    // Read backwards to find the beginning of the last line
    while ($fileSize > 0) {
        fseek($fileHandle, --$fileSize, SEEK_SET);
        $char = fgetc($fileHandle);
        if ($char == "\n" && $len > 0) {
            break;
        }
        if ($char != "\r") {
            $lastLine = $char . $lastLine;
            $len++;
        }
    }

    // Truncate the file to remove the last line
    if ($fileSize == 0) { // If it's the first and only line in the file
        ftruncate($fileHandle, 0);
    } else {
        ftruncate($fileHandle, $fileSize);
    }

    // Close the file handle
    fclose($fileHandle);

    $data = explode("<>", $lastLine);
    delteFileByData($data);

    return [true, $lastLine]; // Return true and the last line
}
function bytesToHumanReadable($size){
    if($size == 0){
        $format = "";
    }
    elseif($size <= 1024){
        $format = $size."B";
    }
    elseif($size <= (1024*1024)){
        $format = sprintf ("%dKB",($size/1024));
    }
    elseif($size <= (1000*1024*1024)){
        $format = sprintf ("%.2fMB",($size/(1024*1024)));
    }
    elseif($size <= (1000*1024*1024*1024)){
        $format = sprintf ("%.2fGB",($size/(1024*1024*1024)));
    }
    elseif($size <= (1000*1024*1024*1024*1024)  || $size >= (1000*1024*1024*1024*1024)){
        $format = sprintf ("%.2fTB",($size/(1024*1024*1024*1024)));
    }
    else{ 
        $format = $size."B";
    }

    return $format;
}
function IsBanned($host){
    global $conf;
    if($host == "1337"){
        return false;
    }
    if(in_array($host, $conf['denylist'])) {
            return true;
    }
    return false;
}
function isGlobalBanned($host){
    global $conf;
    if($host == "1337"){
        return false;
    }
    if(in_array($host, $conf['hardBanList'])) {
            return true;
    }
    
    return false;
}

function deleteDataFromLogByID($id){
    global $conf;
    $logFile = $conf['logFile'];
    $openLogFile = fopen($logFile, "r");
    $dataIsFoundInFile = false;
    $newFileContent = [];
    $foundData = null;

    // while not at the end of the file.
    while (!feof($openLogFile)) {
        $line = fgets($openLogFile);
        $data = explode("<>", $line);

        if ($data[0] == $id) {
            $dataIsFoundInFile = true;
            $foundData = $data;
        } else {
            $newFileContent[] = $line;
        }
    }
    fclose($openLogFile);

    // data was not found.
    if ($dataIsFoundInFile == false) {
        return false;
    }


    $openLogFile = fopen($logFile, "w");
    flock($openLogFile, LOCK_EX);

    foreach ($newFileContent as $line) {
        fwrite($openLogFile, $line);
    }
    fclose($openLogFile);
    
    deleteFileByData($foundData);

    return true;
}
function loadCookieSettings(){
    global $conf;

    if(isset($_COOKIE['settings']) == false){
        $cookie = implode("<>", $conf['defaultCookieValues']);
    }else{
        $cookie = $_COOKIE['settings'];
    }

    if(isset($_POST['action']) && $_POST['action'] == "setUserSettings"){
        // the order of this array must be the same order as $conf['defualtCookieValues']
        $cookie = implode("<>", array(   $_POST['showDeleteButton'] ?? ""
                                        ,$_POST['showComment'] ?? ""
					,$_POST['showPreviewImage'] ?? ""
					,$_POST['showFileSize'] ?? ""
                                        ,$_POST['showMimeType'] ?? ""));
    }

    setcookie("settings", $cookie,time()+365*24*3600);
    $_COOKIE['settings'] = $cookie;
}
function getSplitCookie(){
	if(sizeof(explode("<>", $_COOKIE['settings'])) != sizeof(array('showDeleteButton', 'showComment', 'showPreviewImage', 'showFileSize', 'showMimeType')))
		drawErrorPageAndExit_headless("File listing could not be displayed", "Please refresh cookies. If problem persists, contact the administrator");
	return array_combine(['showDeleteButton', 'showComment', 'showPreviewImage', 'showFileSize', 'showMimeType'], explode("<>",$_COOKIE['settings']));
}


function isBoardBeingFlooded() {
    global $conf;
    $lastPost = getDataByID(getLastID());
    if(isDataEmpty($lastPost)){
        // cant flood if there is not even a single post
        return false;
    }

    $lastTime = getDateUploaded($lastPost);
    if($lastTime + $conf['coolDownTime'] > time()){
        return true;
    }else{
        return false;
	}
}
/* main funcitons */

function userUploadedFile(){
    global $conf;

    if(IsBanned($_SERVER['REMOTE_ADDR'])){
	drawErrorPageAndExit("You are banned from uploading!");
    }
    if(isBoardBeingFlooded()){
        drawErrorPageAndExit("OUCH!!", "I need to wait before acepting another file..");
    }
    if($_FILES["upfile"]['size'] <= 0){
        drawErrorPageAndExit('Please select a file.');
    }
    if($_FILES["upfile"]['size'] > $conf['maxUploadSize']){
        drawErrorPageAndExit('File is too big.');
    }
    if($_POST['comment'] == "" && $conf['commentRequired']){
        drawErrorPageAndExit('Comment is required.');
    }
    if(strlen($_POST['comment']) > $conf['maxCommentSize']){
        drawErrorPageAndExit('Comment is too big.');
    }

    $fullFileName = $_FILES["upfile"]["name"];
    $fileInfo = pathinfo($fullFileName);

    $fileName = $fileInfo['filename'];
    $fileExtension = strtolower($fileInfo['extension']);

    if(!in_array($fileExtension, $conf['allowedExtensions'])){
        drawErrorPageAndExit("Invalid extension","file can not be uploaded with that extension");
    }

    $originalExtension = $fileExtension;
    // convert posibly dangerous scripts into text files
    if(in_array($fileExtension, $conf['extentionsToBeConvertedToText'])){
        $originalExtension = $fileExtension;
        $fileExtension = "txt";
    }
    // get mimetype for this post
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // Return MIME type
    $realMimeType = finfo_file($finfo, $_FILES['upfile']['tmp_name']);
    finfo_close($finfo);

    // get a ID for this new post
    $newID = sprintf("%03d", (int)getLastID() + 1);
    $newname = $conf['prefix'] . $newID . "." . $fileExtension;

    rename($_FILES['upfile']['tmp_name'], $conf['uploadDir'].$newname);
    chmod($conf['uploadDir'] . $newname, 0644);

    // remove line breaks from the comment
    $comment = htmlspecialchars(str_replace(array("\0","\t","\r","\n","\r\n"), "", $_POST['comment']));
   
    // check if the extention has been converted to something safe
    if($originalExtension != $fileExtension){
        //show the converstion
        $comment = $comment . '<font color="#ff0000">('. $fileExtension .'←'. $originalExtension .')</font>';
    }
    
    // get password
    if(isset($_POST['password'])){
        $password = $_POST['password'];
    }else{
        $password = '';
    }

    $data = createData( $newID, $fileExtension, $comment, $_SERVER['REMOTE_ADDR'],
                        time(), $_FILES['upfile']['size'], $realMimeType, $password,
                        $fileName);

    // if over max. delete last file
    if(getTotalLogLines() >= $conf['maxAmountOfFiles']){
        if($conf['deleteOldestOnMaxFiles']){
            removeLastData(); //remove file if deleteOldestOnMaxFiles is true
        }
	    drawErrorPageAndExit("File limit reached, contact administrator.");
    }
    writeDataToLogs($data);
	
    //create thumbnail if file type is image and size is above 1mb
    if(preg_match('/image/i', getMimeType($data)) && $_FILES["upfile"]['size'] >= 1*1024*1024) { 
	$imagePath = $conf['uploadDir'].$conf['prefix'].$newID.'.'.$fileExtension;
    	thumbnailImage($imagePath, $conf['thumbDir'].$conf['prefix'].$newID.'_thumb.'.$fileExtension, 200, 95); 
    }
    //create thumbnail if file type is image and size is above 1mb
    if(preg_match('/video/i', getMimeType($data))) { 
	$videoPath = $conf['uploadDir'].$conf['prefix'].$newID.'.'.$fileExtension;
    	thumbnailVideo($videoPath, $conf['thumbDir'].$conf['prefix'].$newID.'_thumb.jpg', 200, 95); 
    }


    drawMessageAndRedirectHome('The process is over. The screen will change automatically.','If this does not change, click "Back".');
}
function userDeletePost(){
    global $conf;
    $fileID = $_POST['deleteFileID'];
    $password = $_POST['password'];

    $postData = getDataByID($fileID);
    if(is_null($postData)){
        drawErrorPageAndExit('Deletion Error','The file cannot be found.');
    }
    elseif($password == getPassword($postData) || $password == $conf['adminPassword']){
	deleteDataFromLogByID($fileID);
	$thumbPath = $conf['thumbDir'] . $conf['prefix'] . getID($postData) . '_thumb.' . getFileExtension($postData);
	unlink($thumbPath);

        drawMessageAndRedirectHome('file has been deleted.','If this page dose not change, click "Back".');
    }
    elseif(getPassword($postData) == ''){
        drawErrorPageAndExit('Deletion Error','There was not a password when this post was created. Contact the administrator to request deletion');
    }else{
        drawErrorPageAndExit('Deletion Error','The password is incorrect.');
    }
}

/*
 *  Start of the main logic
 */

if($conf['logUserIP'] == false){
    $_SERVER['REMOTE_ADDR'] = "1337";
}

// check if user is hard banned (cannot lurk)
if(isGlobalBanned($_SERVER['REMOTE_ADDR'])){
       	drawErrorPageAndExit("You have been banned by the administrator. ヽ(ー_ー )ノ");
}


loadCookieSettings();

/* deletion form was posted to */
if(isset($_POST['deleteFileID']) && isset($_POST['password'])){
    if(is_numeric($_POST['deleteFileID']) == false){
        drawErrorPageAndExit("failed to delete", "deleteFileID is not a number");
    }
    userDeletePost();
    die();
}
/* file is uploading */
if(isset($_FILES['upfile'])){
    userUploadedFile();
    die();
}
/* draw a form when user is attempting to delete a file */
if(isset($_GET['deleteFileID'])){
    drawHeader();
    drawDeletionForm(htmlspecialchars($_GET['deleteFileID']));
    drawFooter();
    die();
}
if(isset($_GET['goingto'])){
    switch($_GET['goingto']){
        case "settings":
            drawHeader();
            drawSettingsForm();
            drawFooter();
            die();
    }
}
if(isset($_GET['page'])){
    $page = $_GET['page'];
    drawHeader();
    drawUploadForm();
    drawPageingBar($page);
    drawActionLinks();
    drawFileListing($page);
    drawFooter();
    die();
}

drawHeader();
drawUploadForm();
drawPageingBar(1);
drawActionLinks();
drawFileListing(1);
drawFooter();
