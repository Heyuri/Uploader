<?php
namespace TwintailUploader\Classes;


class themeManager {
	/** Name of the per-board palette; never a file in the themes directory. */
	public const CUSTOM_THEME = 'Custom';

	private const TYPE_COLOR = 'color';
	private const TYPE_FONT_SIZE = 'fontSize';

	/**
	 * The CSS custom properties a board owner may set, and how each value is
	 * checked.
	 *
	 * This list is the whole of what an owner can put on a page: values are
	 * validated on the way in *and* again on the way out, names outside the
	 * list are dropped, and nothing is ever interpolated into a stylesheet
	 * without passing isValidValue(). Adding an entry here means adding a
	 * label under theme.variables in both language files.
	 */
	public const VARIABLES = [
		'bg-color'               => self::TYPE_COLOR,
		'text-color'             => self::TYPE_COLOR,
		'link-color'             => self::TYPE_COLOR,
		'alink-color'            => self::TYPE_COLOR,
		'vlink-color'            => self::TYPE_COLOR,
		'accent-color'           => self::TYPE_COLOR,
		'secondary-color'        => self::TYPE_COLOR,
		'tablelisting-odd-row'   => self::TYPE_COLOR,
		'tablelisting-hover-row' => self::TYPE_COLOR,
		'red-text'               => self::TYPE_COLOR,
		'gray-text'              => self::TYPE_COLOR,
		'tablelisting-font-size' => self::TYPE_FONT_SIZE,
	];

	/** Last-resort values, so a custom palette always defines every variable */
	public const DEFAULT_VARIABLES = [
		'bg-color'               => '#ffffee',
		'text-color'             => '#800000',
		'link-color'             => '#0000ee',
		'alink-color'            => '#5555ee',
		'vlink-color'            => '#0000ee',
		'accent-color'           => '#007bff',
		'secondary-color'        => '#eeaa88',
		'tablelisting-odd-row'   => '#f7efea',
		'tablelisting-hover-row' => '#f0e0d6',
		'red-text'               => '#ef0a0a',
		'gray-text'              => '#888888',
		'tablelisting-font-size' => '12px',
	];

	private const MIN_FONT_SIZE = 8;
	private const MAX_FONT_SIZE = 24;

	private string $themesDir;
	private string $themesUrl;
	private array $themes = [];

	public function __construct(string $themesDir, string $themesUrl) {
		$this->themesDir = rtrim($themesDir, '/\\');
		$this->themesUrl = rtrim($themesUrl, '/');
		$this->loadThemes();
	}

	/**
	 * Load available theme names from CSS files in the themes directory
	 */
	private function loadThemes(): void {
		if (!is_dir($this->themesDir)) {
			throw new \Exception("Themes directory not found: {$this->themesDir}");
		}

		$files = glob($this->themesDir . '/*.css');
		foreach ($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			$this->themes[] = $name;
		}
	}

	/**
	 * Get all available theme names
	 */
	public function getThemeNames(): array {
		return $this->themes;
	}

	/**
	 * First installed theme, used wherever a name has to resolve to a real
	 * stylesheet — the custom palette has no file of its own.
	 */
	public function getFallbackThemeName(): string {
		return $this->themes[0] ?? '';
	}

	/**
	 * Coerces a stored or configured theme name to something that can safely
	 * be named in the markup: an installed theme, or the custom palette when
	 * the board actually has one. Anything else falls back, so a hand-edited
	 * boards.log can't put a path of its own choosing in front of the script
	 * that builds theme URLs.
	 */
	public function resolveThemeName(string $themeName, bool $customAvailable = false): string {
		if ($customAvailable && $themeName === self::CUSTOM_THEME) {
			return self::CUSTOM_THEME;
		}
		if (in_array($themeName, $this->themes, true)) {
			return $themeName;
		}
		return $this->getFallbackThemeName();
	}

	/**
	 * Get the URL for a theme CSS file. If not found, returns a random theme.
	 */
	public function getThemeUrl(string $themeName): string {
		if (in_array($themeName, $this->themes)) {
			return $this->themesUrl . '/' . $themeName . '.css';
		}
		$randomTheme = $this->themes[array_rand($this->themes)];
		return $this->themesUrl . '/' . $randomTheme . '.css';
	}

	/**
	 * Generate a <link> tag for the selected theme.
	 *
	 * $active is false while a custom palette is showing: the file theme stays
	 * in the page (the style selector switches back to it) but applies to no
	 * media, so only base.css and the palette's variables are in effect.
	 */
	public function generateThemeLink(string $themeName, bool $active = true): string {
		$url = htmlspecialchars($this->getThemeUrl($themeName), ENT_QUOTES, 'UTF-8');
		$media = $active ? '' : ' media="not all"';
		return '<link id="theme-style" rel="stylesheet" href="' . $url . '"' . $media . '>';
	}

