<?php
namespace TwintailUploader\Classes;

class HTMLRenderer {
	private $templatesPath;
	private ?languageManager $lang;
	private array $globals = [];

	public function __construct(string $templatesPath, ?languageManager $lang = null) {
		$this->templatesPath = rtrim($templatesPath, '/');
		$this->lang = $lang;
	}

	/**
	 * Register a variable available to every render (like the language strings).
	 */
	public function addGlobal(string $key, string $value): void {
		$this->globals[$key] = $value;
	}

	/**
	 * Load and render a template with variables
	 */
	public function render(string $templateName, array $variables = []): string {
		$templatePath = $this->templatesPath . '/' . $templateName . '.tpl';

		if (!file_exists($templatePath)) {
			throw new \Exception("Template not found: $templateName");
		}

		$content = file_get_contents($templatePath);

		// Inject language strings (so {{lang.x.y}} resolves) and globals, with
		// caller variables winning on any key collision.
		if ($this->lang !== null) {
			$variables = array_merge($this->lang->getAll(), $variables);
		}
		$variables = array_merge($this->globals, $variables);

		// Build the placeholder map and substitute in a single pass: strtr never
		// re-scans inserted text, so a user value containing "{{someKey}}" can't
		// be resolved against another variable.
		$replacements = [];
		foreach ($variables as $key => $value) {
			$replacements['{{' . $key . '}}'] = (string) $value;
		}
		$content = strtr($content, $replacements);

		// Remove any unreplaced placeholders. Match only real placeholder-key
		// shapes (letters, digits, _ . -) so a user value that happens to
		// contain "{{" can't make this swallow the template text up to the next
		// "}}" — placeholder keys never contain markup characters.
		$content = preg_replace('/{{[A-Za-z0-9_.-]+}}/', '', $content);

		return $content;
	}

	/**
	 * Render multiple template blocks and concatenate them
	 */
	public function renderMultiple(array $templates): string {
		$result = '';
		foreach ($templates as $templateName => $variables) {
			$result .= $this->render($templateName, $variables);
		}
		return $result;
	}

	/**
	 * Check if a template variable is set (returns placeholder if not)
	 */
	public function ifSet(mixed $value, string $defaultValue = ''): string {
		return isset($value) ? (string)$value : $defaultValue;
	}

	/**
	 * Safely escape HTML output
	 */
	public function escape(mixed $value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Conditionally render HTML
	 */
	public function renderIf(bool $condition, string $templateName, array $variables = []): string {
		return $condition ? $this->render($templateName, $variables) : '';
	}

	/**
	 * Render multiple items using a template
	 */
	public function renderItems(array $items, string $templateName, ?callable $mapFn = null): string {
		$result = '';
		foreach ($items as $item) {
			$variables = $mapFn ? $mapFn($item) : $item;
			$result .= $this->render($templateName, $variables);
		}
		return $result;
	}
}
