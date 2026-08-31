<div class="uploadComplete">
	<p class="uploadCompleteHeading">{{lang.upload.uploadComplete}}</p>

	<p><a id="uploadedFileLink" class="uploadedFileLink" href="{{fileUrl}}">{{fileName}}</a></p>

	<p>
		<input type="text" id="uploadedFileUrl" class="uploadedFileUrl" value="{{fileUrl}}" size="50" readonly>
		<button type="button" id="copyUploadedUrl" data-copied-label="{{lang.upload.copied}}">{{lang.upload.copyLink}}</button>
	</p>

	{{expiryNotice}}

	<p>[<a href="{{backUrl}}">{{lang.upload.uploadAnother}}</a>]</p>
</div>

<script>
(function () {
	var input = document.getElementById("uploadedFileUrl");
	var button = document.getElementById("copyUploadedUrl");
	if (!input || !button) return;

	// the stored path is relative to this page — hand out the full URL
	input.value = new URL(input.value, window.location.href).href;

	button.addEventListener("click", function () {
		input.select();

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(input.value);
		} else {
			try { document.execCommand("copy"); } catch (e) {}
		}

		button.textContent = button.dataset.copiedLabel;
	});
})();
</script>
