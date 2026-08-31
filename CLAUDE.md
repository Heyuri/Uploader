# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Twintail Uploader — a Heyuri fork of the classic Japanese imageboard uploader *PHPぁぷろだ*. Plain PHP web app: **no build step, no package manager, no test suite, no framework**. Edit PHP/JS/CSS/template files directly. Run locally with `php -S localhost:8000` from the repo root (entry point `warota.php`); uploads need `data/`, `src/`, `thmb/` writable with the log/counter files present (see README installation/`chmod` notes).

## Request lifecycle

`warota.php` → defines `ROOT_DIR`/`GLOBAL_DATA_DIR`, loads `config.php` (returns `$conf`), registers `autoloader.php` (PSR-4: `TwintailUploader\…` → `code/TwintailUploader/…`), includes `Functions/` via `include.php`, resolves the board being served (see below), `chdir()`s into it, defines `DATA_DIR`, then calls `requestHandler->handleRequest()`.

Routing is one `switch` on `$_REQUEST['request']` in [requestHandler.php](code/TwintailUploader/Classes/requestHandler.php) (default `index`); request names are the `REQUEST_*` constants. The constructor wires up all collaborators and passes them into per-request controllers. Adding a page = new constant + `case`.

Two route families differ:
- **HTML routes** echo `drawHeader()` → body → `drawFooter()` on `uploaderHTML`.
- **Chunk-upload routes** (`uploadChunk`, `finalizeChunkUpload`) are intercepted *before* any HTML/cookie handling and return **JSON only** — `display_errors` is forced off there so stray output can't corrupt the JSON. Never emit HTML on these paths.

## Data model — flat-file, no database

All upload metadata lives in one delimited log, `data/souko.log` (`$conf['logFile']`). One line per upload, fields joined by the literal `<>`:

```
id<>fileExtension<>comment<>ip<>time<>size<>mimeType<>password<>originalFileName<>storedName<>expiresTime<>fileHash
```

- **Field order is load-bearing** — [uploadEntry.php](code/TwintailUploader/Classes/uploadEntry.php) maps the exploded array positionally (the `$propertyMap` in its constructor). Changing order/count means migrating every existing line and updating `uploadEntry` + `logFile::writeDataToLogs`.
- **Newest entries are prepended** (first line = most recent, last = oldest).
- Comments and original file names containing `<>` are rewritten to `‹›`, and control chars (`\r \n \t \0`) are stripped, before saving — a newline would otherwise inject a second, attacker-controlled log line. Do the same for any new free-text field.
- IDs come from a separate `flock`-guarded counter `data/count.log` (`uploadEntryRepository::getNextID`), reconciled to `max(counter, highest ID in the log)` on every call so a stale-but-positive counter can't hand out an ID that already exists.
- The last three fields were added incrementally (`storedName`/`expiresTime` for temporary hosting, `fileHash` for its dedup) and are absent on older lines, which parse fine — `storedName` empty means "named after the ID", `expiresTime` 0/absent means "never expires", `fileHash` empty means "not deduped". Anything reading the log positionally must tolerate a short line (see `searchRepository::parseLogLine`, which accepts ≥9 fields and defaults the rest).
- Writes hold `flock(LOCK_EX)` — keep locking when editing `logFile.php`. `logFile::pruneEntries` is the locked read-filter-rewrite the expiry sweep goes through; `uploadEntryRepository::deleteDataFromLogByID` holds the lock across its whole read-modify-rewrite too.

Stored files are `<storedName>.<ext>` in `src/`, thumbnails in `thmb/`. `storedName` is `<prefix><3-digit-id>` (e.g. `up001.jpg`) normally and a random 8-char string under temporary hosting. Use the path builders on `uploadEntry` (`getFileName`/`getFilePath`/`getThumbPath`) rather than re-deriving paths. Bans are flat files too: `data/banlist.dat` (IPs) and `data/banned_hashes.dat` (SHA-256 of contents) via [banChecker.php](code/TwintailUploader/Classes/banChecker.php), which takes the directory to write and an optional wider directory it only reads.

## Action log

A third flat file, `data/actions.log`, records what happened rather than what is stored: uploads, deletions (by uploader, by mod, by rotation, by expiry), bans and unbans, logins/failed logins/logouts, board creation and management, and config saves. Field order is fixed by `actionLogRepository::FIELDS`:

