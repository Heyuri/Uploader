<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.configTitle}}</h3>
	<p>{{lang.admin.configDescription}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	{{statusMessage}}

	<form method="post" action="{{saveUrl}}">
		<table class="alignLeft">
			<tbody>
				{{configRows}}
			</tbody>
		</table>
		<br>
		<button type="submit">{{lang.admin.saveButton}}</button>
	</form>
</div>
