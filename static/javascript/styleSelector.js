// The board's own palette, when it has one, is a <style> block in the head
// rather than a file in css/themes — switching to it turns the theme link off
// and the block on, so it applies on top of base.css alone.
const CUSTOM_THEME = "Custom";

function metaContent(name, fallback) {
	const metaTag = document.querySelector('meta[name="' + name + '"]');
	return metaTag ? metaTag.content : fallback;
}

function availableThemes() {
	const defaultTheme = metaContent("default-theme", "Futaba");
	const themes = metaContent("available-themes", defaultTheme).split(",").filter(Boolean);

	// the custom palette is only on offer when the page actually carries one
	return themes.filter(theme => theme !== CUSTOM_THEME || document.getElementById("custom-theme"));
}

function applyTheme(themeName) {
	const linkEl = document.getElementById("theme-style");
	const customEl = document.getElementById("custom-theme");
	const isCustom = themeName === CUSTOM_THEME;

	if (customEl) {
		customEl.media = isCustom ? "all" : "not all";
	}

	if (linkEl) {
		linkEl.media = isCustom ? "not all" : "all";
		if (!isCustom) {
			const staticUrl = metaContent("static-url", "static/");
			linkEl.href = staticUrl + "css/themes/" + themeName + ".css";
		}
	}
}

document.addEventListener("DOMContentLoaded", () => {
	const styleDropdown = document.getElementById("style-selector");

	const defaultTheme = metaContent("default-theme", "Futaba");
	const themes = availableThemes();

	let savedTheme = localStorage.getItem("selectedTheme");

	if (!savedTheme || !themes.includes(savedTheme)) {
		savedTheme = defaultTheme;
		localStorage.setItem("selectedTheme", savedTheme);
	}

	// Apply saved theme if different from default
	if (savedTheme !== defaultTheme) {
		applyTheme(savedTheme);
	}

	if (styleDropdown) {
		themes.forEach(theme => {
			const option = document.createElement("option");
			option.value = theme;
			option.textContent = theme;
			styleDropdown.appendChild(option);
		});

		styleDropdown.value = savedTheme;

		styleDropdown.addEventListener("change", (event) => {
			const selectedTheme = event.target.value;
			applyTheme(selectedTheme);
			localStorage.setItem("selectedTheme", selectedTheme);
		});
	}
});

// Early-apply non-default theme to prevent flash of wrong theme
(function() {
	const defaultTheme = metaContent("default-theme", "Futaba");
	const savedTheme = localStorage.getItem("selectedTheme");
	if (savedTheme && savedTheme !== defaultTheme && availableThemes().includes(savedTheme)) {
		applyTheme(savedTheme);
	}
})();
