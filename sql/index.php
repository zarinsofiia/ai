<?php
// sql/index.php  — MSSQL-only UI with latency badge + organized helpers + cancel-in-flight + infinite-scroll + stats
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

    /* STATS: small, removable header bar */
    .statsbar {
      display: flex;
      gap: 10px;
      align-items: center;
      padding: 8px 12px;
      background: #161616;
      border-bottom: 1px solid #2a2a2a;
      font-size: 12px;
      color: #bdbdbd;
    }

    .stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #1a1a1a;
      border: 1px solid #2a2a2a;
      white-space: nowrap;
    }

    .ok-dot,
    .fail-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }

    .ok-dot {
      background: #22c55e;
    }

    /* green */
    .fail-dot {
      background: #ef4444;
    }

    /* red */

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

    .sql-btn[disabled] {
      opacity: .6;
      cursor: not-allowed;
    }

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

    /* [∞-scroll] loader pill at the very top while older page is fetched */
    .top-loader {
      align-self: center;
      font-size: 12px;
      color: #9ca3af;
      background: #1a1a1a;
      border: 1px solid #2a2a2a;
      border-radius: 999px;
      padding: 6px 10px;
      margin: 4px 0 8px;
      display: none;
    }

    .top-loader.active {
      display: inline-block;
    }
  </style>
</head>

