<style>
  .dashboard {
    padding: 40px;
    color: #f1f1f1;
  }

  .dashboard h2 {
    font-size: 28px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .dashboard-cards {
    margin-top: 30px;
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
  }

  .dashboard-card {
    background-color: #1f1f1f;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
    width: 250px;
    text-decoration: none;
    color: #f1f1f1;
    transition: background-color 0.2s ease, transform 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .dashboard-card:hover {
    background-color: #292929;
    transform: translateY(-3px);
  }

  .dashboard-card i {
    font-size: 30px;
    color: #0a84ff;
  }

  .card-title {
    font-size: 18px;
    font-weight: 600;
  }

  .card-desc {
    font-size: 14px;
    color: #aaa;
  }
</style>

<div class="dashboard">
  <h2><i class="fa-solid fa-robot"></i> Welcome back!</h2>
  <p style="font-size: 16px; color: #aaa;">Select a module from the options below to get started with your assistant.</p>

  <div class="dashboard-cards">
  <a href="main.php?page=chat" class="dashboard-card">
    <i class="fa-solid fa-comments"></i>
    <div class="card-title">Chat</div>
    <div class="card-desc">Chat naturally with your AI assistant to get instant answers or help.</div>
  </a>

  <a href="main.php?page=sql" class="dashboard-card">
    <i class="fa-solid fa-database"></i>
    <div class="card-title">SQL Assistant</div>
    <div class="card-desc">Ask questions in plain English and retrieve data directly from your database.</div>
  </a>
</div>

</div>
