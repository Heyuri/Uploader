<div class="boardCreatePage centerItem">
	<h3>{{lang.boards.createBoard}}</h3>
	<p><i>{{lang.boards.createDescription}}</i></p>
	<div class="utilityOptions"><a href="{{backUrl}}">{{lang.nav.back}}</a></div>
	<hr>

	<form method="post" action="{{action}}">
		<input type="hidden" name="action" value="createBoard">
		<table class="configEditorTable">
			<tbody>
				<tr>
					<td class="postblock"><label for="boardUri">{{lang.boards.uri}}</label></td>
					<td>
						<div class="fieldHint">{{lang.boards.uriHint}}</div>
						<div><input type="text" id="boardUri" name="uri" maxlength="16" pattern="[A-Za-z0-9_-]+" required></div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardName">{{lang.boards.boardTitle}}</label></td>
					<td><input type="text" id="boardName" name="title" maxlength="64" required></td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardSubName">{{lang.boards.boardSubTitle}}</label></td>
					<td><textarea id="boardSubName" name="subTitle" maxlength="256" cols="48" rows="3"></textarea></td>
				</tr>
				<tr>
					<td class="postblock"><label for="ownerPassword">{{lang.boards.ownerPassword}}</label></td>
					<td>
						<div class="fieldHint">{{lang.boards.ownerPasswordHint}}</div>
						<div><input type="password" id="ownerPassword" name="ownerPassword" maxlength="64" required></div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardDefaultComment">{{lang.boards.defaultComment}}</label></td>
					<td><input type="text" id="boardDefaultComment" name="defaultComment" maxlength="128" value="{{defaultComment}}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardPrefix">{{lang.boards.filePrefix}}</label></td>
					<td><input type="text" id="boardPrefix" name="prefix" maxlength="10" pattern="[A-Za-z0-9_-]*" placeholder="up"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardCommentRequired">{{lang.boards.commentRequired}}</label></td>
					<td><input type="checkbox" id="boardCommentRequired" name="commentRequired" value="1" checked></td>
				</tr>
				<tr>
					<td class="postblock"><label for="boardListed">{{lang.boards.listedLabel}}</label></td>
					<td><input type="checkbox" id="boardListed" name="listed" value="1" checked></td>
				</tr>
			</tbody>
		</table>
		<p class="grayText">{{limitsNote}}</p>
		<button type="submit">{{lang.boards.createBoard}}</button>
	</form>
</div>