	/**
	 * Generate <link rel="preload"> tags for all themes except the default
	 */
	public function generatePreloadLinks(): string {
		$links = [];
		foreach ($this->themes as $theme) {
			$url = htmlspecialchars($this->getThemeUrl($theme), ENT_QUOTES, 'UTF-8');
			$links[] = '<link rel="preload" href="' . $url . '" as="style">';
		}
		return implode("\n", $links);
	}

	/**
	 * Reads the variables out of an installed theme's stylesheet, so a custom
	 * palette can start from what the board is already showing. Only
	 * whitelisted, valid declarations come back — the rest of the file
	 * (Ayashii2 has real rules in it) is ignored.
	 */
	public function getThemeVariables(string $themeName): array {
		if (!in_array($themeName, $this->themes, true)) {
			return [];
		}

		$css = @file_get_contents($this->themesDir . '/' . $themeName . '.css');
		if ($css === false) {
			return [];
		}

		$found = [];
		if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;{}]+);/i', $css, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$found[strtolower($match[1])] = trim($match[2]);
			}
		}

		return self::sanitizeVariables($found);
	}

	/**
	 * Keeps only the whitelisted variables whose values pass validation, in
	 * VARIABLES order. Everything a board owner submits goes through here.
	 *
	 * @param array $values variable name (without the leading --) => raw value
	 */
	public static function sanitizeVariables(array $values): array {
		$clean = [];

		foreach (self::VARIABLES as $name => $type) {
			if (!array_key_exists($name, $values)) {
				continue;
			}

			$value = $values[$name];
			if (!is_string($value) && !is_int($value)) {
				continue; // arrays and objects never become a CSS value
			}

			$normalized = self::normalizeValue($type, (string) $value);
			if ($normalized !== null) {
				$clean[$name] = $normalized;
			}
		}

		return $clean;
	}

	/**
	 * Normalizes one value, or null when it isn't something this variable may
	 * hold. Colors are hex only and sizes are a bounded pixel count, so no
	 * value can carry a function call, another declaration, a comment, or the
	 * end of the <style> element.
	 */
	private static function normalizeValue(string $type, string $value): ?string {
		$value = trim($value);

		if ($type === self::TYPE_COLOR) {
			if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
				return strtolower($value);
			}
			return null;
		}

		if ($type === self::TYPE_FONT_SIZE) {
			if (!preg_match('/^([0-9]{1,2})(?:px)?$/i', $value, $match)) {
				return null;
			}
			$size = (int) $match[1];
			if ($size < self::MIN_FONT_SIZE || $size > self::MAX_FONT_SIZE) {
				return null;
			}
			return $size . 'px';
		}

		return null;
	}

	/**
	 * Packs a validated palette into one boards.log field. Names and values
	 * are already restricted to [a-z0-9-], # and digits, so neither separator
	 * below can appear inside them and the field can't grow a "<>" of its own.
	 */
	public static function serializeVariables(array $values): string {
		$parts = [];
		foreach (self::sanitizeVariables($values) as $name => $value) {
			$parts[] = $name . ':' . $value;
		}
		return implode(';', $parts);
	}

	/**
	 * Unpacks a stored palette, revalidating every pair: the log is a plain
	 * file an administrator may have edited by hand, so what comes out of it
	 * is treated exactly like form input.
	 */
	public static function parseVariables(string $stored): array {
		if ($stored === '') {
			return [];
		}

		$values = [];
		foreach (explode(';', $stored) as $pair) {
			if (strpos($pair, ':') === false) {
				continue;
			}
			[$name, $value] = explode(':', $pair, 2);
			$values[trim($name)] = $value;
		}

		return self::sanitizeVariables($values);
	}

	/**
	 * The <style> block that carries a board's palette.
	 *
	 * Everything written here has been through sanitizeVariables(), so the
	 * only characters that reach the page are names from VARIABLES and values
	 * matching the patterns above.
	 *
	 * $active mirrors generateThemeLink(): the block sits in the page either
	 * way and the style selector turns it on and off.
	 */
	public static function generateCustomThemeStyle(array $values, bool $active): string {
		$declarations = '';
		foreach (self::sanitizeVariables($values) as $name => $value) {
			$declarations .= "\n\t\t\t--" . $name . ': ' . $value . ';';
		}

		if ($declarations === '') {
			return '';
		}

		$media = $active ? '' : ' media="not all"';
		return '<style id="custom-theme"' . $media . '>' . "\n\t\t:root {" . $declarations . "\n\t\t}\n\t\t</style>";
	}
}

?>
