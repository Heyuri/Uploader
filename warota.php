<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


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

$page_title   = 'Everything';          // Board title.
$title_sub    = 'Home for your files'; // Board description.
$logfile      = 'souko.log';           // Log file (You may want to change this or block direct access from internet)
$logmax       = 5000;                  // Maximum amount of files that can be uploaded
$limitk       = 20*1024;               // max size in KB (normal size is 2Mb)
$max_all_flag = 1;                     // 総容量規制を使用する=1(未実装)
$max_all_size = 200*1024*1024*1024;    // Total board capacity (in bytes). 200*1024*1024*1024B = 200GB.
$updir        = './src/';              // File storage directory
$prefix       = '';                    // Filename prefix (eg. set to "up" for filenames to be up001.txt, up002.jpg)
$commax       = 250;                   // Maximum comment lenght (In bytes. It's half this value for fullwidth characters)
$page_def     = 20;                    // Number of files to display per page.
$admin        = 'adminpassword';       // Admin deletion password. You can delete any file using this as the PW. MAKE SURE TO CHANGE.
$auto_link    = 0;                     // コメントの自動リンク（Yes=1;No=0);
$last_time    = 0;                     // 同一IPからの連続投稿許可する間隔(分)(0で無制限)
$last_file    = 'last.log';            // 連続投稿制限用ファイル(空ファイルで666)
$count_look   = 0;                     // カウンタ表示(Yes=1,No=0)
$count_file   = 'count.log';           // カウンタファイル(空ファイルで666)
$count_start  = '2009/09/01';          // カウンタ開始日
$sam_look     = 0;                     // 画像一覧表示(Yes=1,No=0)←img.php必須
$denylist     = array('192.168.0.1','sex.com','annony'); //アクセス拒否ホスト

//Allow extensions (these must be in lowercase or it will give an error)
$arrowext     = array('dat','htm','torrent','deb','lzh','ogm','doc','class','js','swift','cc','tga','ape','woff2','cab','whl','mpe','rmvb','srt','pdf','xz','exe','m4a','crx','vob','tif','gz','roq','m4v','gif','rb','3g2','m4a','rvb','sid','ai','wma','pea','bmp','py','mp4','m4p','ods','jpeg','command','azw4','otf','ebook','rtf','ttf','mobi','ra','flv','ogv','mpg','xls','jpg','mkv','nsv','mp3','kmz','java','lua','m2v','deb','rst','csv','pls','pak','egg','tlz','c','cbz','xcodeproj','iso','xm','azw','webm','3ds','azw6','azw3','cue','kml','woff','zipx','3gp','po','mpa','mng','wps','wpd','a','s7z','ics','tex','go','ps','org','yml','msg','xml','cpio','epub','docx','lha','flac','odp','wmv','vcxproj','mar','eot','less','asf','apk','css','mp2','odt','patch','wav','msi','rs','gsm','ogg','cbr','azw1','m','dds','h','dmg','mid','psd','dwg','aac','s3m','cs','cpp','au','aiff','diff','avi','bat','html','pages','bin','txt','rpm','m3u','max','vcf','svg','ppt','clj','png','svi','tiff','tgz','mxf','7z','drc','yuv','mov','tbz2','bz2','gpx','shar','xcf','dxf','jar','qt','tar','xpi','zip','thm','cxx','3dm','rar','md','scss','mpv','webp','war','pl','xlsx','mpeg','aaf','avchd','mod','rm','it','wasm','el','eps','nes','smc','sfc','md','smd','gen','gg','z64','v64','n64','gb','gbc','gba','srl','gcm','gcz','nds','dsi','wbfs','wad','cia','3ds','ngp','ngc','pce','vb','ws','wsc','dsv','sav','ps2','mcr','mpk','eep','st0','dta','srm','afa','zpaq','arc','paq','lpaq','swf','pdn','lol','php','sh','img','ico','asc', 'm2ts', 'nzb', 'appimage', 'json');

// ▼Yakuba(設定追加)
$b_changeext  = array('htm','mht','cgi','php','html','sh','shtml','xml','svg');
$a_changeext  = 'txt';       // 強制変換後の拡張子
$homepage_add = '../../';    // [Home]のリンク先(相対、絶対両方可能)
// ▲Yakuba

