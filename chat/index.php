<style>
  .chat-body {
  display: flex;
  flex-direction: column;
  height: 100%;
  position: relative;
  overflow: hidden; /* ✅ added */
}


  .chat-messages {
    flex: 1;
    overflow-y: auto;
    /* padding: 80px 20px 140px; */
    display: flex;
    flex-direction: column;
    gap: 10px;

    /* ✅ added nice scrollbar */
    scrollbar-width: thin;                     /* Firefox */
    scrollbar-color: #444 transparent;         /* Firefox */
  }

  /* ✅ Webkit scrollbar for Chrome/Edge */
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
  margin-top: auto; /* Push input to bottom inside flex layout */
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

  .send-button {
    margin-left: 12px;
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    background-color: #0a84ff;
    color: white;
    font-size: 15px;
    cursor: pointer;
  }

  .send-button:hover {
    background-color: #006fd6;
  }

  .error-message {
    align-self: flex-start;
    background-color: #3a1a1a;
    color: #ff5f5f;
    border: 1px solid #ff5f5f;
    border-radius: 10px;
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

  <!-- ✅ input stays here, scroll only above -->
  <div class="chat-input-container">
    <input id="prompt" class="chat-input" placeholder="Type your message..." />
<button class="icon-button" onclick="askStreamingAI()" title="Send">
  <i class="fa-solid fa-paper-plane"></i>
</button>
<button id="mic-btn" class="icon-button" title="Speak">
  <i class="fa-solid fa-microphone"></i>
</button>
  </div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function () {
    window.askStreamingAI = async function () {
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

      try {
        const res = await fetch('http://192.168.2.70:3001/api/askAI', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
          },
          body: JSON.stringify({
            model: 'deepseek-r1:7b',
            prompt: prompt
          })
        });

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let fullText = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value);
          fullText += chunk;
          aiMsg.textContent = fullText;
          chatbox.scrollTop = chatbox.scrollHeight;
        }
      } catch (err) {
        aiMsg.textContent = '❌ Error: ' + err.message;
      }
    };

    function appendMessage(role, text) {
      const chatbox = document.getElementById('chat-container');
      const msg = document.createElement('div');
      msg.classList.add(role === 'user' ? 'user-message' : 'ai-message', 'message');
      msg.textContent = text;
      chatbox.appendChild(msg);
      chatbox.scrollTop = chatbox.scrollHeight;
    }
  });
</script>
