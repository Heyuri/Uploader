<div class="manageFilesPage centerItem">
	<h3>{{lang.boards.boardSettings}}</h3>
	<p>{{lang.boards.settingsDescription}}</p>
	<a href="{{backUrl}}">{{lang.admin.backToDashboard}}</a>
	<hr>

	<form method="post" action="{{saveUrl}}">
		<input type="hidden" name="csrfToken" value="{{csrfToken}}">
		<table class="configEditorTable">
			<tbody>
				<tr>
					<td class="postblock">{{lang.boards.uri}}</td>
					<td>{{boardUri}} <span class="grayText">{{lang.boards.uriFixed}}</span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsTitle">{{lang.boards.boardTitle}}</label></td>
					<td><input type="text" id="settingsTitle" name="title" maxlength="64" value="{{title}}" required></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsSubTitle">{{lang.boards.boardSubTitle}}</label></td>
					<td><textarea id="settingsSubTitle" name="subTitle" maxlength="256" cols="48" rows="3">{{subTitle}}</textarea></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsDefaultComment">{{lang.boards.defaultComment}}</label></td>
					<td><input type="text" id="settingsDefaultComment" name="defaultComment" maxlength="128" value="{{defaultComment}}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsCommentRequired">{{lang.boards.commentRequired}}</label></td>
					<td><input type="checkbox" id="settingsCommentRequired" name="commentRequired" value="1" {{commentRequiredChecked}}></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsListed">{{lang.boards.listedLabel}}</label></td>
					<td><input type="checkbox" id="settingsListed" name="listed" value="1" {{listedChecked}}></td>
				</tr>
				<tr>
					<td class="postblock"><label for="settingsNewPassword">{{lang.boards.newPassword}}</label></td>
					<td><input type="password" id="settingsNewPassword" name="newPassword" maxlength="64" autocomplete="new-password">
						<span class="grayText">{{lang.boards.newPasswordHint}}</span></td>
				</tr>
			</tbody>
		</table>

		<h4>{{lang.theme.heading}}</h4>
		<p class="grayText">{{lang.theme.description}}</p>
		<table class="configEditorTable">
			<tbody>
				<tr>
					<td class="postblock"><label for="settingsTheme">{{lang.theme.defaultTheme}}</label></td>
					<td><select id="settingsTheme" name="theme">{{themeOptions}}</select></td>
				</tr>
			</tbody>
		</table>

		<h4>{{lang.theme.customHeading}}</h4>
		<p class="grayText">{{lang.theme.customDescription}}</p>
		<table class="configEditorTable themeVariableTable">
			<tbody>
				{{themeVariableRows}}
			</tbody>
		</table>
		<br>
		<button type="submit">{{lang.admin.saveButton}}</button>
	</form>
</div>
