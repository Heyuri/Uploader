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
                    echo '[<a href="boards/'.$conf['boardURL'] .'">'.$conf['boardURL'] .'</a>]<br>';
                }
            }
        }
    }
}

function linkBack(){
    $conf = require_once 'config.php';
    if($conf['linkBack']){
        echo '[<a href='.$conf['linkBack'].'>back</a>]<br>';
    }
}

?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=0.47">
        <title>uploader</title>
    </head>
    <body alink="#ff0000" link="#ffff00" text="#00ff00" vlink="#0000ff" background="static/bg.png">
        <center>
        <img src="static/title.png" alt="logo" height="100" width="384"><br>
        this is a file sharing website where users can create there own upload board.<br>
        there is also anonymous fetures that disabled logging at php level<br>
        <?php linkBack(); ?>
        <table border="1"><tbody><tr><td>
            Boards:<br>
            <?php drawBoardListing(); ?>
            <br>
            board creation:<br>
            <a href="newboard.php">creation form</a><br>
        </td></tr></tbody></table>
        <p>
            <a href="https://github.com/Heyuri/Uploader">Uploader</a>
        </p>
        </center>
    </body>
</html>