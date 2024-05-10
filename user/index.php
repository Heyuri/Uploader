<?php
function drawBoardListing(){
    // symlink black magic
    $boards = __DIR__ . "/boards";
    foreach (new DirectoryIterator($boards) as $fileInfo) {
        if ($fileInfo->isDir() && !$fileInfo->isDot()) {
            $boardName = $fileInfo->getFilename();
            $configFile = $boards . "/" . $boardName . "/config.php";
            if (file_exists($configFile)) {
                $conf = require $configFile;
                if($conf['boardListed']){
                    echo '<center><h2>[<a href="boards/'.$conf['boardURL'] .'">'.$conf['boardURL'] .'</a>]</h2></center><br>';
                }
            }
        }
    }
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
function drawHeader(){
    global $conf;

    echo '
    <html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="Berry" content="no">
    <meta name="ROBOTS" content="NOINDEX,NOFOLLOW">
    <meta http-equiv="cache-control" content="max-age=0">
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="0">
    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
    <meta http-equiv="pragma" content="no-cache">
    <meta name="robots" content="follow,archive">
    <title>uploader</title>
    </head>
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
    
    <table width="100%"><tbody><tr><td bgcolor="#eeaa88"><strong><font size="4">Boards</font></strong></td></tr></tbody></table>
    <a href="newboard.php">Create Board</a>';
}


drawHeader();
drawBoardListing();
drawFooter();
