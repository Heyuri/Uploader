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
  for($i = 0; $i < $logmax-1; $i++)	//いままでの分を追記
    fputs($fp, $lines[$i]);
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
else if($size_all <= (10*1024*1024))    $size_all_hyouzi = sprintf ("%.2fMB",($size_all/(1024*1024)));
else if($size_all <= (1000*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fGB",($size_all/(1024*1024*1024)));
else if($size_all <= (10*1024*1024*1024*1024))  $size_all_hyouzi = sprintf ("%.2fTB",($size_all/(1024*1024*1024*1024)));
else                                    $size_all_hyouzi = $size_all."B";                                   $size_all_hyouzi = $size_all."B";


// 投稿フォームヘッダ(Yakuba改造)-------------------------------------------
if($size_all_hikaku >= $max_all_size){
  echo 'The total capacity has exceeded the limit and is currently under posting restriction.<br>Please notify the administrator.<br><br>';
}
else{
  echo '
  <FORM METHOD="POST" ENCTYPE="multipart/form-data" ACTION="'.$PHP_SELF.'">
  FILE Max '.$limitk.'KB (Max '.$logmax.'Files)<br>
  <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="'.$limitb.'">
  <INPUT TYPE=file  SIZE="40" NAME="upfile"> 
  DELKey: <INPUT TYPE=password SIZE="10" NAME="pass" maxlength="10"> 
