<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AI SQL Assistant</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f2f2f2;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    header {
      background: #343a40;
      color: white;
      padding: 16px 24px;
      font-size: 20px;
      font-weight: bold;
    }

    .chat-container {
      flex: 1;
      overflow-y: auto;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .bubble {
      max-width: 80%;
      padding: 14px 18px;
      border-radius: 12px;
      line-height: 1.5;
      white-space: pre-wrap;
    }

    .user-msg {
      align-self: flex-end;
      background: #007bff;
      color: white;
      border-bottom-right-radius: 0;
    }

    .ai-msg {
      align-self: flex-start;
      background: #e9ecef;
      border-bottom-left-radius: 0;
    }

    .input-bar {
      background: white;
      padding: 16px;
      display: flex;
      border-top: 1px solid #ccc;
    }

    .input-bar textarea {
      flex: 1;
      resize: none;
      padding: 10px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #ccc;
    }

    .input-bar button {
      margin-left: 12px;
      padding: 10px 20px;
      background: #007bff;
      color: white;
      font-weight: bold;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .input-bar button:hover {
      background: #0056b3;
    }

    .sql-box {
      font-size: 14px;
      font-family: monospace;
      background: #f8f9fa;
      border-left: 4px solid #007bff;
      padding: 10px;
      margin-top: 8px;
    }
  </style>
</head>
<body>

<header>🧠 AI SQL Assistant</header>

<div class="chat-container" id="chat">
  <!-- Chat bubbles will be inserted here -->
</div>

<div class="input-bar">
  <textarea id="prompt" rows="2" placeholder="Ask anything about your database..."></textarea>
  <button onclick="askAI()">Send</button>
</div>

<script>
  function appendBubble(text, type) {
    const chat = document.getElementById('chat');
    const div = document.createElement('div');
    div.className = `bubble ${type === 'user' ? 'user-msg' : 'ai-msg'}`;
    div.innerText = text;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
  }

  function appendSQL(sql, result, summary) {
    const chat = document.getElementById('chat');
    const bubble = document.createElement('div');
    bubble.className = 'bubble ai-msg';
    bubble.innerHTML = `
      <strong>📄 Summary:</strong> ${summary || 'N/A'}<br>
      <div class="sql-box"><strong>🧾 SQL:</strong><br>${sql}</div>
      <div class="sql-box"><strong>📊 Result:</strong><br><pre>${JSON.stringify(result, null, 2)}</pre></div>
    `;
    chat.appendChild(bubble);
    chat.scrollTop = chat.scrollHeight;
  }

  async function askAI() {
    const promptInput = document.getElementById('prompt');
    const prompt = promptInput.value.trim();
    if (!prompt) return;

    appendBubble(prompt, 'user');
    promptInput.value = '';

    try {
      const res = await fetch('http://192.168.2.70:3001/api/askAI/sql', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: 'MFDoom/deepseek-r1-tool-calling:7b', prompt })
      });

      const data = await res.json();
      if (data.error) {
        appendBubble(`❌ ${data.error}`, 'ai');
      } else {
        appendSQL(data.sql, data.result, data.summary);
      }

    } catch (err) {
      appendBubble(`❌ Network error: ${err.message}`, 'ai');
    }
  }
</script>

</body>
</html>
