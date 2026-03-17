<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<meta name="default-theme" content="{{defaultTheme}}">
		<meta name="available-themes" content="{{availableThemes}}">
		<meta name="static-url" content="{{staticUrl}}">
		{{themeLink}}
		{{preloadLinks}}
		<script src="{{staticUrl}}javascript/styleSelector.js"></script>
		<script src="{{staticUrl}}javascript/chunkUploader.js" defer></script>
		<script src="{{staticUrl}}javascript/clipboard.js" defer></script>
		<link rel="stylesheet" href="{{staticUrl}}css/base.css">
		<meta
			id="languageMeta"
			data-file-size="{{lang.upload.fileSizeLabel}}"
			data-file-name="{{lang.upload.fileNameLabel}}"
			data-uploading="{{lang.upload.uploading}}"
			data-finalizing="{{lang.upload.finalizing}}"
			data-complete="{{lang.upload.complete}}"
			data-upload-error-prefix="{{lang.upload.uploadErrorPrefix}}"
			data-server-error-finalize="{{lang.upload.serverErrorDuringFinalize}}"
			data-server-error="{{lang.upload.serverError}}"
			data-network-error="{{lang.upload.networkError}}"
			data-upload-aborted="{{lang.upload.uploadAborted}}"
		>
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
