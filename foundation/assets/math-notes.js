document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-mb-note-editor]').forEach(function (form) {
    var text = form.querySelector('[data-mb-note-content]');
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
