<!DOCTYPE html>
<html>
	<head>
		<meta name="default-theme" content="{{defaultTheme}}">
		<meta name="available-themes" content="{{availableThemes}}">
		<meta name="static-url" content="{{staticUrl}}">
		{{themeLink}}
		{{preloadLinks}}
		<script src="{{staticUrl}}javascript/styleSelector.js" defer></script>
		<script src="{{staticUrl}}javascript/chunkUploader.js" defer></script>
		<script src="{{staticUrl}}javascript/clipboard.js" defer></script>
		<link rel="stylesheet" href="{{staticUrl}}css/base.css">
		<meta id="languageMeta" data-file-size="{{lang.upload.fileSizeLabel}}" data-file-name="{{lang.upload.fileNameLabel}}">
		<title>{{boardTitle}}</title>
	</head>
	
	<body>
		<div class="styleSelectorContainer">
			<form id="style-form">
				<label for="style-selector">{{lang.settings.styleSelector}}</label>
				<select id="style-selector"></select>
			</form>
		</div>
		<h1 class="titleHeader">{{boardTitle}}</h1>
		<h2 class="subtitleHeader">{{boardSubTitle}}</h2>
