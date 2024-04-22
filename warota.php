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
function drawPageBar($page, $total){
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
function drawErrorAndKeepRunning($mes1,$mes2=""){ 
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
    if($size_all_hikaku >= $max_all_size / (1024*1024)){
        echo 'The total capacity has exceeded the limit and is currently under posting restriction.<br>Please notify the administrator.<br><br>';
    }
    else{
        echo '
        <FORM METHOD="POST" ENCTYPE="multipart/form-data" ACTION="'.$PHP_SELF.'">
        FILE Max '.$limitk.'KB (Max '.$logmax.'Files)<br>
        <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="'.$limitb.'">
        <INPUT TYPE=file  SIZE="40" NAME="upfile"> 
        DELKey: <INPUT TYPE=password SIZE="10" NAME="pass" maxlength="10"><br>
        COMMENT<i><small>(※If no comment is entered, the page will be reloaded / URL will be auto-linked.)</small></i><br>
        <input type="text" size="45" value="ｷﾀ━━━(ﾟ∀ﾟ)━━━!!" name="com">
        <INPUT TYPE=submit VALUE="Up/Reload"><INPUT TYPE=reset VALUE="Cancel"><br>
        <small>Allowed extensions:'.$arrow.'</small>
        </FORM>
        ';
    }
}
function drawDeletionForm($fielID){
    echo'
    <form action='.$_SERVER['PHP_SELF'].' method="POST">
    <input type=hidden name=deletePostID value="'.$fielID.'">
    Enter your password: <input type=password size=12 name=deletePassword>
    <input type=submit value="Delete"></form>"';
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
    return $postData[5];
}
function getPassword($postData){
    return $postData[6];
}
function getOriginalFileName($postData){
    return $postData[7];
}
/* helper libs */
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
function deleteDataFromLogByID($id){
    global $conf;
    $logFile = $conf['logFile'];
    $openLogFile = fopen($logFile, "r");
    $dataIsFoundInFile = false;
    $newFileContent = [];

    // while not at the end of the file.
    while (!feof($openLogFile)) {
        $line = fgets($openLogFile);
        $array = explode("<>", $line);

        if ($array[0] == $id) {
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

/*
 *  Start of the main logic
 */

if(IsBaned($_SERVER['REMOTE_ADDR'])){
    drawErrorPageAndExit('you are banned');
}

$userSettings = loadCookieSettings();

/* deletion form was posted to */
if(is_numeric($_POST['deleteFileID']) && isset($_POST['deletionPassword'])){
    $fileID = $_POST['deleteFileID'];
    $password = $_POST['deletionPassword'];

    $postData = getDataByID($fileID);
    if(is_null($postData)){
        error('Deletion Error','The file cannot be found.');
    }
    if($password == $conf['adminPassword'] || $password == getPassword($postData)){
        $filePath = $conf['uploadDir'] ."/". $conf['prefix'] . $fileID . getFileExtention($postData);
        if(file_exists($filePath)){
            unlink($filePath);
        }
        deleteDataFromLogByID($fileID);
        runend('file has been deleted.','If this page dose not change, click "Back".');
    }else{
        error('Deletion Error','The password is incorrect.');
    }
}
/* draw form when user is atempting to delete a file */
elseif(is_numeric($_GET['deleteFileID'])){
    drawDeletionForm(htmlspecialchars($_GET['deleteFileID']));
    die();
}

// Upload writing process 
if(file_exists($upfile) && $com && $upfile_size > 0){
    if(strlen($com) > $conf['maxCommentSize']){
        error('Comment too big.');
    }
    if($upfile_size > $conf['maxFileSize']){
        error('File too big.');
    }

    /* 連続投稿制限 */
    if($last_time > 0){
        $now = time();
        $last = @fopen($last_file, "r+") or die("連続投稿用ファイル $last_file を作成してください");
        $lsize = fgets($last, 1024);
        list($ltime, $lip) = explode("<>", $lsize);
        if($_SERVER['REMOTE_ADDR'] == $lip && $last_time*60 > ($now-$ltime)){
            error('連続投稿制限中','時間を置いてやり直してください');
        }
        rewind($last);
        fputs($last, "$now,$host,");
        fclose($last);
    }

    /* 拡張子と新ファイル名 */
    $pos = strrpos($upfile_name,".");                             //拡張子取得
    $ext = substr($upfile_name,$pos+1,strlen($upfile_name)-$pos);
    $ext = strtolower($ext);                                      //小文字化
    if(!in_array($ext, $arrowext)){
        error("拡張子エラー","その拡張子ファイルはアップロードできません");
    }
    // ▼Yakuba追加
    if(in_array($ext,$b_changeext)){
        $org_ext = $ext;
        $new_ext = $a_changeext;
        $ext = $a_changeext;
    }

    /* 拒否拡張子はtxtに変換
    for($i=0; $i<count($denyext); $i++){
        if(strstr($ext,$denyext[$i])) $ext = 'txt';
    }
    */

    list($id,) = explode("<>", $lines[0]);                        //No取得
    $id = sprintf("%03d", ++$id);                                 //インクリ
    $newname = $prefix.$id.".".$ext;

    /* 自鯖転送 */
    move_uploaded_file($upfile, $updir.$newname);//3.0.16より後のバージョンのPHP 3または 4.0.2 後
    //copy($upfile, $updir.$newname);
    chmod($updir.$newname, 0644);

    /* MIMEタイプ */
    if(!$upfile_type) $upfile_type = "text/plain";//デフォMIMEはtext/plain

    $com = str_replace(array("\0","\t","\r","\n","\r\n"), "", $com);//改行除去
    // ▼Yakuba追加(もし拡張子を変えたならその旨タグ変換を表示)
    if($new_ext){
        $com = "$com <font color=\"#ff0000\">($new_ext←$org_ext)</font>";
    }
    $now = gmdate("Y/m/d(D)H:i", time()+9*60*60);	//日付のフォーマット
    $pwd = ($pass) ? substr(md5($pass), 2, 7) : "*";	//パスっ作成（無いなら*）

    $dat = implode("<>", array($id,$ext,$com,$host,$now,$upfile_size,$upfile_type,$pwd,$upfile_name,));

    if(count($lines) >= $logmax){		//ログオーバーならデータ削除
        for($d = count($lines)-1; $d >= $logmax-1; $d--){
        list($did,$dext,)=explode("<>", $lines[$d]);
        if(file_exists($updir.$prefix.$did.".".$dext)) {
            unlink($updir.$prefix.$did.".".$dext);
        }
        }
    }

    $fp = fopen ($logfile , "w");		//書き込みモードでオープン
    flock($fp ,LOCK_EX);
    fputs ($fp, "$dat\n");		//先頭に書き込む
    foreach ($lines as $line)
        fputs($fp, $line);
    fclose ($fp);
    reset($lines);
    $lines = file($logfile);		//入れなおし
    runend('The process is over. The screen will change automatically.','If this does not change, click "Back".');

}

foreach($arrowext as $list){
    $arrow .= $list." ";
}




// For total capacity comparison(MB)
$size_all_hikaku = $size_all/(1024*1024);

// Change total file capacity unit\
if($size_all == 0)                      $size_all_hyouzi = $size_all."B";
else if($size_all <= 1024)              $size_all_hyouzi = $size_all."B";
else if($size_all <= (1024*1024))       $size_all_hyouzi = sprintf ("%dKB",($size_all/1024));
else if($size_all <= (1000*1024*1024))    $size_all_hyouzi = sprintf ("%.2fMB",($size_all/(1024*1024)));
else if($size_all <= (1000*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fGB",($size_all/(1024*1024*1024)));
else if($size_all <= (1000*1024*1024*1024*1024) || $size_all >= (1000*1024*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fTB",($size_all/(1024*1024*1024*1024)));
else                                    $size_all_hyouzi = $size_all."B";





// Counter display selection
if($count_look){
  echo "<small>$count_start から ";
  if(file_exists($count_file)){
    $fp = fopen($count_file,"r+");  //読み書きモードでオープン
    $count = fgets($fp, 64);        //64バイトorEOFまで取得、カウントアップ
    $count++;
    fseek($fp, 0);                  //ポインタを先頭に、ロックして書き込み
    flock($fp, LOCK_EX);
    fputs($fp, $count);
    fclose($fp);                    //ファイルを閉じる
    echo $count.'人 </small>';      //カウンタ表示
  }
}



/* Log start position */
$st = ($page) ? ($page - 1) * $page_def : 0;
if(!$page) $page = 1;
if($page == "all"){
  $st = 0;
  $page_def = count($lines);
}
echo paging($page, count($lines));//ページリンク


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