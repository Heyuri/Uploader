<?php
/*
 *  these are the defualt configs for each new board. please set what you want user boards to have here.
 *  please note some things users can over write. 
 *  check the newBoard() funciton in admin.php to get a idea of what can be over written
 */

return $conf = [
    'boardTitle' => 'fileUploader',
    'boardSubTitle' => 'for long term storage of bigger files',
    'home' => "https://up.example.com",
    'cssFile' => '../../styles.css',
    'adminPassword' => "lolpenis",
    'deletionPassword' => "123",    // this is the password for delete a board
    'logUserIP' => true,
    'boardListed' => true,

    'timeZone' => 'UTC',            // timezone
    'logFile' => "userPosts.block", // name of file that will hold each post's data (in your webserver settings. disallow acsses to anyting with *.block)
    'uploadDir' => "src/",          // upload location (slash is required).
    'prefix' => "",                 // prefix to add in front of your file name. Don't change after setting
    'coolDownTime' => 10,           // time in seconds untill can be uploaded to again. (set to -1 for no cool down)

    'maxAmountOfFiles' => 300,              // max files allowed on server
    'maxTotalSize' => 200*1024*1024*1024,   // total sized allowed in bytes (defualt is 20gb)
    'filesPerListing' => 10,                // how many files listed per page
    'maxUploadSize' => 20*1024*1024,        // max upload size in bytes (defualt is 20mb, make sure you change php.ini and your webserver to allow higher limits)
    
    'commentRequired' => true,              // comment is requires or not
    'maxCommentSize' => 128,                // max comment length
    'deleteOldestOnMaxFiles' => false,      // delete oldest file if user uploads when maxxed out (bug, if you lower max files with this on. uploads will fail and delete last post)
    'defaultComment' => '',                 // default comment tfilled into the form (wont auto fill empty comment)

    'pageBarOnBottom' => true,              // if there should be a paging on the bottom as well
    'allowDrawUsage' => true,               // will draw usage if set
    'allowDrawDateUploaded' => true,        // set to false to not let others see when a file was uploaded
    'allowDrawOriginalName' => true,        // set to false to not let others see what the original file name was

    'denylist' => ['0.0.0.0'],      //IPs that are blocked from uploading but can still view the rest of the page | DON'T LEAVE BLANK
    'hardBanList' => ['0.0.0.0'],   //IPs in here will recieve an error message when attempting to load the page => cannot interact at all | DON'T LEAVE BLANK

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
    'extentionsToBeConvertedToText' => [ // keep these defualts or you risk remote code execution.
        'htm','mht','cgi','php','html','sh','shtml','xml','svg'
    ],
    'defualtCookieValues' => [// leve it either checked or empty string
        'showDeleteButton' => 'checked',
        'showComment' => 'checked',
        'showFileSize' => 'checked',
        'showMimeType' => '',
        'showImagePreview' => 'checked',
        'showOriginalName' => 'checked',
        'showDateUploaded' => 'checked',
    ],
];

