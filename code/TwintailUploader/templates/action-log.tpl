<div class="manageFilesPage centerItem">
	<h3>{{lang.actionLog.title}}</h3>
	<p>{{description}}</p>
	{{scopeNote}}

	<div class="utilityOptions">
		<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	</div>

	<form class="actionLogFilter" method="get" action="{{mainScript}}">
		<input type="hidden" name="request" value="admin">
		<input type="hidden" name="modPage" value="actionLog">
		<label for="actionFilter">{{lang.actionLog.filter}}</label>
		<select id="actionFilter" name="actionFilter">
			{{filterOptions}}
		</select>
		{{ipFilterField}}
		<button type="submit">{{lang.actionLog.apply}}</button>
	</form>

	{{filterNotice}}

	{{pagingBar}}

	<div class="tableScroll">
		<table class="fileListingTable">
			{{tableHeader}}
			{{tableRows}}
		</table>
	</div>

	{{pagingBar}}
	<hr>
	{{entryCount}}
</div>
