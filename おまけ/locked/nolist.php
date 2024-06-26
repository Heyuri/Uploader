<?php

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


if(phpversion()>="4.1.0"){//PHP4.1.0以降対応
  $_GET = array_map("_clean", $_GET);
  $_POST = array_map("_clean", $_POST);//11/8修正
  extract($_GET);
  extract($_POST);
  extract($_COOKIE);
  extract($_SERVER);
  $upfile_type=_clean($_FILES['upfile']['type']);
  $upfile_size=$_FILES["upfile"]["size"];
  $upfile_name=_clean($_FILES["upfile"]["name"]);
  $upfile=$_FILES["upfile"]["tmp_name"];
}


// 基本設定-----------------------------------------------------------------
  $page_title   = 'Unlisted Uploads';          // Page titile.
  $page_desc = 'Files uploaded on this board will not be listed publicly and will have randomized filenames.'; //Page description.
  $iprec = 1; //Whether or not to record IP addresses.
  $logfile      = 'nosouko.log';          // Log file.
  $logmax       = 200;                  // Max amount of posts on the board.
  $limitk       = 2000*1024;               //Filesize limit in mb.
  $max_all_flag = 1;                    // Not implemented.
  $max_all_size = 200000;                   //Max size of all files on the board.
  $updir        = './n/';             // Directory where files are uploaded.
  $prefix       = '';                   // File prefix（up001.txt,up002.jpgならup）.
  $commax       = 250;                  //Comment volume limit.
  $page_def     = 20;                   // Number of files per page.
  $admin        = 'admin';               // Admin password. Every file can be deleted if with this if it is inputted on the file deletion screen.
  $auto_link    = 0;                    // Comment auto linking（Yes=1;No=0).
  $last_time    = 0;                    // Time in between posting from the same IP.
  $last_file    = 'nolast.log';           // File for limiting continous posting.
  $count_look   = 0;                    // Counter display(Yes=1,No=0).
  $count_file   = 'nocount.log';          // Counter file.
  $count_start  = '2009/09/01';         // Counter start date.
  $sam_look     = 0;                    // Image list (sam.php required).
  $denylist     = array('192.168.0.1','sex.com','annony');                          //Hosts to deny access to.
  $arrowext     = array('bmp','cgi','gif','jpg','png','txt','mht','htm','html');    //Allow extensions (these must be in lowercase or else an error will be given)
    //Allowed filetypes.

  // ▼Yakuba(設定追加)
  $b_changeext  = array('htm','mht','cgi','php','html','sh','shtml','xml','svg');
  $a_changeext  = 'txt';                // Filetype to convert above files to.
  $base_php     = 'nolist.php';          // Name of this php file.
  $homepage_add = '../index.htm';      // Location of homepage.
  // ▲Yakuba

// 項目表示（環境設定）の初期状態 (表示ならChecked 表示しないなら空)--------
  $f_act  = 'checked';          //Enable post deletion form. 
  $f_com  = 'checked';          //Display comment.
  $f_size = 'checked';          //Display size.
  $f_mime = '';                 //Display mime type.
  $f_date = 'checked';          //Display date.
  $f_anot = '';                 //Open files in new window (not functioning).
  $f_orig = '';                 //Display original filename.


// ファイル、DIRの有無チェック----------------------------------------------
// ▼Yakuba(ファイル、DIRがなければ注意)
if ( !file_exists($logfile) ) {
  echo ($logfile.' There is no log file, please create it.(0666or0600)<br><br>');
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
  echo ('Aborting the process.。');
  exit;
}
// ▲Yakuba


if($act=="envset"){
  $cookval = implode("<>", array($acte,$come,$sizee,$mimee,$datee,$anote));
  setcookie ("upcook", $cookval,time()+365*24*3600);
}
function _clean($str) {
  $str = htmlspecialchars($str);
  if (get_magic_quotes_gpc()) $str = stripslashes($str);
  return $str;
}


// ヘッダ-------------------------------------------------------------------
$header = '
<html>
<head>
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
<a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
</H5>
</BODY>
</HTML>
OSHIRI;


echo $header;

