document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const username = document.getElementById('username').value;
  const password = document.getElementById('password').value;

  const response = await fetch('http://192.168.2.22:3001/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password })
  });

  const data = await response.json();

  if (response.ok && data.accessToken) {
    localStorage.setItem('bearerToken', data.accessToken);
    window.location.href = '../dashboard/index.php';
  } else {
    document.getElementById('loginMessage').innerText = '❌ Login failed';
  }
});
