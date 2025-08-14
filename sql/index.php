<?php
// sql/index.php
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background-color: #121212;
        color: #f1f1f1;
        height: 100%;
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
        padding-bottom: 10px;
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
        background-color: #007bff;
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
        padding: 20px;
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

    .run-button {
        padding: 14px 24px;
        border: none;
        border-radius: 10px;
        background-color: #0a84ff;
        color: white;
        font-size: 15px;
        cursor: pointer;
    }

    .run-button:hover {
        background-color: #006fd6;
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
        transition: background-color 0.2s;
    }

    .sql-btn:hover {
        background-color: #3a3a3a;
    }

    /* Disabled look */
    /* .sql-btn:disabled, .sql-input:disabled {
        opacity: 0.6; 
        cursor: not-allowed;
    } */

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
        opacity: 0.2;
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
            opacity: 0.2;
        }

        40% {
            opacity: 1;
        }
    }
</style>

<!-- Top progress bar -->
<div id="busybar" class="busybar"></div>

<div class="sql-body">
    <div class="sql-messages" id="sql-container">
        <div class="sql-response message"> Type a natural language query like "Show me top 5 customers"</div>
    </div>

    <div class="sql-input-container">
        <input id="sql-prompt" class="sql-input" placeholder="Ask in plain English..." />
        <button class="sql-btn" onclick="askMSSQLAI()"><i class="fa-solid fa-paper-plane"></i></button>
        <button id="record-btn" class="sql-btn"><i class="fa-solid fa-microphone"></i></button>
        <input type="file" id="audio-upload" accept="audio/*" style="display: none;" />
        <button class="sql-btn" onclick="document.getElementById('audio-upload').click()">
            <i class="fa-solid fa-file-audio"></i>
        </button>
    </div>
    <span id="record-status" style="padding-left: 20px; font-size: 13px; color: #aaa;"></span>
</div>

<script src="../ai/auth-check.js"></script>
<script>
  function appendShowRaw(parent, payload) {
    const details = document.createElement('details');

    const sm = document.createElement('summary');
    sm.textContent = 'Show raw';
    sm.style.cursor = 'pointer';
    details.appendChild(sm);

    const pre = document.createElement('pre');
    pre.style.marginTop = '8px';
    pre.style.fontSize = '12px';
    pre.style.color = '#aaa';
    pre.style.whiteSpace = 'pre-wrap';
    pre.style.backgroundColor = '#1e1e1e';
    pre.style.padding = '10px';
    pre.style.borderRadius = '8px';
    pre.textContent = (typeof payload === 'string')
      ? payload
      : JSON.stringify(payload, null, 2);

    details.appendChild(pre);
    parent.appendChild(details);
  }
</script>

<script>
    // ------- Small UI helpers for loading -------
    const busybar = document.getElementById('busybar');

    function setLoading(enabled) {
        // Toggle top progress bar
        busybar.classList.toggle('active', !!enabled);

        // Disable/enable inputs and buttons
        // document.querySelectorAll('.sql-input, .sql-btn').forEach(el => {
        //     el.disabled = !!enabled;
        // });
    }

    function createAssistantLoadingBubble() {
        const container = document.getElementById('sql-container');
        const wrapper = document.createElement('div');
        wrapper.classList.add('sql-response', 'message');

        const contentSpan = document.createElement('span');
        // Typing dots animation
        contentSpan.innerHTML = `
            <div class="typing" aria-label="Assistant is typing">
                <span class="dot"></span><span class="dot"></span><span class="dot"></span>
            </div>
        `;
        wrapper.appendChild(contentSpan);
        container.appendChild(wrapper);
        container.scrollTop = container.scrollHeight;

        return {
            wrapper,
            contentSpan
        };
    }

    function appendMessage(role, text) {
        const container = document.getElementById('sql-container');
        const msg = document.createElement('div');
        msg.classList.add(role === 'user' ? 'user-message' : 'sql-response', 'message');
        msg.textContent = text;
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
    }
</script>

