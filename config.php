<?php
/* MAIN CONFIGURATION FILE FOR WAROTA.PHP*/

//Paths
define('ROOTPATH', './'); // main path for project

$conf = [
    'boardTitle' => 'Everything',
    'boardSubTitle' => 'Home for your files',
    'home' => "https://cgi.heyuri.net/goatse/",
    'adminPassword' => "lolpenis",

    'timeZone' => 'UTC',        // timezone
    'logFile' => "souko.log",   // name of flat file
    'uploadDir' => "src/",      // upload location (slash is required).

    'maxAmountOfFiles' => 20,       // max files allowed on server
    'maxTotalSize' => 21474836480,  // total sized allowed in bytes
    'filesPerListing' => 20,         // how many files listed per page
    'maxUploadSize' => 20971520,    // max upload size in bytes
    'commentRequired' => true,      // comment is requires or not
    'maxCommentSize' => 128,        // max comment length

    'denylist' => ['0.0.0.0'],

    'prefix' => "", // front part of a file name
    'allowedExtensions' =>  ['dat','htm','torrent','deb','lzh','ogm','doc','class','js','swift','cc','tga','ape','woff2','cab','whl','mpe','rmvb','srt','pdf','xz','exe','m4a','crx','vob','tif','gz','roq','m4v','gif','rb','3g2','m4a','rvb','sid','ai','wma','pea','bmp','py','mp4','m4p','ods','jpeg','command','azw4','otf','ebook','rtf','ttf','mobi','ra','flv','ogv','mpg','xls','jpg','mkv','nsv','mp3','kmz','java','lua','m2v','deb','rst','csv','pls','pak','egg','tlz','c','cbz','xcodeproj','iso','xm','azw','webm','3ds','azw6','azw3','cue','kml','woff','zipx','3gp','po','mpa','mng','wps','wpd','a','s7z','ics','tex','go','ps','org','yml','msg','xml','cpio','epub','docx','lha','flac','odp','wmv','vcxproj','mar','eot','less','asf','apk','css','mp2','odt','patch','wav','msi','rs','gsm','ogg','cbr','azw1','m','dds','h','dmg','mid','psd','dwg','aac','s3m','cs','cpp','au','aiff','diff','avi','bat','html','pages','bin','txt','rpm','m3u','max','vcf','svg','ppt','clj','png','svi','tiff','tgz','mxf','7z','drc','yuv','mov','tbz2','bz2','gpx','shar','xcf','dxf','jar','qt','tar','xpi','zip','thm','cxx','3dm','rar','md','scss','mpv','webp','war','pl','xlsx','mpeg','aaf','avchd','mod','rm','it','wasm','el','eps','nes','smc','sfc','md','smd','gen','gg','z64','v64','n64','gb','gbc','gba','srl','gcm','gcz','nds','dsi','wbfs','wad','cia','3ds','ngp','ngc','pce','vb','ws','wsc','dsv','sav','ps2','mcr','mpk','eep','st0','dta','srm','afa','zpaq','arc','paq','lpaq','swf','pdn','lol','php','sh','img','ico','asc', 'm2ts', 'nzb', 'appimage', 'json'],
    'extentionsToBeConvertedToText' => ['htm','mht','cgi','php','html','sh','shtml','xml','svg'],
    'defualtCookieValues' => ['showDeleteButton' => 'checked','showComment' => 'checked','showFileSize' => 'checked','showMimeType' => ''],
    'ip' => '1337',
];

// Which file information to display? ('checked' for it to be shown, '' for not shown)--------
  $f_act  = 'checked';          //ACT (Link for deleting files)
  $f_com  = 'checked';          //Comment
  $f_size = 'checked';          //Filesize
  $f_mime = '';                 //MIME type
  $f_date = '';                 //Date of upload
  $f_anot = 'checked';          //Open files in a new tab
  $f_orig = '';
  $secret = 'yuzuyu';           //Original filename


/* anti-flood module settings */
  $antiflood = false; //false -> disable anti-flood script | true -> enable anti-flood script
  $cooldown = 10; // anti-flood cooldown in SECONDS [WILL ONLY BE USED IF ANTIFLOOD SCRIPT IS ENABLED]

  /* ip check module settings [KEEP THIS MODULE DISABLED TO HAVE NO IP TRACKING] */
  $ipcheck = false; //false -> disable script | true -> enable script
  $banlist = array('0.0.0.0'); //list of banned IPs

  //temporary module manager
  $module_List = array(
	  'mod_antiflood' => ROOTPATH.'mod/antiflood.php',
	  'mod_ipcheck' => ROOTPATH.'mod/ipcheck.php'
  );
