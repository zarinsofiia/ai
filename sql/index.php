<?php
// sql/index.php  — MSSQL-only UI with latency badge + organized helpers
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>MSSQL AI</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <style>
    :root {
      color-scheme: dark;
    }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background-color: #121212;
      color: #f1f1f1;
      height: 100dvh;
      display: flex;
      flex-direction: column;
    }

    /* Top indeterminate progress bar */
    .busybar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, transparent, #0a84ff, transparent);
      background-size: 200% 100%;
      animation: busybar-move 1s linear infinite;
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s ease;
      z-index: 9999;
    }

    .busybar.active {
      opacity: 1;
    }

    @keyframes busybar-move {
      0% {
        background-position: 200% 0;
      }

      100% {
        background-position: -200% 0;
      }
    }

    .sql-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .sql-messages {
      flex: 1;
      overflow-y: auto;
      padding: 16px 16px 10px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      scrollbar-width: thin;
      scrollbar-color: #444 transparent;
    }

    .sql-messages::-webkit-scrollbar {
      width: 6px;
    }

    .sql-messages::-webkit-scrollbar-thumb {
      background-color: #444;
      border-radius: 3px;
    }

    .sql-messages::-webkit-scrollbar-track {
      background: transparent;
    }

    .message {
      max-width: 75%;
      padding: 14px 18px;
      border-radius: 16px;
      font-size: 15px;
      line-height: 1.6;
      white-space: pre-wrap;
      word-wrap: break-word;
      display: inline-block;
    }

    .user-message {
      align-self: flex-end;
      background-color: #0a84ff;
      color: white;
      border-top-right-radius: 0;
    }

    .sql-response {
      align-self: flex-start;
      background-color: #2c2c2c;
      color: #f1f1f1;
      border-top-left-radius: 0;
    }

    .sql-input-container {
      margin-top: auto;
      display: flex;
      gap: 10px;
      padding: 14px 16px;
      background-color: #1e1e1e;
      border-top: 1px solid #333;
      position: relative;
    }

    .sql-input {
      flex: 1;
      padding: 14px;
      border-radius: 10px;
      border: 1px solid #444;
      font-size: 15px;
      background-color: #2b2b2b;
      color: #f1f1f1;
      outline: none;
    }

    .sql-btn {
      padding: 14px;
      border: none;
      border-radius: 10px;
      background-color: #2a2a2a;
      color: #f1f1f1;
      font-size: 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background-color .2s;
    }

    .sql-btn:hover {
      background-color: #3a3a3a;
    }

    /* Typing dots animation inside the assistant bubble */
    .typing {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      min-height: 12px;
    }

    .typing .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #bbb;
      opacity: .2;
      animation: blink 1s infinite ease-in-out;
    }

    .typing .dot:nth-child(2) {
      animation-delay: .2s;
    }

    .typing .dot:nth-child(3) {
      animation-delay: .4s;
    }

    @keyframes blink {

      0%,
      80%,
      100% {
        opacity: .2;
      }

      40% {
        opacity: 1;
      }
    }

    /* tiny latency badge */
    .latency-badge {
      margin-left: .5rem;
      padding: .15rem .45rem;
      border: 1px solid #2f2f2f;
      border-radius: 999px;
      font: 12px/1.2 system-ui;
      color: #9ca3af;
      background: #181818;
      white-space: nowrap;
    }

    details>summary {
      list-style: none;
      cursor: pointer;
      user-select: none;
    }

    details>summary::marker,
    details>summary::-webkit-details-marker {
      display: none;
    }

    details>summary {
      font-size: 13px;
      color: #9ca3af;
    }
  </style>
</head>

