<?php
/***
  �����܂���
* �T���l�C���J�b�^�[�@�i�摜�ꗗ�jby ToR

* ��PHP�I�v�V������GD���K�v�ł��i�����I�ł̓_���ȂƂ��낪����
* $_GET���g�p���Ă܂��B�Â��o�[�W������PHP�ł�$_GET��$HTTP_GET_VARS $_SERVER��$HTTP_SERVER_VARS
**/

$img_dir   = "./src/";                  //�摜�ꗗ�f�B���N�g��
$thumb_dir = "./thumb/";                //�T���l�C���ۑ��f�B���N�g��
$ext       = ".+\.png$|.+\.jpe?g$";     //�g���q�CGIF��GD���ް�ޮ݂ɂ���Ă͖���
$W         = 110;                       //�o�͉摜��
$H         = 85;                        //�o�͉摜����
$cols      = 4;                         //1�s�ɕ\������摜��
$page_def  = 20;                        //1�y�[�W�ɕ\������摜��

if ($_GET["cmd"]=="min" && isset($_GET["pic"])) {
  $src = $img_dir.$_GET["pic"];

  // �摜�̕��ƍ����ƃ^�C�v���擾
  $size = GetImageSize($src);
  switch ($size[2]) {
    case 1 : $im_in = ImageCreateFromGIF($src);  break;
    case 2 : $im_in = ImageCreateFromJPEG($src); break;
    case 3 : $im_in = ImageCreateFromPNG($src);  break;
  }
  // �ǂݍ��݃G���[��
  if (!$im_in) {
    $im_in = ImageCreate($W,$H);
    $bgc = ImageColorAllocate($im_in, 0xff, 0xff, 0xff);
    $tc  = ImageColorAllocate($im_in, 0,0x80,0xff);
    ImageFilledRectangle($im_in, 0, 0, $W, $H, $bgc);
    ImageString($im_in,1,5,30,"Error loading {$_GET["pic"]}",$tc);
    ImagePNG($im_in);
    exit;
   }
  // ���T�C�Y
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
  // �o�͉摜�i�T���l�C���j�̃C���[�W���쐬
  $im_out = ImageCreateTrueColor($out_w, $out_h);
  // ���摜���c���Ƃ� �R�s�[���܂��B
  ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);

  // �����ŃG���[���o����͉��Q�s�ƒu�������Ă��������B(GD2.0�ȉ�
  //$im_out = ImageCreate($out_w, $out_h);
  //ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);

  // �T���l�C���摜���u���E�U�ɏo�́A�ۑ�
  switch ($size[2]) {
  case 1 : if (function_exists('ImageGIF')) { ImageGIF($im_out); ImageGIF($im_out, $thumb_dir.$_GET["pic"]); } break;
  case 2 : ImageJPEG($im_out);ImageJPEG($im_out, $thumb_dir.$_GET["pic"]); break;
  case 3 : ImagePNG($im_out); ImagePNG($im_out, $thumb_dir.$_GET["pic"]);  break;
  }
  // �쐬�����C���[�W��j��
  ImageDestroy($im_in);
  ImageDestroy($im_out);
  exit;
}
// �f�B���N�g���ꗗ�擾�A�\�[�g
$d = dir($img_dir);
while ($ent = $d->read()) {
  if (eregi($ext, $ent)) {
    $files[] = $ent;
  }
}
$d->close();
// �\�[�g
natsort($files);
$files2 = array_reverse($files);
//�w�b�_HTML
echo <<<HEAD
<html>
<body bgcolor=#ffffee><center><b>�T���l�C���ꗗ</b><br><br>
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
    // �T���l�C�������鎞�ͻ�Ȳقւ��ݸ�A����ȊO�ͻ�Ȳٕ\���A�쐬
    if (file_exists($thumb_dir.$image)) $piclink = $thumb_dir.$image;
    else $piclink = $_SERVER["PHP_SELF"]."?cmd=min&pic=".$image;
//���C��HTML
    echo <<<EOD
<td align=center><a href="$img_dir$image" target=_blank>
<img src="$piclink" border="0"><br>$filename</a></td>
EOD;
    $counter++;
    if (((($counter) % $cols) == 0)) echo "</tr><tr>";
  }
}
echo "</tr></table><br>";

//�߰�ރ����N
if ($_GET["start"] > 0) {
  $prevstart = $_GET["start"] - $page_def;
  echo "<a href=\"$_SERVER[PHP_SELF]?start=$prevstart\">&lt;&lt;�O��</a>�@";
}
if ($ends < $maxs) {
  $nextstart = $ends+1;
  echo "�@<a href=\"$_SERVER[PHP_SELF]?start=$nextstart\">����&gt;&gt;</a>";
}

echo "</center><div align=right><a href=http://php.s3.to>���b�cPHP!</a> <a href=http://php.s3.to/bbs/up/sam.php.txt>�\�[�X</a></div>
</body></html>";
?>