<script>
    // -------- Plain SQL AI (MySQL/etc.) ----------
    async function askSQLAI() {
        const input = document.getElementById('sql-prompt');
        const prompt = input.value.trim();
        if (!prompt) return;

        appendMessage('user', prompt);
        input.value = '';

        // Show loading UI
        setLoading(true);
        const {
            wrapper,
            contentSpan
        } = createAssistantLoadingBubble();

        const makeRequest = async () => {
            const token = localStorage.getItem('bearerToken');
            return await fetch('http://192.168.2.22:3001/api/askAI/sql', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    prompt
                })
            });
        };

        const container = document.getElementById('sql-container');

        try {
            let res = await makeRequest();

            if (res.status === 401 || res.status === 403) {
                setLoading(false);
                return logoutAndRedirect();
            }

            const data = await res.json();
            console.log('✅ API Response:', data);

            if (data.error) {
                contentSpan.textContent = '❌ ' + (data.error || 'An error occurred.');

                const rawPre = document.createElement('pre');
                rawPre.style.marginTop = '10px';
                rawPre.style.fontSize = '12px';
                rawPre.style.color = '#aaa';
                rawPre.style.whiteSpace = 'pre-wrap';
                rawPre.style.backgroundColor = '#1e1e1e';
                rawPre.style.padding = '10px';
                rawPre.style.borderRadius = '8px';
                rawPre.textContent = JSON.stringify(data, null, 2);
                wrapper.appendChild(rawPre);

                setLoading(false);
                return;
            }

            let summary = data.summary?.trim();
            if (!summary) {
                summary = Array.isArray(data.result) ?
                    `✅ ${data.result.length} row(s) returned.` :
                    '⚠️ No summary available.';
            }

            // Formatting
            summary = summary
                .replace(/<\/?think>/gi, '')
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            contentSpan.innerHTML = summary;

            const rawPre = document.createElement('pre');
            rawPre.style.marginTop = '10px';
            rawPre.style.fontSize = '12px';
            rawPre.style.color = '#aaa';
            rawPre.style.whiteSpace = 'pre-wrap';
            rawPre.style.backgroundColor = '#1e1e1e';
            rawPre.style.padding = '10px';
            rawPre.style.borderRadius = '8px';
            rawPre.textContent = JSON.stringify(data, null, 2);
            wrapper.appendChild(rawPre);
        } catch (err) {
            contentSpan.textContent = '❌ Error: ' + err.message;

            const rawPre = document.createElement('pre');
            rawPre.style.marginTop = '10px';
            rawPre.style.fontSize = '12px';
            rawPre.style.color = '#aaa';
            rawPre.style.whiteSpace = 'pre-wrap';
            rawPre.style.backgroundColor = '#1e1e1e';
            rawPre.style.padding = '10px';
            rawPre.style.borderRadius = '8px';
            rawPre.textContent = err.stack || err.message;
            wrapper.appendChild(rawPre);
        } finally {
            setLoading(false);
            container.scrollTop = container.scrollHeight;
        }
    }
</script>

