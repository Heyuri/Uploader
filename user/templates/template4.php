COMMENT<i><small>（※If no comment is entered, the page will be reloaded / URL will be auto-linked.）</small></i><br>
  <input type="text" size="45" value="ｷﾀ━━━(ﾟ∀ﾟ)━━━!!" name="com">
  <INPUT TYPE=submit VALUE="Up/Reload"><INPUT TYPE=reset VALUE="Cancel"><br>
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
if($c_size) echo '<td width="7%"><tt><b>SIZE</b></tt></td>';
if($c_mime) echo '<td><tt><b>MIME</b></tt></td>';
if($c_date) echo '<td width="23%"><tt><b>DATE</b></tt></td>';
if($c_orig) echo '<td><tt><b>ORIG</b></tt></td>';

echo '</tr>';

//メイン表示
for($i = $st; $i < $st+$page_def; $i++){
  if($lines[$i]=="") continue;
  list($id,$ext,$com,$host,$now,$size,$mtype,$pas,$orig,)=explode("<>",$lines[$i]);
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
  if($c_orig) echo "<td><font size=2 color=aaaaaa>$orig</font></td>";
  echo "</tr>\n";
  
  }


echo "</table><HR>";
echo 'Used '.$size_all_hyouzi.'／ '.$max_all_size.'MB<br>';
echo 'Used '.count($lines).'Files／ '.$logmax.'Files<br>';
// echo paging($page,count($lines));
echo $foot;
?>
