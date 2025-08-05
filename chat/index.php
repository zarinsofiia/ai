<style>
  /* (keep your original style unchanged) */
  .chat-body {
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
    overflow: hidden;
  }

  .chat-messages {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    scrollbar-width: thin;
    scrollbar-color: #444 transparent;
  }

  .chat-messages::-webkit-scrollbar {
    width: 6px;
  }

  .chat-messages::-webkit-scrollbar-thumb {
    background-color: #444;
    border-radius: 3px;
  }

  .chat-messages::-webkit-scrollbar-track {
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
    border-bottom-right-radius: 12px;
    border-bottom-left-radius: 12px;
  }

  .ai-message {
    align-self: flex-start;
    background-color: #2c2c2c;
    color: #f1f1f1;
    border-top-left-radius: 0;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
  }

  .chat-input-container {
    margin-top: auto;
    display: flex;
    gap: 10px;
    padding: 20px;
    background-color: #1e1e1e;
    border-top: 1px solid #333;
  }

  .chat-input {
    flex: 1;
    padding: 14px;
    border-radius: 10px;
    border: 1px solid #444;
    font-size: 15px;
    background-color: #2b2b2b;
    color: #f1f1f1;
    outline: none;
  }

  .icon-button {
    width: 48px;
    height: 48px;
    background-color: #2a2a2a;
    border: none;
    border-radius: 10px;
    color: #f1f1f1;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s ease;
  }

  .icon-button:hover {
    background-color: #3a3a3a;
  }
</style>

<div class="chat-body">
  <div class="chat-messages" id="chat-container">
    <div class="ai-message message">Hi! Ask me anything</div>
  </div>

  <div class="chat-input-container">
    <input id="prompt" class="chat-input" placeholder="Type your message..." />
    <button class="icon-button" onclick="askStreamingAI()" title="Send">
      <i class="fa-solid fa-paper-plane"></i>
    </button>
    <button id="mic-btn" class="icon-button" title="Record audio">
      <i class="fa-solid fa-microphone"></i>
    </button>
    <input type="file" id="chat-audio-upload" accept="audio/*" style="display: none;" />
    <button class="icon-button" onclick="document.getElementById('chat-audio-upload').click()" title="Upload audio">
      <i class="fa-solid fa-file-audio"></i>
    </button>
  </div>
  <span id="chat-record-status" style="padding-left: 10px; font-size: 13px; color: #aaa;"></span>

</div>
<script src="../ai/auth-check.js"></script>


<script>
  async function askStreamingAI() {
    const input = document.getElementById('prompt');
    const prompt = input.value.trim();
    if (!prompt) return;

    appendMessage('user', prompt);
    input.value = '';

    const chatbox = document.getElementById('chat-container');
    const aiMsg = document.createElement('div');
    aiMsg.classList.add('ai-message', 'message');
    chatbox.appendChild(aiMsg);
    chatbox.scrollTop = chatbox.scrollHeight;

    let accessToken = localStorage.getItem('bearerToken');
    let res = await fetchWithAuth(accessToken, prompt);

    if (res?.status === 401 || res?.status === 403) {
      // Token invalid or expired — logout
      return logoutAndRedirect();
    }

    if (!res || !res.ok) {
      aiMsg.textContent = '❌ Error: Failed to fetch response';
      return;
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let fullText = '';

    while (true) {
      const {
        done,
        value
      } = await reader.read();
      if (done) break;
      const chunk = decoder.decode(value);
      fullText += chunk;
      aiMsg.textContent = fullText;
      chatbox.scrollTop = chatbox.scrollHeight;
    }
  }


  async function fetchWithAuth(token, prompt) {
    try {
      return await fetch('http://192.168.2.69:3001/api/askAI', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({
          model: 'deepseek-r1:7b',
          prompt
        })
      });
    } catch (err) {
      console.error('Fetch error:', err);
      return null;
    }
  }

  function appendMessage(role, text) {
    const chatbox = document.getElementById('chat-container');
    const msg = document.createElement('div');
    msg.classList.add(role === 'user' ? 'user-message' : 'ai-message', 'message');
    msg.textContent = text;
    chatbox.appendChild(msg);
    chatbox.scrollTop = chatbox.scrollHeight;
  }
</script>

<script>
  // 🎤 Microphone Recording (transcribe only)
  let mediaRecorder;
  let audioChunks = [];
  const micBtn = document.getElementById('mic-btn');
  const micIcon = micBtn.querySelector('i');
  const chatStatus = document.getElementById('chat-record-status');

  micBtn.addEventListener('click', async () => {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.stop();
      micIcon.className = 'fa-solid fa-microphone';
      chatStatus.textContent = 'Transcribing...';
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
            const res = await fetch('http://192.168.2.69:3001/api/askAI/transcribe', {
              method: 'POST',
              headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
              },
              body: formData
            });
            const data = await res.json();
            document.getElementById('prompt').value = data.transcript || '';
            chatStatus.textContent = '✅ Transcribed';
          } catch (err) {
            chatStatus.textContent = '❌ Transcription failed';
          }
        };

        mediaRecorder.start();
        micIcon.className = 'fa-solid fa-stop';
        chatStatus.textContent = '🎙️ Recording... Click to stop';
      } catch (err) {
        chatStatus.textContent = '❌ Microphone access denied';
      }
    }
  });
</script>

<script>
  // 🎵 File Upload Transcription (no auto-send)
  document.getElementById('chat-audio-upload').addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('audio', file);
    const chatStatus = document.getElementById('chat-record-status');
    chatStatus.textContent = '⏳ Transcribing uploaded file...';

    try {
      const res = await fetch('http://192.168.2.69:3001/api/askAI/transcribe', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + localStorage.getItem('bearerToken')
        },
        body: formData
      });

      const data = await res.json();
      document.getElementById('prompt').value = data.transcript || '';
      chatStatus.textContent = '✅ Transcribed from file';
    } catch (err) {
      chatStatus.textContent = '❌ File transcription failed';
    }
  });
</script>