<?php
if (isset($_GET['page'])) {
    switch ($_GET['page']) {
        case 'login':
            $page = 'login/index.php';
            break;
        case 'chat':
            $page = 'chat/index.php';
            break;
        case 'dashboard':
            $page = 'dashboard/index.php';
            break;
        case 'sql':
            $page = 'sql/index.php';
            break;
            // default:
            //     $page = 'login/index.php';
    }
} else {
    // $page = 'login/index.php';
}
