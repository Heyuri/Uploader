<div class="manageFilesPage centerItem">
	<h3>{{heading}}</h3>
	<p>{{description}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<form method="post" action="{{saveUrl}}">
		<input type="hidden" name="csrfToken" value="{{csrfToken}}">
		<table class="configEditorTable">
			<tbody>
				{{overrideRows}}
			</tbody>
		</table>
		<br>
		<button type="submit">{{lang.admin.saveButton}}</button>
	</form>
</div>
