<?php
namespace HeyuriUploader\Functions;

function sanitizeComment($comment): string {
	return htmlspecialchars(str_replace(array("\0", "\t", "\r", "\n", "\r\n"), "", $comment));
}