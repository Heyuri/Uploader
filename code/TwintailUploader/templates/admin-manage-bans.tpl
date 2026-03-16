<div class="manageFilesPage centerItem">
	<h3>{{lang.admin.bans}}</h3>
	<p>{{lang.admin.manageBans}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<h4>{{lang.admin.bannedIPs}}</h4>
	<form method="post" action="{{addBanUrl}}">
		<label for="banValue">{{lang.admin.ipAddress}}</label>
		<input type="text" id="banValue" name="banValue" required>
		<input type="hidden" name="banType" value="ip">
		<button type="submit">{{lang.admin.ban}}</button>
	</form>
	{{bannedIPsList}}

	<hr>

	<h4>{{lang.admin.bannedFileHashes}}</h4>
	<form method="post" action="{{addBanUrl}}">
		<label for="banHashValue">{{lang.admin.fileHashLabel}}</label>
		<input type="text" id="banHashValue" name="banValue" size="64" required>
		<input type="hidden" name="banType" value="hash">
		<button type="submit">{{lang.admin.ban}}</button>
	</form>
	{{bannedHashesList}}
</div>
