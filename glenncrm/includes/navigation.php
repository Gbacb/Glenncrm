<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
            <?php echo get_setting('company_name', 'Glenn CRM'); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'customers' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php?page=customers">
                        <i class="bi bi-people"></i> Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'leads' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php?page=leads">
                        <i class="bi bi-funnel"></i> Leads
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'reminders' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php?page=reminders">
                        <i class="bi bi-bell"></i> Reminders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'reports' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php?page=reports">
                        <i class="bi bi-bar-chart"></i> Reports
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo $_SESSION['first_name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (is_admin()): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=users">
                                <i class="bi bi-people"></i> User Management
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=settings">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=logout">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>