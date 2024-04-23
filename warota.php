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

//add config values
require 'config.php';


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

// ファイル、DIRの有無チェック----------------------------------------------
// ▼Yakuba(ファイル、DIRがなければ注意)
if ( !file_exists($logfile) ) {
  echo ($logfile.' がありません。作成してください。(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($count_file)) {
  echo ($count_file.' がありません。作成してください。(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($last_file)) {
  echo ($last_file.' がありません。作成してください。(0666or0600)<br><br>');
  $out = '1';
}

if (!file_exists($updir)) {
  echo ($updir.' がありません。作成してください。(0777or0701)<br><br>');
  $out = '1';
}

if ($out){
  echo ('処理を中止します。');
  exit;
}
// ▲Yakuba


if($act=="envset"){
  $cookval = implode("<>", array($acte,$come,$sizee,$mimee,$datee,$anote));
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
<br><br>'.$title_sub.'<br><br><br>
</tt>
';


// フッタ-------------------------------------------------------------------
$foot = <<<OSHIRI
<BR><H5 align="right">
<a href="https://github.com/Heyuri/Uploader/">Heyuri</a> + <a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
</H5>
</BODY>
</HTML>
OSHIRI;


echo $header;

//Unit conversion function
function FormatByte($size){             //バイトのフォーマット（B→kB）
  if($size == 0)                    $format = "";
  else if($size <= 1024)            $format = $size."B";
  else if($size <= (1024*1024))     $format = sprintf ("%dKB",($size/1024));
  else if($size <= (1000*1024*1024))  $format = sprintf ("%.2fMB",($size/(1024*1024)));
  else if($size <= (1000*1024*1024*1024))  $format = sprintf ("%.2fGB",($size/(1024*1024*1024)));
  else if($size <= (1000*1024*1024*1024*1024)  || $size >= (1000*1024*1024*1024*1024))  $format = sprintf ("%.2fTB",($size/(1024*1024*1024*1024)));
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

$host = 1337;
// if ipcheck and de-anon are enabled then it will log IP
if($ipcheck) {
	require_once $module_List['mod_ipcheck'];
	
	if(function_exists('getIP'))	$host = call_user_func('getIP');
}


if(!$upcook) $upcook=implode("<>",array($f_act,$f_com,$f_size,$f_mime,$f_date,$f_anot));
list($c_act,$c_com,$c_size,$c_mime,$c_anot)=explode("<>",$upcook);



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
<input type=checkbox name=datee value=checked >DATE<br>
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
  //check if IP is banned from uploading [only usuable if ipcheck module is enabled]
  if(function_exists('matchIP_to_denylist')) call_user_func('matchIP_to_denylist', $host);
	
  if(strlen($com) > $commax)    error('Comment too big.');
  if($upfile_size > $limitb)	error('File too big.');

  //will check if anti-flood script is enabled
  if($antiflood) {
	require_once $module_List['mod_antiflood'];
	if(function_exists('anti_flood_check')) {
		call_user_func('anti_flood_check');
	}
  }

  /* 拡張子と新ファイル名 */
  $pos = strrpos($upfile_name,".");                             //拡張子取得
  $ext = substr($upfile_name,$pos+1,strlen($upfile_name)-$pos);
  $ext = strtolower($ext);                                      //小文字化
  if(!in_array($ext, $arrowext))
    error("拡張子エラー","その拡張子ファイルはアップロードできません");

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
else if($size_all <= (1000*1024*1024))    $size_all_hyouzi = sprintf ("%.2fMB",($size_all/(1024*1024)));
else if($size_all <= (1000*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fGB",($size_all/(1024*1024*1024)));
else if($size_all <= (1000*1024*1024*1024*1024) || $size_all >= (1000*1024*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fTB",($size_all/(1024*1024*1024*1024)));
else                                    $size_all_hyouzi = $size_all."B";


// Post form header (Yakuba modification)-------------------------------------------
// Check if the overall filesize limit for the board has been exceeded
if($size_all_hikaku >= $max_all_size / (1024*1024)){
  echo 'The total capacity has exceeded the limit and is currently under posting restriction.<br>Please notify the administrator.<br><br>';
}
else{
  echo '
  <FORM METHOD="POST" ENCTYPE="multipart/form-data" ACTION="'.$PHP_SELF.'">
  FILE Max '.FormatByte($limitb).' (Max '.$logmax.' Files)<br>
  <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="'.$limitb.'">
  <INPUT TYPE=file  SIZE="40" NAME="upfile"> 
  DELKey: <INPUT TYPE=password SIZE="10" NAME="pass" maxlength="10"><br>
  COMMENT<i><small>（※If no comment is entered, the page will be reloaded / URL will be auto-linked.）</small></i><br>
  <input type="text" size="45" value="ｷﾀ━━━(ﾟ∀ﾟ)━━━!!" name="com">
  <INPUT TYPE=submit VALUE="Up/Reload"><INPUT TYPE=reset VALUE="Cancel"><br>
  <small>Allowed extensions：'.$arrow.'</small>
  </FORM>
  ';
}


// ▼Yakuba(カウンタ表示選択)
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
    echo $count.'人　</small>';          //カウンタ表示
  }
}


/* モードリンク*/
echo '
<!--（こわれにくさレベル1）「■」＝投稿記事削除</small>
<HR size=1><small><a href="'.$PHP_SELF.'?act=env">環境設定</a> | <a href=?>リロード</a> |
　<a href="img.php">画像一覧</a>
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
if($c_act) echo '<td width="4%"><tt><b>DEL</b></tt></td>';
echo '<td width="8%"><tt><b>NAME</b></tt></td>';
if($c_com)  echo '<td width="58%"><tt><b>COMMENT</b></tt></td>';
if($c_size) echo '<td width="7%"><tt><b> SIZE</b></tt></td>';
if($c_mime) echo '<td><tt><b>MIME</b></tt></td>';
echo '</tr>';

//メイン表示
for($i = $st; $i < $st+$page_def; $i++){
  if($lines[$i]=="") continue;
  list($id,$ext,$com,$host,$now,$size,$mtype,$pas,)=explode("<>",$lines[$i]);
  $fsize = FormatByte($size);
  if($auto_link) $com = ereg_replace("(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)","<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",$com);

  $filename = $prefix.$id.".$ext";
  $target = $updir.$filename;

  echo "<tr><!--$host-->";//ホスト表示
  if($c_act) echo "<td><small><a href='$PHP_SELF?del=$id'>■</a></small></td>";
  echo "<td><a href='$target'>$filename</a></td>";
  if($c_com) echo "<td><font size=2>$com</font></td>";
  if($c_size) echo "<td><font size=2>$fsize</font></td>";
  if($c_mime) echo "<td><font size=2 color=888888>$mtype</font></td>";
  echo "</tr>\n";
  }


echo "</table><HR>";
echo 'Used '.$size_all_hyouzi.'／ '.FormatByte($max_all_size).'<br>';
echo 'Used '.count($lines).' Files／ '.$logmax.' Files<br>';
// echo paging($page,count($lines));
echo $foot;
?>
