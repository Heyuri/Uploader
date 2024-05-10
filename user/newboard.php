<?php
function newBoard($url, $name, $subName, $adminPassword, $deletionPassword, $commentRequired, $autoDeleteOldest, $boardListed, $defaultComment="", $filePrefix="", $passwordRequired=false){
    if (!is_dir(__DIR__ . '/boards/')) {
        mkdir(__DIR__ . '/boards/', 0755);
    }
    if (file_exists(__DIR__ . "/boards/".$url)){
        drawErrorPageAndExit("this board already exist");
    }
    mkdir(__DIR__ . '/boards/'.$url);
    mkdir(__DIR__ . '/boards/'.$url.'/src');
    mkdir(__DIR__ . '/boards/'.$url.'/csrc');
    
    file_put_contents(__DIR__ . '/boards/' . $url . '/index.php', '<?php require_once \'../../templates/base.php\'; ?>' . PHP_EOL);
    file_put_contents(__DIR__ . '/boards/' . $url . '/images.php', '<?php require_once \'../../templates/images.php\'; ?>' . PHP_EOL);
    file_put_contents(__DIR__ . '/boards/' . $url . '/css.php', '<?php require_once \'../../templates/css.php\'; ?>' . PHP_EOL);	
    //symlink(__DIR__.'/templates/base.php', __DIR__ . '/boards/'.$url.'/index.php');
    //symlink(__DIR__.'/templates/images.php', __DIR__ . '/boards/'.$url.'/images.php');
    //symlink(__DIR__.'/templates/css.php', __DIR__ . '/boards/'.$url.'/css.php');
      //symlink(__DIR__ . "/mod.php", __DIR__ . '/boards/'.$url.'/mod.php');
      //copy(__DIR__ . "/debug.php", __DIR__ . '/boards/'.$url.'/debug.php');

    touch(__DIR__ . '/boards/'.$url.'/md5.block');
    touch(__DIR__ . '/boards/'.$url.'/userPosts.block');
    touch(__DIR__ . '/boards/'.$url.'/csrc/custom.css');
    $conf = require_once __DIR__.'/templates/config.php';
    
    mkdir(__DIR__ . '/boards/'.$url.'/'.$conf['thumbDir']);
    // these configs cant be changes after the board is created
    // somconfigs only admins can change
    $conf['mainScript'] = 'index.php';
    $conf['boardURL'] = $url;
    $conf['boardTitle'] = $name;
    $conf['boardSubTitle'] = $subName;
    $conf['adminPassword'] = $adminPassword;
    $conf['deletionPassword'] = $deletionPassword;
    $conf['boardListed'] = $boardListed;
    $conf['prefix'] = $filePrefix;
    $conf['passwordRequired'] = $passwordRequired;
    
    $conf['coolDownTime'] = $conf['coolDownTime'] + 5;
    $conf['logUserIP'] = false;
    $conf['allowDrawDateUploaded'] = false;
    $conf['allowDrawOriginalName'] = false;
    
    if($autoDeleteOldest){
        $conf['deleteOldestOnMaxFiles'] = true;
        $conf['maxAmountOfFiles'] = $conf['maxAmountOfFiles'] + 200;
    }
    if($commentRequired){
        $conf['commentRequired'] = $commentRequired;
        $conf['defaultComment'] = "";
    }else{
        $conf['commentRequired'] = $commentRequired;
        $conf['defaultComment'] = $defaultComment;
    }

    $newConf = '<?php return ' . var_export($conf, true) . ';';

    if (file_put_contents(__DIR__ . '/boards/'.$url.'/config.php', $newConf) === false) {
        drawErrorPageAndExit("Failed to write configuration. contact the admin");
    }
    header('Location: boards/'.$url.'/index.php');
    exit;
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
function drawFooter(){
    echo '
    <br>
    <h5 align="right">
        <a href="https://github.com/Heyuri/Uploader/">Heyuri x nashikouen</a> + <a href="http://zurubon.strange-x.com/uploader/">ずるぽんあぷろだ</a> + <a href="http://php.s3.to/">ﾚｯﾂ PHP!</a> + <a href="http://t-jun.kemoren.com/">隠れ里の村役場</a><BR>
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
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">';
}
function userSubmitedBoard(){
    // Check all required fields are present
    $requiredFields = ['url', 'name', 'subName', 'adminPassword', 'deletionPassword'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            die("Error: All fields except Default Comment are required.");
        }
    }
    // Sanitize and check lengths of other fields
    $name = strip_tags($_POST['name']);
    if (strlen($name) > 32) {
        $errorMessage = "Name must be no longer than 32 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $url = strip_tags($_POST['url']);
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $_POST['url']) || strlen($_POST['url']) > 16) {
        $errorMessage = "url can only contain alphanumeric characters, dashes, underscores and must be no longer than 16 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    // Sanitize and check lengths of other fields
    $subName = strip_tags($_POST['subName']);
    if (strlen($subName) > 256) {
        $errorMessage = "Sub Name must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $adminPassword = strip_tags($_POST['adminPassword']);
    if (strlen($adminPassword) > 16) {
        $errorMessage = "mod Password must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $deletionPassword = strip_tags($_POST['deletionPassword']);
    if (strlen($deletionPassword) > 16) {
        $errorMessage = "Deletion Password must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $defaultComment = isset($_POST['defaultComment']) ? strip_tags($_POST['defaultComment']) : '';
    if (strlen($defaultComment) > 128) {
        $errorMessage = "Default Comment must be no longer than 128 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $url = $_POST['url'];
    $name = $_POST['name'];
    // Strip HTML tags from other inputs
    $subName = strip_tags($_POST['subName']);
    $adminPassword = $_POST['adminPassword'];
    $deletionPassword = $_POST['deletionPassword'];
    $defaultComment = isset($_POST['defaultComment']) ? strip_tags($_POST['defaultComment']) : '';
    $filePrefix = isset($_POST['filePrefix']) ? preg_replace('/\s+/', '_', strip_tags($_POST['filePrefix'])) : '';
    // Validate booleans
    $commentRequired = isset($_POST['commentRequired']) ? filter_var($_POST['commentRequired'], FILTER_VALIDATE_BOOLEAN) : false;
    $autoDeleteOldest = isset($_POST['autoDeleteOldest']) ? filter_var($_POST['autoDeleteOldest'], FILTER_VALIDATE_BOOLEAN) : false;
    $boardListed = isset($_POST['boardListed']) ? filter_var($_POST['boardListed'], FILTER_VALIDATE_BOOLEAN) : false;
    $passwordRequired = isset($_POST['passRequired']) ? filter_var($_POST['passRequired'], FILTER_VALIDATE_BOOLEAN) : false;
    
    newBoard($url, $name, $subName, $adminPassword, $deletionPassword, $commentRequired, $autoDeleteOldest,$boardListed, $defaultComment, $filePrefix, $passwordRequired);
    exit;
}

if (isset($_POST["action"]) && $_POST["action"] == "newBoard") {
    userSubmitedBoard();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=0.47">
    <title>creation Form</title>
</head>
<body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">
    <center>
    <h1>New Board</h1>
    
    <form action="newboard.php" method="post">
        <input type="hidden" name="action" value="newBoard">
        <table border="1"><tbody>
        <tr>
            <td><label for="url">Board URL</label></td>
            <td><input type="text" id="url" name="url" required maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="name">Board name</label></td>
            <td><input type="text" id="name" name="name" required maxlength="32"></td>
        </tr>
        <tr>
            <td><label for="subName">Board descripton:</label></td>
            <td><textarea tabindex="6" maxlength="256" cols="48" rows="4" name="subName"></textarea></td>
        </tr>
        <tr>
            <td><label for="adminPassword">Board admin password:</label></td>
            <td><input type="password" id="adminPassword" name="adminPassword" required maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="deletionPassword">Board deletion Password:</label></td>
            <td><input type="password" id="deletionPassword" name="deletionPassword" required maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="defaultComment">Default Comment:</label></td>
            <td><input type="text" id="defaultComment" name="defaultComment" maxlength="128"></td>
        </tr>
	  <tr>
            <td><label for="filePrefix">File prefix:</label></td>
            <td><input  type="text" maxlength="20" name="filePrefix"></td>
        </tr>
        <tr>
            <td><label for="commentRequired">Required a comment to post:</label></td>
            <td><input type="checkbox" id="commentRequired" name="commentRequired"></td>
        </tr>
<tr>
            <td><label for="passRequired">Password required for upload:</label></td>
            <td><input type="checkbox" id="passRequired" name="passRequired"></td>
        </tr>

	<tr>
            <td><label for="boardListed">Listed:</label></td>
            <td><input type="checkbox" id="boardListed" name="boardListed"></td>
	</tr>
        <tr>
            <td><label for="autoDeleteOldest">Auto delete oldest post:</label></td>
            <td><input type="checkbox" id="autoDeleteOldest" name="autoDeleteOldest"></td>
            <td><input type="submit" value="Create Board"></td>   
        </tr>
        </tbody>
        </table>
    </form>
    </center>
</body>
</html>
