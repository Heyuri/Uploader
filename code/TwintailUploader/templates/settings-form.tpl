<div class="settingsPage centerItem">
	<h3>{{lang.settings.title}}</h3>
	<h4>{{lang.settings.userHeading}}</h4>
	<form method="POST" action="{{action}}">
		<ul class="subtleList">
			<li>
				<input type="checkbox" id="showDeleteButton" name="showDeleteButton" value="checked" {{showDeleteButton}}>
				<label for="showDeleteButton">{{lang.settings.showDeleteButton}}</label>
			</li>
			<li>
				<input type="checkbox" id="showComment" name="showComment" value="checked" {{showComment}}>
				<label for="showComment">{{lang.settings.showComments}}</label>
			</li>
			<li>
				<input type="checkbox" id="showPreviewImage" name="showPreviewImage" value="checked" {{showPreviewImage}}>
				<label for="showPreviewImage">{{lang.settings.showPreview}}</label>
			</li>
			<li>
				<input type="checkbox" id="showFileName" name="showFileName" value="checked" {{showFileName}}>
				<label for="showFileName">{{lang.settings.showFileName}}</label>
			</li>
			<li>
				<input type="checkbox" id="showFileSize" name="showFileSize" value="checked" {{showFileSize}}>
				<label for="showFileSize">{{lang.settings.showFileSize}}</label>
			</li>
			<li>
				<input type="checkbox" id="showMimeType" name="showMimeType" value="checked" {{showMimeType}}>
				<label for="showMimeType">{{lang.settings.showMimeTypes}}</label>
			</li>
			<li>
				<input type="checkbox" id="showDate" name="showDate" value="checked" {{showDate}}>
				<label for="showDate">{{lang.settings.showDate}}</label>
			</li>
		</ul>

		<input type="hidden" name="action" value="setUserSettings">
		<input type="submit" value="{{lang.settings.save}}">
		<input type="reset" value="{{lang.settings.clear}}">
	</form>
</div>