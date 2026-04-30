<?php
// esp32.php - Programador ESP32 Web con esptool-js real
// Requiere: HTTPS o localhost + Chrome/Edge con Web Serial API

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🔧 ESP32 Flash Tool Web</title>

  <!-- Polyfill Buffer inline -->
  <script>
  (function() {
    function BufferPolyfill(arg, encoding) {
      if (typeof arg === 'number') return new Uint8Array(arg);
      if (typeof arg === 'string') {
        if (encoding === 'base64') {
          const bin = atob(arg);
          const arr = new Uint8Array(bin.length);
          for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
          return arr;
        }
        return new TextEncoder().encode(arg);
      }
      return new Uint8Array(arg);
    }
    BufferPolyfill.from = function(arg, encoding) {
      if (typeof arg === 'string') {
        if (encoding === 'base64') {
          const bin = atob(arg);
          const arr = new Uint8Array(bin.length);
          for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
          return arr;
        }
        return new TextEncoder().encode(arg);
      }
      return new Uint8Array(arg);
    };
    BufferPolyfill.alloc = function(size, fill) {
      const arr = new Uint8Array(size);
      if (fill !== undefined) arr.fill(fill);
      return arr;
    };
    BufferPolyfill.allocUnsafe = function(size) { return new Uint8Array(size); };
    BufferPolyfill.concat = function(list, totalLength) {
      if (totalLength === undefined) totalLength = list.reduce((s, b) => s + b.length, 0);
      const result = new Uint8Array(totalLength);
      let offset = 0;
      for (const buf of list) { result.set(buf, offset); offset += buf.length; }
      return result;
    };
    BufferPolyfill.isBuffer   = function(obj) { return obj instanceof Uint8Array; };
    BufferPolyfill.isEncoding = function()    { return false; };
    window.Buffer = BufferPolyfill;
  })();
  </script>

  <!-- Cargar esptool-js como ES module y exponer globalmente -->
  <script type="module">
    import { ESPLoader, Transport } from './esptool-bundle.js';
    window.ESPLoader    = ESPLoader;
    window.Transport    = Transport;
    window.esptoolReady = true;
  </script>

  <style>
    :root {
      --primary: #2196F3; --success: #4CAF50; --error: #f44336;
      --warning: #FF9800; --info: #9C27B0; --bg: #1e1e2e;
      --card: #2a2a3e; --text: #e0e0e0; --mono: 'Fira Code', monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--bg); color: var(--text); line-height: 1.6;
      padding: 20px; max-width: 1000px; margin: 0 auto;
    }
    header { text-align: center; padding: 20px 0; border-bottom: 1px solid #444; margin-bottom: 25px; }
    header h1 { font-size: 1.9rem; margin-bottom: 5px; }
    header p { color: #aaa; }

    .card { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .card h3 { margin-bottom: 15px; color: var(--primary); display: flex; align-items: center; gap: 8px; }

    .status { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #333; border-radius: 8px; margin-bottom: 15px; font-family: var(--mono); font-size: 0.9rem; }
    .status.info    { border-left: 4px solid var(--primary); }
    .status.success { border-left: 4px solid var(--success); }
    .status.error   { border-left: 4px solid var(--error); }
    .status.warning { border-left: 4px solid var(--warning); }

    .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; font-weight: 500; }
    .btn:hover         { background: #1976D2; transform: translateY(-1px); }
    .btn:disabled      { background: #555; cursor: not-allowed; transform: none; opacity: 0.7; }
    .btn.danger        { background: var(--error); }
    .btn.danger:hover  { background: #d32f2f; }
    .btn.success       { background: var(--success); }
    .btn.success:hover { background: #388e3c; }
    .btn.warning       { background: var(--warning); color: #111; }
    .btn.warning:hover { background: #F57C00; }
    .btn.info          { background: var(--info); }
    .btn.release       { background: #607D8B; }
    .btn.release:hover { background: #455A64; }

    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }

    .partition-row {
      display: grid; grid-template-columns: 110px 1fr 90px auto;
      gap: 10px; align-items: center; padding: 12px;
      background: #252538; border-radius: 8px; margin-bottom: 10px;
    }
    .partition-row label   { font-size: 0.9rem; font-weight: 500; color: #ccc; }
    .partition-row .offset { font-family: var(--mono); color: var(--warning); font-size: 0.85rem; }
    .partition-row .size   { font-size: 0.8rem; color: #888; text-align: right; }
    .partition-row .remove { background: none; border: none; color: var(--error); cursor: pointer; font-size: 1.2rem; }

    #log {
      background: #111; border: 1px solid #444; border-radius: 8px; padding: 15px;
      font-family: var(--mono); font-size: 0.85rem; max-height: 350px; overflow-y: auto;
      white-space: pre-wrap; word-break: break-all;
    }
    #log .timestamp { color: #888; margin-right: 8px; }
    #log .error   { color: var(--error); }
    #log .success { color: var(--success); }
    #log .warning { color: var(--warning); }
    #log .info    { color: var(--primary); }

    .progress-container { margin: 15px 0; }
    .progress-bar { background: #333; border-radius: 4px; overflow: hidden; height: 24px; }
    .progress-fill {
      height: 100%; background: linear-gradient(90deg, var(--success), #66BB6A);
      transition: width 0.3s; width: 0%; display: flex;
      align-items: center; justify-content: center;
      color: white; font-size: 0.8rem; font-weight: 500;
    }
    .progress-label { font-size: 0.85rem; color: #aaa; margin-top: 5px; }

    .hidden  { display: none !important; }
    .divider { height: 1px; background: #444; margin: 20px 0; }

    details { margin: 10px 0; }
    summary { cursor: pointer; padding: 8px 0; color: var(--primary); font-weight: 500; }

    .https-warn {
      background: #3a1a00; border: 1px solid var(--warning); border-radius: 8px;
      padding: 12px 16px; margin-bottom: 15px; font-size: 0.9rem; color: #ffcc80;
    }

    .baud-select {
      background: #222; color: var(--text); border: 1px solid #555;
      border-radius: 6px; padding: 8px 12px; font-family: var(--mono); font-size: 0.9rem;
    }

    #loadingOverlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.75); display: flex; align-items: center;
      justify-content: center; z-index: 999; flex-direction: column; gap: 14px;
    }
    #loadingOverlay p { color: #fff; font-size: 1rem; }
    .spinner {
      width: 44px; height: 44px; border: 4px solid #444;
      border-top-color: var(--primary); border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    footer { text-align: center; padding: 20px; color: #777; font-size: 0.85rem; border-top: 1px solid #444; margin-top: 30px; }

    @media (max-width: 700px) {
      .partition-row { grid-template-columns: 1fr; }
      .btn-group { flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

  <div id="loadingOverlay">
    <div class="spinner"></div>
    <p>Cargando esptool-js...</p>
  </div>

  <header>
    <h1>🔧 ESP32 Flash Tool Web</h1>
    <p>Programa bootloader, partitions, firmware y LittleFS directamente desde el navegador</p>
  </header>

  <main>

    <div id="httpsWarn" class="https-warn hidden">
      ⚠️ <strong>Web Serial API requiere HTTPS o localhost.</strong>
      Accede por <code>https://192.168.1.162/esp32.php</code> o activa
      <code>brave://flags/#enable-experimental-web-platform-features</code> en Brave.
    </div>

    <div class="card">
      <div id="connectionStatus" class="status info">
        <span>🔌</span><span id="statusText">Conecta ESP32 por USB y pulsa "🔌 Conectar"</span>
      </div>

      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <label style="font-size:0.9rem;color:#aaa">Velocidad:</label>
        <select id="baudSelect" class="baud-select">
          <option value="115200" selected>115200 (ROM mode)</option>
          <option value="230400">230400</option>
          <option value="460800">460800</option>
          <option value="921600">921600</option>
        </select>
      </div>

      <div class="btn-group">
        <button id="btnConnect"    class="btn">🔌 Conectar ESP32</button>
        <button id="btnDisconnect" class="btn danger hidden">🔌 Desconectar</button>
        <button id="btnRelease"    class="btn release">🔓 Liberar Puerto</button>
        <button id="btnReset"      class="btn warning">🔄 Reset ESP32</button>
      </div>
    </div>

    <div class="card">
      <h3>📦 Particiones a Programar</h3>

      <div id="partitionsList">
        <div class="partition-row" data-offset="0x1000">
          <label>🔹 Bootloader</label>
          <input type="file" class="partition-file" accept=".bin" data-type="bootloader">
          <span class="offset">0x1000</span>
          <span class="size" id="size-bootloader"></span>
        </div>
        <div class="partition-row" data-offset="0x8000">
          <label>🗂️ Partitions</label>
          <input type="file" class="partition-file" accept=".bin" data-type="partitions">
          <span class="offset">0x8000</span>
          <span class="size" id="size-partitions"></span>
        </div>
        <div class="partition-row" data-offset="0x10000">
          <label>🚀 Firmware</label>
          <input type="file" class="partition-file" accept=".bin" data-type="firmware">
          <span class="offset">0x10000</span>
          <span class="size" id="size-firmware"></span>
        </div>
        <div class="partition-row" data-offset="0x300000">
          <label>📁 LittleFS</label>
          <input type="file" class="partition-file" accept=".bin" data-type="littlefs">
          <span class="offset">0x300000</span>
          <span class="size" id="size-littlefs"></span>
        </div>
      </div>

      <details style="margin-top:15px">
        <summary>⚙️ Añadir partición personalizada</summary>
        <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
          <input type="text" id="customOffset" placeholder="Offset (ej: 0x20000)"
            style="padding:8px;border-radius:6px;border:1px solid #555;background:#222;color:white;font-family:var(--mono)">
          <input type="file" id="customFile" accept=".bin" style="flex:1">
          <button id="btnAddCustom" class="btn">➕ Añadir</button>
        </div>
      </details>

      <div class="divider"></div>

      <div class="btn-group">
        <button id="btnErase"  class="btn danger">🗑️ Borrar Flash Completo</button>
        <button id="btnFlash"  class="btn success" disabled>🚀 Programar Seleccionadas</button>
        <button id="btnVerify" class="btn info"    disabled>🔍 Verificar</button>
      </div>

      <div id="progressContainer" class="progress-container hidden">
        <div class="progress-bar">
          <div id="progressFill" class="progress-fill">0%</div>
        </div>
        <div id="progressLabel" class="progress-label">Esperando...</div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3>📋 Consola</h3>
        <div style="display:flex;gap:8px">
          <button id="btnClear"    class="btn" style="padding:6px 12px;font-size:0.85rem">🗑️ Limpiar</button>
          <button id="btnDownload" class="btn" style="padding:6px 12px;font-size:0.85rem">💾 Guardar</button>
        </div>
      </div>
      <div id="log"></div>
    </div>

  </main>

  <footer>
    <p>🔐 Web Serial API • Chrome/Edge/Brave (HTTPS) • esptool-js (ROM mode)</p>
    <p>🔗 <?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']); ?></p>
  </footer>

  <script>
    let port          = null;
    let esploader     = null;
    let transport     = null;
    let logBuffer     = [];
    let selectedFiles = {};

    const $ = id => document.getElementById(id);
    const els = {
      status:            $('connectionStatus'),
      statusText:        $('statusText'),
      btnConnect:        $('btnConnect'),
      btnDisconnect:     $('btnDisconnect'),
      btnRelease:        $('btnRelease'),
      btnReset:          $('btnReset'),
      btnErase:          $('btnErase'),
      btnFlash:          $('btnFlash'),
      btnVerify:         $('btnVerify'),
      progressContainer: $('progressContainer'),
      progressFill:      $('progressFill'),
      progressLabel:     $('progressLabel'),
      log:               $('log'),
      btnClear:          $('btnClear'),
      btnDownload:       $('btnDownload'),
      partitionsList:    $('partitionsList'),
      btnAddCustom:      $('btnAddCustom'),
      customOffset:      $('customOffset'),
      customFile:        $('customFile'),
      baudSelect:        $('baudSelect'),
      httpsWarn:         $('httpsWarn'),
      loadingOverlay:    $('loadingOverlay'),
    };

    const ts = () => `[${new Date().toLocaleTimeString('es-ES')}]`;

    function log(msg, type = 'info') {
      if (!msg || !msg.toString().trim()) return;
      const line  = document.createElement('div');
      const clean = msg.toString().replace(/</g,'&lt;').replace(/>/g,'&gt;');
      line.innerHTML = `<span class="timestamp">${ts()}</span><span class="${type}">${clean}</span>`;
      els.log.appendChild(line);
      logBuffer.push(`${ts()} [${type}] ${msg}`);
      if (els.log.children.length > 600) els.log.removeChild(els.log.firstChild);
      els.log.scrollTop = els.log.scrollHeight;
    }

    function updateStatus(text, type = 'info') {
      els.statusText.textContent = text;
      els.status.className = `status ${type}`;
    }

    function formatBytes(bytes) {
      if (!bytes) return '';
      const u = ['B','KB','MB','GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(1024));
      return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${u[i]}`;
    }

    function parseOffset(str) {
      str = str.trim().toLowerCase();
      return str.startsWith('0x') ? parseInt(str, 16) : parseInt(str, 10);
    }

    function arrayBufferToBstr(buffer) {
      const bytes = new Uint8Array(buffer);
      let str = '';
      for (const b of bytes) str += String.fromCharCode(b);
      return str;
    }

    // ================================
    // 🔓 LIBERAR TODOS LOS PUERTOS
    // ================================
    async function releasePort() {
      log('🔓 Liberando puertos...', 'warning');

      if (transport) {
        try {
          if (transport.reader) {
            try { await transport.reader.cancel(); } catch(e) {}
            try { transport.reader.releaseLock(); } catch(e) {}
          }
          await transport.disconnect();
        } catch(e) {}
        transport = null;
      }

      if (port) {
        try { await port.close(); } catch(e) {}
        port = null;
      }

      try {
        const ports = await navigator.serial.getPorts();
        for (const p of ports) {
          try { await p.close(); } catch(e) {}
        }
      } catch(e) {}

      transport = null; port = null; esploader = null;

      updateStatus('🔓 Puerto liberado — pulsa Conectar', 'info');
      els.btnConnect.classList.remove('hidden');
      els.btnDisconnect.classList.add('hidden');
      els.btnFlash.disabled  = true;
      els.btnVerify.disabled = true;
      log('✅ Puerto liberado — ahora pulsa 🔌 Conectar ESP32', 'success');
    }

    // ================================
    // 🔌 CONEXIÓN — runStub() sobreescrito para saltar stub
    // ================================
    async function connectPort() {
      try {
        // Cerrar puertos previos silenciosamente
        try {
          const ports = await navigator.serial.getPorts();
          for (const p of ports) { try { await p.close(); } catch(e) {} }
        } catch(e) {}

        log('🔍 Solicitando puerto serie...');
        updateStatus('⏳ Selecciona el puerto USB del ESP32', 'warning');

        port = await navigator.serial.requestPort();
        transport = new window.Transport(port, true);

        const terminal = {
          clean:     ()    => {},
          writeLine: (msg) => log(msg),
          write:     (msg) => { if (msg && msg.trim()) log(msg); },
        };

        esploader = new window.ESPLoader({
          transport,
          baudrate:    115200,
          romBaudrate: 115200,
          terminal,
          debugLogging: false,
        });

        // ⚡ CLAVE: sobreescribir runStub para que no suba el stub
        // Esto evita el timeout y el doble open del puerto
        esploader.runStub = async function() {
          log('⏭️ Saltando stub (ROM mode directo)', 'info');
          this.IS_STUB = false;
          return this.chip;
        };

        log('⏳ Conectando con el chip...', 'info');
        const chip = await esploader.main();
        log(`✅ Chip: ${chip}`, 'success');

        updateStatus(`✅ Conectado — ${chip}`, 'success');
        els.btnConnect.classList.add('hidden');
        els.btnDisconnect.classList.remove('hidden');
        updateFlashButtonState();

      } catch (err) {
        log(`❌ Error conexión: ${err.message}`, 'error');
        if (err.message?.includes('already open')) {
          log('💡 Puerto ocupado — pulsa 🔓 Liberar Puerto y vuelve a intentarlo', 'warning');
        } else if (err.message?.includes('in use')) {
          log('💡 Puerto en uso — cierra Arduino IDE / Monitor Serie', 'warning');
        }
        updateStatus('❌ Error de conexión', 'error');
        port = null; transport = null; esploader = null;
      }
    }

    async function disconnectPort() {
      try { if (transport) await transport.disconnect(); } catch(e) {}
      try { if (port) await port.close(); } catch(e) {}
      transport = null; esploader = null; port = null;
      updateStatus('🔌 Desconectado', 'info');
      els.btnConnect.classList.remove('hidden');
      els.btnDisconnect.classList.add('hidden');
      els.btnFlash.disabled  = true;
      els.btnVerify.disabled = true;
      log('🔌 Desconectado correctamente');
    }

    // ================================
    // 🗑️ BORRAR FLASH
    // ================================
    async function eraseFlash() {
      if (!esploader) { log('❌ Primero conecta el ESP32', 'error'); return; }
      if (!confirm('⚠️ ¿Borrar TODO el flash del ESP32?\n\nSe perderán firmware, particiones y datos.')) return;

      try {
        log('🗑️ Borrando flash completo...', 'warning');
        updateStatus('🗑️ Borrando flash...', 'warning');
        els.btnErase.disabled = true;
        els.progressContainer.classList.remove('hidden');
        els.progressLabel.textContent = 'Borrando... (puede tardar 30-60s)';

        await esploader.eraseFlash();

        els.progressFill.style.width = '100%';
        els.progressFill.textContent = '100%';
        log('✅ Flash borrado correctamente', 'success');
        updateStatus('✅ Flash vacío', 'success');
      } catch (err) {
        log(`❌ Error en borrado: ${err.message}`, 'error');
        updateStatus('❌ Error en borrado', 'error');
      } finally {
        setTimeout(() => {
          els.progressContainer.classList.add('hidden');
          els.progressFill.style.width = '0%';
          els.progressFill.textContent = '0%';
        }, 1500);
        els.btnErase.disabled = false;
      }
    }

    // ================================
    // 🚀 PROGRAMAR
    // ================================
    async function flashPartitions() {
      const files = Object.values(selectedFiles);
      if (!files.length) { log('⚠️ Selecciona al menos un archivo .bin', 'warning'); return; }
      if (!esploader)    { log('❌ Primero conecta el ESP32', 'error'); return; }

      try {
        log(`🚀 Programando ${files.length} partición(es)...`, 'info');
        updateStatus('🚀 Programando...', 'warning');
        els.btnFlash.disabled = true;
        els.btnErase.disabled = true;
        els.progressContainer.classList.remove('hidden');

        const fileArray = [];
        for (const { file, offset, type } of files) {
          log(`📦 Cargando ${type}: ${file.name} @ ${offset} (${formatBytes(file.size)})`);
          const buffer = await file.arrayBuffer();
          fileArray.push({ data: arrayBufferToBstr(buffer), address: parseOffset(offset) });
        }

        await esploader.writeFlash({
          fileArray,
          flashSize: 'keep',
          flashMode: 'keep',
          flashFreq: 'keep',
          eraseAll:  false,
          compress:  true,
          reportProgress(fileIndex, written, total) {
            const pct = Math.round((written / total) * 100);
            els.progressFill.style.width  = `${pct}%`;
            els.progressFill.textContent  = `${pct}%`;
            const f = files[fileIndex];
            els.progressLabel.textContent = `Grabando ${f?.type ?? 'archivo ' + (fileIndex+1)} — ${formatBytes(written)} / ${formatBytes(total)}`;
          },
          calculateMD5Hash() { return ''; },
        });

        log('✅ ¡Programación completada!', 'success');
        updateStatus('✅ ESP32 programado correctamente', 'success');
        await esploader.hardReset();
        log('🔄 Reset realizado — el ESP32 ejecuta el nuevo firmware', 'success');

      } catch (err) {
        log(`❌ Error en flash: ${err.message}`, 'error');
        updateStatus('❌ Error en programación', 'error');
      } finally {
        setTimeout(() => {
          els.progressContainer.classList.add('hidden');
          els.progressFill.style.width  = '0%';
          els.progressFill.textContent  = '0%';
          els.progressLabel.textContent = '';
        }, 2000);
        els.btnErase.disabled = false;
        updateFlashButtonState();
      }
    }

    // ================================
    // 🔍 VERIFICAR
    // ================================
    async function verifyFlash() {
      if (!esploader) { log('❌ Conecta el ESP32 primero', 'error'); return; }
      const files = Object.values(selectedFiles);
      if (!files.length) { log('⚠️ Selecciona archivos para verificar', 'warning'); return; }

      log('🔍 Verificando flash...', 'info');
      updateStatus('🔍 Verificando...', 'warning');

      try {
        for (const { file, offset, type } of files) {
          const buffer   = await file.arrayBuffer();
          const written  = arrayBufferToBstr(buffer);
          const addr     = parseOffset(offset);
          const readBack = await esploader.readFlash(addr, written.length);
          let match = true;
          for (let i = 0; i < written.length; i++) {
            if (written.charCodeAt(i) !== readBack.charCodeAt(i)) { match = false; break; }
          }
          log(match ? `✅ ${type} @ ${offset} — OK` : `❌ ${type} @ ${offset} — FALLO`,
              match ? 'success' : 'error');
        }
        updateStatus('✅ Verificación completada', 'success');
      } catch (err) {
        log(`❌ Error verificación: ${err.message}`, 'error');
        updateStatus('❌ Error en verificación', 'error');
      }
    }

    // ================================
    // 🔄 RESET
    // ================================
    async function resetESP32() {
      if (!esploader) { log('❌ Conecta el ESP32 primero', 'error'); return; }
      try {
        await esploader.hardReset();
        log('🔄 Reset enviado', 'success');
      } catch (err) {
        log(`⚠️ Error reset: ${err.message}`, 'warning');
      }
    }

    // ================================
    // 🎛️ UI
    // ================================
    function updateFlashButtonState() {
      const hasFiles = Object.keys(selectedFiles).length > 0;
      els.btnFlash.disabled  = !esploader || !hasFiles;
      els.btnVerify.disabled = !esploader || !hasFiles;
    }

    function setupPartitionInputs() {
      document.querySelectorAll('.partition-file').forEach(input => {
        input.addEventListener('change', e => {
          const file   = e.target.files[0];
          if (!file) return;
          const row    = e.target.closest('.partition-row');
          const type   = e.target.dataset.type;
          const offset = row.dataset.offset;
          selectedFiles[type] = { file, offset, type };
          const sizeEl = row.querySelector('.size');
          sizeEl.textContent = formatBytes(file.size);
          sizeEl.style.color = '#4CAF50';
          log(`📄 ${type}: ${file.name} (${formatBytes(file.size)}) @ ${offset}`);
          updateFlashButtonState();
        });
      });
    }

    function addCustomPartition() {
      const offset = els.customOffset.value.trim();
      const file   = els.customFile.files[0];
      if (!offset || !file) { log('⚠️ Offset y archivo requeridos', 'warning'); return; }
      if (!/^0x[0-9a-fA-F]+$/.test(offset) && !/^\d+$/.test(offset)) {
        log('❌ Offset inválido', 'error'); return;
      }
      const type = `custom_${Date.now()}`;
      selectedFiles[type] = { file, offset, type };
      const row = document.createElement('div');
      row.className      = 'partition-row';
      row.dataset.offset = offset;
      row.innerHTML = `
        <label>🔸 Custom</label>
        <span style="color:#ccc;font-size:0.85rem">${file.name}</span>
        <span class="offset">${offset}</span>
        <button class="remove" title="Eliminar">&times;</button>
      `;
      row.querySelector('.remove').onclick = () => {
        delete selectedFiles[type]; row.remove(); updateFlashButtonState();
        log(`🗑️ Eliminada partición ${offset}`);
      };
      els.partitionsList.appendChild(row);
      log(`➕ Custom: ${file.name} @ ${offset} (${formatBytes(file.size)})`);
      els.customOffset.value = '';
      els.customFile.value   = '';
      updateFlashButtonState();
    }

    function setupDragDrop() {
      document.querySelectorAll('.partition-row').forEach(row => {
        row.addEventListener('dragover',  e => { e.preventDefault(); row.style.outline = '2px dashed var(--primary)'; });
        row.addEventListener('dragleave', () => { row.style.outline = ''; });
        row.addEventListener('drop', e => {
          e.preventDefault(); row.style.outline = '';
          const file  = e.dataTransfer.files[0];
          const input = row.querySelector('input[type="file"]');
          if (file?.name.endsWith('.bin') && input) {
            const dt = new DataTransfer(); dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
          }
        });
      });
    }

    function clearLog()    { els.log.innerHTML = ''; logBuffer = []; log('🗑️ Consola limpiada'); }
    function downloadLog() {
      if (!logBuffer.length) { log('⚠️ Sin logs', 'warning'); return; }
      const blob = new Blob([logBuffer.join('\n')], { type: 'text/plain' });
      const a    = document.createElement('a');
      a.href     = URL.createObjectURL(blob);
      a.download = `esp32_flash_${new Date().toISOString().slice(0,10)}.log`;
      a.click(); URL.revokeObjectURL(a.href);
      log('💾 Log guardado');
    }

    // ================================
    // 🚀 INIT
    // ================================
    function init() {
      els.loadingOverlay.style.display = 'none';
      log('🚀 ESP32 Flash Tool Web cargado (ROM mode)');
      log(`🔗 URL: ${window.location.href}`);

      if (!('serial' in navigator)) {
        log('❌ Web Serial API no disponible', 'error');
        log('💡 Usa Chrome/Edge o activa brave://flags/#enable-experimental-web-platform-features', 'warning');
        updateStatus('🚫 Usa Chrome/Edge con HTTPS', 'error');
        els.httpsWarn.classList.remove('hidden');
        els.btnConnect.disabled = true;
        return;
      }

      if (!window.isSecureContext) {
        log('⚠️ Contexto no seguro: Web Serial puede no funcionar', 'warning');
        els.httpsWarn.classList.remove('hidden');
      }

      if (!window.ESPLoader || !window.Transport) {
        log('❌ esptool-js no cargado. Verifica que esptool-bundle.js está en /var/www/html/', 'error');
        updateStatus('❌ esptool-js no disponible', 'error');
        els.btnConnect.disabled = true;
        return;
      }

      log('✅ esptool-js listo', 'success');
      log('💡 Si el puerto aparece ocupado pulsa 🔓 Liberar Puerto', 'info');

      els.btnConnect.onclick    = connectPort;
      els.btnDisconnect.onclick = disconnectPort;
      els.btnRelease.onclick    = releasePort;
      els.btnReset.onclick      = resetESP32;
      els.btnErase.onclick      = eraseFlash;
      els.btnFlash.onclick      = flashPartitions;
      els.btnVerify.onclick     = verifyFlash;
      els.btnAddCustom.onclick  = addCustomPartition;
      els.btnClear.onclick      = clearLog;
      els.btnDownload.onclick   = downloadLog;

      setupPartitionInputs();
      setupDragDrop();

      navigator.serial.addEventListener('disconnect', e => {
        if (port && e.target === port) {
          log('🔌 ESP32 desconectado físicamente', 'warning');
          disconnectPort();
        }
      });

      window.addEventListener('beforeunload', async () => {
        try { if (transport) await transport.disconnect(); } catch(e) {}
        try { if (port) await port.close(); } catch(e) {}
      });

      log('✅ Listo. Conecta el ESP32 y selecciona los archivos .bin');
    }

    window.addEventListener('load', () => {
      let elapsed = 0;
      const check = setInterval(() => {
        elapsed += 50;
        if (window.ESPLoader && window.Transport) {
          clearInterval(check); init(); return;
        }
        if (elapsed >= 5000) {
          clearInterval(check);
          els.loadingOverlay.style.display = 'none';
          log('❌ Timeout cargando esptool-js. Comprueba que esptool-bundle.js está en /var/www/html/', 'error');
          updateStatus('❌ Error cargando esptool-js', 'error');
        }
      }, 50);
    });
  </script>
</body>
</html>