<script>
    // -------- MSSQL AI ----------
  async function askMSSQLAI() {
  const input = document.getElementById('sql-prompt');
  const prompt = input.value.trim();
  if (!prompt) return;

  // helper (scoped): add <details><summary>Show raw</summary><pre>...</pre>
  function appendShowRaw(parent, payload) {
    const details = document.createElement('details');

    const sm = document.createElement('summary');
    sm.textContent = 'Show raw';
    sm.style.cursor = 'pointer';
    details.appendChild(sm);

    const pre = document.createElement('pre');
    pre.style.marginTop = '8px';
    pre.style.fontSize = '12px';
    pre.style.color = '#aaa';
    pre.style.whiteSpace = 'pre-wrap';
    pre.style.backgroundColor = '#1e1e1e';
    pre.style.padding = '10px';
    pre.style.borderRadius = '8px';
    pre.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);

    details.appendChild(pre);
    parent.appendChild(details);
  }

  // helper (scoped): simple formatter like your history renderer
  function formatSummary(s) {
    if (!s) return '';
    return s
      .replace(/<\/?think>/gi, '')
      .replace(/\n/g, '<br>')
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  }

  appendMessage('user', prompt);
  input.value = '';

  setLoading(true);
  const { wrapper, contentSpan } = createAssistantLoadingBubble();
  const container = document.getElementById('sql-container');

  const makeRequest = async () => {
    const token = localStorage.getItem('bearerToken');
    return await fetch('http://192.168.2.22:3001/api/askAI/sql-mssql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      },
      body: JSON.stringify({ prompt })
    });
  };

  const t0 = performance.now();
  let summaryPlain = null, sqlText = null, resultObj = null, errorText = null;

  try {
    let res = await makeRequest();

    if (res.status === 401 || res.status === 403) {
      setLoading(false);
      return logoutAndRedirect();
    }

    // if server returns non-2xx with JSON body, still parse to show details
    const data = await res.json().catch(() => ({}));
    console.log('✅ MSSQL AI Response:', data);

    if (!res.ok || data.error) {
      // Error path — show error text and a Show raw section
      errorText = (data && data.error) ? data.error : (`HTTP ${res.status}: ${res.statusText || 'Error'}`);
      contentSpan.textContent = '❌ ' + errorText;
      appendShowRaw(wrapper, { source: 'sql-mssql', request: { prompt }, response: data });
    } else {
      // Success path — display summary, attach Show raw
      summaryPlain = (data.summary || '').trim();
      sqlText = data.sql || data.sql_text || data.sqlToRun || null;
      resultObj = data.result ?? (data.mssqlResult?.recordset ?? null);

      let summary = summaryPlain || (Array.isArray(resultObj)
        ? `✅ ${resultObj.length} row(s) returned.`
        : '⚠️ No summary available.');

      contentSpan.innerHTML = formatSummary(summary);

      // attach raw payload with derived fields for convenience
      appendShowRaw(wrapper, {
        source: 'sql-mssql',
        request: { prompt },
        derived: {
          sqlText,
          rows: Array.isArray(resultObj) ? resultObj.length : null
        },
        response: data
      });
    }
  } catch (err) {
    // Network or parse error
    errorText = err.message;
    contentSpan.textContent = '❌ Error: ' + err.message;
    appendShowRaw(wrapper, { source: 'sql-mssql', request: { prompt }, error: err.message, stack: err.stack || null });
  } finally {
    const latencyMs = Math.round(performance.now() - t0);

    // fire-and-forget DB log
    saveLogLocally({
      source: 'sql-mssql',
      question: prompt,
      summary: summaryPlain,
      sqlText,
      result: resultObj,
      errorText,
      latencyMs
    });

    setLoading(false);
    container.scrollTop = container.scrollHeight;
  }
}

</script>

<script>
    // 🎤 Voice recording (unchanged)
    let mediaRecorder;
    let audioChunks = [];
    const recordBtn = document.getElementById('record-btn');
    const micIcon = recordBtn.querySelector('i');
    const recordStatus = document.getElementById('record-status');

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
                        const res = await fetch('http://192.168.2.22:3001/api/askAI/transcribe', {
                            method: 'POST',
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
                            },
                            body: formData
                        });

                        if (res.status === 401 || res.status === 403) {
                            return logoutAndRedirect();
                        }
                        const data = await res.json();
                        document.getElementById('sql-prompt').value = data.transcript || '';
                        recordStatus.textContent = '✅ Transcribed';
                    } catch (err) {
                        recordStatus.textContent = '❌ Transcription failed';
                    }
                };

                mediaRecorder.start();
                micIcon.className = 'fa-solid fa-stop';
                recordStatus.textContent = '🎙️ Recording... Click to stop';
            } catch (err) {
                recordStatus.textContent = '❌ Microphone access denied';
            }
        }
    });

    // 🎵 File upload (unchanged)
    document.getElementById('audio-upload').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('audio', file);

        const status = document.getElementById('record-status');
        status.textContent = '⏳ Transcribing uploaded file...';

        try {
            const res = await fetch('http://192.168.2.22:3001/api/askAI/transcribe', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
                },
                body: formData
            });
            if (res.status === 401 || res.status === 403) {
                return logoutAndRedirect();
            }

            const data = await res.json();
            document.getElementById('sql-prompt').value = data.transcript || '';
            status.textContent = '✅ Transcribed from file';
        } catch (err) {
            status.textContent = '❌ File transcription failed';
        }
    });

    function speakText(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US'; // or 'ms-MY' for Malay
        utterance.rate = 1; // 0.5 - 2
        speechSynthesis.speak(utterance);
    }

    // If you use this elsewhere, uncomment your existing implementation.
    // function logoutAndRedirect() {
    //   localStorage.removeItem('bearerToken');
    //   localStorage.removeItem('refreshToken');
    //   alert('Your session has expired. Please login again.');
    //   window.location.href = '../ai';
    // }
