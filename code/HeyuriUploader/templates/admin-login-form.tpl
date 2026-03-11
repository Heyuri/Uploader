<div class="loginFormContainer centerItem">
	<h3>{{lang.admin.login}}</h3>
	<form method="post" enctype="multipart/form-data" action="{{mainScript}}">
		<input type="hidden" name="request" value="login">
		<label for="password"><span title="Password for your account.">{{lang.admin.passwordLabel}}</span></label>
		<input type="password" id="password" name="password">
		<input type="submit" id="submitButton" value="{{lang.admin.enter}}">
	</form>
</div>