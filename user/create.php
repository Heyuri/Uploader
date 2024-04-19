<?php
	

				
		function kaptcha_validate($key) {
define('KAPTCHA_API_URL', 'https://sys.kolyma.org/kaptcha/kaptcha.php');
define('VIP_API_KEY', '2chsh343626347');
	$check = file_get_contents('https://vipcode.kolyma.org/login/vip.php?key='.VIP_API_KEY.'&addr='.$_SERVER['REMOTE_ADDR']);
	if ($_SERVER['REMOTE_ADDR'] == $check) {
		return true;
	}
			
	if (isset($_REQUEST["_KAPTCHA_NOJS"])) {
		if ($_SERVER['REMOTE_ADDR'] == $check && (isset($_GET["nojs"]) || isset($_GET["nojscheck"]) || isset($_GET["_KAPTCHA_NOJS"]))) {
    		return true;
		}

		$k = $_REQUEST["_KAPTCHA_KEY"]??false;
		if (!$k) return false;
		return stristr(file_get_contents(KAPTCHA_API_URL."?nojscheck&key=&_KAPTCHA=".$k), "CHECK correct");
	}

	$k = $_REQUEST["_KAPTCHA"]??false;
	if (!$k) return false;
	return stristr(file_get_contents(KAPTCHA_API_URL."?_KAPTCHA=".$k."&key=".$key), "CHECK correct");
}

		if (!kaptcha_validate($_POST["_KAPTCHA_KEY"])) {
			die('You seem to have mistyped the CAPTCHA');
		} 
