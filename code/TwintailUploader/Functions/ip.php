<?php
namespace TwintailUploader\Functions;

/**
 * The client address, resolved safely.
 *
 * REMOTE_ADDR is the only value the client can't forge, so it is the default.
 * A proxy header (X-Forwarded-For / CF-Connecting-IP) is only trusted when the
 * request actually came from a proxy listed in $conf['trustedProxies'] — set
 * that to your reverse proxy / Cloudflare ranges, leave it empty otherwise.
 */
function getUserIP(): string {
	$remote = $_SERVER['REMOTE_ADDR'] ?? '';
	$trusted = $GLOBALS['TWINTAIL_TRUSTED_PROXIES'] ?? [];

	if (!empty($trusted) && ipInRanges($remote, $trusted)) {
		$cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
		if (filter_var($cf, FILTER_VALIDATE_IP)) {
			return $cf;
		}

		// Walk the forwarded chain from the right, skipping our own proxies,
		// and take the first address we didn't put there ourselves.
		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
		if ($forwarded !== '') {
			$parts = array_map('trim', explode(',', $forwarded));
			for ($i = count($parts) - 1; $i >= 0; $i--) {
				$ip = $parts[$i];
				if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
				if (ipInRanges($ip, $trusted)) continue;
				return $ip;
			}
		}
	}

	return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
}

/**
 * True when $ip matches any of $ranges, each a plain address or CIDR
 * (both IPv4 and IPv6).
 */
function ipInRanges(string $ip, array $ranges): bool {
	$packedIp = safeInetPton($ip);
	if ($packedIp === false) {
		return false;
	}

	foreach ($ranges as $range) {
		$range = trim($range);
		if ($range === '') continue;

		if (strpos($range, '/') === false) {
			if (safeInetPton($range) === $packedIp) {
				return true;
			}
			continue;
		}

		[$subnet, $bits] = explode('/', $range, 2);
		$packedSubnet = safeInetPton($subnet);
		// An empty or non-numeric prefix ("1.2.3.4/") must not be read as /0 —
		// that would match every address and silently trust everything.
		if ($packedSubnet === false || strlen($packedSubnet) !== strlen($packedIp)
			|| !ctype_digit($bits)) {
			continue;
		}
		$bits = (int) $bits;
		// A prefix wider than the address (e.g. a /33 on IPv4) would read past
		// the end of the packed bytes in bitsMatch.
		if ($bits > strlen($packedIp) * 8) {
			continue;
		}

		if (bitsMatch($packedIp, $packedSubnet, $bits)) {
			return true;
		}
	}

	return false;
}

/**
 * inet_pton() that never throws. A null byte in the argument raises a
 * ValueError in PHP 8 (the @ operator only suppresses the warning path), which
 * would otherwise bubble up as an uncaught 500 on attacker-supplied input.
 *
 * @return string|false the packed address, or false for anything invalid
 */
function safeInetPton(string $address) {
	if ($address === '' || strpos($address, "\0") !== false) {
		return false;
	}
	try {
		return @inet_pton($address);
	} catch (\ValueError $e) {
		return false;
	}
}

/** Compares the first $bits of two packed (inet_pton) addresses. */
function bitsMatch(string $a, string $b, int $bits): bool {
	$bytes = intdiv($bits, 8);
	if ($bytes > strlen($a)) {
		return false;
	}
	if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
		return false;
	}

	$remainder = $bits % 8;
	if ($remainder === 0) {
		return true;
	}

	$mask = ~((1 << (8 - $remainder)) - 1) & 0xFF;
	return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
}