</script>
<script>
    // --- local log helper (calls save_log.php on localhost) ---
    const MAX_JSON_BYTES = 500 * 1024; // 500KB cap to be safe
    const truncateJson = (s) => (!s ? null : (s.length > MAX_JSON_BYTES ? s.slice(0, MAX_JSON_BYTES) : s));

    function getUserIdFromLSorJWT() {
        const ls = localStorage.getItem('userId');
        if (ls && !isNaN(ls)) return parseInt(ls, 10);

        const token = localStorage.getItem('bearerToken');
        const claims = parseJwt(token);
        const id = getIdFromClaims(claims);
        return (id != null && !Number.isNaN(Number(id))) ? Number(id) : null;
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
            const userId = getUserIdFromLSorJWT(); // ← pulls from localStorage / JWT
            const payload = {
                user_id: userId, // ← will be null only if truly unknown
                source,
                question,
                summary,
                sql_text: sqlText || null,
                result_json: result ? JSON.stringify(result) : null,
                error_text: errorText || null,
                latency_ms: latencyMs ?? null
            };
            await fetch('../ai/sql/save_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-User-Id': userId ?? '' // optional server-side fallback
                },
                body: JSON.stringify(payload)
            });
        } catch (e) {
            console.warn('saveLogLocally failed:', e.message);
        }
    }
</script>
<script>
  // --- helpers for history rendering ---
  const container = document.getElementById('sql-container');

  function formatSummary(s) {
    if (!s) return '';
    return s
      .replace(/<\/?think>/gi, '')
      .replace(/\n/g, '<br>')
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  }

  function safeParse(json) {
    try { return json ? JSON.parse(json) : null; } catch { return null; }
  }

  // NEW: try to infer row count from common shapes
  function getRowCountFromResultJSON(resultJson) {
    const r = safeParse(resultJson);
    if (!r) return null;
    if (Array.isArray(r)) return r.length;
    if (Array.isArray(r?.recordset)) return r.recordset.length;   // mssql
    if (Array.isArray(r?.result)) return r.result.length;         // some APIs
    if (typeof r === 'object' && typeof r.length === 'number') return r.length;
    return null;
  }

  function renderHistoryItem(item) {
    // user bubble
    appendMessage('user', item.question || '');

    // assistant bubble
    const wrap = document.createElement('div');
    wrap.classList.add('sql-response', 'message');

    // include a tiny timestamp + source
    const meta = document.createElement('div');
    meta.style.fontSize = '11px';
    meta.style.opacity = '0.7';
    meta.style.marginBottom = '6px';
    meta.textContent = `[${item.source}] ${item.created_at}${item.latency_ms ? ' • ' + item.latency_ms + ' ms' : ''}`;
    wrap.appendChild(meta);

    const contentSpan = document.createElement('span');

    // ✅ CHANGE: prefer error_text, then summary, then row count, else fallback
    const hasError = !!(item.error_text && item.error_text.trim());
    if (hasError) {
      contentSpan.textContent = '❌ ' + item.error_text.trim(); // textContent to avoid HTML injection
      contentSpan.style.color = '#ff6b6b';
    } else if (item.summary && item.summary.trim()) {
      contentSpan.innerHTML = formatSummary(item.summary.trim());
    } else {
      const n = getRowCountFromResultJSON(item.result_json);
      const fallback = (n != null) ? `✅ ${n} row(s) returned.` : '⚠️ No summary available.';
      contentSpan.innerHTML = formatSummary(fallback);
    }
    wrap.appendChild(contentSpan);

    // raw payload (sql + result/error) collapsed
    if (item.sql_text || item.result_json || item.error_text) {
      const details = document.createElement('details');
      const sm = document.createElement('summary');
      sm.textContent = 'Show raw';
      sm.style.cursor = 'pointer';
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
    const params = new URLSearchParams({ limit: '100' });
    if (userId) params.set('user_id', userId);

    try {
      const res = await fetch('/ai/sql/get_logs.php?' + params.toString());
      const data = await res.json();
      if (!data.ok) {
        appendMessage('sql', '⚠️ Failed to load history: ' + (data.error || 'Unknown error'));
        return;
      }
      if (!data.items || data.items.length === 0) {
        appendMessage('sql', 'Type a natural language query like "Show me top 5 customers"');
        return;
      }
      for (const item of data.items) renderHistoryItem(item);
      container.scrollTop = container.scrollHeight;
    } catch (e) {
      appendMessage('sql', '⚠️ Failed to load history: ' + e.message);
    }
  }

  document.addEventListener('DOMContentLoaded', loadHistory);
</script>
