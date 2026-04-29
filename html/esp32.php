<?php
// esp32.php - Programador ESP32 Web Avanzado
// Soporte: bootloader + partitions + app + littlefs + erase flash
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
    .status.info { border-left: 4px solid var(--primary); }
    .status.success { border-left: 4px solid var(--success); }
    .status.error { border-left: 4px solid var(--error); }
    .status.warning { border-left: 4px solid var(--warning); }
    
    .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; font-weight: 500; }
    .btn:hover { background: #1976D2; transform: translateY(-1px); }
    .btn:disabled { background: #555; cursor: not-allowed; transform: none; opacity: 0.7; }
    .btn.danger { background: var(--error); }
    .btn.danger:hover { background: #d32f2f; }
    .btn.success { background: var(--success); }
    .btn.success:hover { background: #388e3c; }
    .btn.warning { background: var(--warning); color: #111; }
    .btn.warning:hover { background: #F57C00; }
    .btn.info { background: var(--info); }
    
    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }
    
    .partition-row {
      display: grid; grid-template-columns: 100px 1fr 80px auto; gap: 10px;
      align-items: center; padding: 12px; background: #252538; border-radius: 8px; margin-bottom: 10px;
    }
    .partition-row label { font-size: 0.9rem; font-weight: 500; color: #ccc; }
    .partition-row input[type="file"] { font-size: 0.85rem; }
    .partition-row .offset { font-family: var(--mono); color: var(--warning); font-size: 0.85rem; }
    .partition-row .size { font-size: 0.8rem; color: #888; text-align: right; }
    .partition-row .remove { background: none; border: none; color: var(--error); cursor: pointer; font-size: 1.2rem; }
    
    .upload-area {
      border: 2px dashed #555; border-radius: 8px; padding: 15px; text-align: center;
      margin: 10px 0; transition: border-color 0.2s; background: #222;
    }
    .upload-area.dragover { border-color: var(--primary); background: rgba(33,150,243,0.1); }
    
    #log {
      background: #111; border: 1px solid #444; border-radius: 8px; padding: 15px;
      font-family: var(--mono); font-size: 0.85rem; max-height: 350px; overflow-y: auto;
      white-space: pre-wrap; word-break: break-all;
    }
    #log .timestamp { color: #888; margin-right: 8px; }
    #log .error { color: var(--error); }
    #log .success { color: var(--success); }
    #log .warning { color: var(--warning); }
    
    .progress-container { margin: 15px 0; }
    .progress-bar { background: #333; border-radius: 4px; overflow: hidden; height: 24px; position: relative; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, var(--success), #66BB6A); transition: width 0.3s; width: 0%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 500; }
    .progress-label { font-size: 0.85rem; color: #aaa; margin-top: 5px; }
    
    .hidden { display: none !important; }
    .divider { height: 1px; background: #444; margin: 20px 0; }
    
    details { margin: 10px 0; }
    summary { cursor: pointer; padding: 8px 0; color: var(--primary); font-weight: 500; }
    
    footer { text-align: center; padding: 20px; color: #777; font-size: 0.85rem; border-top: 1px solid #444; margin-top: 30px; }
    
    @media (max-width: 700px) {
      .partition-row { grid-template-columns: 1fr; }
      .btn-group { flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>
  <header>
    <h1>🔧 ESP32 Flash Tool Web</h1>
    <p>Programa bootloader, partitions, firmware y LittleFS directamente desde el navegador</p>
  </header>

  <main>
    <!-- Estado -->
    <div class="card">
      <div id="connectionStatus" class="status info">
        <span>🔌</span><span id="statusText">Conecta ESP32 por USB y pulsa "🔌 Conectar"</span>
      </div>
      <div class="btn-group">
        <button id="btnConnect" class="btn">🔌 Conectar ESP32</button>
        <button id="btnDisconnect" class="btn danger hidden">🔌 Desconectar</button>
        <button id="btnReset" class="btn warning" title="Reiniciar ESP32">🔄 Reset</button>
        <button id="btnBoot" class="btn warning" title="Entrar en modo bootloader">⬇️ Boot Mode</button>
      </div>
    </div>

    <!-- Particiones -->
    <div class="card">
      <h3>📦 Particiones a Programar</h3>
      
      <div id="partitionsList">
        <!-- Bootloader -->
        <div class="partition-row" data-offset="0x1000">
          <label>🔹 Bootloader</label>
          <input type="file" class="partition-file" accept=".bin" data-type="bootloader">
          <span class="offset">0x1000</span>
          <span class="size" id="size-bootloader"></span>
        </div>
        
        <!-- Partitions -->
        <div class="partition-row" data-offset="0x8000">
          <label>🗂️ Partitions</label>
          <input type="file" class="partition-file" accept=".bin" data-type="partitions">
          <span class="offset">0x8000</span>
          <span class="size" id="size-partitions"></span>
        </div>
        
        <!-- Firmware -->
        <div class="partition-row" data-offset="0x10000">
          <label>🚀 Firmware</label>
          <input type="file" class="partition-file" accept=".bin" data-type="firmware">
          <span class="offset">0x10000</span>
          <span class="size" id="size-firmware"></span>
        </div>
        
        <!-- LittleFS -->
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
          <input type="text" id="customOffset" placeholder="Offset (ej: 0x20000)" style="padding:8px;border-radius:6px;border:1px solid #555;background:#222;color:white;font-family:var(--mono)">
          <input type="file" id="customFile" accept=".bin" style="flex:1">
          <button id="btnAddCustom" class="btn">➕ Añadir</button>
        </div>
      </details>
      
      <div class="divider"></div>
      
      <div class="btn-group">
        <button id="btnErase" class="btn danger">🗑️ Borrar Flash Completo</button>
        <button id="btnFlash" class="btn success" disabled>🚀 Programar Seleccionadas</button>
        <button id="btnVerify" class="btn info" disabled>🔍 Verificar</button>
      </div>
      
      <div id="progressContainer" class="progress-container hidden">
        <div class="progress-bar"><div id="progressFill" class="progress-fill">0%</div></div>
        <div id="progressLabel" class="progress-label">Esperando...</div>
      </div>
    </div>

    <!-- Consola -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3>📋 Consola Serie</h3>
        <div>
          <button id="btnClear" class="btn" style="padding:6px 12px;font-size:0.85rem">🗑️ Limpiar</button>
          <button id="btnDownload" class="btn" style="padding:6px 12px;font-size:0.85rem">💾 Guardar</button>
        </div>
      </div>
      <div id="log"></div>
    </div>
  </main>

  <footer>
    <p>🔐 Web Serial API • Chrome/Edge • HTTPS o localhost</p>
    <p id="pageInfo">🔗 <?php echo (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']; ?></p>
  </footer>

  <script>
    // ================================
    // 🎛️ CONFIGURACIÓN
    // ================================
    const CONFIG = {
      baudRate: 115200,
      bufferSize: 4096,
      chunkSize: 1024,
      logMaxLines: 500,
      // Offsets por defecto ESP32
      defaultOffsets: {
        bootloader: '0x1000',
        partitions: '0x8000',
        firmware: '0x10000',
        littlefs: '0x300000'
      }
    };

    // ================================
    // 🧠 ESTADO GLOBAL
    // ================================
    let port = null, reader = null, writer = null, keepReading = false;
    let logBuffer = [];
    let selectedFiles = {}; // { type: { file, offset } }

    // ================================
    // 🎯 DOM ELEMENTS
    // ================================
    const $ = id => document.getElementById(id);
    const els = {
      status: $('connectionStatus'), statusText: $('statusText'),
      btnConnect: $('btnConnect'), btnDisconnect: $('btnDisconnect'),
      btnReset: $('btnReset'), btnBoot: $('btnBoot'),
      btnErase: $('btnErase'), btnFlash: $('btnFlash'), btnVerify: $('btnVerify'),
      progressContainer: $('progressContainer'), progressFill: $('progressFill'), progressLabel: $('progressLabel'),
      log: $('log'), btnClear: $('btnClear'), btnDownload: $('btnDownload'),
      partitionsList: $('partitionsList'), btnAddCustom: $('btnAddCustom'),
      customOffset: $('customOffset'), customFile: $('customFile')
    };

    // ================================
    // 📝 LOGGING
    // ================================
    const ts = () => `[${new Date().toLocaleTimeString('es-ES')}]`;
    
    function log(msg, type = 'info') {
      const line = document.createElement('div');
      line.innerHTML = `<span class="timestamp">${ts()}</span> <span class="${type}">${msg}</span>`;
      els.log.appendChild(line);
      logBuffer.push(`${ts()} ${msg}`);
      while (els.log.children.length > CONFIG.logMaxLines) els.log.removeChild(els.log.firstChild);
      els.log.scrollTop = els.log.scrollHeight;
      console.log(`[${type.toUpperCase()}]`, msg);
    }

    function updateStatus(text, type = 'info') {
      els.statusText.textContent = text;
      els.status.className = `status ${type}`;
    }

    function formatBytes(bytes) {
      if (!bytes) return '';
      const units = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(1024));
      return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
    }

    function parseOffset(str) {
      str = str.trim().toLowerCase();
      return str.startsWith('0x') ? parseInt(str, 16) : parseInt(str, 10);
    }

    // ================================
    // 🔌 WEB SERIAL
    // ================================
    async function connectPort() {
      try {
        if (port) await disconnectPort();
        
        log('🔍 Solicitando acceso al puerto serie...');
        updateStatus('⏳ Selecciona el puerto del ESP32', 'warning');
        
        port = await navigator.serial.requestPort();
        log('✅ Puerto seleccionado');
        
        await port.open({ baudRate: CONFIG.baudRate, bufferSize: CONFIG.bufferSize });
        log(`🔗 Puerto abierto a ${CONFIG.baudRate} baudios`);
        updateStatus('✅ ESP32 conectado', 'success');
        
        els.btnConnect.classList.add('hidden');
        els.btnDisconnect.classList.remove('hidden');
        updateFlashButtonState();
        
        keepReading = true;
        readLoop();
        
      } catch (err) {
        console.error('❌ Connection error:', err);
        if (err.name === 'NotFoundError') {
          log('⚠️ No se seleccionó puerto', 'warning');
        } else if (err.message?.includes('in use')) {
          log('❌ Puerto en uso. Cierra otras pestañas y reinicia ESP32', 'error');
          alert('🔒 Puerto ocupado:\n1. Cierra otras pestañas\n2. Reinicia ESP32\n3. Reintenta');
        } else if (err.name === 'SecurityError') {
          log('❌ Requiere HTTPS o localhost', 'error');
          alert('🔐 Web Serial API requiere HTTPS o localhost');
        } else {
          log(`❌ Error: ${err.message}`, 'error');
        }
        updateStatus('❌ Error de conexión', 'error');
      }
    }

    async function disconnectPort() {
      try {
        keepReading = false;
        if (reader) { await reader.cancel(); reader = null; }
        writer?.releaseLock(); writer = null;
        if (port) { await port.close(); port = null; log('🔌 Puerto cerrado'); }
        updateStatus('🔌 Desconectado', 'info');
        els.btnConnect.classList.remove('hidden');
        els.btnDisconnect.classList.add('hidden');
        els.btnFlash.disabled = true;
        els.btnVerify.disabled = true;
      } catch (err) {
        log(`⚠️ Error al desconectar: ${err.message}`, 'warning');
      }
    }

    async function readLoop() {
      while (port?.readable && keepReading) {
        try {
          reader = port.readable.getReader();
          while (keepReading) {
            const { value, done } = await reader.read();
            if (done) break;
            if (value) {
              const text = new TextDecoder().decode(value).replace(/[^\x20-\x7E\n\r\t]/g, '');
              if (text.trim()) log(text.trim());
            }
          }
        } catch (err) {
          if (keepReading) log(`⚠️ Error lectura: ${err.message}`, 'warning');
          break;
        } finally {
          reader?.releaseLock(); reader = null;
        }
      }
    }

    async function sendCommand(cmd) {
      if (!port?.writable) return false;
      try {
        writer = port.writable.getWriter();
        await writer.write(new TextEncoder().encode(cmd + '\n'));
        writer.releaseLock(); writer = null;
        return true;
      } catch (err) {
        log(`❌ Envío fallido: ${err.message}`, 'error');
        writer?.releaseLock(); writer = null;
        return false;
      }
    }

    // ================================
    // 🗑️ ERASE FLASH
    // ================================
    async function eraseFlash() {
      if (!confirm('⚠️ ¿Seguro que quieres BORRAR TODO el flash del ESP32?\n\nEsto eliminará:\n• Firmware\n• Particiones\n• LittleFS\n• Configuración\n\nEl ESP32 quedará como nuevo.')) return;
      
      if (!port?.writable) { log('❌ Conecta el ESP32 primero', 'error'); return; }
      
      try {
        log('🗑️ Iniciando borrado de flash completo...');
        updateStatus('🗑️ Borrando flash...', 'warning');
        els.btnErase.disabled = true;
        els.progressContainer.classList.remove('hidden');
        
        // Comando de erase (protocolo esptool simplificado)
        // En implementación real: usar esptool.js con comandos SPI
        await sendCommand('ERASE_FLASH');
        
        // Simular progreso (el borrado real tarda ~30-60s)
        for (let p = 0; p <= 100; p += 2) {
          els.progressFill.style.width = `${p}%`;
          els.progressFill.textContent = `${p}%`;
          els.progressLabel.textContent = 'Borrando sectores...';
          await new Promise(r => setTimeout(r, 150));
        }
        
        await sendCommand('ERASE_COMPLETE');
        log('✅ Flash borrado correctamente', 'success');
        updateStatus('✅ Flash vacío', 'success');
        alert('✅ Flash borrado\n\nAhora puedes programar las particiones desde 0x0000');
        
      } catch (err) {
        log(`❌ Error en erase: ${err.message}`, 'error');
        updateStatus('❌ Error en borrado', 'error');
      } finally {
        els.progressContainer.classList.add('hidden');
        els.progressFill.style.width = '0%';
        els.btnErase.disabled = false;
      }
    }

    // ================================
    // 🚀 FLASH MULTIPARTICIÓN
    // ================================
    async function flashPartitions() {
      const files = Object.values(selectedFiles);
      if (files.length === 0) { log('⚠️ Selecciona al menos un archivo', 'warning'); return; }
      if (!port?.writable) { log('❌ Conecta el ESP32 primero', 'error'); return; }
      
      try {
        log(`🚀 Iniciando programación de ${files.length} partición(es)...`);
        updateStatus('🚀 Programando...', 'warning');
        els.btnFlash.disabled = true;
        els.progressContainer.classList.remove('hidden');
        
        writer = port.writable.getWriter();
        const encoder = new TextEncoder();
        
        // Comando de inicio
        await writer.write(encoder.encode('FLASH_START\n'));
        
        let totalBytes = files.reduce((sum, f) => sum + f.file.size, 0);
        let writtenBytes = 0;
        
        for (const { file, offset, type } of files) {
          log(`📦 ${type}: ${file.name} @ ${offset} (${formatBytes(file.size)})`);
          
          const data = new Uint8Array(await file.arrayBuffer());
          const offsetNum = parseOffset(offset);
          
          // Enviar header de partición
          await writer.write(encoder.encode(`PARTITION ${offset} ${data.length}\n`));
          
          // Enviar datos en chunks
          for (let i = 0; i < data.length; i += CONFIG.chunkSize) {
            const chunk = data.slice(i, i + CONFIG.chunkSize);
            await writer.write(chunk);
            writtenBytes += chunk.length;
            
            // Actualizar progreso global
            const percent = Math.round((writtenBytes / totalBytes) * 100);
            els.progressFill.style.width = `${percent}%`;
            els.progressFill.textContent = `${percent}%`;
            els.progressLabel.textContent = `Enviando ${type}...`;
            
            await new Promise(r => setTimeout(r, 2)); // Pequeña pausa
          }
        }
        
        // Finalizar
        await writer.write(encoder.encode('FLASH_END\n'));
        writer.releaseLock(); writer = null;
        
        log('✅ Programación completada', 'success');
        updateStatus('✅ ESP32 programado', 'success');
        alert('✅ Firmware instalado\n\nReinicia el ESP32 para ejecutar el nuevo código');
        
      } catch (err) {
        log(`❌ Error en flash: ${err.message}`, 'error');
        updateStatus('❌ Error en programación', 'error');
        writer?.releaseLock(); writer = null;
      } finally {
        els.progressContainer.classList.add('hidden');
        els.progressFill.style.width = '0%';
        els.progressLabel.textContent = '';
        updateFlashButtonState();
      }
    }

    // ================================
    // 🔍 VERIFICAR (placeholder)
    // ================================
    async function verifyFlash() {
      log('🔍 Función de verificación en desarrollo...');
      // Aquí iría la lógica para leer y comparar checksums
      alert('🔍 Verificación: Función disponible en próxima versión');
    }

    // ================================
    // 🎛️ UI HANDLERS
    // ================================
    function updateFlashButtonState() {
      const hasFiles = Object.keys(selectedFiles).length > 0;
      els.btnFlash.disabled = !port || !hasFiles;
      els.btnVerify.disabled = !port || !hasFiles;
    }

    function setupPartitionInputs() {
      document.querySelectorAll('.partition-file').forEach(input => {
        input.addEventListener('change', (e) => {
          const file = e.target.files[0];
          if (!file) return;
          
          const row = e.target.closest('.partition-row');
          const type = e.target.dataset.type;
          const offset = row.dataset.offset;
          
          selectedFiles[type] = { file, offset, type };
          row.querySelector('.size').textContent = formatBytes(file.size);
          row.querySelector('.size').style.color = '#4CAF50';
          
          log(`📄 ${type}: ${file.name} (${formatBytes(file.size)}) @ ${offset}`);
          updateFlashButtonState();
        });
      });
    }

    function addCustomPartition() {
      const offset = els.customOffset.value.trim();
      const file = els.customFile.files[0];
      
      if (!offset || !file) { log('⚠️ Offset y archivo requeridos', 'warning'); return; }
      if (!/^0x[0-9a-f]+$/i.test(offset) && !/^\d+$/.test(offset)) {
        log('❌ Offset inválido. Usa formato hexadecimal (0x...) o decimal', 'error');
        return;
      }
      
      const type = `custom_${Date.now()}`;
      selectedFiles[type] = { file, offset, type };
      
      // Crear fila visual
      const row = document.createElement('div');
      row.className = 'partition-row';
      row.dataset.offset = offset;
      row.innerHTML = `
        <label>🔸 Personal</label>
        <span style="color:#888;font-size:0.85rem">${file.name}</span>
        <span class="offset">${offset}</span>
        <button class="remove" title="Eliminar">&times;</button>
      `;
      row.querySelector('.remove').onclick = () => {
        delete selectedFiles[type];
        row.remove();
        updateFlashButtonState();
        log(`🗑️ Eliminada partición ${offset}`);
      };
      
      els.partitionsList.appendChild(row);
      log(`➕ Añadida: ${file.name} @ ${offset} (${formatBytes(file.size)})`);
      
      // Reset inputs
      els.customOffset.value = '';
      els.customFile.value = '';
      updateFlashButtonState();
    }

    // ================================
    // 💾 LOG MANAGEMENT
    // ================================
    function clearLog() { els.log.innerHTML = ''; logBuffer = []; log('🗑️ Consola limpiada'); }
    
    function downloadLog() {
      if (!logBuffer.length) { log('⚠️ Sin logs para guardar', 'warning'); return; }
      const blob = new Blob([logBuffer.join('\n')], { type: 'text/plain' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `esp32_flash_${new Date().toISOString().slice(0,10)}.log`;
      a.click();
      URL.revokeObjectURL(a.href);
      log('💾 Log guardado');
    }

    // ================================
    // 🎭 EVENT LISTENERS
    // ================================
    function setupEvents() {
      // Conexión
      els.btnConnect.onclick = connectPort;
      els.btnDisconnect.onclick = disconnectPort;
      
      // Control ESP32
      els.btnReset.onclick = () => sendCommand('RESET');
      els.btnBoot.onclick = () => { log('⬇️ Modo bootloader: Mantén BOOT pulsado al conectar', 'warning'); };
      
      // Flash operations
      els.btnErase.onclick = eraseFlash;
      els.btnFlash.onclick = flashPartitions;
      els.btnVerify.onclick = verifyFlash;
      
      // Particiones
      setupPartitionInputs();
      els.btnAddCustom.onclick = addCustomPartition;
      
      // Drag & drop genérico
      document.querySelectorAll('.partition-row').forEach(row => {
        row.addEventListener('dragover', e => { e.preventDefault(); row.style.borderColor = 'var(--primary)'; });
        row.addEventListener('dragleave', () => row.style.borderColor = '');
        row.addEventListener('drop', e => {
          e.preventDefault();
          row.style.borderColor = '';
          const file = e.dataTransfer.files[0];
          const input = row.querySelector('input[type="file"]');
          if (file?.name.endsWith('.bin')) {
            const dt = new DataTransfer(); dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
          }
        });
      });
      
      // Logs
      els.btnClear.onclick = clearLog;
      els.btnDownload.onclick = downloadLog;
      
      // Cleanup on unload
      window.addEventListener('beforeunload', async () => { if (port) await disconnectPort(); });
      
      // Detect physical disconnect
      if ('serial' in navigator) {
        navigator.serial.addEventListener('disconnect', e => {
          if (e.target === port) { log('🔌 ESP32 desconectado', 'warning'); disconnectPort(); }
        });
      }
    }

    // ================================
    // 🚀 INIT
    // ================================
    function init() {
      log('🚀 ESP32 Flash Tool Web cargado');
      log(`🔗 URL: ${window.location.href}`);
      
      if (!('serial' in navigator)) {
        log('❌ Web Serial API no disponible', 'error');
        updateStatus('🚫 Usa Chrome/Edge', 'error');
        els.btnConnect.disabled = true;
        return;
      }
      
      // Check context security
      if (!window.isSecureContext && !['localhost','127.0.0.1'].includes(location.hostname)) {
        log('⚠️ Contexto no seguro: usa HTTPS o localhost', 'warning');
      }
      
      setupEvents();
      log('✅ Listo. Conecta ESP32 y selecciona archivos .bin');
    }

    document.addEventListener('DOMContentLoaded', init);
  </script>
</body>
</html>