```
time<>scope<>actor<>ip<>action<>target<>details
```

Unlike `souko.log` it is **appended** (oldest first) and **capped** at `$conf['actionLogMaxEntries']` — `addMany()` is one locked read-append-prune-rewrite, and `add()` is the single-entry case. Short lines parse fine, so a log written by an older version still reads.

- Every scope writes its own: a board records into `boards/<uri>/data/actions.log`, like its upload log and ban lists. The instance-wide *Action log* mod page merges them all through `actionLogController::getInstanceEntries()`, using the same `fileSource` list the recent files page uses.
- [actionLogController.php](code/TwintailUploader/Controllers/actionLogController.php) is the one recorder per request; the router hands it to the collaborators and calls `setActor()` once a mod session is authenticated (`user`/`admin`/`owner`, plus `system` via `recordSystem()`/`recordSystemBatch()` for sweeps and rotation). `forBoard()` returns a recorder writing into a board's own log — that is how instance-wide pages record something that happened *to* a board where its owner can see it. A board deletion is the exception: it goes in the instance log, since the board's own log dies with the directory.
- Free text goes through the same control-char-then-delimiter scrubbing `logFile` uses. **Addresses are stored raw and masked at render time**, exactly like the file listings: `uploaderHTML::drawActionLogPage()` runs the actor IP *and* the target through `maskAddress()`, so an owner never sees the address a ban was placed on. Keep that when adding fields that can hold an address.
- Adding an action = a constant on [actionLogEntry.php](code/TwintailUploader/Classes/actionLogEntry.php), an entry in its `ACTIONS` list (which drives the page's filter), and a label under `actionLog.actions` in **both** language files.

## User boards

A board is the *same* app pointed at a different directory — there is no second codebase. `boards/<uri>/index.php` is a generated stub that sets `$boardUri` and requires `warota.php`; that resolves the board from the registry and `chdir()`s into `boards/<uri>/`, so every relative path (`src/`, `thmb/`, `data/`) resolves inside the board **and** doubles as a URL relative to `boards/<uri>/index.php` — the same trick the main uploader relies on. Never rely on the process CWD being the repo root; use `ROOT_DIR`/`GLOBAL_DATA_DIR` for anything instance-wide.

The registry is another delimited log, `data/boards.log`, field order fixed by `boardRepository::FIELDS`:

```
id<>uri<>title<>subTitle<>ownerPasswordHash<>ipSalt<>createdTime<>creatorIp<>listed<>locked<>commentRequired<>defaultComment<>prefix<>theme<>customTheme
```

Unlike `souko.log`, boards are **appended** (oldest first) and the whole file is rewritten on every change. `theme` and `customTheme` were added last and are absent on older lines, which parse fine — `board` fills every field it isn't given with a default.

- [board.php](code/TwintailUploader/Classes/board.php) is the row object. `applyToConfig()` derives a board's `$conf` from the global one — it deliberately leaves `allowedExtensions`, `extensionsToBeConvertedToText`, `adminPassword` and `chunkSize` inherited, so nothing an owner controls is security-relevant. New per-board overrides belong there.
- [boardController.php](code/TwintailUploader/Controllers/boardController.php) owns everything touching a board directory (scaffolding, recursive delete, settings).
- **Theming is the one thing an owner does override, and it is variables, never CSS.** `theme` names an installed stylesheet from `static/css/themes/` (empty = the instance default) or the literal `Custom`; `customTheme` is that board's palette, packed as `name:value;name:value`. [themeManager.php](code/TwintailUploader/Classes/themeManager.php) is the whole contract: `VARIABLES` whitelists the custom properties and their types, `sanitizeVariables()` drops anything else and rebuilds each value from a strict match (hex colours, a pixel size between 8 and 24), and `generateCustomThemeStyle()` writes the `<style id="custom-theme">` block from what survived. Validation runs on save *and* again on every render, so a hand-edited `boards.log` is no more trusted than a form post — nothing can smuggle a rule, a `url()`, or a `</style>` onto the page. `resolveThemeName()` does the same for theme names, which reach the markup and the JS that builds theme URLs. A palette is base.css plus the instance default theme's variables plus the owner's, so the file theme is switched off (`media="not all"`) while it is showing and a partial palette is still a complete theme. Adding a variable means an entry in `VARIABLES` and a label under `theme.variables` in both language files.
- Auth: global admin is session key `mod_id`; board owners are `board_owner`, a list of URIs, so owning one board grants nothing on another. Both live on `sessionController`.
- [migrateUserBoards.php](migrateUserBoards.php) converts pre-4.3 boards (`user/boards/<uri>/`, each a standalone copy of the uploader) into this layout. It is CLI-only, reads the old boards without modifying them, and is safe to re-run. The old upload log used the same nine `<>` fields, but stored the board password and every per-file deletion password in plain text and named thumbnails `<prefix><id>_thumb.<ext>` — the script hashes the former and renames the latter. The old `config.php` is owner-controlled code, so it is **never `require`d**: `parseReturnedArray()` statically tokenises the `return [...]` literal and reads only scalars/arrays, evaluating nothing. Copied `src/` files are filtered against `allowedExtensions` (and dropped if in `extensionsToBeConvertedToText`) so a legacy `.php`/`.svg` can't become web-served.
- **Owners must never see an IP.** They get `board::hashIp()` — sha256 of a per-board salt + address, truncated. The `$hideIPs` flag threaded through `handleBoardAdmin` → `drawManageFilesPage`/`drawManageBansPage` → `buildTableRow` is what enforces this; ban links take a `fileID` and resolve the address server-side so no address ever reaches the page, and owner ban removals post hashes that `resolveHashedIPs()` maps back. Keep it that way when adding mod tools.

The visitor's display preferences are the one thing that is **not** `<>`-delimited: [cookieSettingsManager.php](code/TwintailUploader/Classes/cookieSettingsManager.php) stores them as a JSON object keyed by setting name. The set of settings is whatever `$conf['defaultCookieValues']` declares — add a key there and it is read, written and defaulted automatically. Values are normalised to `'checked'`/`''` on the way out, so a hand-edited cookie can't reach the markup. Legacy positional cookies are still decoded once and rewritten as JSON.

The deletion password the visitor uploads with rides in a second cookie of its own, [uploadPasswordCookie.php](code/TwintailUploader/Classes/uploadPasswordCookie.php) — free text can't live in the settings cookie, whose values are only `'checked'`/`''`. Both upload paths (`uploadedFileService::processFiles` and `chunkUploadService::finalizeUpload`) `remember()` it beside hashing it, and `uploaderHTML::drawUploadForm` prefills the field from it. It is `HttpOnly`, control chars and anything past 64 characters are stripped on write *and* on read, and an empty password is not a request to forget the stored one.

A visitor who has never uploaded gets one made for them: `ensure()` generates a random password from an alphabet with the lookalike characters left out, remembers it, and returns it, so the same one comes back on every later visit until they type their own. **It sets a cookie, so it has to run before any output** — `requestHandler` calls it in the `index` and `catalog` cases ahead of `drawHeader()`, and `drawUploadForm` only ever reads. Any new route that draws the upload form needs the same call. Because the password is generated rather than chosen, the field is `type="text"`: one you can't read is one you can't write down. (`remember()` no-ops on the `setcookie` once `headers_sent()`, so a mistimed call degrades instead of printing a warning into the markup.)

## Templating & i18n

No template engine: [HTMLRenderer.php](code/TwintailUploader/Classes/HTMLRenderer.php) resolves `{{key}}` placeholders in `.tpl` files in `templates/`, then strips any left unresolved. Substitution is a **single `strtr` pass** — inserted text is never re-scanned, so a user value that happens to contain `{{someKey}}` can't be resolved against another variable. **Callers must still escape** user data (`htmlspecialchars`/`$renderer->escape()`) before it reaches a template. Variables come from three places, later winning on collision: `addGlobal()` globals (the CSRF token — see Security), the flattened language strings, and the per-render array the `draw*()` methods on [uploaderHTML.php](code/TwintailUploader/Classes/uploaderHTML.php) pass.

Language files `lang/en.php` and `lang/ja.php` return nested arrays; [languageManager.php](code/TwintailUploader/Classes/languageManager.php) flattens them to dot keys (`errors.failedToDelete`) and injects all of them into every render as `{{lang.errors.failedToDelete}}`. **Add any new user-facing string to both files** under the matching key. `get()` supports `sprintf` args.

## Configuration

`config.php` returns the `$conf` array used everywhere. It's editable by hand or via the admin UI (`?request=admin&modPage=config`), which **rewrites the file through `requestHandler::writeConfig` and strips all comments**. Only scalar, already-existing keys are UI-writable (types coerced to the existing value) — a new UI-editable key must already exist in `config.php` with the right scalar type. `adminPassword` is plaintext; admin auth is session-based. Security-relevant lists: `allowedExtensions` (upload whitelist) and `extensionsToBeConvertedToText` (e.g. `php`/`html`/`svg` forced to serve as text).

Two switches change what the app *is*, and boards inherit both:

- `unlisted` hides the file list from users — index shows only the upload form, catalog and search redirect to it, their nav links go, and the chunk uploader's finalize stops returning `listingHtml`. An upload then ends on `upload-complete.tpl` with the uploader's own link instead of a redirect. Mod pages are unaffected: they are the only way to see the whole list.
- `temporaryHosting` (+ `temporaryHostingHours`, `temporaryFileNameLength`) stores uploads under a random name and stamps `expiresTime` on them. [temporaryHosting.php](code/TwintailUploader/Classes/temporaryHosting.php) owns naming, the sweep, and content dedup: it hashes each upload (`hashFile`) into the log's `fileHash` field and `findDuplicate()` hands an identical, still-live re-upload back as the same entry instead of writing a second copy. `requestHandler::handleRequest` calls `sweep()` on every request, throttled to once a minute by `data/expiry.stamp`, and [expireFiles.php](expireFiles.php) is the CLI/cron equivalent (`--all` forks one process per board, since `DATA_DIR` can only be defined once). Names are random rather than content-derived so a re-upload after expiry never resurrects the old URL. Entries expire on their own stored stamp, so turning the switch off does not save files that were already given one.

## Security model

A few cross-cutting protections are easy to break by accident — preserve them when touching auth, routing, or output:

- **Client IP.** `getUserIP()` ([Functions/ip.php](code/TwintailUploader/Functions/ip.php)) returns `REMOTE_ADDR` and only believes `X-Forwarded-For`/`CF-Connecting-IP` when `REMOTE_ADDR` is one of `$conf['trustedProxies']` (IPs or CIDRs, v4/v6; empty by default). This IP is the ban key, the flood key, the logged uploader, and the seed for `board::hashIp()` — never read the forwarded headers directly. `warota.php` publishes the trusted list into `$GLOBALS['TWINTAIL_TRUSTED_PROXIES']`.
- **CSRF.** Every state-changing admin/owner action carries a per-session token. `sessionController::getCsrfToken()`/`verifyCsrfToken()` (constant-time) hold it; `requestHandler::requireCsrf()` gates each mutating mod action (deletes, bans, board toggles/reset/delete, `saveConfig`, `saveSettings`). The token reaches the page via `uploaderHTML::setCsrfToken()` → `HTMLRenderer::addGlobal('csrfToken', …)`, so POST forms use `{{csrfToken}}` and GET action-links append `&csrfToken=`. Set the token whenever you add an admin page, and validate it on any new mutation. Public/anonymous flows (upload, login, board creation) are intentionally exempt so anonymous visitors don't get a session.
- **Sessions.** [session.php](code/TwintailUploader/Classes/session.php) sets `HttpOnly` + `SameSite=Lax` + `Secure` (when HTTPS) before `session_start()`, and `session::regenerate()` rotates the ID on privilege change (`logIn`/`logInBoard`) to defeat fixation.
- **Output & decode.** Callers escape before templating (see Templating); `thumbnailImage()` rejects images over ~50MP before decoding (decompression-bomb guard); the upload whitelist (`allowedExtensions`) and text-coercion list (`extensionsToBeConvertedToText`) are the only things standing between an upload and code execution, so both are among the keys `board::applyToConfig` refuses to let an owner override.

## Note on `おまけ/`

Legacy/optional extras, **not part of the main app flow**: `おまけ/locked/` holds the original pre-fork scripts. Changes to the main app don't affect these. (The old standalone `user/` multi-board experiment has been replaced by the user boards described above.)
