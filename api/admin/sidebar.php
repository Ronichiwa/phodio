
<header class="main-header d-lg-none">
    <div class="d-flex justify-content-between align-items-center w-100">
        <div class="header-logo">
            SOUL<span style="color:var(--accent-red)">PRINT</span>
        </div>
        <button class="menu-toggle-btn" onclick="toggleSidebar()">
            <i class="ri-menu-3-line"></i>
        </button>
    </div>
</header>

<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header d-flex justify-content-between align-items-center">
        <span>SOUL<span style="color:var(--accent-red)">PRINT</span></span>
        <button class="btn d-lg-none text-white p-0" onclick="toggleSidebar()">
            <i class="ri-close-line ri-xl"></i>
        </button>
    </div>
    
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="nav-link-custom <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
            <i class="ri-dashboard-line"></i> Dashboard
        </a>
        <a href="bookings.php" class="nav-link-custom <?= (basename($_SERVER['PHP_SELF']) == 'bookings.php') ? 'active' : '' ?>">
            <i class="ri-calendar-event-line"></i> Bookings
        </a>
        <a href="expenses.php" class="nav-link-custom <?= (basename($_SERVER['PHP_SELF']) == 'expenses.php') ? 'active' : '' ?>">
            <i class="ri-wallet-3-line"></i> Expenses
        </a>
        <a href="liabilities.php" class="nav-link-custom <?= (basename($_SERVER['PHP_SELF']) == 'liabilities.php') ? 'active' : '' ?>">
            <i class="ri-bank-card-line"></i> Liabilities
        </a>
        <a href="tracker.php" class="nav-link-custom <?= (basename($_SERVER['PHP_SELF']) == 'tracker.php') ? 'active' : '' ?>">
            <i class="ri-line-chart-line"></i> Daily Tracker
        </a>
        
    <!--    <div class="mt-auto">
            <a href="settings.php" class="nav-link-custom">
                <i class="ri-settings-3-line"></i> Settings
            </a>
        </div>-->
        <div class="mt-auto">
           
            <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal" class="nav-link-custom text-danger fw-bold">
                <i class="ri-logout-box-r-line"></i> Logout
            </a>
        </div>
    </nav>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: #1a1a1a; border-radius: 15px;">
            <div class="modal-body p-5 text-center">
                <div class="icon-circle mx-auto mb-4" style="background: rgba(239, 68, 68, 0.1); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="ri-logout-circle-line text-danger" style="font-size: 2rem;"></i>
                </div>
                <h4 class="fw-bold text-white mb-2">End Session?</h4>
                <p class="text-muted mb-4">Are you sure you want to log out of the Soulprint Management System?</p>
                
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-dark px-4 py-2 border-secondary" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Cancel</button>
                    <a href="logout.php" class="btn btn-danger px-4 py-2" style="border-radius: 8px; font-weight: 600; background: #ef4444;">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleSidebar() {
    document.getElementById('mainSidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
    // Prevent background scrolling when menu is open
    document.body.style.overflow = document.getElementById('mainSidebar').classList.contains('show') ? 'hidden' : 'auto';
}
</script>
