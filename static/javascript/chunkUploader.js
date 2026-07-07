/**
 * Chunk uploader for Heyuri Uploader.
 * Intercepts the upload form submission and sends the file in chunks,
 * falling back to normal form submission if anything goes wrong.
 */
document.addEventListener("DOMContentLoaded", () => {
	const form = document.getElementById("uploadForm");
	if (!form) return;

	const fileInput = form.querySelector('input[name="upfile"]');
	const progressContainer = document.getElementById("uploadProgress");
	const progressBar = document.getElementById("progressBar");
	const progressText = document.getElementById("progressText");
	const submitButton = form.querySelector('button[type="submit"]');

	// --- i18n ---
	const languageMeta = document.getElementById("languageMeta");

	const TEXT = {
		uploading: languageMeta?.dataset.uploading || "Uploading...",
		finalizing: languageMeta?.dataset.finalizing || "Finalizing...",
		complete: languageMeta?.dataset.complete || "Complete!",
		uploadErrorPrefix: languageMeta?.dataset.uploadErrorPrefix || "Upload error: ",
		serverErrorFinalize: languageMeta?.dataset.serverErrorFinalize || "Server error during finalize (HTTP %s)",
		serverError: languageMeta?.dataset.serverError || "Server error (HTTP %s)",
		networkError: languageMeta?.dataset.networkError || "Network error — check your connection.",
		uploadAborted: languageMeta?.dataset.uploadAborted || "Upload aborted.",
		copyLink: languageMeta?.dataset.copyLink || "Copy link",
		copied: languageMeta?.dataset.copied || "Copied"
	};

	const uploadedFilesContainer = document.getElementById("uploadedFiles");
	const uploadedFilesList = document.getElementById("uploadedFilesList");

	function format(str, val) {
		return str.replace("%s", val);
	}

	// Read chunk size from data attribute (set by PHP), default 2MB
	const chunkSize = parseInt(form.dataset.chunkSize, 10) || (2 * 1024 * 1024);
	const mainScript = form.dataset.mainScript || "warota.php";

	form.addEventListener("submit", (e) => {
		const file = fileInput.files[0];
		if (!file) return; // let normal validation handle it

		// Use chunk upload
		e.preventDefault();
		uploadInChunks(file);
	});

	async function uploadInChunks(file) {
		const totalChunks = Math.ceil(file.size / chunkSize);
		const comment = form.querySelector('input[name="comment"]').value;
		const password = form.querySelector('input[name="password"]').value;
		const requestFrom = form.querySelector('input[name="requestFrom"]').value;

		// Show progress bar, disable submit
		progressContainer.style.visibility = "visible";
		submitButton.disabled = true;
		updateProgress(0, TEXT.uploading);

		let uploadId = null;
		let totalBytesSent = 0;

		try {
			// Send each chunk sequentially
			for (let i = 0; i < totalChunks; i++) {
				const start = i * chunkSize;
				const end = Math.min(start + chunkSize, file.size);
				const chunk = file.slice(start, end);

				const formData = new FormData();
				formData.append("chunkData", chunk);
				formData.append("chunkIndex", i);
				formData.append("totalChunks", totalChunks);
				formData.append("fileName", file.name);
				formData.append("fileSize", file.size);
				formData.append("request", "uploadChunk");

				if (uploadId) {
					formData.append("uploadId", uploadId);
				}

				const chunkBytesStart = totalBytesSent;
				const result = await sendChunkWithProgress(formData, (chunkLoaded) => {
					// Continuous progress: bytes sent so far / total file size, scaled to 0-90%
					const overallLoaded = chunkBytesStart + chunkLoaded;
					const percent = Math.round((overallLoaded / file.size) * 90);
					updateProgress(Math.min(percent, 90));
				});

				if (result.error) {
					throw new Error(result.error);
				}

				totalBytesSent = end;

				// Save upload ID from first chunk response
				if (i === 0 && result.uploadId) {
					uploadId = result.uploadId;
				}
			}

			// All chunks sent — finalize
			updateProgress(95, TEXT.finalizing);

			const finalizeData = new FormData();
			finalizeData.append("request", "finalizeChunkUpload");
			finalizeData.append("uploadId", uploadId);
			finalizeData.append("comment", comment);
			finalizeData.append("password", password);
			finalizeData.append("requestFrom", requestFrom);
			finalizeData.append("pageNumber", currentPageNumber());

			const finalResponse = await fetch(mainScript + "?request=finalizeChunkUpload", {
				method: "POST",
				body: finalizeData,
			});

			let finalResult;
			try {
				finalResult = await finalResponse.json();
			} catch (e) {
				throw new Error(format(TEXT.serverErrorFinalize, finalResponse.status));
			}

			if (!finalResponse.ok || finalResult.error) {
				throw new Error(finalResult.error || format(TEXT.serverErrorFinalize, finalResponse.status));
			}

			updateProgress(100, TEXT.complete);

			// Update the page in place instead of reloading
			if (finalResult.file) {
				addUploadedFileEntry(finalResult.file);
			}
			if (typeof finalResult.listingHtml === "string") {
				replaceListing(finalResult.listingHtml);
			}
			resetForNextUpload();
		} catch (err) {
			progressContainer.style.visibility = "hidden";
			updateProgress(0);
			submitButton.disabled = false;
			alert(TEXT.uploadErrorPrefix + err.message);
		}
	}

	/**
	 * Sends a chunk via XMLHttpRequest so we can track upload progress byte-by-byte.
	 */
	function sendChunkWithProgress(formData, onProgress) {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open("POST", mainScript + "?request=uploadChunk");

			xhr.upload.addEventListener("progress", (e) => {
				if (e.lengthComputable) {
					onProgress(e.loaded);
				}
			});

			xhr.addEventListener("load", () => {
				let result;
				try {
					result = JSON.parse(xhr.responseText);
				} catch (e) {
					// Server returned non-JSON (e.g. HTML error page)
					reject(new Error(format(TEXT.serverError, xhr.status)));
					return;
				}

				if (xhr.status >= 200 && xhr.status < 300) {
					resolve(result);
				} else {
					reject(new Error(result.error || format(TEXT.serverError, xhr.status)));
				}
			});

			xhr.addEventListener("error", () => reject(new Error(TEXT.networkError)));
			xhr.addEventListener("abort", () => reject(new Error(TEXT.uploadAborted)));

			xhr.send(formData);
		});
	}

	function updateProgress(percent, text) {
		progressBar.value = percent;
		progressText.textContent = text || (percent + "%");
	}

	// Reads the page the listing is currently showing so the server can re-render it.
	function currentPageNumber() {
		const page = new URLSearchParams(window.location.search).get("pageNumber");
		return page || "1";
	}

	// Swaps the freshly rendered listing into the page.
	function replaceListing(html) {
		const current = document.getElementById("fileListing");
		if (!current) return;

		const holder = document.createElement("div");
		holder.innerHTML = html;
		const fresh = holder.querySelector("#fileListing");
		if (fresh) {
			current.replaceWith(fresh);
		} else {
			current.innerHTML = html;
		}
	}

	// Appends one uploaded file (name + copy-link button) to the session list.
	function addUploadedFileEntry(file) {
		if (!uploadedFilesList) return;
		if (uploadedFilesContainer) uploadedFilesContainer.classList.remove("hidden");

		const absoluteUrl = new URL(file.path, window.location.href).href;

		const entry = document.createElement("li");
		entry.className = "uploadedFileEntry";

		const name = document.createElement("span");
		name.className = "uploadedFileName";
		name.textContent = file.name;

		const button = document.createElement("button");
		button.type = "button";
		button.className = "copyLinkButton";
		button.title = TEXT.copyLink;
		button.setAttribute("aria-label", TEXT.copyLink);
		button.dataset.copyUrl = absoluteUrl;

		entry.appendChild(name);
		entry.appendChild(button);
		uploadedFilesList.appendChild(entry);
	}

	// Clears the file selection so another file can be uploaded right away.
	function resetForNextUpload() {
		progressContainer.style.visibility = "hidden";
		updateProgress(0);
		submitButton.disabled = false;
		fileInput.value = "";

		const preview = document.getElementById("fileListContainer");
		if (preview) preview.remove();
	}

	function copyToClipboard(text, button) {
		const done = () => showCopied(button);
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, () => fallbackCopy(text, done));
		} else {
			fallbackCopy(text, done);
		}
	}

	function fallbackCopy(text, done) {
		const textarea = document.createElement("textarea");
		textarea.value = text;
		textarea.className = "offscreenTextarea";
		document.body.appendChild(textarea);
		textarea.select();
		try {
			document.execCommand("copy");
		} catch (e) {
			// Ignore — nothing more we can do
		}
		document.body.removeChild(textarea);
		done();
	}

	// Briefly swaps the clipboard icon for a "Copied" label.
	function showCopied(button) {
		if (button.dataset.reverting === "1") return;
		button.dataset.reverting = "1";
		button.classList.add("copied");
		button.textContent = TEXT.copied;
		setTimeout(() => {
			button.textContent = "";
			button.classList.remove("copied");
			button.dataset.reverting = "0";
		}, 2000);
	}

	if (uploadedFilesList) {
		uploadedFilesList.addEventListener("click", (e) => {
			const button = e.target.closest(".copyLinkButton");
			if (!button) return;
			copyToClipboard(button.dataset.copyUrl, button);
		});
	}
});
