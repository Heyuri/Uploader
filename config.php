<?php
/* MAIN CONFIGURATION FILE FOR WAROTA.PHP*/

//Paths
define('ROOTPATH', './'); // main path for project


// 基本設定-----------------------------------------------------------------
  $page_title   = 'Everything';          // Board title.
  $title_sub    = 'Home for your files'; // Board description.
  $logfile      = 'souko.log';           // Log file (You may want to change this or block direct access from internet)
  $logmax       = 5000;                  // Maximum amount of files that can be uploaded
  $limitb       = 30*1024*1024;           // 最大投稿容量(B)(＜upload_max_filesize ← 標準2B)
  $limitk       = $limitb / 1024;           // 最大投稿容量(KB)(＜upload_max_filesize ← 標準2KB)
  $max_all_flag = 1;                     // 総容量規制を使用する=1(未実装)
  $max_all_size = 200*1024*1024*1024;    // Total board capacity (in bytes). 200*1024*1024*1024B = 200GB.
  $denylist     = array('192.168.0.1','sex.com','annony');                          //アクセス拒否ホスト
  $updir        = './src/';              // File storage directory
  $prefix       = '';                    // Filename prefix (eg. set to "up" for filenames to be up001.txt, up002.jpg)
  $commax       = 250;                   // Maximum comment lenght (In bytes. It's half this value for fullwidth characters)
  $page_def     = 20;                    // Number of files to display per page.
  $admin        = 'adminpassword';       // Admin deletion password. You can delete any file using this as the PW. MAKE SURE TO CHANGE.
  $auto_link    = 0;                     // コメントの自動リンク（Yes=1;No=0);
  $last_time    = 0;                     // 同一IPからの連続投稿許可する間隔(分)(0で無制限)
  $last_file    = 'last.log';            // 連続投稿制限用ファイル(空ファイルで666)
  $count_look   = 0;                     // カウンタ表示(Yes=1,No=0)
  $count_file   = 'count.log';           // カウンタファイル(空ファイルで666)
  $count_start  = '2009/09/01';          // カウンタ開始日
  $sam_look     = 0;                     // 画像一覧表示(Yes=1,No=0)←img.php必須
  $arrowext     = array('dat','htm','torrent','deb','lzh','ogm','doc','class','js','swift','cc','tga','ape','woff2','cab','whl','mpe','rmvb','srt','pdf','xz','exe','m4a','crx','vob','tif','gz','roq','m4v','gif','rb','3g2','m4a','rvb','sid','ai','wma','pea','bmp','py','mp4','m4p','ods','jpeg','command','azw4','otf','ebook','rtf','ttf','mobi','ra','flv','ogv','mpg','xls','jpg','mkv','nsv','mp3','kmz','java','lua','m2v','deb','rst','csv','pls','pak','egg','tlz','c','cbz','xcodeproj','iso','xm','azw','webm','3ds','azw6','azw3','cue','kml','woff','zipx','3gp','po','mpa','mng','wps','wpd','a','s7z','ics','tex','go','ps','org','yml','msg','xml','cpio','epub','docx','lha','flac','odp','wmv','vcxproj','mar','eot','less','asf','apk','css','mp2','odt','patch','wav','msi','rs','gsm','ogg','cbr','azw1','m','dds','h','dmg','mid','psd','dwg','aac','s3m','cs','cpp','au','aiff','diff','avi','bat','html','pages','bin','txt','rpm','m3u','max','vcf','svg','ppt','clj','png','svi','tiff','tgz','mxf','7z','drc','yuv','mov','tbz2','bz2','gpx','shar','xcf','dxf','jar','qt','tar','xpi','zip','thm','cxx','3dm','rar','md','scss','mpv','webp','war','pl','xlsx','mpeg','aaf','avchd','mod','rm','it','wasm','el','eps','nes','smc','sfc','md','smd','gen','gg','z64','v64','n64','gb','gbc','gba','srl','gcm','gcz','nds','dsi','wbfs','wad','cia','3ds','ngp','ngc','pce','vb','ws','wsc','dsv','sav','ps2','mcr','mpk','eep','st0','dta','srm','afa','zpaq','arc','paq','lpaq','swf','pdn','lol','php','sh','img','ico','asc', 'm2ts', 'nzb', 'appimage', 'json');    //Allow extensions (these must be in lowercase or it will give an error)
    //許可拡張子 小文字（それ以外はエラー

  /* anti-flood module settings */ 
  $antiflood = false; //false -> disable anti-flood script | true -> enable anti-flood script
  $cooldown = 10; // anti-flood cooldown in SECONDS [WILL ONLY BE USED IF ANTIFLOOD SCRIPT IS ENABLED]

  /* ip check module settings [KEEP THIS MODULE DISABLED TO HAVE NO IP TRACKING] */
  $ipcheck = false; //false -> disable script | true -> enable script
  $banlist = array(''); //list of banned IPs

  // ▼Yakuba(設定追加)
  $b_changeext  = array('htm','mht','cgi','php','html','sh','shtml','xml','svg');
  $a_changeext  = 'txt';                // 強制変換後の拡張子
  $base_php     = 'warota.php';          // このファイル名
  $homepage_add = '../../';      // [Home]のリンク先(相対、絶対両方可能)
  // ▲Yakuba

// 項目表示（環境設定）の初期状態 (表示ならChecked 表示しないなら空)--------
  $f_act  = 'checked';          //ACT（削除リンク）
  $f_com  = 'checked';          //コメント
  $f_size = 'checked';          //ファイルサイズ
  $f_mime = '';                 //MIMEタイプ
  $f_date = '';          //日付け
  $f_anot = 'checked';                 //別窓で開く？
  $f_orig = '';
  $secret = 'yuzuyu';                 //元ファイル名

  //temporary module manager
  $module_List = array(
	  'mod_antiflood' => ROOTPATH.'mod/antiflood.php',
	  'mod_ipcheck' => ROOTPATH.'mod/ipcheck.php'
  );
