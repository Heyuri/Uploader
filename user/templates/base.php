<?php

if(file_exists("debug.php")){
    require_once("debug.php");
}


// Define config file here. This all you need to do in this file.
$configFile = 'config.php';
require_once '../../globalconf.php';

/***************************************************************************
  PHPぁぷろだ by ToR(http://php.s3.to)
  source by ずるぽん(http://zurubon.virtualave.net/)

  Yakuba改(20090922版)
    このアップローダはPHPぁぷろだのカスタマ版です。
    ソースもとのずるぽん様、PHP化をされたレッツPHP様に感謝です。

■お約束
  ・動作無保証。何が起きても泣かない！
  ・商用利用は可能ですが、違法目的には使わないでください。
  ・再配布などはご自由に。ただしリンクは消さないでください。
  ・ここらへんはレッツPHP様準拠と言うことで…

■履歴
  2001/08/30
  2001/09/04 v1.1 クッキーで環境設定、FTP転送（削除はまだ
  2002/06/12 v1.2 move_uploaded_fileに変更（215行
  2002/07/23 v1.3 del=のCSS対策(147行
  2002/08/06 v2.0 仕様ちょと変える(許可拡張子、元ファイル名表示
  2004/10/10 v2.2 もろもろ修正
  2005/01/10 v2.3 改行削除
  2009/09/20 改   もろもろ改造(大きな改造箇所はYakubaコメント有)
    ・ログファイルなどの存在チェック
    ・ファイルの総容量の表示
    ・総容量規制(上限を越えると投稿できない)
    ・SnUploaderに少しだけレイアウトを近づけた
    ・特定の環境で、アップしたファイルを削除しログが空になるとログファイルが
      消える、又は極一部の環境で改行のみのログファイルが未来永劫に作られる問
      題を修正。
  2009/09/22 改   拡張子の強制変換とF5バグの修正
    ・指定した拡張子をアップの際に強制変換。
    ・拡張子を変換した場合、コメントに変更前後の拡張子を表示させるようにした。
    ・アップなどの処理の直後、F5を押すと同じ処理を繰り返すバグを修正。
  2024/04/20 v3.0 software is uploaded to github and shared with few developers
    ・Changed deprecated PHP codes into modern ones
    ・It displays total board size in the proper unit now
    ・Fixed the bug where it didn't check board filesize limit

■設置準備
  ・格納ディレクトリを作成しパーミッションは777に。
  ・カウンタファイル、連続投稿ファイル、ログファイルを用意してアップ。
  ・上記ファイルのパーミッションを666に変更。
  ・ルートディレクトリは755(suExec=701)でＯＫ。
  ・本体は644(600)でＯＫ。

■注意(確認しておくのが望ましい)
  ・容量制限（標準2M）に関係ありそうな、php.iniの項目
      「upload_max_filesize」「post_max_size」「memory_limit」「max_execution_time」
  ・アップロード自体に関係ありそうな、php.iniの項目
      「file_uploads」「upload_tmp_dir」
  ・<?php phpinfo(); ?>でPHPの設定情報を確認(出来ない鯖も有るけど)
  ・もしもの為の.htaccess （CGI禁止SSI禁止Index表示禁止）
      Options -ExecCGI -Includes -Indexes
      .txtでも、中身がHTMLだと表示されちゃうので注意

**************************************************************************/
//

