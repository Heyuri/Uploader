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
		updateProgress(0, "Uploading...");

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
			updateProgress(95, "Finalizing...");

			const finalizeData = new FormData();
			finalizeData.append("request", "finalizeChunkUpload");
			finalizeData.append("uploadId", uploadId);
			finalizeData.append("comment", comment);
			finalizeData.append("password", password);
			finalizeData.append("requestFrom", requestFrom);

			const finalResponse = await fetch(mainScript + "?request=finalizeChunkUpload", {
				method: "POST",
				body: finalizeData,
			});

			let finalResult;
			try {
				finalResult = await finalResponse.json();
			} catch (e) {
				throw new Error("Server error during finalize (HTTP " + finalResponse.status + ")");
			}

			if (!finalResponse.ok || finalResult.error) {
				throw new Error(finalResult.error || "Server error during finalize (HTTP " + finalResponse.status + ")");
			}

			updateProgress(100, "Complete!");

			// Redirect on success
			if (finalResult.redirect) {
				window.location.href = finalResult.redirect;
			}
		} catch (err) {
			progressContainer.style.visibility = "hidden";
			updateProgress(0);
			submitButton.disabled = false;
			alert("Upload error: " + err.message);
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
					reject(new Error("Server error (HTTP " + xhr.status + ")"));
					return;
				}

				if (xhr.status >= 200 && xhr.status < 300) {
					resolve(result);
				} else {
					reject(new Error(result.error || "Server error (HTTP " + xhr.status + ")"));
				}
			});

			xhr.addEventListener("error", () => reject(new Error("Network error — check your connection.")));
			xhr.addEventListener("abort", () => reject(new Error("Upload aborted.")));

			xhr.send(formData);
		});
	}

	function updateProgress(percent, text) {
		progressBar.value = percent;
		progressText.textContent = text || (percent + "%");
	}
});
