document.addEventListener('DOMContentLoaded', function () {
  var canvasAssignmentAction = document.querySelector('form [name="action"][value="mb_canvas_save_assignment"]');
  if (canvasAssignmentAction) {
    var canvasAssignmentForm = canvasAssignmentAction.form;
    var launchTokenSource = document.querySelector('[data-mb-canvas-launch-token]');
    var serverLaunchField = canvasAssignmentForm.querySelector('[name="mb_canvas_launch"]');
    var launchToken = (serverLaunchField && serverLaunchField.value) || (launchTokenSource && launchTokenSource.dataset.mbCanvasLaunchToken) || new URL(window.location.href).searchParams.get('mb_canvas_launch') || '';
    var syncCanvasAssignmentFields = function () {
      var marker = canvasAssignmentForm.querySelector('[name="mb_canvas_assignment_submit"]');
      if (!marker) { marker = document.createElement('input'); marker.type = 'hidden'; marker.name = 'mb_canvas_assignment_submit'; canvasAssignmentForm.appendChild(marker); }
      marker.value = '1';
      var launchField = canvasAssignmentForm.querySelector('[name="mb_canvas_launch"]');
      if (!launchField) { launchField = document.createElement('input'); launchField.type = 'hidden'; launchField.name = 'mb_canvas_launch'; canvasAssignmentForm.appendChild(launchField); }
      if (launchToken) launchField.value = launchToken;
    };
    syncCanvasAssignmentFields();
    canvasAssignmentForm.addEventListener('submit', syncCanvasAssignmentFields);
    canvasAssignmentForm.addEventListener('formdata', function (event) {
      event.formData.set('mb_canvas_assignment_submit', '1');
      if (launchToken) event.formData.set('mb_canvas_launch', launchToken);
    });
  }

  var lessonNoteTarget = document.querySelector('[data-mb-note-content]');
  document.querySelectorAll('.mb-vocab-add-note').forEach(function (button) {
    if (!lessonNoteTarget) { button.hidden = true; return; }
    button.addEventListener('click', function () {
      var vocabulary = button.dataset.mbVocabularyText || '';
      if (!vocabulary) return;
      var separator = lessonNoteTarget.value.trim() ? '\n' : '';
      lessonNoteTarget.value += separator + vocabulary;
      lessonNoteTarget.dispatchEvent(new Event('input', { bubbles: true }));
      lessonNoteTarget.focus();
      lessonNoteTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
      button.textContent = 'Added to My Notes ✓';
    });
  });

  document.querySelectorAll('[data-mb-note-editor]').forEach(function (form) {
    form.enctype = 'multipart/form-data';
    var text = form.querySelector('[data-mb-note-content]');
    if (text) text.required = false;
    if (form.closest('.mb-notes-workspace-main')) {
      var title = form.querySelector('[name="note_title"]');
      var titleLabel = title && title.closest('label');
      if (titleLabel && titleLabel.firstChild) titleLabel.firstChild.nodeValue = 'Work title';
      var textLabel = text && text.closest('label');
      if (textLabel && textLabel.firstChild) textLabel.firstChild.nodeValue = 'My written work';
      var save = form.querySelector('button[type="submit"]');
      var noteId = form.querySelector('[name="note_id"]');
      if (save) save.textContent = noteId && noteId.value ? 'Save Changes' : 'Create Work';
    }
    form.querySelectorAll('[data-mb-symbol]').forEach(function (button) {
      button.addEventListener('click', function () {
        var start = text.selectionStart || 0;
        var end = text.selectionEnd || 0;
        text.value = text.value.slice(0, start) + button.dataset.mbSymbol + text.value.slice(end);
        text.focus();
        text.setSelectionRange(start + button.dataset.mbSymbol.length, start + button.dataset.mbSymbol.length);
      });
    });

    var canvas = form.querySelector('[data-mb-drawing]');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var hidden = form.querySelector('[data-mb-drawing-data]');
    var color = form.querySelector('[data-mb-draw-color]');
    var size = form.querySelector('[data-mb-draw-size]');
    var tool = 'pen';
    var drawing = false;
    var history = [];

    var upload = form.querySelector('[data-mb-assignment-upload]');
    if (!upload) {
      var uploadBox = document.createElement('div');
      uploadBox.className = 'mb-assignment-upload';
      uploadBox.innerHTML = '<label><strong>Upload a screenshot or assignment</strong><input type="file" name="assignment_file" accept=".jpg,.jpeg,.png,.webp,.pdf" data-mb-assignment-upload></label><small>JPG, PNG, WebP, or PDF up to 8 MB. Images open on the drawing board for annotation; PDFs remain attached while you write or draw.</small>';
      var workspace = form.querySelector('.mb-drawing-workspace');
      if (workspace) workspace.parentNode.insertBefore(uploadBox, workspace);
      upload = uploadBox.querySelector('[data-mb-assignment-upload]');
    }

    function whiteBackground() {
      ctx.save(); ctx.globalCompositeOperation = 'destination-over'; ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height); ctx.restore();
    }
    function snapshot() { if (history.length > 20) history.shift(); history.push(canvas.toDataURL('image/png')); }
    function load(source, record) {
      ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      if (!source) { if (record) snapshot(); return; }
      var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); if (record) snapshot(); }; img.src = source;
    }
    load(hidden.value, true);

    if (upload) upload.addEventListener('change', function () {
      var file = upload.files && upload.files[0];
      if (!file || file.type.indexOf('image/') !== 0) return;
      var reader = new FileReader();
      reader.onload = function () {
        var image = new Image();
        image.onload = function () {
          var scale = Math.min(1, 1200 / image.width, 1200 / image.height);
          canvas.width = Math.max(1, Math.round(image.width * scale));
          canvas.height = Math.max(1, Math.round(image.height * scale));
          canvas.classList.add('has-upload-background');
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
          history = []; snapshot();
        };
        image.src = reader.result;
      };
      reader.readAsDataURL(file);
    });

    function point(event) {
      var rect = canvas.getBoundingClientRect();
      var touch = event.touches && event.touches[0];
      return { x: ((touch ? touch.clientX : event.clientX) - rect.left) * canvas.width / rect.width, y: ((touch ? touch.clientY : event.clientY) - rect.top) * canvas.height / rect.height };
    }
    function start(event) {
      event.preventDefault(); drawing = true; snapshot(); var p = point(event); ctx.beginPath(); ctx.moveTo(p.x, p.y);
    }
    function move(event) {
      if (!drawing) return; event.preventDefault(); var p = point(event);
      ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = Number(size.value);
      ctx.globalCompositeOperation = tool === 'eraser' ? 'destination-out' : 'source-over';
      ctx.globalAlpha = tool === 'highlighter' ? 0.25 : 1; ctx.strokeStyle = color.value;
      ctx.lineTo(p.x, p.y); ctx.stroke();
    }
    function stop() { if (!drawing) return; drawing = false; ctx.closePath(); ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over'; }
    canvas.addEventListener('pointerdown', start); canvas.addEventListener('pointermove', move); window.addEventListener('pointerup', stop);

    form.querySelectorAll('[data-tool]').forEach(function (button) {
      button.addEventListener('click', function () {
        var action = button.dataset.tool;
        if (action === 'undo') { var previous = history.pop(); if (previous) load(previous, false); return; }
        if (action === 'clear') { snapshot(); load('', false); return; }
        tool = action;
        form.querySelectorAll('[data-tool="pen"],[data-tool="highlighter"],[data-tool="eraser"]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      });
    });
    form.addEventListener('submit', function () { whiteBackground(); hidden.value = canvas.toDataURL('image/webp', 0.82); });
  });
});
