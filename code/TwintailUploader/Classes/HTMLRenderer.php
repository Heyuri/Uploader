<?php
namespace TwintailUploader\Classes;

class HTMLRenderer {
	private $templatesPath;
	private ?languageManager $lang;

	public function __construct(string $templatesPath, ?languageManager $lang = null) {
		$this->templatesPath = rtrim($templatesPath, '/');
		$this->lang = $lang;
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

		// Inject language strings so {{lang.x.y}} placeholders are resolved
		if ($this->lang !== null) {
			$variables = array_merge($this->lang->getAll(), $variables);
		}
		
		// Replace template variables
		foreach ($variables as $key => $value) {
			$placeholder = '{{' . $key . '}}';
			$content = str_replace($placeholder, (string)$value, $content);
		}

		// Remove any unreplaced placeholders
		$content = preg_replace('/{{[^}]+}}/', '', $content);

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
