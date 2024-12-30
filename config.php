<?php
/* MAIN CONFIGURATION FILE FOR WAROTA.PHP*/

//Paths
define('ROOTPATH', './'); // main path for project


/*
 *  the reason for configs like this is it makes it really easy to dump this table into a web veiw
 *  or update them and save them with out having to ssh in and edit it manualy. there is no varable export for using defines.
 */

return $conf = [
    'boardTitle' => 'Everything',
    'boardSubTitle' => 'Home for your files',
    'home' => "https://cgi.heyuri.net/goatse/",
    'adminPassword' => "lolpenis",
    'logUserIP' => false,
    'mainScript' => 'warota.php',

    'timeZone' => 'UTC',        // timezone
    'logFile' => "souko.log",   // name of flat file
    'uploadDir' => "src/",      // upload location (slash is required).
    'thumbDir' => "thmb/",	    // thumbnail directory
    'prefix' => "up",           // prefix to add in front of your file name. Don't change after setting
    'coolDownTime' => 5,        // time in seconds untill can be uploaded to again. (set to -1 for no cool down)

    'defaultComment' => 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!',  // default comment for upload
    'maxAmountOfFiles' => 200,              // max files allowed on server
    'maxTotalSize' => 200*1024*1024*1024,   // total sized allowed in bytes
    'filesPerListing' => 5,                 // how many files listed per page
    'maxUploadSize' => 20*1024*1024,        // max upload size in bytes
    'commentRequired' => true,              // comment is requires or not
    'maxCommentSize' => 128,                // max comment length
    'deleteOldestOnMaxFiles' => false,      // delete oldest file if user uploads when maxxed out.
    'thumbnailExtention' => 'jpg',

    'denylist' => ['0.0.0.0'],    //IPs that are blocked from uploading but can still view the rest of the page | DON'T LEAVE BLANK
    'hardBanList' => ['0.0.0.0'], //IPs in here will recieve an error message when attempting to load the page => cannot interact at all | DON'T LEAVE BLANK

    'allowedExtensions' =>  [
        'dat','htm','torrent','deb','lzh','ogm','doc','class','js','swift','cc','tga','ape','woff2','cab','whl','mpe',
        'rmvb','srt','pdf','xz','exe','m4a','crx','vob','tif','gz','roq','m4v','gif','rb','3g2','m4a','rvb','sid','ai',
        'wma','pea','bmp','py','mp4','m4p','ods','jpeg','command','azw4','otf','ebook','rtf','ttf','mobi','ra','flv','ogv',
        'mpg','xls','jpg','mkv','nsv','mp3','kmz','java','lua','m2v','deb','rst','csv','pls','pak','egg','tlz','c','cbz',
        'xcodeproj','iso','xm','azw','webm','3ds','azw6','azw3','cue','kml','woff','zipx','3gp','po','mpa','mng','wps',
        'wpd','a','s7z','ics','tex','go','ps','org','yml','msg','xml','cpio','epub','docx','lha','flac','odp','wmv','vcxproj',
        'mar','eot','less','asf','apk','css','mp2','odt','patch','wav','msi','rs','gsm','ogg','cbr','azw1','m','dds','h',
        'dmg','mid','psd','dwg','aac','s3m','cs','cpp','au','aiff','diff','avi','bat','html','pages','bin','txt','rpm',
        'm3u','max','vcf','svg','ppt','clj','png','svi','tiff','tgz','mxf','7z','drc','yuv','mov','tbz2','bz2','gpx','shar',
        'xcf','dxf','jar','qt','tar','xpi','zip','thm','cxx','3dm','rar','md','scss','mpv','webp','war','pl','xlsx','mpeg',
        'aaf','avchd','mod','rm','it','wasm','el','eps','nes','smc','sfc','md','smd','gen','gg','z64','v64','n64','gb','gbc',
        'gba','srl','gcm','gcz','nds','dsi','wbfs','wad','cia','3ds','ngp','ngc','pce','vb','ws','wsc','dsv','sav','ps2',
        'mcr','mpk','eep','st0','dta','srm','afa','zpaq','arc','paq','lpaq','swf','pdn','lol','php','sh','img','ico','asc',
        'm2ts', 'nzb', 'appimage', 'json'
    ],
    'extentionsToBeConvertedToText' => [
        'htm','mht','cgi','php','html','sh','shtml','xml','svg'
    ],
    'defaultCookieValues' => [
        'showDeleteButton' => 'checked',
        'showComment' => 'checked',
        'showPreviewImage' => '',
        'showFileSize' => 'checked',
        'showMimeType' => ''
    ],
];
