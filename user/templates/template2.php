$iprec = 0;
$logfile      = 'souko.log';
$logmax       = 500;
$limitk       = 500*1024;
$max_all_flag = 1;
$max_all_size = 250000; 
$updir        = './src/'; 
$commax       = 250;
$page_def     = 20; 
$auto_link    = 0; 
$last_time    = 0;
$last_file    = 'last.log';
$count_look   = 0;
$count_file   = 'count.log';
$count_start  = '2009/09/01';
$sam_look     = 0; 
$denylist     = array('192.168.0.1','sex.com','annony');
$arrowext     = array('bmp','cgi','gif','jpg','png','txt','mht','htm','html');
$b_changeext  = array('mht','cgi','php','html','sh','htm','shtml','svg','xml');
$a_changeext  = 'txt'; 
$homepage_add = '/../user'; 
$f_act  = 'checked';
$f_anot = '';
// ファイル、DIRの有無チェック----------------------------------------------
// ▼Yakuba(ファイル、DIRがなければ注意)
if ( !file_exists($logfile) ) {
  echo ($logfile.' There is no count file, please create it.(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($count_file)) {
  echo ($count_file.' There is no count file, please create it.(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($last_file)) {
  echo ($last_file.' There is no last_file log file, please create it.(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($updir)) {
  echo ($updir.' There is no upload directory, please create it.(0777or0701)<br><br>');
  $out = '1';
}

if ($out){
  echo ('Aborting the process.');
  exit;
}
// ▲Yakuba


if($act=="envset"){
  $cookval = implode("<>", array($acte,$come,$sizee,$mimee,$datee,$anote,$orige));
  setcookie ("upcook", $cookval,time()+365*24*3600);
}
function _clean($str) {
  $str = htmlspecialchars($str);
  return $str;
}


// ヘッダ-------------------------------------------------------------------
$header = '
<html>
<head>
<link rel="stylesheet" href="csrc/001.css">
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<meta name="Berry" content="no">
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
<meta http-equiv="pragma" content="no-cache">
<title>'.$page_title.'</title>
<style>
  <!--
  a:link    {color:#0000ee;}
  a:hover   {color:#5555ee;}
  a:visited {color:#0000ee;}
  tr:nth-child(odd) {background-color: #f7efea;}
  tr:hover {background-color: #f0e0d6;}
  table {border-collapse: collapse;}
  -->
</style>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
<table width="100%"><tr><td bgcolor="#eeaa88"><strong><font size="4">'.$page_title.'</font></strong></td></tr></table>
<tt>
<br><br>【'.$page_desc.'】<br><br><br>
</tt>
';


// フッタ-------------------------------------------------------------------
$foot = <<<OSHIRI
<BR><H5 align="right">
<a href="settings.htm">Settings</a> <a href="css.php">Upload CSS</a><br>
<a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
</H5>
</BODY>
</HTML>
OSHIRI;


echo $header;

function FormatByte($size){             //バイトのフォーマット（B→kB）
	// ファイル総容量単位変更----------------------------------------------------
	if($size == 0)                      $format = $size."B";
	else if($size <= 1024)              $format = $size."B";
	else if($size <= (1024*1024))       $format = sprintf ("%dKB",($size/1024));
	else if($size <= (10*1024*1024))    $format = sprintf ("%.2fMB",($size/(1024*1024)));
	else if($size <= (1000*1024*1024*1024))  $format = sprintf ("%.2fGB",($size/(1024*1024*1024)));
	else if($size <= (10*1024*1024*1024*1024))  $format = sprintf ("%.2fTB",($size/(1024*1024*1024*1024)));
	else                                    $format = $size."B";
	return $format;
}

function paging($page, $total){         //ページリンク作成
  global $PHP_SELF,$page_def,$homepage_add;

    for ($j = 1; $j * $page_def < $total+$page_def; $j++) {
      if($page == $j){                  //今表示しているのはﾘﾝｸしない
        $next .= "[ <b>$j</b> ]";
      }else{
        $next .= sprintf("[<a href=\"%s?page=%d\">%d</a>]", $PHP_SELF,$j,$j);//他はﾘﾝｸ
      }
    }

    // ▼Yakuba(画像一覧のリンク表示を選択)
    global $sam_look;
    if($page=="all" and $sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>] [<a href=\"img.php\">Image List</a>]　[<b>ALL</b>] %s",$next,$PHP_SELF);
    else if($page=="all" and !$sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>]　[<b>ALL</b>] %s",$next,$PHP_SELF);
    else if($page!="all" and $sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>] [<a href=\"img.php\">Image List</a>]　[<a href=\"$base_php?page=all\">ALL</a>] %s",$next,$PHP_SELF);
    else return sprintf ("[<a href=\"$homepage_add\">Home</a>]　[<a href=\"$base_php?page=all\">ALL</a>] %s",$next,$PHP_SELF);
}

function error($mes1,$mes2=""){         //えっらーﾒｯｾｰｼﾞ

  echo "<hr><center><strong>$mes1</strong><br><p>$mes2</p></center>";

  // ▼Yakuba
  global $base_php;
  echo '[<a href="'.$base_php.'">Back</a>]';
  // ▲Yakuba

  global $foot;
  echo $foot;
  exit;
}


// ▼Yakuba追加(処理の終わりに画面を読み直し。さもないとそのままF5押すと処理が続行される！)
function runend($mes1,$mes2=""){         //処理終了メッセージ

  echo "<hr><center><strong>$mes1</strong><br><p>$mes2</p></center>";

  // ▼Yakuba
  global $base_php;
  echo '[<a href="'.$base_php.'">Back</a>]';
  // ▲Yakuba

  global $foot,$base_php;

  echo "<script type='text/javascript'>setTimeout(\"location.href='$base_php'\",0)</script>";
  echo $foot;
  exit;
}


/* start */
$limitb = $limitk * 1024;
if ($iprec == 0) {
$host = 1337;
} else {
$host = @gethostbyaddr($REMOTE_ADDR);
}
if(!$upcook) $upcook=implode("<>",array($f_act,$f_com,$f_size,$f_mime,$f_date,$f_anot,$f_orig));
list($c_act,$c_com,$c_size,$c_mime,$c_date,$c_anot,$c_orig)=explode("<>",$upcook);


/* アクセス制限 */
if(is_array($denylist)){
  	foreach($denylist as $line) {
		if(strstr($host, $line)) error('アクセス制限','あなたにはアクセス権限がありません。');
	}
}


/* 削除実行 */
if($delid && $delpass!=""){
  $old = file($logfile);
  $find = false;
  for($i=0; $i<count($old); $i++){
    list($did,$dext,,,,,,$dpwd,)=explode("<>",$old[$i]);
    if($delid==$did){
      $find = true;
      $del_ext = $dext;
      $del_pwd = rtrim($dpwd);
    }else{
      $new[] = $old[$i];
    }
  }
  if(!$find) error('Deletion Error','The file cannot be found.');
  if($delpass == $admin || substr(md5($delpass), 2, 7) == $del_pwd){
    if(file_exists($updir.$prefix.$delid.".$del_ext")) unlink($updir.$prefix.$delid.".$del_ext");
    
    $fp = fopen($logfile, "w");
    flock($fp, LOCK_EX);

    if(!$new) {fputs($fp,$new);}                // Yakuba修正
    else      {fputs($fp, implode("",$new));}

    fclose($fp);
    runend('The process is over. The screen will change automatically.','If this does not change, click "Back".');
  }else{
    error('Deletion Error','The password is incorrect.');
  }
}
/* 削除フォーム */
if($del){
  error("Post Data Deletion","
<form action=$PHP_SELF method=\"POST\">
<input type=hidden name=delid value=\"".htmlspecialchars($del)."\">
Enter your password：<input type=password size=12 name=delpass>
<input type=submit value=\"Delete\"></form>");
}

/* 環境設定フォーム */
if($act=="env"){
  echo "
<hr>
<strong>環境設定</strong><br>
<form method=GET action=\"$PHP_SELF\">
<input type=hidden name=act value=\"envset\">
<ul>
<li><strong>表示設定</strong>
<ul>
<input type=checkbox name=acte value=checked $c_act>ACT<br>
<input type=checkbox name=come value=checked $c_com>COMMENT<br>
<input type=checkbox name=sizee value=checked $c_size>SIZE<br>
<input type=checkbox name=mimee value=checked $c_mime>MIME<br>
<input type=checkbox name=datee value=checked $c_date>DATE<br>
<input type=checkbox name=orige value=checked $c_orig>ORIG<br>
</ul>
<li><strong>動作設定</strong>
<ul>
<input type=checkbox name=anote value=checked $c_anot>ファイルを開く時は別窓で開く<br>
</ul>
<br>
cookieを利用しています。<br>
上記の設定で訪問することができます。<br><br>
<input type=submit value=\"登録\">
<input type=reset value=\"元に戻す\">
</form>
<a href=\"$PHP_SELF\">Back</a>
";
echo $foot;
exit;
}
$lines = file($logfile);


// アプロード書き込み処理---------------------------------------------------
if(file_exists($upfile) && $com && $upfile_size > 0){
  if(strlen($com) > $commax)    error('Comment too big.');
  if($upfile_size > $limitb)    error('File too big.');

  /* 連続投稿制限 */
  if($last_time > 0){
    $now = time();
    $last = @fopen($last_file, "r+") or die("連続投稿用ファイル $last_file を作成してください");
    $lsize = fgets($last, 1024);
    list($ltime, $lip) = explode("<>", $lsize);
    if($host == $lip && $last_time*60 > ($now-$ltime)){
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





 