function ob_file_callback($buffer)
{
  global $ob_file;
  fwrite($ob_file,$buffer);
}
function ob_file_callback2($buffer)
{
  global $ob_file2;
  fwrite($ob_file2,$buffer);
}
function ob_file_callback3($buffer)
{
  global $ob_file3;
  fwrite($ob_file3,$buffer);
}
function ob_file_callback4($buffer)
{
  global $ob_file4;
  fwrite($ob_file4,$buffer);
}
function ob_file_callback5($buffer)
{
  global $ob_file5;
  fwrite($ob_file5,$buffer);
}
function ob_file_callback6($buffer)
{
  global $ob_file6;
  fwrite($ob_file6,$buffer);
}
$required = array('url', 'title', 'desc', 'dates', 'pass', 'comd', 'mimed', 'sized', 'origd', 'upp', 'listd');
$error = false;
foreach($required as $field) {
  if (empty($_POST[$field])) {
    $error = true;
  }
}
$dirtemp = "templates";
if ($error) {
$body1 = '
<html>
<head>
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<meta name="Berry" content="no">
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
<meta http-equiv="pragma" content="no-cache">
<title>Error</title>
<style>
  <!--
  a:link    {color:#0000ee;}
  a:hover   {color:#5555ee;}
  a:visited {color:#0000ee;}
  -->
</style>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
<table width="100%"><tr><td bgcolor="#eeaa88"><strong><font size="4">Error</font></strong></td></tr></table>
<center><img src="aihelper.png" alt="aihelper"></center> 
<center><font size="8">You did not fill out all of the fields!</font></center>
';
echo $body1;
} else {
  $url = $_POST["url"];
if(preg_match('/[^a-z_\-0-9]/i', $url))
{
  echo "Please only use alphanumeric characters on board URLs.";
}
 else {
  $url = trim($url);
  $url = stripslashes($url);
  $url = htmlspecialchars($url);
  $password = $_POST["pass"];
  if (strpos($password, '>') !== false) {
    echo 'Password contains an invalid character.';
} elseif (strpos($password, '<') !== false) {
echo 'Password contains an invalid character.';
} elseif (strpos($password, '&') !== false) {
echo 'Password contains an invalid character.';
}else {
  $base = "$url.php";
  $comen = $_POST["comd"];
  $passup = $_POST["upp"];
  $listdd = $_POST["listd"];
  $saizu = $_POST["sized"];
  $origu = $_POST["origd"];
  $mime = $_POST["mimed"];
  $title = $_POST["title"];
    if (strpos($title, '>') !== false) {
    echo 'Title contains an invalid character.';
} elseif (strpos($title, '<') !== false) {
echo 'Title contains an invalid character.';
} elseif (strpos($title, '&') !== false) {
echo 'Title contains an invalid character.';
}else {
  $desc = $_POST["desc"];
  $fprefix = $_POST["prefix"];
  $date = $_POST["dates"];
  $yes = "Yes";
  $no = "No";
  $comen = trim($comen);
  $comen = stripslashes($comen);
  $comen = htmlspecialchars($comen);
  $passup = trim($passup);
  $passup = stripslashes($passup);
  $passup = htmlspecialchars($passup);
  $saizu = trim($saizu);
  $saizu = stripslashes($saizu);
  $saizu = htmlspecialchars($saizu);
  $origu = trim($origu);
  $origu = stripslashes($origu);
  $origu = htmlspecialchars($origu);
  $mime = trim($mime);
  $mime = stripslashes($mime);
  $mime = htmlspecialchars($mime);
  $date = trim($date);
  $date = stripslashes($date);
  $date = htmlspecialchars($date);
  $fprefix = trim($fprefix);
  $fprefix = stripslashes($fprefix);
  $fprefix = htmlspecialchars($fprefix);
  $desc = trim($desc);
  $desc = stripslashes($desc);
  $desc = htmlspecialchars($desc);
  if(is_dir($url)) {
  $body3 = '
<html>
<head>
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<meta name="Berry" content="no">
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
<meta http-equiv="pragma" content="no-cache">
<title>Error</title>
<style>
  <!--
  a:link    {color:#0000ee;}
  a:hover   {color:#5555ee;}
  a:visited {color:#0000ee;}
  -->
</style>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
<table width="100%"><tr><td bgcolor="#eeaa88"><strong><font size="4">Error</font></strong></td></tr></table>
<center><img src="aihelper.png" alt="aihelper"></center> 
<center><font size="8">That board URL already exists, or is a system directory.</font></center>
';
echo $body3;
  } else {
  mkdir($url);  
  if ($listdd == $yes) {
  $fp = fopen('index.htm', 'a');
  fwrite($fp, '<font size="6"><center><a href="/user/'.$url.'">'.$url.'</a><br><br></font></center>');  
  fclose($fp);  
  }
  exec("cp templates/template1.php $url.php"); 
  $ob_file = fopen("$url.txt","w");
  ob_start('ob_file_callback');
  echo '$page_title = ';
  echo "'";
  echo "$title";
  echo "'";
  echo ';';
  echo "\n";
  echo '$page_desc = ';
  echo "'";
  echo "$desc";
  echo "'";
  echo ';';
  echo "\n";
  echo '$base_php = ';
  echo "'";
  echo "index.php";
  echo "'";
  echo ';';
  echo "\n";
  if ($date == $yes) {
  echo '$f_date = ';
  echo "'";
  echo "checked";
  echo "'";
  echo ';';
  }
  else {
  echo '$f_date = ';
  echo "'";
  echo "'";
  echo ';';
  }
  echo "\n";
  echo '$prefix = ';
  echo "'";
  echo "$fprefix";
  echo "'";
  echo ';';
  echo "\n";
  echo '$admin = ';
  echo "'";
  echo "$password";
  echo "'";
  echo ';';
  echo "\n";
  if ($comen == $yes) {
  echo '$f_com = ';
  echo "'";
  echo "checked";
  echo "'";
  echo ';';
  }
  else {
  echo '$f_com = ';
  echo "'";
  echo "'";
  echo ';';
  }
  echo "\n";
  if ($mime == $yes) {
  echo '$f_mime = ';
  echo "'";
  echo "checked";
  echo "'";
  echo ';';
  }
  else {
  echo '$f_mime = ';
  echo "'";
  echo "'";
  echo ';';
  }
  echo "\n";
  if ($saizu == $yes) {
  echo '$f_size = ';
  echo "'";
  echo "checked";
  echo "'";
  echo ';';
  }
  else {
  echo '$f_size = ';
  echo "'";
  echo "'";
  echo ';';
  }
  echo "\n";
  if ($origu == $yes) {
  echo '$f_orig = ';
  echo "'";
  echo "checked";
  echo "'";
  echo ';';
  }
  else {
  echo '$f_orig = ';
  echo "'";
  echo "'";
  echo ';';
  }
  echo "\n";
  ob_end_flush();
  exec("cat $url.txt >> $url.php");
  exec("cat templates/template2.php >> $url.php");
  $ob_file2 = fopen("$url.txt.2","w");
  ob_start('ob_file_callback2');
  if ($passup == $yes) {
  echo 'if($pass2 !== $admin) error';
  echo "('Incorrect password.')";
  echo ';';
  echo "\n";
  }
  ob_end_flush();
  exec("cat $url.txt.2 >> $url.php");
  exec("cat templates/template3.php >> $url.php");
  $ob_file3 = fopen("$url.txt.3","w");
  ob_start('ob_file_callback3');
  if ($passup == $yes) {
  echo 'Pass: <INPUT TYPE=password SIZE="10" NAME="pass2" maxlength="25"><br>';  
  }
  ob_end_flush();
  exec("cat $url.txt.3 >> $url.php");
  exec("cat templates/template4.php >> $url.php");
exec("mv $url.php $url/index.php && cd $url && touch souko.log last.log count.log && mkdir src && cd - && rm -rf $url.txt $url.txt.2 $url.txt.3 && cd $url && mkdir templates && cd - && cp templates/template1.php templates/template2.php templates/template3.php templates/template4.php $url/templates && cp templates/settings.htm $url/settings.htm && cp aihelper.png $url/aihelper.png && cp templates/settings.php $url/settings.php && cp aiyay.png $url/aiyay.png");
  $ob_file4 = fopen("$url.settings.php","w");
  ob_start('ob_file_callback4');
  echo '<?php';
  echo "\n";
  echo '$title = ';
  echo '"'; 
  echo "$title";
  echo '"'; 
  echo ';';
  echo "\n";
    echo '$password = ';
  echo '"'; 
  echo "$password";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '$desc = ';
  echo '"'; 
  echo "$desc";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '$comen = ';
  echo '"'; 
  echo "$comen";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '$passup = ';
  echo '"'; 
  echo "$passup";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '$saizu = ';
  echo '"'; 
  echo "$saizu";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '$origu = ';
  echo '"'; 
  echo "$origu";
  echo '"'; 
  echo ';';
  echo "\n";
    echo '$mime = ';
  echo '"'; 
  echo "$mime";
  echo '"'; 
  echo ';';
  echo "\n";
    echo '$date = ';
  echo '"'; 
  echo "$date";
  echo '"'; 
  echo ';';
  echo "\n";
    echo '$fprefix = ';
  echo '"'; 
  echo "$fprefix";
  echo '"'; 
  echo ';';
  echo "\n";
      echo '$url = ';
  echo '"'; 
  echo "$url";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '?>';
  ob_end_flush();
  exec("mv $url.settings.php $url/settingvar.php");
  $ob_file5 = fopen("$url.settings.pass.php","w");
  ob_start('ob_file_callback5');
    echo '<?php';
    echo "\n";
    echo '$password = ';
  echo '"'; 
  echo "$password";
  echo '"'; 
  echo ';';
  echo "\n";
  echo '?>';
  ob_end_flush();
  exec("cp templates/css.php $url/css.php && mv $url.settings.pass.php $url/settingspass.php && cd $url && touch cssouko.log clast.log ccount.log && mkdir csrc");
    $body4 = '
<html>
<head>
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<meta name="Berry" content="no">
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
<meta http-equiv="pragma" content="no-cache">
<title>Board Created</title>
<style>
  <!--
  a:link    {color:#0000ee;}
  a:hover   {color:#5555ee;}
  a:visited {color:#0000ee;}
  -->
</style>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
<table width="100%"><tr><td bgcolor="#eeaa88"><strong><font size="4">Board Created </font></strong></td></tr></table>
<center><img src="aiyay.png" alt="aiyay"></center> 
<center><font size="8"><a href="/user/'.$url.'">Board created!</a></font></center> ';
 echo $body4;
  }
  }
  }
  }
  }
?>
