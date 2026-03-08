document.addEventListener("DOMContentLoaded", () => {
	const styleDropdown = document.getElementById("style-selector");

	const metaTag = document.querySelector('meta[name="default-theme"]');
	const defaultTheme = metaTag ? metaTag.content : "Futaba";

	const staticUrlTag = document.querySelector('meta[name="static-url"]');
	const staticUrl = staticUrlTag ? staticUrlTag.content : "static/";

	const themesMetaTag = document.querySelector('meta[name="available-themes"]');
	const themes = themesMetaTag ? themesMetaTag.content.split(",") : [defaultTheme];

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

	function applyTheme(themeName) {
		const linkEl = document.getElementById("theme-style");
		if (linkEl) {
			linkEl.href = staticUrl + "css/themes/" + themeName + ".css";
		}
	}
});

// Early-apply non-default theme to prevent flash of wrong theme
(function() {
	const metaTag = document.querySelector('meta[name="default-theme"]');
	const defaultTheme = metaTag ? metaTag.content : "Futaba";
	const savedTheme = localStorage.getItem("selectedTheme");
	if (savedTheme && savedTheme !== defaultTheme) {
		const staticUrlTag = document.querySelector('meta[name="static-url"]');
		const staticUrl = staticUrlTag ? staticUrlTag.content : "static/";
		const linkEl = document.getElementById("theme-style");
		if (linkEl) {
			linkEl.href = staticUrl + "css/themes/" + savedTheme + ".css";
		}
	}
})();