/*
 * Heyuri's file uploader.
 */

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
    <link rel="stylesheet" href="csrc/custom.css">
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
	 	max-height: 90px;
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

	if(!file_exists($thumbPath)) $thumbPath = $path;

	if(preg_match('/audio/i', getMimeType($data))) $thumbPath = STATICPATH.'images/audio_overlay.png'; //if file is an audio it will use a default image 	
	
	if(preg_match('/video/i', getMimeType($data))) $thumbPath = $conf['thumbDir'].$conf['prefix'].getID($data).'_thumb.png'; //if file is a video it will use a default image 
	if(preg_match('/video/i', getMimeType($data)) && !file_exists($thumbPath)) $thumbPath = STATICPATH.'images/video_overlay.png';

	if(preg_match('/application/i', getMimeType($data))) $thumbPath = STATICPATH.'images/application_overlay.png'; //if file isn't media it will use a default image 


	if($cookie['showDeleteButton']) echo    '<td><small><a href='. $_SERVER['PHP_SELF'] .'?deleteFileID='.getID($data).'>■</a></small></td>';
	if($cookie['showPreviewImage']) echo    '<td class="previewContainer"><a href="'. $path .'"><img class="imagePreview" src="'.$thumbPath.'"><br>'.$fileName.'</a> </td>'; else echo '<td> <a href="'. $path .'">'.$fileName.'</td>';
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
    <h5 align="right"> <a href="'.$_SERVER['PHP_SELF'].'?goingto=ownersettings">Board Owner Settings</a> <a href="css.php">Upload CSS</a><br>
        <a href="https://github.com/Heyuri/Uploader/">Heyuri</a> + <a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
    </h5>
    </body>
    </html>';
}
function drawErrorPageAndExit_headless($mes1,$mes2=""){
    global $conf;
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center> 
    [<a href="'.$conf['mainScript'].'">Back</a>]';
    drawFooter();
    exit;
}

