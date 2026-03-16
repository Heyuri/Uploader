<h3>{{lang.search.title}}</h3>
<form method="GET" action="{{action}}">
	<table>
		<tr><td>{{lang.search.fileName}}</td><td><input name="originalFileName" value="{{originalFileName}}"></td></tr>
		<tr><td>{{lang.search.comment}}</td><td><input name="comment" value="{{comment}}"></td></tr>
		<tr><td>{{lang.search.extension}}</td><td><input name="fileExtension" value="{{fileExtension}}"></td></tr>
		<tr><td>{{lang.search.sort}}</td><td><select name="sortDir"><option value="desc" {{sortDescSelected}}>{{lang.search.newestFirst}}</option><option value="asc" {{sortAscSelected}}>{{lang.search.oldestFirst}}</option></select></td></tr>
	</table>

	<input type="hidden" name="request" value="search">
	<input type="submit" id="searchSubmitButton" value="{{lang.search.searchButton}}">
</form>
