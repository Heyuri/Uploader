<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.bans}}</h3>
	<p>{{lang.boards.manageBansDescription}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<h4>{{lang.boards.bannedPosters}}</h4>
	<p class="grayText">{{lang.boards.bannedPostersHint}}</p>
	{{bannedIPsList}}

	<hr>

	<h4>{{lang.admin.bannedFileHashes}}</h4>
	{{bannedHashesList}}
</div>