function drawErrorPageAndExit($mes1,$mes2=""){
    global $conf;
    drawHeader();
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center> 
    [<a href="'.$conf['mainScript'].'">Back</a>]';
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

function drawBoardDeletionForm() {
   global $conf;
   if(!empty($_POST)) {
	   if($_POST['deletionPassword'] == $conf['deletionPassword'] && $_POST['deletionPasswordconfirm'] == $conf['deletionPassword'] || $_POST['deletionPassword'] == SUPERADMINPASS) {
		   //return to index
		    echo '
    			<hr>
    			<center>
        			<strong>BOARD DELETED</strong><br>
    			</center> 
    			[<a href="../../index.php">Return</a>]';
		   deleteBoard();
		   die;
	   }
   	   else {
		drawErrorPageAndExit_headless('Password Incorrect');
	   }
   }
   else {
   echo '
    	<center>
	    <h2>Delete Board</h2> 
	    <h5>delete user board</h5>
    
	    <form action="'.$_SERVER['PHP_SELF'].'?goingto=delete" method="post">
	    	<input type="hidden" name="goingto" value="delete">
		<table border="1"><tbody>
		 <tr>
        	    <td><label for="deletionPassword">Board deletion Password:</label></td>
        	    <td><input type="password" id="deletionPassword" name="deletionPassword" required maxlength="16"></td>
	        </tr>
		 <tr>
	            <td><label for="deletionPassword">Board deletion Password CONFIRMATION:</label></td>
	            <td><input type="password" id="deletionPasswordconfirm" name="deletionPasswordconfirm" required maxlength="16"></td>
	        </tr>
		</tbody>
		</table>
		<input type="submit" value="Delete Board">
    	</form>
	    </center>
	';
   }
}


function drawOwnerForm(){
	global $conf;

	$passIsChecked = '';
	if($conf['passwordRequired'])
		$passIsChecked = 'checked';
	$commIsChecked = '';
	if($conf['commentRequired'])
		$commIsChecked = 'checked';
    echo '
    <center>
    <h2>Edit Board</h2>
	<h5>leave passwords blank for no change to them</h5> 
    <form action="'.$_SERVER['PHP_SELF'].'?goingto=edit" method="post">
	<input type="hidden" name="goingto" value="edit">
	<table border="1"><tbody>
        <tr>
            <td><label for="name">Board name</label></td>
            <td><input type="text" id="name" name="name" maxlength="32" value="'.$conf['boardTitle'].'"></td>
        </tr>
        <tr>
            <td><label for="subName">Board descripton:</label></td>
            <td><textarea tabindex="6" maxlength="256" cols="48" rows="4" name="subName">'.$conf['boardSubTitle'].'</textarea></td>
        </tr>
        <tr>
            <td><label for="adminPassword">Board admin password:</label></td>
            <td><input type="password" id="adminPassword" name="adminPassword" maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="deletionPassword">Board deletion Password:</label></td>
            <td><input type="password" id="deletionPassword" name="deletionPassword" maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="defaultComment">Default Comment:</label></td>
            <td><input type="text" id="defaultComment" name="defaultComment" maxlength="128" value="'.$conf['defaultComment'].'"></td>
        </tr>
	<tr>
            <td><label for="passRequired">Password required for upload:</label></td>
            <td><input type="checkbox" id="passRequired" name="passRequired" '.$passIsChecked.'> </td>
	</tr>
	<tr>
            <td><label for="commentRequired">Required a comment to post:</label></td>
            <td><input type="checkbox" id="commentRequired" name="commentRequired" '.$commIsChecked.'> </td>
        </tr>

	<tr>
	     <td><label for="passCurrent">Current Password: *</label></td>
	     <td><input type="password" id="passCurrent" name="passCurrent" required></td>
	</tr>
       </tbody>
      </table>
	<input type="submit" value="Edit Board">
   </form>
	<br><br><br> <a href="'.$_SERVER['PHP_SELF'].'?goingto=delete"><h4>DELETE BOARD</h4></a>
 </center>';
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

function removeDir(string $dir): void {
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,
                 RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()){
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($dir);
}


function handleBoardEdit() {
    global $conf;
    $oldConf = $conf; //an alias for conf
	
    if(!(isset($_POST['passCurrent'])) || $_POST['passCurrent'] != $oldConf['adminPassword'] && $_POST['passCurrent'] != SUPERADMINPASS)
	drawErrorPageAndExit("Validation Error", "Password incorrect!");

    // Sanitize and check lengths of other fields
    $name = $_POST['name'] != '' ? strip_tags($_POST['name']) : $name = $oldConf['boardTitle'];
    if (strlen($name) > 32) {
        $errorMessage = "Name must be no longer than 32 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }
    
    // Sanitize and check lengths of other fields
    $subName = $_POST['subName'] != '' ? strip_tags($_POST['subName']) : $subName = $oldConf['boardSubTitle'];
    if (strlen($subName) > 256) {
        $errorMessage = "Sub Name must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $adminPassword = $_POST['adminPassword'] != '' ? strip_tags($_POST['adminPassword']) : $adminPassword = $oldConf['adminPassword'];
    if (strlen($adminPassword) > 16) {
        $errorMessage = "mod Password must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $deletionPassword = $_POST['deletionPassword'] != '' ? strip_tags($_POST['deletionPassword']) : $deletionPassowrd = $oldConf['deletionPassword'];
    if (strlen($deletionPassword) > 16) {
        $errorMessage = "Deletion Password must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $defaultComment = $_POST['defaultComment'] != '' ? strip_tags($_POST['defaultComment']) : $defaultComment = $oldConf['defaultComment'];
    if (strlen($defaultComment) > 128) {
        $errorMessage = "Default Comment must be no longer than 128 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $commentRequired = isset($_POST['commentRequired']) ? filter_var($_POST['commentRequired'], FILTER_VALIDATE_BOOLEAN) : $commentRequired = false;
    $passwordRequired = isset($_POST['passRequired']) ? filter_var($_POST['passRequired'], FILTER_VALIDATE_BOOLEAN) : $passwordRequired = false;

    $newConf = $oldConf;

    $newConf['boardTitle'] = $name;
    $newConf['boardSubTitle'] = $subName;
    $newConf['adminPassword'] = $adminPassword;
    $newConf['deletionPassword'] = $deletionPassword;
    $newConf['passwordRequired'] = $passwordRequired;
    
    if($commentRequired){
        $newConf['commentRequired'] = $commentRequired;
        $newConf['defaultComment'] = "";
    }else{
        $newConf['commentRequired'] = $commentRequired;
        $newConf['defaultComment'] = $defaultComment;
    }



    $newConf_export = '<?php return ' . var_export($newConf, true) . ';';

    if (file_put_contents('config.php', $newConf_export) === false) {
        drawErrorPageAndExit("Failed to write configuration. contact the admin");
    }

    drawMessageAndRedirectHome('Board has been edited','If this page dose not change, click "Back".');
}

//generate thumbnail
function thumbnailImage($imagePath, $thumbPath, $w, $h) {
  // Quality and dimensions settings
        $maxWidth = 200;
        $maxHeight = 95;
       	
        $image = imagecreatefromstring(file_get_contents($imagePath));
    
        $width = imagesx($image);
        $height = imagesy($image);
    
        $newWidth = $w;
        $newHeight = $h;
    

        if ($width > $maxWidth || $height > $maxHeight) {
		$aspectRatio = $width / $height;
    
                if ($width > $height) {
                    $newWidth = $maxWidth;
                    $newHeight = $maxWidth / $aspectRatio;
                } else {
                    $newHeight = $maxHeight;
                    $newWidth = $maxHeight * $aspectRatio;
                }
            }
    
         // Create a new image
         $thumbnail = imagecreatetruecolor((int)$newWidth, (int)$newHeight);
         // Resize the image to the new dimensions
         imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
    
         // Save the thumbnail to a temporary file
         $thumbnailPath = tempnam(sys_get_temp_dir(), 'thumbnail');
         imagejpeg($thumbnail, $thumbPath, 80);
    
         // Free up memory
         imagedestroy($image);
         imagedestroy($thumbnail);
    
}
function thumbnailVideo($videoPath, $thumbPath) {
	$thumbnailPath = tempnam(sys_get_temp_dir(), 'thumbnail') . ".jpg";

        // Ensure the environment variable is included in the command
        $ffmpegCommand = "LD_LIBRARY_PATH=/usr/local/lib:/usr/X11R6/lib ffmpeg -i {$videoPath} -vframes 1  {$thumbPath} 2>&1";
        exec($ffmpegCommand);
        return $thumbnailPath;
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
	global $conf;
	if(sizeof(explode("<>", $_COOKIE['settings'])) != sizeof(array('showDeleteButton', 'showComment', 'showPreviewImage', 'showFileSize', 'showMimeType')))
		$_COOKIE['settings'] = implode("<>", $conf['defaultCookieValues']); 
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
function deleteBoard(){
    global $conf;
    removeDir(realpath(ROOTPATH) . "/boards/". $conf['boardURL']);
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
    //check if password is required
    if($_POST['password'] != $conf['adminPassword'] && $_POST['password'] != SUPERADMINPASS && $conf['passwordRequired']) {
	    drawErrorPageAndExit('Incorrect password');
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
	
	if($conf['logUserIP'] == false){
    	$_SERVER['REMOTE_ADDR'] = "1337";
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
    	thumbnailVideo($videoPath, $conf['thumbDir'].$conf['prefix'].$newID.'_thumb.png', 200, 95); 
    }
    drawMessageAndRedirectHome('The process is over. The screen will change automatically.','If this does not change, click "Back".');
}
function userDeletePost(){
    global $conf;
    $fileID = $_POST['deleteFileID'];
    $password = $_POST['password'];

    $postData = getDataByID($fileID);
    
    if(empty($password)) drawErrorPageAndExit('Deletion Error', "The password you entered was blank.");
    
    if(is_null($postData)){
        drawErrorPageAndExit('Deletion Error','The file cannot be found.');
    } elseif(getPassword($postData) == ''){
        drawErrorPageAndExit('Deletion Error','There was not a password when this post was created. Contact the administrator to request deletion');
    } elseif($password === getPassword($postData) || $password === $conf['adminPassword']){
		deleteDataFromLogByID($fileID);
		$thumbPath = $conf['thumbDir'] . $conf['prefix'] . getID($postData) . '_thumb.' . getFileExtension($postData);
		unlink($thumbPath);

        drawMessageAndRedirectHome('file has been deleted.','If this page does not change, click "Back".');
    } else{
        drawErrorPageAndExit('Deletion Error','The password is incorrect.');
    }
}

/*
 *  Start of the main logic
 */

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
