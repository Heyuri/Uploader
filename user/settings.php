<?php
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
$required = array('cpass');
$error = false;
foreach($required as $field) {
  if (empty($_POST[$field])) {
    $error = true;
  }
}
include 'settingvar.php';
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
    $cpassd = $_POST["cpass"];
    if ($cpassd != $password) {
    $body2 = '
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
<center><font size="8">Password incorrect!</font></center>
';
echo $body2;
    } else {
    if (!empty($_POST["pass"])) {
    $password = $_POST["pass"];
    if (strpos($password, '>') !== false) {
    echo 'Password contains an invalid character.';
} elseif (strpos($password, '<') !== false) {
echo 'Password contains an invalid character.';
} elseif (strpos($password, '&') !== false) {
echo 'Password contains an invalid character.';
exit;
}
    }
    if (!empty($_POST["comd"])) {
    $comen = $_POST["comd"];
    $comen = trim($comen);
    }
    if (!empty($_POST["upp"])) {
    $passup = $_POST["upp"];
    $passup = trim($passup);
    }
    if (!empty($_POST["sized"])) {
    $saizu = $_POST["sized"];
    $saizu = trim($saizu);
    }
    if (!empty($_POST["origd"])) {
    $origu = $_POST["origd"];
    $origu = trim($origu);
    }
    if (!empty($_POST["mimed"])) {
    $mime = $_POST["mimed"];
    $mime = trim($mime);
    }
    if (!empty($_POST["title"])) {
    $title = $_POST["title"];
    if (strpos($title, '>') !== false) {
    echo 'Title contains an invalid character.';
} elseif (strpos($title, '<') !== false) {
echo 'Title contains an invalid character.';
} elseif (strpos($title, '&') !== false) {
echo 'Title contains an invalid character.';
exit;
}
    }
    if (!empty($_POST["desc"])) {
    $desc = $_POST["desc"];
    $desc = trim($desc);
    }
    if (!empty($_POST["prefix"])) {
    $fprefix = $_POST["prefix"];
    $fprefix = trim($fprefix);
    }
    if (!empty($_POST["dates"])) {
    $date = $_POST["dates"];
    $date = trim($date);
    }
  $url = trim($url);
  $base = "$url.php";
  $yes = "Yes";
  $no = "No";
  
 
  
  
  
  
  
  
  
  
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
  echo 'Pass: <INPUT TYPE=password SIZE="10" NAME="pass2" maxlength="10"><br>';  
  }
  ob_end_flush();
  exec("cat $url.txt.3 >> $url.php");
  exec("cat templates/template4.php >> $url.php");
exec("rm -rf index.php && mv $url.php index.php && rm -rf $url.txt $url.txt.2 $url.txt.3");
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
  echo '?>';
  ob_end_flush();
  exec("rm -rf settingvar.php && mv $url.settings.php settingvar.php");
     $body4 = '
<html>
<head>
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<meta name="Berry" content="no">
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
<meta http-equiv="pragma" content="no-cache">
<title>Board Settings Changed</title>
<style>
  <!--
  a:link    {color:#0000ee;}
  a:hover   {color:#5555ee;}
  a:visited {color:#0000ee;}
  -->
</style>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
<table width="100%"><tr><td bgcolor="#eeaa88"><strong><font size="4">Board Settings Changed </font></strong></td></tr></table>
<center><img src="aiyay.png" alt="aiyay"></center> 
<center><font size="8"><a href="https://2ch.cx/user/'.$url.'">Settings changed!</a></font></center> ';
 echo $body4;
  }
  }
?>
