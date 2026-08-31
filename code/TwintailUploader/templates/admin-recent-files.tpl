<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.recentFiles}}</h3>
	<p>{{lang.admin.recentFilesDescription}}</p>
	<p class="grayText">{{lang.admin.bansAreInstanceWide}}</p>

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
	<button type="submit" onclick="return confirm('{{lang.admin.confirmDeleteSelected}}');">{{lang.admin.deleteSelected}}</button>
	</form><hr>
	{{fileCount}}
</div>
