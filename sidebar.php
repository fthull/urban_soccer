<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index3.html" class="brand-link">
        <img src="logom.png" alt="AdminLTE Logo" class="brand-image" style="opacity: .8">
    </a>
<br>
<br>
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="admin.php" class="nav-link <?php if ($active_page == 'dashboard') echo 'active'; ?>">
                        <i class="nav-icon fas fa-th"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tab_booking.php" class="nav-link <?php if ($active_page == 'pesanan') echo 'active'; ?>">
                        <i class="nav-icon fas fa-book"></i>
                        <p>Booking</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="history.php" class="nav-link <?php if ($active_page == 'history') echo 'active'; ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Histori</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_content.php" class="nav-link <?php if ($active_page == 'kelola-website') echo 'active'; ?>">
                        <i class="nav-icon fas fa-edit"></i>
                        <p>Manage Website</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>