function FormatByte($size){             //バイトのフォーマット（B→kB）
  if($size == 0)                    $format = "";
  else if($size <= 1024)            $format = $size."B";
  else if($size <= (1024*1024))     $format = sprintf ("%dKB",($size/1024));
  else if($size <= (10*1024*1024))  $format = sprintf ("%.2fMB",($size/(1024*1024)));
  else                              $format = $size."B";
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
    if($page=="all" and $sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>] [<a href=\"sam.php\">画像一覧</a>]　[<b>ALL</b>] %s",$next,$PHP_SELF);
    else if($page=="all" and !$sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>]　[<b>ALL</b>] %s",$next,$PHP_SELF);
    else if($page!="all" and $sam_look) return sprintf ("[<a href=\"$homepage_add\">Home</a>] [<a href=\"sam.php\">画像一覧</a>]　[<a href=\"$base_php?page=all\">ALL</a>] %s",$next,$PHP_SELF);
    else return sprintf ("[<a href=\"$homepage_add\">Home</a>]　 %s",$next,$PHP_SELF);
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
function runend2($mes1,$mes2=""){         //処理終了メッセージ

  echo "<hr><center><strong>$mes1</strong><br><p>$mes2</p></center>";

  // ▼Yakuba
  global $base_php;
  echo '[<a href="'.$base_php.'">Back</a>]';
  // ▲Yakuba

  global $foot,$base_php;

  
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
if(!$upcook) $upcook=implode("<>",array($f_act,$f_com,$f_size,$f_mime,$f_date,$f_anot));
list($c_act,$c_com,$c_size,$c_mime,$c_date,$c_anot,$c_orig)=explode("<>",$upcook);


/* アクセス制限 */
if(is_array($denylist)){
  while(list(,$line)=each($denylist)){
    if(strstr($host, $line)) error('Your host is on the deny list.');
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
<input type=password name=act value=\"envset\">
<ul>
<li><strong>表示設定</strong>
<ul>
<input type=checkbox name=acte value=checked $c_act>ACT<br>
<input type=checkbox name=come value=checked $c_com>COMMENT<br>
<input type=checkbox name=sizee value=checked $c_size>SIZE<br>
<input type=checkbox name=mimee value=checked $c_mime>MIME<br>
<input type=checkbox name=datee value=checked $c_date>DATE<br>
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
      error('Too many consecutive posts.');
    }
    rewind($last);
    fputs($last, "$now,$host,");
    fclose($last);
  }

  /* 拡張子と新ファイル名 */
    if (strpos($upfile_name, '.') != true) {
    error('No Extension','Files must contain extensions.');
}
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
  $id = substr(md5(rand()),0,6);                                //インクリ
  $newname = $prefix.$id.".".$ext;

  /* 自鯖転送 */
  $www = 0;
  while($www != 1) {
  if (file_exists(n/$newname)) {
  $id = substr(md5(rand()),0,6);
  $newname = $prefix.$id.".".$ext;
  }
  else {
  $www++;
  move_uploaded_file($upfile, $updir.$newname);//3.0.16より後のバージョンのPHP 3または 4.0.2 後
  }
  }
  
  //copy($upfile, $updir.$newname);
  chmod($updir.$newname, 0644);

  /* MIMEタイプ */
  if(!$upfile_type) $upfile_type = "text/plain";//デフォMIMEはtext/plain

  /* コメント他 */	//タグ変換
  if(get_magic_quotes_gpc()) $com = stripslashes($com);	//￥除去
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
  for($i = 0; $i < $logmax-1; $i++)	//いままでの分を追記
    fputs($fp, $lines[$i]);
  fclose ($fp);
  reset($lines);
  $lines = file($logfile);		//入れなおし
  runend2('Uploaded! File is <a href=n/'.$newname.'>here</a>.','https://'.$_SERVER['SERVER_NAME'].'/n/'.$newname.'');

}
foreach($arrowext as $list) $arrow .= $list." ";


// ▼Yakuba(ファイル総容量計算)
$size_all=0;
$logfile_open = fopen($logfile,"r");
while(!feof($logfile_open)){
    $csv = fgets($logfile_open);
    $str = explode("<>",$csv);
    $size_one = $str[5];
    $size_all = $size_all+$size_one;
} 
fclose($logfile_open);

$size_all_hikaku = $size_all/(1024*1024);       // 総容量比較用(MB)


// ファイル総容量単位変更----------------------------------------------------
if($size_all == 0)                      $size_all_hyouzi = $size_all."B";
else if($size_all <= 1024)              $size_all_hyouzi = $size_all."B";
else if($size_all <= (1024*1024))       $size_all_hyouzi = sprintf ("%dKB",($size_all/1024));
else if($size_all <= (10*1024*1024))    $size_all_hyouzi = sprintf ("%.2fMB",($size_all/(1024*1024)));
else                                    $size_all_hyouzi = $size_all."B";


// 投稿フォームヘッダ(Yakuba改造)-------------------------------------------
if($size_all_hikaku >= $max_all_size){
  echo 'The total capacity has exceeded the limit and is currently under posting restriction.<br>Please notify the administrator.<br><br>';
}
else{
  echo '
  <FORM METHOD="POST" ENCTYPE="multipart/form-data" ACTION="'.$PHP_SELF.'">
  FILE Max '.$limitk.'KB<br>
  <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="'.$limitb.'">
  <INPUT TYPE=file  SIZE="40" NAME="upfile"> 
  <INPUT TYPE=hidden SIZE="10" NAME="pass" maxlength="10"><br>

  <input type="hidden" size="45" value="ｷﾀ━━━(ﾟ∀ﾟ)━━━!!" name="com">
  <INPUT TYPE=submit VALUE="Up/Reload"><INPUT TYPE=reset VALUE="Cancel"><br>
  </FORM>
  ';
}


// ▼Yakuba(Counter display選択)
if($count_look){
  echo "<small>$count_start から ";
  if(file_exists($count_file)){
    $fp = fopen($count_file,"r+");//読み書きモードでオープン
    $count = fgets($fp, 64);	//64バイトorEOFまで取得、カウントアップ
    $count++;
    fseek($fp, 0);        //ポインタを先頭に、ロックして書き込み
    flock($fp, LOCK_EX);
    fputs($fp, $count);
    fclose($fp);          //ファイルを閉じる
    echo $count.'人　</small>';          //Counter display
  }
}


/* モードリンク*/
echo '
<!--（こわれにくさレベル1）「■」＝投稿記事削除</small>
<HR size=1><small><a href="'.$PHP_SELF.'?act=env">環境設定</a> | <a href=?>リロード</a> |
　<a href="sam.php">画像一覧</a>
</small>-->
<HR size=1>
';

/* ログ開始位置 */
$st = ($page) ? ($page - 1) * $page_def : 0;
if(!$page) $page = 1;
if($page == "all"){
  $st = 0;
  $page_def = count($lines);
}
echo paging($page, count($lines));//ページリンク


// メインヘッダ(表示項目を変更する場合にはwidthの調整もしてね)--------------
echo '<HR><table width="100%" style="font-size:10pt;"><tr>';
echo '<img src="/../aiyay.png" alt="aiyay" class="center">';
echo '</tr>';

//メイン表示
for($i = $st; $i < $st+$page_def; $i++){
  if($lines[$i]=="") continue;
  list($id,$ext,$com,$host,$now,$size,$mtype,$pas,)=explode("<>",$lines[$i]);
  $fsize = FormatByte($size);
  if($auto_link) $com = ereg_replace("(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)","<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",$com);

  $filename = $prefix.$id.".$ext";
  $target = $updir.$filename;

  if($c_anot) $jump = "target='_new'";
  echo "<tr><!--$host-->";//ホスト表示
  if($c_act) echo "<td><small><a href='$PHP_SELF?del=$id'>■</a></small></td>";
  echo "<td><a href='$target' $jump>$filename</a></td>";
  if($c_com) echo "<td><font size=2>$com</font></td>";
  if($c_size) echo "<td><font size=2>$fsize</font></td>";
  if($c_mime) echo "<td><font size=2 color=888888>$mtype</font></td>";
  if($c_date) echo "<td><font size=2>$now</font></td>";
  echo "</tr>\n";
  }


echo "</table><HR>";
// echo paging($page,count($lines));
echo $foot;
?>
