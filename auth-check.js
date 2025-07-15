function logoutAndRedirect() {
  localStorage.removeItem('bearerToken');
  localStorage.removeItem('refreshToken');
  alert('Your session has expired. Please login again.');
  window.location.href = '../ai'; // adjust if needed
}

// Decode token and check expiry
function isTokenExpired(token) {
  if (!token) return true;
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    const now = Math.floor(Date.now() / 1000);
    return payload.exp < now;
  } catch (e) {
    return true; // invalid format
  }
}

// ✅ Check immediately on load
(function () {
  const token = localStorage.getItem('bearerToken');
  if (!token || isTokenExpired(token)) {
    logoutAndRedirect();
  }
})();
