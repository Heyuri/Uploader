<?php 

$conf = require_once 'config.php';

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



/* draw functions */
function drawHeader(){
    global $conf;

    echo '
    <html>
    <head>
    <META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
    <meta name="Berry" content="no">
    <meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
    <meta http-equiv="cache-control" content="max-age=0">
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="0">
    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
    <meta http-equiv="pragma" content="no-cache">
    <style>
        a:link    {color:#0000ee;}
        a:hover   {color:#5555ee;}
        a:visited {color:#0000ee;}
        tr:nth-child(odd) {background-color: #f7efea;}
        tr:hover {background-color: #f0e0d6;}
        table {border-collapse: collapse;}
	.imagePreview {
	 height: 100px;
	 max-width: 200px;
	 padding-right: 5px;
	 padding-top: 2px;
	}    	
    </style>
    <title>'.$conf['boardTitle'].'</title>
    </head>
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
        <table width="100%">
            <tr><td bgcolor="#eeaa88">
                <strong><font size="4">'.$conf['boardTitle'].' Custom CSS Uploader</font></strong>
            </td></tr>
        </table>
        <tt><br>
        <br> 
        CSS Uploader for '.$conf['boardTitle'].'<br>
        <br>
        <br>
        </tt> [<a href='.$conf['mainScript'].'>Back</a>]<br><br>';
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
    drawHeader();
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
function drawMessageAndRedirectHome($mes1,$mes2=""){ 
    drawHeader();
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center>
    [<a href="'.$_SERVER['PHP_SELF'].'">Back</a>]
    <script type="text/javascript">setTimeout(location.href="'.$_SERVER['PHP_SELF'].'",0)</script>';
    drawFooter();
    exit;
}

function drawUploadForm(){
    // Post form header (Yakuba modification)
    // Check if the overall filesize limit for the board has been exceeded
    global $conf;
        echo '
        <form method="post" enctype="multipart/form-data" action="'. $_SERVER['PHP_SELF'] .'">
        <input type="hidden" name="MAX_FILE_SIZE" value="'. $conf['maxUploadSize'] .'">
            MAX UPLOAD SIZE: '. bytesToHumanReadable($conf['maxUploadSize']) .'<br>
            <input type=file name="upfile"> 
	    UPLOAD KEY: <input type=password size="10" name="password" maxlength="10"><br>

            <input type=submit value="Up/Reload">
            <input type=reset value="Cancel"><br>
            <small>Allowed extensions: css</summary></small>
        </form>
        ';
}


/* file is uploading */
if(isset($_FILES['upfile'])){
    userUploadedFile();
    die();
}


function userUploadedFile(){
    global $conf;

    if($_FILES["upfile"]['size'] <= 0){
        drawErrorPageAndExit('Please select a file.');
    }
    if($_FILES["upfile"]['size'] > $conf['maxUploadSize']){
        drawErrorPageAndExit('File is too big.');
    }
    
    //check if password is correct
    if($_POST['password'] != $conf['adminPassword']) {
    	drawErrorPageAndExit('Incorrect password');
    }
    
    $fullFileName = $_FILES["upfile"]["name"];
    $fileInfo = pathinfo($fullFileName);

    $fileName = $fileInfo['filename'];
    $fileExtension = strtolower($fileInfo['extension']);

    if($fileExtension != 'css'){
        drawErrorPageAndExit("Invalid extension","file can not be uploaded with that extension");
    }
   
    rename($_FILES['upfile']['tmp_name'], 'csrc/custom.css');
    chmod('csrc/custom.css', 0644);

    drawMessageAndRedirectHome('Custom CSS uploaded!');
}

drawHeader();
drawUploadForm();
drawFooter();
