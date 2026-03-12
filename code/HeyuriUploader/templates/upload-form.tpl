{{capacityWarning}}
<noscript>
	<link rel="stylesheet" href="static/css/fallback-noscript.css">
	<div class="redText">{{lang.upload.javascriptQoL}}</div>
</noscript>
<form id="uploadForm" method="post" enctype="multipart/form-data" action="{{action}}" data-chunk-size="{{chunkSize}}" data-main-script="{{mainScript}}">
	<input type="hidden" name="request" value="uploadFile">
	<input type="hidden" name="requestFrom" value="{{requestFrom}}">

	<p class="maxFileSize">{{maxFileSize}}</p>

	<label for="upfile"><span title="{{lang.upload.chooseFileTooltip}}">{{lang.upload.chooseFile}}</span></label>
	<input type="file" id="upfile" name="upfile" required> <br>
	
	<label for="comment"><span title="{{lang.upload.commentTooltip}}">{{lang.upload.comment}}</span></label>
	<input class="commentInput" type="text" id="comment" name="comment" value="{{defaultComment}}">
	<button type="submit">{{lang.upload.uploadReload}}</button>
	<button type="reset">{{lang.upload.cancel}}</button><br>

	<label for="password"><span title="{{lang.upload.passwordTooltip}}">{{lang.upload.password}}</span></label>
	<input type="password" id="password" name="password">

	<div id="uploadProgress" class="chunk-only" style="margin-top:5px; visibility:hidden;">
		<progress id="progressBar" value="0" max="100" style="width:200px; vertical-align:middle;"></progress>
		<span id="progressText" style="font-size:12px;"></span>
	</div>

	<details>
		<summary>{{lang.upload.allowedExtensions}}</summary>
		<p>{{allowedExtensions}}</p>
	</details>
</form>
<hr>
