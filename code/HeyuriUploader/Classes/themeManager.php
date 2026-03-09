<?php
namespace HeyuriUploader\Classes;


class themeManager {
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
	 * Generate a <link> tag for the selected theme
	 */
	public function generateThemeLink(string $themeName): string {
		$url = htmlspecialchars($this->getThemeUrl($themeName), ENT_QUOTES, 'UTF-8');
		return '<link id="theme-style" rel="stylesheet" href="' . $url . '">';
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
}

?>
