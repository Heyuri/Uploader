PHPぁぷろだ by ToR(http://php.s3.to)  
source by ずるぽん(http://zurubon.virtualave.net/)  
English translation & various modifications by Heyuri (https://www.heyuri.net/)

Heyuri updates (edition 20260326)  
  This uploader is a custom version of PHPぁぷろだ.  
  Many thanks to ずるぼん-sama for the original source and レッツPHP-sama for the PHP conversion.  
  The last update before Heyuri took over was Yakuba modifications (edition 20090922).

## Terms and Conditions
- We give no guarantees on its operation. Don’t cry if anything bad happens!
- Commercial use is allowed, but do not use it for illegal purposes.
- You are free to redistribute and modify. However, you can not remove the links.
- These rules are in accordance with レッツPHP-sama's standards...

## History
2001/08/30  
2001/09/04 v1.1 Cookies enabled for preferences, FTP transfer (deletion not yet works)  
2002/06/12 v1.2 Changed to move_uploaded_file (line 215)  
2002/07/23 v1.3 Some CSS measures for deletion (line 147)  
2002/08/06 v2.0 Slight changes in specifications (about allowed extensions, original file name display)  
2004/10/10 v2.2 Various fixes  
2005/01/10 v2.3 Removed line breaks  
2009/09/20 Revision   Major modifications commented by Yakuba
- Check if the log files etc exist.
- Display total size of the board
- Total capacity limit (cannot post if the limit is exceeded)
- Slightly adjusted the layout to resemble SnUploader
- Fixed a problem in certain environments where the log file disappears when the uploaded file is deleted and the log is empty

2009/09/22 Revision   Fixed a bug about forced extension conversions and F5'ing
- Forced conversion of specified extensions during upload.
- When the extension of a file is converted, display its original extension in its comment.
- Fixed a bug where the same operation was repeated if F5 was pressed immediately after the operation, such as uploading duplicate files.

2020/06/?? Nakura from Heyuri has partially translated it to English  
2024/04/20 v3.0 The software is uploaded to github and shared with Hachikuji and Penman, who started working on it to make major changes  
2024/05/17 Revision   Major changes were made to the Uploader's code
- Changed all deprecated PHP codes into modern ones
- English translation is completed
- It displays total board and file sizes in the proper storage units now
- Fixed the bug where it didn't check if the board's file size limit was exceeded
- Thumbnails implemented. Files larger than 1MB will get thumbnailed. Can be enabled from settings
- Brought back sam.php as images.php
- User boards (user/) are now an "extra" part of the software. People can create their own boards
- Fixed an issue where the server was getting into an error loop if log file didn't exist
- Configurations are now in a separate file. Main script doesn't need to be edited by default anymore (unless path of config.php is changed)
- User boards are now an "extra" part of the software. Users can create their own boards. They can have custom CSS for their boards too
- Configurable cooldown added against flooding
- It's now anonymous by default, but can have a setting to log IPs of uploaders
- If logging IPs, there are other settings to block IPs from viewing the board & uploading files
- Some default CSS fixes
- If user didn't enter any password for a file, only the administrator can delete the file

2024/05/19 v3.1 Fixed a bug about not loading if the user had invalid cookies<br>
2024/08/03 v3.2 IP bans can now work without logging setting turned on as well<br>
2024/10/08 v3.3 Fixed a bug where files could be deleted with empty password<br>
2024/12/31 v3.4 Fixed the bug about video thumbnails not getting deleted with videos<br>
2026/03/08 v4.0 Major code rework and mod tool implementations.<br>
2026/03/16 v4.1 Misc improvements including some front-end updates, Japanese language option added. A homepage for the software is created.<br>
2026/03/26 v4.2 Minor tweaks to table HTML and CSS.<br>
2026/07/07 v4.3 AJAX file uploading without a full page refresh, track own uploaded files with a button to copy their links to clipboard.

## Installation
```bash
git clone https://github.com/Heyuri/Uploader.git /var/www/sites/uploader.test/Uploader
```
### Permissions
Make sure all files in the owned by the web group (which is typically www-data or www). Which you can do by running `chown -R user:www /var/www/sites/uploader.test/Uploader`. You may want to make the owned user your personal user so uploading and editing files is easier.   

Run chmod on the following files. E.g (`chmod 755 warota.php` and so on)

- 755 warota.php 
- 775 config.php  - note: it uses 775 so its modifiable so the config editing mod tool can work
- 755 autoloader.php
- 755 code/
- run: `chmod -R 755 code`
- 775 data/ - note: the uploader creates its log and ban files in here, so it has to be writable
- 775 boards/ - note: the web user creates a directory in here for every user board

### Restricting access
`data/` contains sensitive information and shouldn't be visible to users. Each user board keeps its own `boards/<name>/data/`, so block those too.

#### httpd (openBSD)
Open /etc/httpd.conf In the server block for the site your uploader instance is on and paste the following location block into it:
```conf
location "/Uploader/data/*" {
  block
}

location "/Uploader/data/" {
  block
}

location match "/Uploader/boards/.*/data/.*" {
  block
}
```
#### nginx
Open your site conf file in `/etc/nginx/sites-available/` and add this to the server block:
```conf
location ~ ^/Uploader/data/ {
    deny all;
    return 403;
}

location ~ ^/Uploader/boards/[^/]+/data/ {
    deny all;
    return 403;
}
```
#### Apache
There is already a .htaccess file in data that will prevent accesses to data. The same file is written into every user board's data directory when the board is created.

### Configuration
After setting permissions, you shouldn't need to do much else however you should change the admin password. 

You can find the admin password in `config.php` under 'adminPassword'. 

You can configure the instance through a web UI by going to 'Admin room' on `warota.php`, entering the password, then going to 'Config' in the vertical list of admin pages.

Be aware that only simple integer values will appear, and that `config.php` may change after submitting an edit.

### Unlisted mode

Setting `unlisted` in `config.php` hides the file list from users. The index shows only the upload form, the catalog and search send visitors back to it, and their nav links go away. An upload then ends on a page with the uploader's own link on it rather than a redirect back to a listing, so a file is only reachable by someone who was given its URL.

Mod pages are unaffected — logged in, the whole list is still there. Boards inherit the switch.

### Temporary hosting

Setting `temporaryHosting` turns the uploader into a drop box: every upload is stored under a random name instead of a numbered one, is stamped with an expiry, and is deleted once that passes.

- `temporaryHosting` — turn it on
- `temporaryHostingHours` — how long an upload is kept
- `temporaryFileNameLength` — length of the random file name, 4 to 32

Names are random rather than derived from the file, so a re-upload after expiry never resurrects an old URL. Within the lifetime, though, uploading an identical file again hands back the entry that already exists instead of storing a second copy.

Files are swept lazily while the uploader serves requests, at most once a minute. That is enough for a busy instance; where files have to go on time regardless of traffic, run the sweep from cron instead:

```bash
php expireFiles.php              # the main uploader
php expireFiles.php <boardName>  # one user board
php expireFiles.php --all        # the main uploader and every board
```

Files that were already given an expiry keep it, so turning `temporaryHosting` back off does not rescue them.

### User boards

Users can run their own uploaders at `boards/<name>/`. Each board has its own file list, upload directory, thumbnails, counter, bans, and settings. But shares the instance's code, themes, languages and allowed extensions. Board owners can never change anything security-relevant.

The registry of boards is a flat file, `data/boards.log`, one `<>`-delimited line per board. It is created automatically the first time it is needed.

Relevant `config.php` keys:

- `allowUserBoards` — turn board creation on or off
- `boardsDir` — where boards live, `boards/` by default
- `maxUserBoards` — how many boards may exist at once
- `boardMaxAmountOfFiles`, `boardMaxUploadSize`, `boardMaxTotalSize` — per-board limits, applied to every board

**Board owners** log in at their board's *Admin room* with the password they chose at creation. They can delete files, ban posters and files from their own board, and change their board's name, description, default comment, listing, theme and password. They never see an uploader's IP address: every uploader is shown as a hash salted with a secret unique to that board, and bans are made by clicking a post rather than by typing an address.

**Theming** is the one thing an owner changes about how the board looks. A board can default to any of the instance's installed themes, or to a palette of its own: a fixed list of colours (background, text, links, accents, file list rows) and the file list's font size. Visitors still get the style selector and can pick another theme; the board's palette is simply the one it starts on, and joins the installed themes in the selector as *Custom*.

Owners set values, never CSS. Colours have to be hex and the font size a pixel count between 8 and 24; anything else is dropped, on save and again every time the page is drawn. A board cannot add a rule, load a font or an image, or otherwise put styling of its own choosing on the page.

**The instance admin** gets a *Boards* mod page listing every board with its size, file count, creator and state. From there a board can be listed/unlisted, locked (kept readable but closed to uploads), have its owner password reset, be deleted along with all of its files, or be moderated directly. Admins see real IPs on boards, and instance-wide IP and file-hash bans apply to every board.

### Action log

Uploads, deletions, bans, logins, board changes and config edits are recorded in `data/actions.log`, a `<>`-delimited flat file that is created automatically. Every user board keeps its own, next to its upload log, so a board's history lives with the board.

The admin's *Action log* mod page merges the instance's log with every board's, newest first, and can be narrowed to one kind of action. Every address in it is a link that narrows the log to that poster, matching whether they acted or were acted on, so following one shows everything the log has on them; the same links are on the addresses in the file listings. Board owners get the same page for their own board, where addresses appear as the same poster hashes they see everywhere else — including the address a ban was placed on.

Relevant `config.php` keys:

- `actionLog` — turn recording on or off
- `actionLogFile` — file name, `actions.log` by default
- `actionLogMaxEntries` — how many actions each log keeps; the oldest fall off the front once it is full

### Migrating boards made before 4.3

Older versions kept user boards in `user/boards/<name>/`, each a standalone copy of the uploader with its own `config.php`. `migrateUserBoards.php` converts those into the current layout. It only reads the old boards, so it is safe to run and re-run:

```bash
php migrateUserBoards.php --dry-run   # report what would happen
php migrateUserBoards.php             # do it
```

It can only be run from the command line — requesting it over the web returns 403. Pass `--source=path/to/user/boards` if the old boards aren't in the default place. Boards that are already registered, whose directory already exists, or whose name is reserved or unusable are reported and skipped, so a partial run can simply be run again.

Per board it creates `boards/<name>/` with the usual `src/`, `thmb/` and `data/`, copies the uploads across, renames thumbnails from the old `<prefix><id>_thumb.<ext>` to the current `<prefix><id>s.jpg`, seeds `data/count.log` from the highest ID in the log, turns the old `denylist` and `hardBanList` into the board's own ban list, and registers the board in `data/boards.log`. The old `defaultTheme` is kept as the board's theme when the instance still ships a theme by that name, and dropped otherwise.

Two things are rewritten rather than copied. The board's admin password and every per-file deletion password were stored in plain text; they are hashed on the way in, so the old passwords keep working but are no longer readable on disk. Everything else is carried over as-is.

Some old settings have no equivalent and are reported as they are dropped: `deletionPassword` (a board-wide deletion password), `passwordRequired`, and the per-board `maxAmountOfFiles` / `maxUploadSize` / `maxTotalSize`, which are now instance-wide `boardMax*` values. Per-board custom CSS (`csrc/custom.css`) is not carried over either. Old boards also mostly stored `1337` instead of an IP, since they defaulted to not logging them — those posts stay unattributable and can't be banned by poster.

Once the new boards look right, the old `user/` directory can be deleted.

The boards it creates belong to whoever ran it. If that is not the user your web server runs as, the new board will render fine but every upload, deletion and ban on it will fail, because the uploader cannot write its `data/`, `src/` and `thmb/`. Either run the migrator as that user, or hand the boards over afterwards:

```bash
chown -R www-data:www-data boards/<name>
```

### Cloudflare

If the uploader sits behind Cloudflare, deleted files can keep being served from the CDN cache until it expires. Setting these keys in `config.php` (or in the admin config page) makes the uploader purge the cache of a file and its thumbnail whenever the file is deleted:

- `cloudflareEnabled` — turn the integration on
- `cloudflareApiToken` — an API token with the *Zone -> Cache Purge* permission
- `cloudflareZoneId` — the zone ID of the domain serving the uploads (shown on the domain's overview page)
- `cloudflareBaseUrl` — public URL of the uploader root without a trailing slash, e.g. `https://files.example.com/up`. Leave empty to detect it from the current request

Purge failures are written to the PHP error log and never block a deletion.

## Cautions (it is recommended to check these)
- These variables in php.ini may need to be changed if you want to allow files larger than 2MBs to get uploaded:
  「upload_max_filesize」「post_max_size」「memory_limit」「max_execution_time」
- And these variables in php.ini may be related to uploading process itself:
  「file_uploads」「upload_tmp_dir」
- You can check your server's PHP settings with `<?php phpinfo(); ?>` (Some servers may not allow this)
- Make sure uploaded .php files (and other potentially dangerous extensions) are properly converted to .txt
- Hide the log files from displaying from internet with .htaccess, or change their default names so users don't know