/* defualt enviorment settings. changes the look of the site */
$D_showDeleteButton  = 'checked';
$D_showComment  = 'checked';
$D_showSize = 'checked';
$D_showMimeType = '';
//$D_showDateUploaded = ''; // dose not work
//$D_openInNewWinow = ''; // dose not work
//$D_showOriginalFileName = '';// dose not work


date_default_timezone_set($conf['timeZone']);

/* draw functions */
function drawHeader(){
    global $page_title;
    global $title_sub;

    echo '
    <html>
    <head>
    <META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
    <meta name="Berry" content="no">
    <meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
    <meta http-equiv="pragma" content="no-cache">
    <title>'.$page_title.'</title>
    </head>
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
        <table width="100%">
            <tr><td bgcolor="#eeaa88">
                <strong><font size="4">'.$page_title.'</font></strong>
            </td></tr>
        </table>
        <tt><br>
        <br>
        '.$title_sub.'<br>
        <br>
        <br>
        </tt>';
}
function drawPageingBar($page, $total){
    global $PHP_SELF,$page_def,$homepage_add;

    for ($j = 1; $j * $page_def < $total+$page_def; $j++) {
        if($page == $j){
            $next .= '[ <b>'.$j.'</b> ]';
        }else{
            $next .= sprintf('[<a href="%s?page=%d">%d</a>]', $PHP_SELF,$j,$j);
        }
    }

    // ▼Yakuba(画像一覧のリンク表示を選択)
    global $sam_look;
    global $base_php;
    if($page=="all" and $sam_look) 
        return sprintf ('[<a href="'.$homepage_add.'">Home</a>] [<a href="images.php">Image List</a>] [<b>ALL</b>] %s',$next,$PHP_SELF);
    elseif($page=="all" and !$sam_look) 
        return sprintf ('[<a href="'.$homepage_add.'">Home</a>] [<b>ALL</b>] %s',$next,$_SERVER['PHP_SELF']);
    elseif($page!="all" and $sam_look) 
        return sprintf ('[<a href="'.$homepage_add.'">Home</a>] [<a href="images.php">Image List</a>] [<a href="'.$_SERVER['PHP_SELF'].'?page=all">ALL</a>] %s',$next,$PHP_SELF);
    else 
        return sprintf ('[<a href="'.$homepage_add.'">Home</a>] [<a href="'.$base_php.'?page=all">ALL</a>] %s',$next,$PHP_SELF);
}
function drawFileListingN($n){

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
    center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center>
    [<a href="'.$_SERVER['PHP_SELF'].'">Back</a>]
    <script type="text/javascript">setTimeout("location.href="'.$_SERVER['PHP_SELF'].'",0)</script>';
    drawFooter();
    exit;
}
function drawUploadForm(){
    // Post form header (Yakuba modification)
    // Check if the overall filesize limit for the board has been exceeded
    global $conf;
    if(getTotalUseageInBytes() >= $conf['maxTotalSize']){
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
            <input type=file  name="40" name="upfile"> 

            DELETION KEY: <input type=password size="10" name="password" maxlength="10"><br>
            COMMENT<i><small>(※If no comment is entered, the page will be reloaded / URL will be auto-linked.)</small></i><br>
            <input type="text" size="45" value="ｷﾀ━━━(ﾟ∀ﾟ)━━━!!" name="com">
            <input type=submit value="Up/Reload">
            <input type=reset value="Cancel"><br>
            <small>Allowed extensions:'. $conf['allowedExtensions'] .'</small>
        </form>
        ';
    }
}
function drawDeletionForm($fielID){
    echo'
    <form action='.$_SERVER['PHP_SELF'].' method="post">
        <input type=hidden name=deletePostID value="'.$fielID.'">
        Enter your password: <input type=password size=12 name=deletePassword>
        <input type=submit value="Delete">
    </form>"';
}
function drawSettingsForm(){
    echo '
    <hr>
    <strong>client Settings</strong><br>
    <form method=POST action="'.$_SERVER['PHP_SELF'].'">
    <input type=hidden name=action value="setUserSettings">
    <ul>
        <li><strong>display</strong>
        <ul>
            <input type=checkbox name=showDeleteButtons value=checked '.$_COOKIE['showDeleteButtons'].'>ACT<br>
            <input type=checkbox name=showComments  value=checked '.$_COOKIE['showComments'].'>COMMENT<br>
            <input type=checkbox name=showFileSizes value=checked '.$_COOKIE['showFileSizes'].'>SIZE<br>
            <input type=checkbox name=showMimeTypes value=checked '.$_COOKIE['showMimeTypes'].'>MIME<br>
        </ul>
    <ul><br>
    <br><br>

    <input type=submit value=\"登録\">
    <input type=reset value=\"元に戻す\">
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
    return getID($array);
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
function getFileExtention($postData){
    return $postData[1];
}
function getComent($postData){
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

/* helper libs */
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
function getTotalUseageInBytes(){
    // Total file size calculation
    global $conf;
    $logFile = $conf['logFile'];
    $totalSize=0;
    $openFile = fopen($logFile,"r");

    //while not at the end of file
    //id<>fileExtention<>comment<>host<>dateUploaded<>sizeInBytes<>mimeType<>Password<>orginalFileName
    while(!feof($openFile)){ 
        $line = fgets($openFile);
        $array = explode("<>",$line);
        $size = getSizeInBytes($array);
        $totalSize = $totalSize + $size;
    } 
    fclose($openFile);
}
function getTotalLogLines(){
    global $conf;
    $lineCount = 0; 
    $fileHandle = fopen($conf['logFile'], 'r'); 

    while (!feof($fileHandle)) {
        fgets($fileHandle);
        $lineCount++; 
    }
    fclose($fileHandle); 

    return $lineCount;
}
function delteFileByData($data){
    global $conf;
    $path = $conf['uploadDir'] ."/". $conf['prefix'] . getID($data) . getFileExtention($data);
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
function IsBaned($host){
    global $denylist;
    foreach($denylist as $line) {
		if(strstr($host, $line)){
            return true;
        }
    }
}
function isRateLimited(){
    //hachi this one is for you to fill in
    return false;
}
function deleteDataFromLogByID($id){
    global $conf;
    $logFile = $conf['logFile'];
    $openLogFile = fopen($logFile, "r");
    $dataIsFoundInFile = false;
    $newFileContent = [];

    // while not at the end of the file.
    while (!feof($openLogFile)) {
        $line = fgets($openLogFile);
        $data = explode("<>", $line);

        if ($data[0] == $id) {
            $dataIsFoundInFile = true;
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
    
    delteFileByData($data);

    return true;
}
function loadCookieSettings(){
    global $conf;
    $defualt = $conf['defualtCookieValues'];

    if(isset($_COOKIE['settings']) == false){
        $cookie = implode("<>", $conf['defualtCookieValues']);
    }

    if($_POST['action']=="setUserSettings"){
        // the order of this array must be the same order as $conf['defualtCookieValues']
        $cookie = implode("<>", array(   $_POST['showDeleteButton']
                                        ,$_POST['showComment']
                                        ,$_POST['showSize']
                                        ,$_POST['showMimeType']));
        setcookie ("settings", $cookie,time()+365*24*3600);
    }
    
    $settings = array_combine($defualt, explode("<>",$cookie));
    return $settings;
}

/* main funcitons */

function userUploadedFile(){
    global $conf;
    if($_POST['comment'] == "" && $conf['commentRequired']){
        drawErrorPageAndExit('comment is required.');
    }
    if(strlen($_POST['comment']) > $conf['maxCommentSize']){
        drawErrorPageAndExit('Comment is too big.');
    }
    if($_FILES["upfile"]['size'] > $conf['maxFileSize']){
        drawErrorPageAndExit('File is too big.');
    }

    if(isRateLimited()){
        drawErrorPageAndExit('you are posting to fast. try again later');
    }

    $fullFileName = $_FILES["upfile"]["name"];
    $fileInfo = pathinfo($fullFileName);

    $fileName = $fileInfo['filename'];
    $fileExtension = strtolower($fileInfo['extension']);

    if(!in_array($fileExtension, $conf['allowedExtentions'])){
        drawErrorPageAndExit("invlaid extension","file can not be uploaded with that extension");
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
    $newID = sprintf("%03d", getLastID() + 1);
    $newname = $conf['prefix'] . $newID . "." . $fileExtension;

    rename($_FILES['upfile']['tmp_name'], $conf['uploadDir'].$newname);
    chmod($conf['uploadDir'] . $newname, 0644);

    //remove line breaks from the comment
    $comment = str_replace(array("\0","\t","\r","\n","\r\n"), "", $_POST['comment']);
    
    // check if the extention has been converted to somthing safe
    if($originalExtension != $fileExtension){
        //show the converstion
        $comment = $comment . '<font color="#ff0000">('. $fileExtension .'←'. $originalExtension .')</font>';
    }
    
    // get password
    if(isset($_POST['password'])){
        $password = $_POST['password'];
    }else{
        $password = "*";
    }

    $data = createData( $newID, $fileExtension, $comment, $_SERVER['REMOTE_HOST'],
                        time(), $_FILES['upfile']['size'], $realMimeType, $password,
                        $fileName);

    // if over max. delete last file
    if(getTotalLogLines() >= $conf['maxAmountOfFiles']){
        removeLastData();
    }
    writeDataToLogs($data);
    drawMessageAndRedirectHome('The process is over. The screen will change automatically.','If this does not change, click "Back".');
}
function userDeletePost(){
    global $conf;
    $fileID = $_POST['deleteFileID'];
    $password = $_POST['deletionPassword'];

    $postData = getDataByID($fileID);
    if(is_null($postData)){
        drawErrorPageAndExit('Deletion Error','The file cannot be found.');
    }
    if($password == $conf['adminPassword'] || $password == getPassword($postData)){
        deleteDataFromLogByID($fileID);
        drawMessageAndRedirectHome('file has been deleted.','If this page dose not change, click "Back".');
    }else{
        drawErrorPageAndExit('Deletion Error','The password is incorrect.');
    }
}

/*
 *  Start of the main logic
 */

if(IsBaned($_SERVER['REMOTE_ADDR'])){
    drawErrorPageAndExit('you are banned');
}

$userSettings = loadCookieSettings();

/* deletion form was posted to */
if(is_numeric($_POST['deleteFileID']) && isset($_POST['deletionPassword'])){
    userDeletePost();
}

/* draw a form when user is atempting to delete a file */
if(is_numeric($_GET['deleteFileID'])){
    drawHeader();
    drawDeletionForm(htmlspecialchars($_GET['deleteFileID']));
    drawFooter();
    die();
}

// file is uploading
if(file_exists($_FILES['upfile'])){
    userUploadedFile();
}

/* Log start position */
$st = ($page) ? ($page - 1) * $page_def : 0;
if(!$page) $page = 1;
if($page == "all"){
  $st = 0;
  $page_def = count($lines);
}
echo paging($page, count($lines));


// Main header (please adjust the width if you change the display items)
echo '<HR><table width="100%" style="font-size:10pt;"><tr>';
if($c_act) echo '<td width="4%"><tt><b>DEL</b></tt></td>';
echo '<td width="8%"><tt><b>NAME</b></tt></td>';
if($c_com)  echo '<td width="58%"><tt><b>COMMENT</b></tt></td>';
if($c_size) echo '<td width="7%"><tt><b>SIZE</b></tt></td>';
if($c_mime) echo '<td><tt><b>MIME</b></tt></td>';
echo '</tr>';

//Main display
for($i = $st; $i < $st+$page_def; $i++){
  if($lines[$i]=="") continue;
  list($id,$ext,$com,$host,$now,$size,$mtype,$pas,)=explode("<>",$lines[$i]);
  $fsize = FormatByte($size);
  if($auto_link) $com = ereg_replace("(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)","<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",$com);

  $filename = $prefix.$id.".$ext";
  $target = $updir.$filename;

  echo "<tr><!--$host-->";
  if($c_act) echo "<td><small><a href='$PHP_SELF?del=$id'>■</a></small></td>";
  echo "<td><a href='$target'>$filename</a></td>";
  if($c_com) echo "<td><font size=2>$com</font></td>";
  if($c_size) echo "<td><font size=2>$fsize</font></td>";
  if($c_mime) echo "<td><font size=2 color=888888>$mtype</font></td>";
  echo "</tr>\n";
  }


echo "</table><HR>";
echo 'Used '.$size_all_hyouzi.'/ '.FormatByte($max_all_size).'<br>';
echo 'Used '.count($lines).' Files/ '.$logmax.'Files<br>';
// echo paging($page,count($lines));

drawHeader();
drawUploadForm();
drawActionLinks();
drawFileListing();
drawFooter();