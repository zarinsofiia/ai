<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
  * {
    box-sizing: border-box;
  }

  body {
    font-family: 'Inter', sans-serif;
    background-color: #121212;
    margin: 0;
    padding: 0;
    color: #f1f1f1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .login-wrapper {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
  }

  .login-container {
    background: #1e1e1e;
    padding: 40px 30px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    width: 100%;
    max-width: 400px;
  }

  .login-container h2 {
    text-align: center;
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 24px;
    color: #ffffff;
  }

  input {
    width: 100%;
    padding: 12px;
    margin-bottom: 16px;
    background-color: #2b2b2b;
    border: 1px solid #444;
    border-radius: 8px;
    color: #f1f1f1;
    font-size: 15px;
  }

  input::placeholder {
    color: #888;
  }

  button {
    width: 100%;
    padding: 12px;
    background-color: #0a84ff;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s ease;
  }

  button:hover {
    background-color: #006fd6;
  }

  .message {
    text-align: center;
    margin-top: 12px;
    font-size: 14px;
    color: #ff5e5e;
  }

  .login-footer {
    text-align: center;
    font-size: 12px;
    color: #777;
    padding: 12px;
    margin-top: 30px;
  }
</style>

<div class="login-wrapper">
  <div class="login-container">
    <h2>Login to AI Assistant</h2>
    <form id="loginForm">
      <input type="text" id="username" placeholder="Username" required>
      <input type="password" id="password" placeholder="Password" required>
      <button type="submit">Login</button>
    </form>
    <div class="message" id="loginMessage"></div>
    <div class="login-footer">AI Assistant • Developed by Softworld Software Sdn Bhd</div>
  </div>
</div>

<script>
  document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const msg = document.getElementById('loginMessage');
    msg.innerText = '';

    if (!username || !password) {
      msg.innerText = '❌ Please fill in both fields.';
      return;
    }

    try {
      const response = await fetch('http://192.168.2.70:3001/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });

      const data = await response.json();

      if (response.ok && data.accessToken) {
        localStorage.setItem('bearerToken', data.accessToken);
        window.location.href = '../dashboard/index.php';
      } else {
        msg.innerText = '❌ Login failed: Invalid credentials';
      }

    } catch (err) {
      msg.innerText = '❌ Server error. Please try again.';
      console.error('Login error:', err);
    }
  });
</script>
