// Drives the admin "Regenerate thumbnails" page: posts one batch at a time
// and keeps going until the server says it is done, so no single request
// runs long enough to hit the PHP time limit.
(function () {
	const form = document.getElementById("thumbnailRegenerator");
	if (!form) return;

	const progress = document.getElementById("thumbnailProgress");
	const failures = document.getElementById("thumbnailFailures");
	const buttons = form.querySelectorAll("button");
	let running = false;

	form.addEventListener("submit", event => event.preventDefault());
	buttons.forEach(button => button.addEventListener("click", event => {
		event.preventDefault();
		if (!running) run(button.value);
	}));

	function format(template, ...args) {
		let i = 0;
		return template.replace(/%s/g, () => args[i++]);
	}

	async function run(mode) {
		running = true;
		buttons.forEach(button => button.disabled = true);
		failures.textContent = "";
		progress.textContent = form.dataset.langStarting;

		let offset = 0, processed = 0, created = 0, failed = 0;

		try {
			while (true) {
				const body = new URLSearchParams({
					csrfToken: form.dataset.csrf,
					source: form.elements.source.value,
					mode: mode,
					offset: offset,
					created: created,
					failed: failed
				});
				const response = await fetch(form.action, { method: "POST", body: body, credentials: "same-origin" });
				if (!response.ok) throw new Error("HTTP " + response.status);

				const result = await response.json();
				if (result.error) throw new Error(result.error);

				processed += result.processed;
				created += result.created;
				failed += result.failed;
				offset = result.nextOffset;

				result.failures.forEach(text => {
					const item = document.createElement("li");
					item.textContent = text;
					failures.appendChild(item);
				});

				if (result.done) {
					progress.textContent = format(form.dataset.langDone, processed, created, failed);
					break;
				}
				progress.textContent = format(form.dataset.langProgress, processed, result.total, created, failed);
			}
		} catch (error) {
			progress.textContent = form.dataset.langError + " " + error.message;
		}

		running = false;
		buttons.forEach(button => button.disabled = false);
	}
})();
