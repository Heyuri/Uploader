<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.regenerateThumbnails}}</h3>
	<p>{{lang.admin.regenerateThumbnailsDescription}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<form id="thumbnailRegenerator" method="post" action="{{batchUrl}}"
		data-csrf="{{csrfToken}}"
		data-lang-starting="{{lang.admin.thumbnailStarting}}"
		data-lang-progress="{{lang.admin.thumbnailProgress}}"
		data-lang-done="{{lang.admin.thumbnailDone}}"
		data-lang-error="{{lang.admin.thumbnailError}}">
		<label for="thumbnailSource">{{lang.admin.thumbnailSource}}</label>
		<select id="thumbnailSource" name="source">{{sourceOptions}}</select>
		<br><br>
		<button type="submit" name="mode" value="missing">{{lang.admin.regenerateMissing}}</button>
		<button type="submit" name="mode" value="all">{{lang.admin.regenerateAll}}</button>
	</form>
	<noscript><p>{{lang.admin.thumbnailsNeedJs}}</p></noscript>

	<p id="thumbnailProgress"></p>
	<ul id="thumbnailFailures" class="subtleList"></ul>
</div>
<script src="{{staticUrl}}javascript/thumbnailRegenerator.js"></script>
