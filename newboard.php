<?php
function newBoard($name, $subName, $adminPassword, $delitionPassword, $anonimize, $commentRequired, $autoDeleteOldest, $boardListed, $defaultComment=""){
    if (!is_dir(__DIR__ . '/boards/')) {
        mkdir(__DIR__ . '/boards/', 0755);
    }
    if (file_exists(__DIR__ . "/boards/".$name)){
        drawErrorPageAndExit("this board already exist");
    }
    mkdir(__DIR__ . '/boards/'.$name);
    mkdir(__DIR__ . '/boards/'.$name.'/src');
    symlink(__DIR__ . "/base.php", __DIR__ . '/boards/'.$name.'/index.php');
    //symlink(__DIR__ . "/mod.php", __DIR__ . '/boards/'.$name.'/mod.php');
    //copy(__DIR__ . "/debug.php", __DIR__ . '/boards/'.$name.'/debug.php');

    touch(__DIR__ . '/boards/'.$name.'/md5.block');
    touch(__DIR__ . '/boards/'.$name.'/userPosts.block');

    $conf = require_once "config.php";
    // these configs cant be changes after the board is created
    // somconfigs only admins can change
    $conf['boardTitle'] = $name;
    $conf['boardSubTitle'] = $subName;
    $conf['adminPassword'] = $adminPassword;
    $conf['deletionPassword'] = $delitionPassword;
    $conf['boardListed'] = $boardListed;
    if($anonimize){
        // since we cant ban the user, we will make it's cool down longer
        $conf['coolDownTime'] = $conf['coolDownTime'] + 5;
        $conf['logUserIP'] = false;
        $conf['allowDrawDateUploaded'] = false;
        $conf['allowDrawOriginalName'] = false;
    }
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

    if (file_put_contents(__DIR__ . '/boards/'.$name.'/config.php', $newConf) === false) {
        drawErrorPageAndExit("Failed to write configuration. contact the admin");
    }
    header('Location: boards/'.$name.'/index.php');
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
    <link rel="stylesheet" type="text/css" href="styles.css" />
    <title>uploader</title>
    </head>
    <body bgcolor="#ffffee" text="#800000" link="#0000ee" alink="#5555ee" vlink="#0000ee">';
}
function userSubmitedBoard(){
    // Check all required fields are present
    $requiredFields = ['name', 'subName', 'adminPassword', 'delitionPassword'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            die("Error: All fields except Default Comment are required.");
        }
    }
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $_POST['name'])) {
        // Redirect back to form with error message if validation fails
        $errorMessage = "Name can only contain letters, numbers, dashes, and underscores.";
        drawErrorPageAndExit("$errorMessage");
        exit; // Stop further execution of the script
    }

    // Validate the 'name'
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $_POST['name']) || strlen($_POST['name']) > 50) {
        $errorMessage = "Name can only contain alphanumeric characters, dashes, underscores and must be no longer than 16 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    // Sanitize and check lengths of other fields
    $subName = strip_tags($_POST['subName']);
    if (strlen($subName) > 50) {
        $errorMessage = "Sub Name must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $adminPassword = strip_tags($_POST['adminPassword']);
    if (strlen($adminPassword) > 50) {
        $errorMessage = "Admin Password must be no longer than 50 characters.";
        drawErrorPageAndExit("$errorMessage");
        exit;
    }

    $delitionPassword = strip_tags($_POST['delitionPassword']);
    if (strlen($delitionPassword) > 50) {
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


    $name = $_POST['name'];
    // Strip HTML tags from other inputs
    $subName = strip_tags($_POST['subName']);
    $adminPassword = $_POST['adminPassword'];
    $delitionPassword = $_POST['delitionPassword'];
    $defaultComment = isset($_POST['defaultComment']) ? strip_tags($_POST['defaultComment']) : '';

    // Validate booleans
    $commentRequired = isset($_POST['commentRequired']) ? filter_var($_POST['commentRequired'], FILTER_VALIDATE_BOOLEAN) : false;
    $anonimize = isset($_POST['anonimize']) ? filter_var($_POST['anonimize'], FILTER_VALIDATE_BOOLEAN) : false;
    $autoDeleteOldest = isset($_POST['autoDeleteOldest']) ? filter_var($_POST['autoDeleteOldest'], FILTER_VALIDATE_BOOLEAN) : false;
    $boardListed = isset($_POST['boardListed']) ? filter_var($_POST['boardListed'], FILTER_VALIDATE_BOOLEAN) : false;

    newBoard($name, $subName, $adminPassword, $delitionPassword, $anonimize, $commentRequired, $autoDeleteOldest,$boardListed, $defaultComment);
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
<body alink="#ff0000" link="#ffff00" text="#00ff00" vlink="#0000ff" background="static/bg.png">
    <center>
    <h1>new Board</h1>
    <form action="admin.php" method="post">
        <input type="hidden" name="action" value="newBoard">
        <table border="1"><tbody>
        <tr>
            <td><label for="name">board name(this will be used of the url):</label></td>
            <td><input type="text" id="name" name="name" required maxlength="16"></td>
        </tr>
        <tr>
            <td><label for="subName">board discripton:</label></td>
            <td><input type="text" id="subName" name="subName" required maxlength="50"></td>
        </tr>
        <tr>
            <td><label for="adminPassword">board's admin password:</label></td>
            <td><input type="password" id="adminPassword" name="adminPassword" required maxlength="50"></td>
        </tr>
        <tr>
            <td><label for="delitionPassword">board deletion Password:</label></td>
            <td><input type="password" id="delitionPassword" name="delitionPassword" required maxlength="50"></td>
        </tr>
        <tr>
            <td><label for="defaultComment">Default Comment:</label></td>
            <td><input type="text" id="defaultComment" name="defaultComment" maxlength="128"></td>
        </tr>
        <tr>
            <td><label for="anonimize">make board fully anonyuse</label></td>
            <td><input type="checkbox" id="anonimize" name="anonimize"></td>
        </tr>
        <tr>
            <td><label for="commentRequired">required a comment to post:</label></td>
            <td><input type="checkbox" id="commentRequired" name="commentRequired"></td>
        </tr>
        <tr>
            <td><label for="boardListed">is board listed:</label></td>
            <td><input type="checkbox" id="boardListed" name="boardListed"></td>
        </tr>
        <tr>
            <td><label for="autoDeleteOldest">auto delete oldest post:</label></td>
            <td><input type="checkbox" id="autoDeleteOldest" name="autoDeleteOldest"></td>
            <td><input type="submit" value="Submit"></td>   
        </tr>
        </tbody>
        </table>
    </form>
    </center>
</body>
</html>