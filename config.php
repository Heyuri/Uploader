<?php
/* MAIN CONFIGURATION FILE FOR WAROTA.PHP / WAROTA.PHP のメイン設定ファイル */

# English explanation / 日本語説明

return $conf = [
    'boardTitle' => 'Everything',        // main title displayed on the page / ページ上部に表示されるタイトル
    'boardSubTitle' => 'Home for your files',    // subtitle shown below the title / タイトル下に表示されるサブタイトル
    'staticUrl' => 'static/',            // static files path / 静的ファイルのパス
    'home' => "https://cgi.heyuri.net/goatse/",  // URL used for the [Home] link / [Home]リンクのリンク先URL
    'adminPassword' => "lolpenis",       // admin password (change in production) / 管理者パスワード（本番環境では必ず変更すること）
    'mainScript' => 'warota.php',        // main application script filename (can be renamed if desired) / メインスクリプトのファイル名（必要に応じて変更可能）

    'timeZone' => 'UTC',          // timezone / タイムゾーン
    'logFile' => "souko.log",     // name of flat file (found in data/) / ログファイル名（data/ 内）
    'counterFile' => "count.log", // name of counter file / アップロードカウンターファイル
    'uploadDir' => "src/",        // upload directory (trailing slash required) / アップロード・ディレクトリ（末尾スラッシュ必須）
    'thumbDir' => "thmb/",	      // thumbnail directory / サムネイル・ディレクトリ
    'prefix' => "up",             // prefix added to uploaded filenames (do not change after deployment) / アップロードファイル名の接頭辞（設定後は変更しないこと）
    'thumb_suffix' => "_thumb",   // suffix appended to thumbnail filenames / サムネイルファイル名の接尾辞
    'coolDownTime' => 5,          // seconds before another upload is allowed (-1 disables cooldown) / 次のアップロードまでの待機秒数（-1で制限なし）

    'defaultTheme' => 'Futaba', // default CSS theme / デフォルトのCSSテーマ
    'language' => 'en',         // language file to load (lang/{language}.php) / 使用する言語ファイル（日本語の場合は ja）

    'defaultComment' => 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!',  // default comment for uploads / アップロード時に自動入力されるデフォルトコメント
    'maxAmountOfFiles' => 200,              // maximum number of uploaded files stored at once / 同時に保存できるアップロードファイルの最大数
    'maxUploadSize' => 20,                  // maximum upload size per file (MB) / 1ファイルあたりの最大アップロードサイズ（MB）
    'maxTotalSize' => 204800,               // total size limit of all stored files in megabytes / 保存されている全ファイルの合計サイズ上限（MB）
    'filesPerListing' => 30,                // number of files displayed per page / 1ページに表示するファイル数
    'chunkSize' => 200*1024*1024,           // chunk size in bytes for chunked uploads (must be under server upload limit, e.g. nginx client_max_body_size) / 分割アップロードのチャンクサイズ（バイト）。サーバーのアップロード制限（例: nginx client_max_body_size）以下にすること
    'commentRequired' => true,              // whether a comment is required when uploading / アップロード時にコメントを必須にするか
    'maxCommentSize' => 400,                // maximum comment length / コメントの最大文字数
    'deleteOldestOnMaxFiles' => false,      // delete the oldest file if the maximum number of files is reached / 最大ファイル数に達した場合、最も古いファイルを削除する
    'thumbnailExtension' => 'jpg',          // file extension used for generated thumbnails / 生成されるサムネイルの拡張子
    'forceJapaneseForJpUsers' => true,    // force Japanese language for users with Japanese browser settings / 日本語のブラウザ設定を持つユーザーに日本語を強制する

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
    ], // allowed file extensions for uploads / アップロードを許可するファイル拡張子一覧

    'extensionsToBeConvertedToText' => [
        'htm','mht','cgi','php','html','sh','shtml','xml','svg', 'py'
    ], // extensions that should be rendered as text / テキストとして表示する拡張子
    
    'defaultCookieValues' => [
        'showDeleteButton' => 'checked',  // show delete button / 削除ボタンを表示
        'showComment' => 'checked',       // show comment text / コメントを表示
        'showPreviewImage' => '',         // show preview thumbnail / プレビュー画像（サムネイル）を表示
        'showFileName' => 'checked',      // show original file name / 元ファイル名を表示
        'showFileSize' => 'checked',      // show file size / ファイルサイズを表示
        'showMimeType' => '',             // show MIME type / MIMEタイプを表示
        'showDate' => 'checked'           // show upload date / 投稿日時を表示
    ], // default UI display options stored in cookies / クッキーに保存される表示設定の初期値
];