<body>
  <div id="busybar" class="busybar"></div>

  <div class="sql-body">
    <!-- STATS -->
    <div class="statsbar" id="statsbar" style="display:none;">
      <div class="stat-pill" id="today-pill">
        <span class="ok-dot"></span>
        <span id="today-ok">Today OK: –</span>
        <span style="opacity:.6;">•</span>
        <span class="fail-dot"></span>
        <span id="today-fail">Fail: –</span>
      </div>
      <div class="stat-pill" id="all-pill">
        <span class="ok-dot"></span>
        <span id="all-ok">All OK: –</span>
        <span style="opacity:.6;">•</span>
        <span class="fail-dot"></span>
        <span id="all-fail">Fail: –</span>
      </div>
    </div>

    <div class="sql-messages" id="sql-container">
      <div id="top-sentinel"></div>
      <div id="top-loader" class="top-loader">Loading older…</div>

      <div class="sql-response message">Type a natural language query like “Show me top 5 customers”</div>
    </div>

    <div class="sql-input-container">
      <input id="sql-prompt" class="sql-input" placeholder="Ask in plain English..." />
      <button class="sql-btn" id="send-btn" title="Send (Enter)" aria-label="Send">
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

  <script src="../ai/auth-check.js"></script>

  <script>
    ;
    (() => {
      const API_BASE = 'http://192.168.2.22:3001/api/askAI';
      const busybar = document.getElementById('busybar');
      const container = document.getElementById('sql-container');
      const topSentinel = document.getElementById('top-sentinel');
      const topLoader = document.getElementById('top-loader');
      const inputEl = document.getElementById('sql-prompt');

      const sendBtn = document.getElementById('send-btn');
      const sendIcon = sendBtn.querySelector('i');
      const recordBtn = document.getElementById('record-btn');
      const uploadAudioBtn = document.getElementById('upload-audio-btn');

      // STATS elements
      const statsbar = document.getElementById('statsbar');
      const todayOkEl = document.getElementById('today-ok');
      const todayFailEl = document.getElementById('today-fail');
      const allOkEl = document.getElementById('all-ok');
      const allFailEl = document.getElementById('all-fail');

      let isSending = false;
      let currentAbortController = null;

      function setLoading(enabled) {
        busybar.classList.toggle('active', !!enabled);
        document.body.setAttribute('aria-busy', enabled ? 'true' : 'false');
        setControlsDuringSend(!!enabled);
      }

      function setControlsDuringSend(sending) {
        isSending = sending;
        if (sending) {
          sendBtn.title = 'Cancel request';
          sendBtn.setAttribute('aria-label', 'Cancel request');
          sendIcon.className = 'fa-solid fa-stop';
        } else {
          sendBtn.title = 'Send (Enter)';
          sendBtn.setAttribute('aria-label', 'Send');
          sendIcon.className = 'fa-solid fa-paper-plane';
        }
        recordBtn.disabled = sending;
        uploadAudioBtn.disabled = sending;
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

      function appendMessage(role, text, {
        prepend = false
      } = {}) {
        const msg = document.createElement('div');
        msg.classList.add(role === 'user' ? 'user-message' : 'sql-response', 'message');
        msg.textContent = text;
        if (prepend) {
          const insertBeforeNode = container.children[2] || null; // after sentinel+loader
          container.insertBefore(msg, insertBeforeNode);
        } else {
          container.appendChild(msg);
          container.scrollTop = container.scrollHeight;
        }
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
        return s.replace(/<\/?think>/gi, '').replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
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

      async function askMSSQLAI() {
        if (isSending) return;
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

        currentAbortController = new AbortController();
        try {
          const res = await fetch(`${API_BASE}/sql-mssql`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
              prompt
            }),
            signal: currentAbortController.signal
          });

          if (res.status === 401 || res.status === 403) {
            setLoading(false);
            return logoutAndRedirect && logoutAndRedirect();
          }

          const data = await res.json().catch(() => ({}));
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

            if (typeof data.latency_ms === 'number') serverLatencyMs = data.latency_ms;
            else if (typeof data.latencyMs === 'number') serverLatencyMs = data.latencyMs;

            const summary = summaryPlain || (Array.isArray(resultObj) ? `✅ ${resultObj.length} row(s) returned.` : '⚠️ No summary available.');
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
          if (err.name === 'AbortError') {
            errorText = 'Request cancelled';
            contentSpan.textContent = '🛑 Cancelled';
            appendShowRaw(wrapper, {
              source: 'sql-mssql',
              request: {
                prompt
              },
              cancelled: true
            });
          } else {
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
          }
        } finally {
          const latencyClientMs = Math.round(performance.now() - t0);
          const latencyToShow = (serverLatencyMs != null ? serverLatencyMs : latencyClientMs);
          const human = prettyLatency(latencyToShow);

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
          currentAbortController = null;
          container.scrollTop = container.scrollHeight;

          // STATS: refresh after each request
          refreshStats().catch(() => {});
        }
      }

      // Voice record/upload (unchanged) …
      let mediaRecorder;
      let audioChunks = [];
      const micIcon = recordBtn.querySelector('i');
      const recordStatus = document.getElementById('record-status');
      const audioInput = document.getElementById('audio-upload');
      recordBtn.addEventListener('click', async () => {
        /* (same as before) */ });
      uploadAudioBtn.addEventListener('click', () => {
        if (!uploadAudioBtn.disabled) audioInput.click();
      });
      audioInput.addEventListener('change', async function() {
        /* (same as before) */ });

      // History helpers (∞-scroll + stats)
      const MAX_JSON_BYTES = 500 * 1024;
      const PAGE_SIZE = 30;
      const SCROLL_FETCH_THRESHOLD = 80;

      const truncateJson = (s) => (!s ? null : (s.length > MAX_JSON_BYTES ? s.slice(0, MAX_JSON_BYTES) : s));
      const safeParse = (json) => {
        try {
          return json ? JSON.parse(json) : null;
        } catch {
          return null;
        }
      };

      function getUserIdFromLSorJWT() {
        const ls = localStorage.getItem('userId');
        if (ls && !isNaN(ls)) return parseInt(ls, 10);
        const token = localStorage.getItem('bearerToken');
        try {
          const claims = parseJwt ? parseJwt(token) : null;
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

      function getRowCountFromResultJSON(resultJson) {
        const r = safeParse(resultJson);
        if (!r) return null;
        if (Array.isArray(r)) return r.length;
        if (Array.isArray(r?.recordset)) return r.recordset.length;
        if (Array.isArray(r?.result)) return r.result.length;
        if (typeof r === 'object' && typeof r.length === 'number') return r.length;
        return null;
      }

      function renderHistoryItem(item, {
        prepend = false
      } = {}) {
        appendMessage('user', item.question || '', {
          prepend
        });
        const wrap = document.createElement('div');
        wrap.classList.add('sql-response', 'message');

        const meta = document.createElement('div');
        meta.style.fontSize = '11px';
        meta.style.opacity = '0.7';
        meta.style.marginBottom = '6px';
        const latPretty = (item.latency_ms != null) ? prettyLatency(Number(item.latency_ms)) : null;
        const createdAt = item.created_at || item.createdAt || '';
        meta.textContent = `[${item.source}] ${createdAt}${latPretty ? ' • ⏱ ' + latPretty : ''}`;
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
            at: createdAt
          };
          pre.textContent = JSON.stringify(payload, null, 2);
          details.appendChild(pre);
          wrap.appendChild(details);
        }

        if (prepend) {
          const insertBeforeNode = container.children[2] || null;
          container.insertBefore(wrap, insertBeforeNode);
        } else {
          container.appendChild(wrap);
        }
      }

      // [∞-scroll] Pagination state
      let isFetchingOlder = false;
      let hasMoreBefore = true;
      let totalLoaded = 0;
      let oldestCursor = null;
      const seenIds = new Set();

      function getItemId(item, idx) {
        return item.id ?? item.log_id ?? item.ID ?? `${item.created_at || item.createdAt || 'no-ts'}#${idx}#${(item.question || '').slice(0,50)}`;
      }

      function buildLogsQuery({
        limit,
        beforeId,
        beforeTs,
        offset
      }) {
        const userId = localStorage.getItem('userId') || '';
        const params = new URLSearchParams();
        params.set('limit', String(limit));
        if (userId) params.set('user_id', userId);
        if (beforeId) params.set('before_id', beforeId);
        if (beforeTs) params.set('before_ts', beforeTs);
        if (offset != null) params.set('offset', String(offset));
        params.set('order', 'desc');
        return params;
      }

      // Fetch page and return full payload (items, next_cursor, stats, …)
      async function fetchLogsPage({
        useCursor = true
      } = {}) {
        const params = buildLogsQuery({
          limit: PAGE_SIZE,
          beforeId: useCursor ? oldestCursor : null,
          beforeTs: null,
          offset: useCursor ? null : totalLoaded
        });
        const url = '/ai/sql/get_logs.php?' + params.toString();
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Unknown error');
        return data;
      }

      function renderHistoryBatch(items, {
        prepend
      }) {
        if (!items || items.length === 0) return 0;
        const list = prepend ? items.slice().reverse() : items.slice();
        let rendered = 0;
        const prevHeight = container.scrollHeight;

        for (let i = 0; i < list.length; i++) {
          const it = list[i];
          const id = getItemId(it, i);
          if (seenIds.has(id)) continue;
          seenIds.add(id);
          renderHistoryItem(it, {
            prepend
          });
          rendered++;
        }

        if (prepend) {
          const newHeight = container.scrollHeight;
          container.scrollTop = newHeight - prevHeight + container.scrollTop; // anchor
        } else {
          container.scrollTop = container.scrollHeight;
        }
        return rendered;
      }

      // STATS render/update
      function renderStats(stats) {
        if (!stats || !stats.all || !stats.today) {
          statsbar.style.display = 'none';
          return;
        }
        statsbar.style.display = 'flex'; //comment this to remove from ui
        const a = stats.all,
          t = stats.today;
        todayOkEl.textContent = `Today OK: ${t.success_pct}% (${t.success}/${t.total || 0})`;
        todayFailEl.textContent = `Fail: ${t.failed_pct}% (${t.failed})`;
        allOkEl.textContent = `All OK: ${a.success_pct}% (${a.success}/${a.total || 0})`;
        allFailEl.textContent = `Fail: ${a.failed_pct}% (${a.failed})`;
      }
      async function refreshStats() {
        const params = buildLogsQuery({
          limit: 1,
          beforeId: null,
          beforeTs: null,
          offset: 0
        });
        const url = '/ai/sql/get_logs.php?' + params.toString();
        const res = await fetch(url);
        const data = await res.json();
        if (data && data.ok) renderStats(data.stats);
      }

      async function loadInitialHistory() {
        container.innerHTML = '';
        container.appendChild(topSentinel);
        container.appendChild(topLoader);
        const intro = document.createElement('div');
        intro.classList.add('sql-response', 'message');
        intro.textContent = 'Type a natural language query like “Show me top 5 customers”';
        container.appendChild(intro);

        try {
          const data = await fetchLogsPage({
            useCursor: true
          });
          const items = data.items || [];
          renderStats(data.stats);
          if (items.length === 0) return;
          // initial render oldest→newest
          renderHistoryBatch(items.slice().reverse(), {
            prepend: false
          });
          oldestCursor = data.next_cursor ?? oldestCursor;
        } catch (e) {
          appendMessage('sql', '⚠️ Failed to load history: ' + e.message);
        }
      }

      async function loadOlderOnScroll() {
        if (isFetchingOlder || !hasMoreBefore) return;
        if (container.scrollTop > SCROLL_FETCH_THRESHOLD) return;

        isFetchingOlder = true;
        topLoader.classList.add('active');

        try {
          let data = await fetchLogsPage({
            useCursor: true
          });
          let rendered = renderHistoryBatch(data.items, {
            prepend: true
          });
          if (data.next_cursor) oldestCursor = data.next_cursor;

          const gotNew = rendered > 0;

          if (!gotNew && hasMoreBefore) {
            data = await fetchLogsPage({
              useCursor: false
            });
            rendered = renderHistoryBatch(data.items, {
              prepend: true
            });
          }
          if (rendered === 0) hasMoreBefore = false;
        } catch (e) {
          console.warn('loadOlderOnScroll failed:', e.message);
        } finally {
          topLoader.classList.remove('active');
          isFetchingOlder = false;
        }
      }

      // Wire-up
      sendBtn.addEventListener('click', () => {
        if (isSending) {
          if (currentAbortController) currentAbortController.abort();
          return;
        }
        askMSSQLAI();
      });
      inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (!isSending) askMSSQLAI();
        }
      });
      container.addEventListener('scroll', () => {
        loadOlderOnScroll();
      });

      document.addEventListener('DOMContentLoaded', () => {
        loadInitialHistory();
      });
    })();
  </script>
</body>

</html>