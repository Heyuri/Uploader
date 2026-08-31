<?php
// Boards live in subdirectories of this one; the listing itself is a route on
// the main script.
$conf = require dirname(__DIR__) . '/config.php';

header('Location: ../' . $conf['mainScript'] . '?request=boards');
