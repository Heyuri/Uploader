<div class="boardListPage">
	<div class="centerItem boardListHeadDesc">
		<h3>{{lang.boards.title}}</h3>
		<p>{{lang.boards.description}}</p>

		<div class="utilityOptions">{{createLink}}</div>
	</div>
	<div class="tableScroll">
	<table class="fileListingTable boardListTable">
		<thead>
			<tr>
				<th class="boardUriColumn">{{lang.boards.uri}}</th>
				<th class="nameColumn">{{lang.boards.boardTitle}}</th>
				<th class="commentColumn">{{lang.boards.boardSubTitle}}</th>
				<th class="dateColumn">{{lang.boards.created}}</th>
			</tr>
		</thead>
		{{boardRows}}
	</table>
	</div>
</div>
