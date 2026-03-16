<form action="{{action}}" method="post">
	<input type="hidden" name="deleteFileID" value="{{fileID}}">
	<input type="hidden" name="request" value="deleteFile">
	{{lang.delete.enterPassword}} <input type="password" name="password">
	<input type="submit" value="{{lang.delete.deleteButton}}">
</form>
