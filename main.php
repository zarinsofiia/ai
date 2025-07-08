<?php include 'routing.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AI Assistant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #121212;
            color: #f1f1f1;
        }

        .layout {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        header {
            background-color: #1e1e1e;
            padding: 15px 30px;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-area {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .sidebar {
            width: 200px;
            background-color: #1a1a1a;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
            border-right: 1px solid #2c2c2c;
            transition: width 0.3s ease;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            color: #ccc;
            font-size: 16px;
            padding: 10px 20px;
            text-align: left;
            cursor: pointer;
        }

        .toggle-sidebar:hover {
            color: #fff;
        }

        .sidebar a {
            color: #f1f1f1;
            text-decoration: none;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 6px;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar a:hover {
            background-color: #2c2c2c;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar.collapsed a span {
            display: none;
        }

        .sidebar.collapsed a {
            justify-content: center;
        }

        .sidebar.collapsed .toggle-sidebar {
            padding: 10px 0;
            text-align: center;
        }

        main {
            flex: 1;
            overflow: auto;
            padding: 30px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        footer {
            background: #111;
            text-align: center;
            padding: 10px;
            color: #888;
            font-size: 12px;
            flex-shrink: 0;
        }

        .sidebar a.active {
            background-color: #333;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <?php if ($_GET['page'] === 'login'): ?>
        <?php include $page; ?>
    <?php else: ?>
        <div class="layout">
            <header>
                <i class="fa-solid fa-robot"></i> AI Assistant
            </header>

            <div class="content-area">
                <nav class="sidebar" id="sidebar">
                    <button class="toggle-sidebar" onclick="toggleSidebar()" title="Toggle Sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <?php $active = $_GET['page'] ?? ''; ?>
                    <a href="main.php?page=dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-line"></i><span>Dashboard</span>
                    </a>
                    <a href="main.php?page=chat" class="<?= $active === 'chat' ? 'active' : '' ?>">
                        <i class="fa-solid fa-comments"></i><span>Chat</span>
                    </a>
                    <a href="main.php?page=sql" class="<?= $active === 'sql' ? 'active' : '' ?>">
                        <i class="fa-solid fa-database"></i><span>SQL</span>
                    </a>
                    <a href="login/index.php" onclick="localStorage.clear()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
                </nav>

                <main>
                    <?php include $page; ?>
                </main>
            </div>

            <footer>AI Assistant • Developed by Softworld Software Sdn Bhd</footer>
        </div>

        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('collapsed');
            }
        </script>
    <?php endif; ?>

</body>

</html>