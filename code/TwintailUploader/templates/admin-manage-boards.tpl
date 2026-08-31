<div class="manageFilesPage centerItem">
	<h3>{{lang.boards.manageBoards}}</h3>
	<p>{{lang.boards.manageBoardsDescription}}</p>

	<div class="utilityOptions">
		<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a> |
		<a href="{{boardsUrl}}">{{lang.boards.title}}</a>
	</div>

	<div class="tableScroll">
	<table class="fileListingTable manageBoardsTable">
		<thead>
			<tr>
				<th class="boardUriColumn">{{lang.boards.uri}}</th>
				<th class="nameColumn">{{lang.boards.boardTitle}}</th>
				<th>{{lang.usage.files}}</th>
				<th class="fileSizeColumn">{{lang.table.size}}</th>
				<th class="dateColumn">{{lang.boards.created}}</th>
				<th class="ipColumn">{{lang.boards.creator}}</th>
				<th>{{lang.boards.listing}}</th>
				<th>{{lang.boards.state}}</th>
				<th class="adminActionsColumn">{{lang.table.actions}}</th>
				<th>{{lang.boards.ownerPassword}}</th>
				<th>{{lang.admin.deleteAction}}</th>
			</tr>
		</thead>
		{{boardRows}}
	</table>
	</div>
</div>
