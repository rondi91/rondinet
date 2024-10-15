
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <button type="button" id="sidebarCollapse" class="btn btn-info">
            <i class="fas fa-align-left"></i>
            <span>Toggle Sidebar</span>
        </button>
        <a class="navbar-brand" href="index.php">Dashboard</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="#">Home</a>
                </li>
                <li class="<?php echo ($current_page == 'manage_pppoe.php') ? 'active' : ''; ?>">
                    <a href="manage_pppoe.php">Manage Routers</a>
                </li>
                <li class="<?php echo ($current_page == 'manage_routers.php') ? 'active' : ''; ?>">
                    <a href="manage_routers.php">Manage Routers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Profile</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
