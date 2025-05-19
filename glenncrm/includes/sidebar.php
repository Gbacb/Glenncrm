<div class="card sidebar">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="<?php echo BASE_URL; ?>/index.php?page=dashboard" class="list-group-item list-group-item-action <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=customers" class="list-group-item list-group-item-action <?php echo $page == 'customers' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=leads" class="list-group-item list-group-item-action <?php echo $page == 'leads' ? 'active' : ''; ?>">
                <i class="bi bi-funnel"></i> Leads
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="list-group-item list-group-item-action <?php echo $page == 'sales' ? 'active' : ''; ?>">
                <i class="bi bi-currency-dollar"></i> Sales
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions" class="list-group-item list-group-item-action <?php echo $page == 'interactions' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots"></i> Interactions
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="list-group-item list-group-item-action <?php echo $page == 'reminders' ? 'active' : ''; ?>">
                <i class="bi bi-bell"></i> Reminders
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=reports" class="list-group-item list-group-item-action <?php echo $page == 'reports' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart"></i> Reports
            </a>
            <?php if (is_admin()): ?>
                <div class="list-group-item text-muted small mt-2">Admin Options</div>
                <a href="<?php echo BASE_URL; ?>/index.php?page=users" class="list-group-item list-group-item-action <?php echo $page == 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i> Users
                </a>
                <a href="<?php echo BASE_URL; ?>/index.php?page=settings" class="list-group-item list-group-item-action <?php echo $page == 'settings' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        Quick Actions
    </div>
    <div class="card-body">
        <div class="d-grid gap-2">
            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=add" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-person-plus"></i> Add Customer
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=add" class="btn btn-sm btn-outline-success">
                <i class="bi bi-plus-circle"></i> New Lead
            </a>
            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-alarm"></i> Set Reminder
            </a>
        </div>
    </div>
</div>