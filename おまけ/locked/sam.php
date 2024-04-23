<?php
/***
  ☆おまけ☆
* サムネイルカッター　（画像一覧）by ToR

* ※PHPオプションにGDが必要です（無料鯖ではダメなところが多い
* $_GET等使用してます。古いバージョンのPHPでは$_GET→$HTTP_GET_VARS $_SERVER→$HTTP_SERVER_VARS
**/

$img_dir   = "./src/";                  //画像一覧ディレクトリ
$thumb_dir = "./thumb/";                //サムネイル保存ディレクトリ
$ext       = ".+\.png$|.+\.jpe?g$";     //拡張子，GIFはGDのﾊﾞｰｼﾞｮﾝによっては無理
$W         = 110;                       //出力画像幅
$H         = 85;                        //出力画像高さ
$cols      = 4;                         //1行に表示する画像数
$page_def  = 20;                        //1ページに表示する画像数

if ($_GET["cmd"]=="min" && isset($_GET["pic"])) {
  $src = $img_dir.$_GET["pic"];

  // 画像の幅と高さとタイプを取得
  $size = GetImageSize($src);
  switch ($size[2]) {
    case 1 : $im_in = ImageCreateFromGIF($src);  break;
    case 2 : $im_in = ImageCreateFromJPEG($src); break;
    case 3 : $im_in = ImageCreateFromPNG($src);  break;
  }
  // 読み込みエラー時
  if (!$im_in) {
    $im_in = ImageCreate($W,$H);
    $bgc = ImageColorAllocate($im_in, 0xff, 0xff, 0xff);
    $tc  = ImageColorAllocate($im_in, 0,0x80,0xff);
    ImageFilledRectangle($im_in, 0, 0, $W, $H, $bgc);
    ImageString($im_in,1,5,30,"Error loading {$_GET["pic"]}",$tc);
    ImagePNG($im_in);
    exit;
   }
  // リサイズ
  if ($size[0] > $W || $size[1] > $H) {
    $key_w = $W / $size[0];
    $key_h = $H / $size[1];
    ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;

    $out_w = $size[0] * $keys;
    $out_h = $size[1] * $keys;
  } else {
    $out_w = $size[0];
    $out_h = $size[1];
  }
  // 出力画像（サムネイル）のイメージを作成
  $im_out = ImageCreateTrueColor($out_w, $out_h);
  // 元画像を縦横とも コピーします。
  ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);

  // ここでエラーが出る方は下２行と置き換えてください。(GD2.0以下
  //$im_out = ImageCreate($out_w, $out_h);
  //ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);

  // サムネイル画像をブラウザに出力、保存
  switch ($size[2]) {
  case 1 : if (function_exists('ImageGIF')) { ImageGIF($im_out); ImageGIF($im_out, $thumb_dir.$_GET["pic"]); } break;
  case 2 : ImageJPEG($im_out);ImageJPEG($im_out, $thumb_dir.$_GET["pic"]); break;
  case 3 : ImagePNG($im_out); ImagePNG($im_out, $thumb_dir.$_GET["pic"]);  break;
  }
  // 作成したイメージを破棄
  ImageDestroy($im_in);
  ImageDestroy($im_out);
  exit;
}
// ディレクトリ一覧取得、ソート
$d = dir($img_dir);
while ($ent = $d->read()) {
  if (eregi($ext, $ent)) {
    $files[] = $ent;
  }
}
$d->close();
// ソート
natsort($files);
$files2 = array_reverse($files);
//ヘッダHTML
echo <<<HEAD
<html>
<body bgcolor=#ffffee><center><b>サムネイル一覧</b><br><br>
<table border="0" cellpadding="2">
<tr>
HEAD;

//print_r($files);
$maxs = count($files)-1;
$ends = $start+$page_def-1;
$counter = 0;
while (list($line, $filename) = each($files2)) {
  if (($line >= $start) && ($line <= $ends)) {
    $image = rawurlencode($filename);
    // サムネイルがある時はｻﾑﾈｲﾙへのﾘﾝｸ、それ以外はｻﾑﾈｲﾙ表示、作成
    if (file_exists($thumb_dir.$image)) $piclink = $thumb_dir.$image;
    else $piclink = $_SERVER["PHP_SELF"]."?cmd=min&pic=".$image;
//メインHTML
    echo <<<EOD
<td align=center><a href="$img_dir$image" target=_blank>
<img src="$piclink" border="0"><br>$filename</a></td>
EOD;
    $counter++;
    if (((($counter) % $cols) == 0)) echo "</tr><tr>";
  }
}
echo "</tr></table><br>";

//ﾍﾟｰｼﾞリンク
if ($_GET["start"] > 0) {
  $prevstart = $_GET["start"] - $page_def;
  echo "<a href=\"$_SERVER[PHP_SELF]?start=$prevstart\">&lt;&lt;前へ</a>　";
}
if ($ends < $maxs) {
  $nextstart = $ends+1;
  echo "　<a href=\"$_SERVER[PHP_SELF]?start=$nextstart\">次へ&gt;&gt;</a>";
}

echo "</center><div align=right><a href=http://php.s3.to>レッツPHP!</a> <a href=http://php.s3.to/bbs/up/sam.php.txt>ソース</a></div>
</body></html>";
?>
