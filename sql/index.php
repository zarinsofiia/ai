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

    .sql-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .sql-messages {
        flex: 1;
        overflow-y: auto;
        /* padding: 80px 20px 140px; */
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
</style>

<div class="sql-body">
    <div class="sql-messages" id="sql-container">
        <div class="sql-response message">💡 Type a natural language query like "Show me top 5 customers"</div>
    </div>

    <div class="sql-input-container">
        <input id="sql-prompt" class="sql-input" placeholder="Ask in plain English..." />
        <button class="sql-btn" onclick="askSQLAI()"><i class="fa-solid fa-paper-plane"></i></button>
        <button id="record-btn" class="sql-btn"><i class="fa-solid fa-microphone"></i></button>
        <input type="file" id="audio-upload" accept="audio/*" style="display: none;" />

        <button class="sql-btn" onclick="document.getElementById('audio-upload').click()">
            <i class="fa-solid fa-file-audio"></i>
        </button>
    </div>
    <span id="record-status" style="padding-left: 20px; font-size: 13px; color: #aaa;"></span>


</div>

<script>
    async function askSQLAI() {
        const input = document.getElementById('sql-prompt');
        const prompt = input.value.trim();
        if (!prompt) return;

        appendMessage('user', prompt);
        input.value = '';

        const container = document.getElementById('sql-container');
        const responseMsg = document.createElement('div');
        responseMsg.classList.add('sql-response', 'message');
        container.appendChild(responseMsg);
        container.scrollTop = container.scrollHeight;

        try {
            const res = await fetch('http://192.168.2.70:3001/api/askSQL', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
                },
                body: JSON.stringify({
                    prompt
                })
            });

            const data = await res.json();
            if (data.summary) {
                responseMsg.textContent = data.summary;
            } else {
                responseMsg.textContent = '✅ SQL executed. Check the console for raw result.';
                console.log('Raw result:', data);
            }
        } catch (err) {
            responseMsg.textContent = '❌ Error: ' + err.message;
        }

        container.scrollTop = container.scrollHeight;
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
                        const res = await fetch('http://192.168.2.70:3001/api/transcribe', {
                            method: 'POST',
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
                            },
                            body: formData
                        });

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
</script>
<script>
document.getElementById('audio-upload').addEventListener('change', async function () {
  const file = this.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('audio', file);

  const status = document.getElementById('record-status');
  status.textContent = '⏳ Transcribing uploaded file...';

  try {
    const res = await fetch('http://192.168.2.70:3001/api/transcribe', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
      },
      body: formData
    });

    const data = await res.json();
    document.getElementById('sql-prompt').value = data.transcript || '';
    status.textContent = '✅ Transcribed from file';
  } catch (err) {
    status.textContent = '❌ File transcription failed';
  }
});
</script>
