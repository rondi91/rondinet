<nav id="sidebar">
    <div class="sidebar-header">
        <h3>Winbox-style Dashboard</h3>
    </div>
    <ul class="list-unstyled components">
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">Dashboard</a>
            <ul class="collapse list-unstyled" id="homeSubmenu">
                <li>
                    <a href="#">Overview</a>
                </li>
                <li>
                    <a href="#">Stats</a>
                </li>
            </ul>
        
        
        </li>
        <li class="<?php echo ($current_page == 'manage_pppoe.php') ? 'active' : ''; ?>">
                    <a href="manage_pppoe.php">Manage PPPOE</a>
                </li>
        <li class="<?php echo ($current_page == 'manage_routers.php') ? 'active' : ''; ?>">
            <a href="manage_routers.php">Manage Routers</a>
        </li>

        <li>
            <a href="#">Profile</a>
        </li>
        <li>
            <a href="setting.php">Settings</a>
        </li>
        <li>
            <a href="#">Logout</a>
        </li>
    </ul>
</nav>
