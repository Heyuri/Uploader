(function() {
	'use strict';

	var fileInput = document.querySelector('input[type="file"][name^="upfile"]');
	if (!fileInput) return;

	// Holds all selected or pasted files in memory before syncing with file input
	var filesState = [];
	var allowedPreviewTypes = ['image/jpeg','image/png','image/gif','image/bmp','image/webp','image/svg+xml'];
	var ignoreChange = false;



	// Determines a suitable extension for a given MIME type
	function getFileExt(m) {
		switch (m) {
			case "image/jpeg": return ".jpg";
			case "image/png": return ".png";
			case "image/gif": return ".gif";
			case "image/bmp": return ".bmp";
			case "image/webp": return ".webp";
			case "image/svg+xml": return ".svg";
			default: return "";
		}
	}

	// Splits a filename into its base name and extension
	function splitName(n) {
		if (!n) return { nameBase:'image', extension:'' };
		var p = n.lastIndexOf('.');
		if (p <= 0) return { nameBase:n, extension:'' };
		return { nameBase:n.slice(0,p), extension:n.slice(p) };
	}

	// Returns how many more files the user may add
	function remaining() {
		return 1;
	}

	function canAdd() {
		return remaining() > 0;
	}

	// Rebuilds the <input type="file"> value to reflect the internal file state
	function syncInputFiles() {
		var dt = new DataTransfer();
		for (var i=0;i<filesState.length;i++) {
			var st = filesState[i];
			var f = new File([st.blob], st.nameBase + st.extension, { type: st.type });
			dt.items.add(f);
		}
		ignoreChange = true;
		fileInput.files = dt.files;
		fileInput.dispatchEvent(new Event('change',{ bubbles:true }));
		ignoreChange = false;
	}

	// ----------------------------------------
	// Dropzone UI for multi-file selection
	// ----------------------------------------

	function makeDropzone() {
		var wrap = document.createElement('div');
		wrap.id = 'dropzoneWrap';
		wrap.style.userSelect = 'none';
		wrap.style.marginTop = '4px';

		var dz = document.createElement('div');
		dz.tabIndex = 0;
		dz.style.border = '2px dashed #888';
		dz.style.padding = '12px';
		dz.style.borderRadius = '6px';
		dz.style.cursor = 'pointer';
		dz.style.textAlign = 'center';
		dz.style.color = '#666';

		var hint = document.createElement('div');
		hint.textContent = 'Select / drop / paste images here';

		dz.appendChild(hint);
		wrap.appendChild(dz);
		fileInput.after(wrap);

		// Hidden picker allows traditional browsing while keeping the UI consistent
		var hiddenPicker = document.createElement('input');
		hiddenPicker.type = 'file';
		hiddenPicker.multiple = true;
		hiddenPicker.style.display='none';
		if (fileInput.accept) hiddenPicker.setAttribute('accept', fileInput.getAttribute('accept'));
		wrap.after(hiddenPicker);

		dz.addEventListener('click', function(){
			if (!canAdd()) return;
			hiddenPicker.click();
		});

		// Provides visual feedback while dragging files over the dropzone
		dz.addEventListener('dragover', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#33a'; dz.style.color='#33a';
		});

		dz.addEventListener('dragleave', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#888'; dz.style.color='#666';
		});

		// Accepts dropped files and forwards them to the file handler
		dz.addEventListener('drop', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#888'; dz.style.color='#666';

			var fl = e.dataTransfer.files;
			if (!fl || !fl.length) return;

			var slots = remaining();
			for (var i=0;i<fl.length && slots>0;i++,slots--) addFile(fl[i], fl[i].name);
		});

		hiddenPicker.addEventListener('change', function(){
			if (!hiddenPicker.files.length) return;
			var slots = remaining();
			for (var i=0;i<hiddenPicker.files.length && slots>0;i++,slots--)
				addFile(hiddenPicker.files[i], hiddenPicker.files[i].name);
			hiddenPicker.value='';
		});
	}

	// ----------------------------------------
	// Rendering and layout of file entries
	// ----------------------------------------

	function ensureList() {
		var c = document.getElementById('fileListContainer');
		if (!c) {
			c = document.createElement('div');
			c.id='fileListContainer';
			c.style.display='flex';
			c.style.flexWrap='wrap';
			c.style.gap='8px';
			c.style.marginTop='4px';

			var dz = document.getElementById('dropzoneWrap');
			if (dz) dz.after(c);
			else fileInput.after(c);
		}
		return c;
	}

	// Removes the file list container entirely when no files remain
	function clearList() {
		var c = document.getElementById('fileListContainer');
		if (c) c.remove();
	}

	// Updates the displayed list of files, including previews and controls
	function render() {
		if (!filesState.length) clearList();
		else {
			var c = ensureList();
			c.innerHTML='';
			for (var i=0;i<filesState.length;i++) c.appendChild(renderBlock(filesState[i], i));
		}

		var dz = document.getElementById('dropzoneWrap');
		if (dz) dz.style.display = canAdd() ? 'block' : 'none';
	}

	// Creates a single file entry including filename input, preview, size display, and actions
	function renderBlock(st,index) {
		var b=document.createElement('div');
		b.style.display='inline-block';
		b.style.maxWidth='220px';

		// Fetch localized labels from meta#languageMeta
		var languageMeta = document.getElementById('languageMeta');
		var fileNameLabel = 'Filename';
		var fileSizeLabel = 'File size';
		if (languageMeta) {
			if (languageMeta.dataset.fileName) fileNameLabel = languageMeta.dataset.fileName;
			if (languageMeta.dataset.fileSize) fileSizeLabel = languageMeta.dataset.fileSize;
		}

		// Removes this file from the selection
		var x=document.createElement('span');
		x.innerHTML='[<a href="javascript:void(0);">X</a>]';
		x.style.display='block';
		x.style.marginBottom='4px';
		x.querySelector('a').addEventListener('click',function(e){
			e.preventDefault(); removeFile(index);
		});
		b.appendChild(x);

		// Allows the user to rename the file before submission
		var fn=document.createElement('div');
		var l=document.createElement('label');
		l.textContent=fileNameLabel;
		var inp=document.createElement('input');
		inp.type='text'; inp.classList.add('inputtext'); inp.style.width='100%';
		inp.value=st.nameBase;
		inp.addEventListener('input',function(){
			st.nameBase = inp.value || 'image';
			syncInputFiles();
		});
		fn.appendChild(l); fn.appendChild(inp);
		b.appendChild(fn);

		// Displays the file's size in kilobytes
		var sc=document.createElement('div');
		var sl=document.createElement('label');
		sl.textContent=fileSizeLabel;
		var sv=document.createElement('div');
		sv.textContent=(st.blob.size/1024).toFixed(2)+' KB';
		sc.appendChild(sl); sc.appendChild(sv);
		b.appendChild(sc);

		// Shows a preview for supported image formats
		if (allowedPreviewTypes.indexOf(st.type)!==-1) {
			var img=document.createElement('img');
			img.style.display='block';
			img.style.marginTop='4px';
			img.style.maxWidth='200px';
			img.style.height='auto';
			var fr=new FileReader();
			fr.onload=e=>img.src=e.target.result;
			fr.readAsDataURL(st.blob);
			b.appendChild(img);

			// Offers a conversion option for WebP files, since PNG is more widely supported
			if (st.type==='image/webp') {
				var bw=document.createElement('div');
				var cb=document.createElement('button');
				cb.textContent='Convert WebP to PNG';
				cb.addEventListener('click',function(e){
					e.preventDefault(); convertWebP(index,img,sv,cb);
				});
				bw.appendChild(cb);
				b.appendChild(bw);
			}
		}

		return b;
	}

	// Converts a WebP file to PNG and updates its state
	function convertWebP(index,img,sizeDiv,btn) {
		var st=filesState[index];
		if (!st || st.type!=='image/webp') return;

		btn.style.opacity='0.5';
		btn.style.pointerEvents='none';

		var cvs=document.createElement('canvas');
		var ctx=cvs.getContext('2d');
		var im=new Image();

		im.onload=function(){
			cvs.width=im.width;
			cvs.height=im.height;
			ctx.drawImage(im,0,0);
			cvs.toBlob(function(p){
				if (!p) return;
				st.blob=p;
				st.type='image/png';
				st.extension='.png';
				sizeDiv.textContent=(p.size/1024).toFixed(2)+' KB';
				var fr=new FileReader();
				fr.onload=e=>img.src=e.target.result;
				fr.readAsDataURL(p);
				syncInputFiles();
			},'image/png');
		};

		var fr2=new FileReader();
		fr2.onload=e=>im.src=e.target.result;
		fr2.readAsDataURL(st.blob);
	}

	// Removes a file from the interface and syncs the input
	function removeFile(i){
		filesState.splice(i,1);
		render();
		syncInputFiles();
	}

	// Adds a new file to the internal state and triggers rendering
	function addFile(f,name){
		var p=splitName(name||f.name||'image');
		var ext=p.extension || getFileExt(f.type);
		var st = {
			blob:f,
			nameBase:p.nameBase||'image',
			extension:ext,
			type:f.type||'application/octet-stream'
		};

		// Single-file mode replaces any existing file
		filesState = [st];

		syncInputFiles();
		render();
	}

	// ----------------------------------------
	// Global event handling
	// ----------------------------------------

	fileInput.style.display = '';

	// Processes files selected through the input element
	fileInput.addEventListener('change',function(){
		if (ignoreChange) return;

		if (!fileInput.files || !fileInput.files.length) {
			return;
		}

		addFile(fileInput.files[0], fileInput.files[0].name);
	});

	// Allows pasted images to be processed just like dropped or selected files
	document.addEventListener('paste',function(e){
		var cd=e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData);
		if (!cd || !cd.items) return;

		for (var i=0;i<cd.items.length;i++) {
			if (cd.items[i].kind==='file') {
				var bl=cd.items[i].getAsFile();
				if (bl) addFile(bl, bl.name);
				break;
			}
		}
		return;
	});

	// Automatically displays an already-selected single file on page load
	if (fileInput.files && fileInput.files.length === 1) {
		addFile(fileInput.files[0], fileInput.files[0].name);
	}
})();