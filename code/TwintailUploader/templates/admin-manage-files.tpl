<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.files}}</h3>
	<p>{{lang.admin.manageFiles}}</p>

	<div class="utilityOptions">
		<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	</div>

	{{filterNotice}}

	{{pagingBar}}

	<form method="post" action="{{bulkDeleteUrl}}">
	<input type="hidden" name="csrfToken" value="{{csrfToken}}">
	<div class="tableScroll">
		<table class="fileListingTable">
			{{manageTableHeader}}
			{{manageTableRows}}
		</table>
	</div>
	{{pagingBar}}
	<br>
	<button type="submit" onclick="return confirm('{{lang.admin.confirmDeleteSelected}}');">{{lang.admin.deleteSelected}}</button>
	</form><hr>
	{{usageInfo}}
</div>