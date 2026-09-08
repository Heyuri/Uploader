<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.boardDefaults}}</h3>
	<p>{{lang.admin.boardDefaultsDescription}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<form method="post" action="{{saveUrl}}">
		<input type="hidden" name="csrfToken" value="{{csrfToken}}">
		<table class="configEditorTable">
			<tbody>
				{{defaultRows}}
			</tbody>
		</table>
		<br>
		<button type="submit">{{lang.admin.saveButton}}</button>
	</form>
</div>