<body>
  <!-- Top progress bar -->
  <div id="busybar" class="busybar"></div>

  <div class="sql-body">
    <div class="sql-messages" id="sql-container">
      <div class="sql-response message">Type a natural language query like “Show me top 5 customers”</div>
    </div>

    <div class="sql-input-container">
      <input id="sql-prompt" class="sql-input" placeholder="Ask in plain English..." />
      <button class="sql-btn" id="send-btn" title="Send (Enter)">
        <i class="fa-solid fa-paper-plane"></i>
      </button>
      <button id="record-btn" class="sql-btn" title="Record voice">
        <i class="fa-solid fa-microphone"></i>
      </button>
      <input type="file" id="audio-upload" accept="audio/*" style="display:none;" />
      <button class="sql-btn" id="upload-audio-btn" title="Upload audio file">
        <i class="fa-solid fa-file-audio"></i>
      </button>
    </div>
    <span id="record-status" style="padding: 0 16px 12px; font-size: 13px; color: #aaa;"></span>
  </div>

  <!-- Your auth helper (kept) -->
  <script src="../ai/auth-check.js"></script>

  <script>
    ;
    (() => {
      // ─────────────────────────────────────────────────────────
      // Config / constants
      // ─────────────────────────────────────────────────────────
      const API_BASE = 'http://192.168.2.22:3001/api/askAI';
      const busybar = document.getElementById('busybar');
      const container = document.getElementById('sql-container');
      const inputEl = document.getElementById('sql-prompt');

      // ─────────────────────────────────────────────────────────
      // UI helpers
      // ─────────────────────────────────────────────────────────
      function setLoading(enabled) {
        busybar.classList.toggle('active', !!enabled);
        // optionally disable inputs while loading
        // document.querySelectorAll('.sql-input, .sql-btn').forEach(el => el.disabled = !!enabled);
      }

      function createAssistantLoadingBubble() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('sql-response', 'message');

        const contentSpan = document.createElement('span');
        contentSpan.innerHTML = `
        <div class="typing" aria-label="Assistant is typing">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
        </div>`;
        wrapper.appendChild(contentSpan);
        container.appendChild(wrapper);
        container.scrollTop = container.scrollHeight;
        return {
          wrapper,
          contentSpan
        };
      }

      function appendMessage(role, text) {
        const msg = document.createElement('div');
        msg.classList.add(role === 'user' ? 'user-message' : 'sql-response', 'message');
        msg.textContent = text;
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
      }

      function appendShowRaw(parent, payload) {
        const details = document.createElement('details');
        const sm = document.createElement('summary');
        sm.textContent = 'Show raw';
        details.appendChild(sm);

        const pre = document.createElement('pre');
        pre.style.marginTop = '8px';
        pre.style.fontSize = '12px';
        pre.style.color = '#aaa';
        pre.style.whiteSpace = 'pre-wrap';
        pre.style.backgroundColor = '#1e1e1e';
        pre.style.padding = '10px';
        pre.style.borderRadius = '8px';
        pre.textContent = (typeof payload === 'string') ? payload : JSON.stringify(payload, null, 2);

        details.appendChild(pre);
        parent.appendChild(details);
      }

      function formatSummary(s) {
        if (!s) return '';
        return s
          .replace(/<\/?think>/gi, '')
          .replace(/\n/g, '<br>')
          .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      }

      function prettyLatency(ms) {
        if (ms == null || isNaN(ms)) return null;
        if (ms < 1000) return `${ms} ms`;
        const s = ms / 1000;
        if (s < 60) return `${s.toFixed(s < 10 ? 1 : 0)} s`;
        const m = Math.floor(s / 60);
        const ss = Math.floor(s % 60).toString().padStart(2, '0');
        return `${m}:${ss}`;
      }

      // ─────────────────────────────────────────────────────────
      // MSSQL call
      // ─────────────────────────────────────────────────────────
      async function askMSSQLAI() {
        const prompt = (inputEl.value || '').trim();
        if (!prompt) return;

        appendMessage('user', prompt);
        inputEl.value = '';

        setLoading(true);
        const {
          wrapper,
          contentSpan
        } = createAssistantLoadingBubble();

        const token = localStorage.getItem('bearerToken');
        const t0 = performance.now();
        let serverLatencyMs = null,
          summaryPlain = null,
          sqlText = null,
          resultObj = null,
          errorText = null;

        try {
          const res = await fetch(`${API_BASE}/sql-mssql`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
              prompt
            })
          });

          if (res.status === 401 || res.status === 403) {
            setLoading(false);
            return logoutAndRedirect && logoutAndRedirect();
          }

          const data = await res.json().catch(() => ({}));
          console.log('✅ MSSQL AI Response:', data);

          if (!res.ok || data.error) {
            errorText = (data && data.error) ? data.error : (`HTTP ${res.status}: ${res.statusText || 'Error'}`);
            contentSpan.textContent = '❌ ' + errorText;
            appendShowRaw(wrapper, {
              source: 'sql-mssql',
              request: {
                prompt
              },
              response: data
            });
          } else {
            summaryPlain = (data.summary || '').trim();
            sqlText = data.sql || data.sql_text || data.sqlToRun || null;
            resultObj = data.result ?? (data.mssqlResult?.recordset ?? null);

            // prefer server-reported latency if present
            if (typeof data.latency_ms === 'number') serverLatencyMs = data.latency_ms;
            else if (typeof data.latencyMs === 'number') serverLatencyMs = data.latencyMs;

            let summary = summaryPlain || (Array.isArray(resultObj) ?
              `✅ ${resultObj.length} row(s) returned.` :
              '⚠️ No summary available.');
            contentSpan.innerHTML = formatSummary(summary);

            appendShowRaw(wrapper, {
              source: 'sql-mssql',
              request: {
                prompt
              },
              response: data
            });
          }
        } catch (err) {
          errorText = err.message;
          contentSpan.textContent = '❌ Error: ' + err.message;
          appendShowRaw(wrapper, {
            source: 'sql-mssql',
            request: {
              prompt
            },
            error: err.message,
            stack: err.stack || null
          });
        } finally {
          const latencyClientMs = Math.round(performance.now() - t0);
          const latencyToShow = (serverLatencyMs != null ? serverLatencyMs : latencyClientMs);
          const human = prettyLatency(latencyToShow);

          // fire-and-forget log (client timing; server timing is already persisted by API if you store it)
          saveLogLocally({
            source: 'sql-mssql',
            question: prompt,
            summary: summaryPlain,
            sqlText,
            result: resultObj,
            errorText,
            latencyMs: latencyClientMs
          });

          if (human) {
            const badge = document.createElement('span');
            badge.className = 'latency-badge';
            badge.textContent = `⏱ ${human}`;
            contentSpan.insertAdjacentElement('afterend', badge);
          }

          setLoading(false);
          container.scrollTop = container.scrollHeight;
        }
      }

      // ─────────────────────────────────────────────────────────
      // Voice recording / upload (kept from your version)
      // ─────────────────────────────────────────────────────────
      let mediaRecorder;
      let audioChunks = [];
      const recordBtn = document.getElementById('record-btn');
      const micIcon = recordBtn.querySelector('i');
      const recordStatus = document.getElementById('record-status');
      const uploadAudioBtn = document.getElementById('upload-audio-btn');
      const audioInput = document.getElementById('audio-upload');

      recordBtn.addEventListener('click', async () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
          mediaRecorder.stop();
          micIcon.className = 'fa-solid fa-microphone';
          recordStatus.textContent = 'Transcribing...';
        } else {
          try {
            const stream = await navigator.mediaDevices.getUserMedia({
              audio: true
            });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);

            mediaRecorder.onstop = async () => {
              const blob = new Blob(audioChunks, {
                type: 'audio/webm'
              });
              const formData = new FormData();
              formData.append('audio', blob, 'voice.webm');

              try {
                const res = await fetch(`${API_BASE}/transcribe`, {
                  method: 'POST',
                  headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
                  },
                  body: formData
                });
                if (res.status === 401 || res.status === 403) {
                  return logoutAndRedirect && logoutAndRedirect();
                }
                const data = await res.json();
                inputEl.value = data.transcript || '';
                recordStatus.textContent = '✅ Transcribed';
              } catch {
                recordStatus.textContent = '❌ Transcription failed';
              }
            };

            mediaRecorder.start();
            micIcon.className = 'fa-solid fa-stop';
            recordStatus.textContent = '🎙️ Recording... Click to stop';
          } catch {
            recordStatus.textContent = '❌ Microphone access denied';
          }
        }
      });

      uploadAudioBtn.addEventListener('click', () => audioInput.click());
      audioInput.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('audio', file);
        recordStatus.textContent = '⏳ Transcribing uploaded file...';

        try {
          const res = await fetch(`${API_BASE}/transcribe`, {
            method: 'POST',
            headers: {
              'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
            },
            body: formData
          });
          if (res.status === 401 || res.status === 403) {
            return logoutAndRedirect && logoutAndRedirect();
          }
          const data = await res.json();
          inputEl.value = data.transcript || '';
          recordStatus.textContent = '✅ Transcribed from file';
        } catch {
          recordStatus.textContent = '❌ File transcription failed';
        }
      });

      // ─────────────────────────────────────────────────────────
      // History helpers
      // ─────────────────────────────────────────────────────────
      const MAX_JSON_BYTES = 500 * 1024; // 500KB cap
      const truncateJson = (s) => (!s ? null : (s.length > MAX_JSON_BYTES ? s.slice(0, MAX_JSON_BYTES) : s));

      function getUserIdFromLSorJWT() {
        const ls = localStorage.getItem('userId');
        if (ls && !isNaN(ls)) return parseInt(ls, 10);

        const token = localStorage.getItem('bearerToken');
        try {
          const claims = parseJwt ? parseJwt(token) : null; // provided by your auth-check.js
          const id = (typeof getIdFromClaims === 'function') ? getIdFromClaims(claims) : claims?.sub;
          return (id != null && !Number.isNaN(Number(id))) ? Number(id) : null;
        } catch {
          return null;
        }
      }

      async function saveLogLocally({
        source,
        question,
        summary,
        sqlText,
        result,
        errorText,
        latencyMs
      }) {
        try {
          const userId = getUserIdFromLSorJWT();
          const payload = {
            user_id: userId,
            source,
            question,
            summary,
            sql_text: sqlText || null,
            result_json: result ? truncateJson(JSON.stringify(result)) : null,
            error_text: errorText || null,
            latency_ms: latencyMs ?? null
          };
          await fetch('../ai/sql/save_log.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-User-Id': userId ?? ''
            },
            body: JSON.stringify(payload)
          });
        } catch (e) {
          console.warn('saveLogLocally failed:', e.message);
        }
      }

      function safeParse(json) {
        try {
          return json ? JSON.parse(json) : null;
        } catch {
          return null;
        }
      }

      function getRowCountFromResultJSON(resultJson) {
        const r = safeParse(resultJson);
        if (!r) return null;
        if (Array.isArray(r)) return r.length;
        if (Array.isArray(r?.recordset)) return r.recordset.length; // mssql
        if (Array.isArray(r?.result)) return r.result.length; // some APIs
        if (typeof r === 'object' && typeof r.length === 'number') return r.length;
        return null;
      }

      function renderHistoryItem(item) {
        appendMessage('user', item.question || '');

        const wrap = document.createElement('div');
        wrap.classList.add('sql-response', 'message');

        const meta = document.createElement('div');
        meta.style.fontSize = '11px';
        meta.style.opacity = '0.7';
        meta.style.marginBottom = '6px';
        const latPretty = (item.latency_ms != null) ? prettyLatency(Number(item.latency_ms)) : null;
        meta.textContent = `[${item.source}] ${item.created_at}${latPretty ? ' • ⏱ ' + latPretty : ''}`;
        wrap.appendChild(meta);

        const contentSpan = document.createElement('span');
        const hasError = !!(item.error_text && item.error_text.trim());
        if (hasError) {
          contentSpan.textContent = '❌ ' + item.error_text.trim();
          contentSpan.style.color = '#ff6b6b';
        } else if (item.summary && item.summary.trim()) {
          contentSpan.innerHTML = formatSummary(item.summary.trim());
        } else {
          const n = getRowCountFromResultJSON(item.result_json);
          const fallback = (n != null) ? `✅ ${n} row(s) returned.` : '⚠️ No summary available.';
          contentSpan.innerHTML = formatSummary(fallback);
        }
        wrap.appendChild(contentSpan);

        if (item.sql_text || item.result_json || item.error_text) {
          const details = document.createElement('details');
          const sm = document.createElement('summary');
          sm.textContent = 'Show raw';
          details.appendChild(sm);

          const pre = document.createElement('pre');
          pre.style.marginTop = '8px';
          pre.style.fontSize = '12px';
          pre.style.color = '#aaa';
          pre.style.whiteSpace = 'pre-wrap';
          pre.style.backgroundColor = '#1e1e1e';
          pre.style.padding = '10px';
          pre.style.borderRadius = '8px';

          const payload = {
            sql: item.sql_text || null,
            result: safeParse(item.result_json),
            error: item.error_text || null,
            source: item.source,
            at: item.created_at
          };
          pre.textContent = JSON.stringify(payload, null, 2);
          details.appendChild(pre);
          wrap.appendChild(details);
        }

        container.appendChild(wrap);
      }

      async function loadHistory() {
        container.innerHTML = '';
        const userId = localStorage.getItem('userId') || '';
        const params = new URLSearchParams({
          limit: '100'
        });
        if (userId) params.set('user_id', userId);

        try {
          const res = await fetch('/ai/sql/get_logs.php?' + params.toString());
          const data = await res.json();
          if (!data.ok) {
            appendMessage('sql', '⚠️ Failed to load history: ' + (data.error || 'Unknown error'));
            return;
          }
          if (!data.items || data.items.length === 0) {
            appendMessage('sql', 'Type a natural language query like “Show me top 5 customers”');
            return;
          }
          for (const item of data.items) renderHistoryItem(item);
          container.scrollTop = container.scrollHeight;
        } catch (e) {
          appendMessage('sql', '⚠️ Failed to load history: ' + e.message);
        }
      }

      // ─────────────────────────────────────────────────────────
      // Wire up
      // ─────────────────────────────────────────────────────────
      document.getElementById('send-btn').addEventListener('click', askMSSQLAI);
      inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          askMSSQLAI();
        }
      });

      document.addEventListener('DOMContentLoaded', loadHistory);
    })();
  </script>
</body>

</html>