<?php $site_root = 'https://' . $_SERVER['HTTP_HOST']; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip!T - Travel Itinerary Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            min-height: 800px;
        }
        .site-header {
            background-color: #000;
            color: #fff;
            padding:  0px;
            transition: all 0.3s ease;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .dropdown-item:focus, .dropdown-item:hover, .nav-item:hover, .nav-item:focus {
    color: #1e2125;
    background-color: #434f5a !important;
}
        .navbar-nav li{ 
            margin: 0px 5px;
        }
        .site-header.scrolled {
            padding: 0;
        }
        .site-header.scrolled .header-content {
            display: none;
        }
        .logo {
            height: 40px;
        }
        .header-logo {
            height: 80px;
        }
        .nav-menu {
            background-color: #212529;
        }
        .main-content {
            margin-top: 150px;
        }
        .navbar-dark .navbar-toggler {
            border-color: rgba(255,255,255,.5);
        }
        .dropdown-submenu {
            position: relative;
        }
        .dropdown-submenu .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -1px;
        }
        @media (min-width: 992px) {
            .header-content .container {
                justify-content: flex-start !important;
            }
            .dropdown:hover > .dropdown-menu {
                display: block;
            }
            .dropdown-submenu:hover > .dropdown-menu {
                display: block;
            }
        }
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: #212529;
                padding: 1rem;
                max-height: 80vh;
                overflow-y: auto;
            }
            .ms-auto {
                margin-left: 0 !important;
            }
            .nav-item {
                width: 100%;
                margin: 0.25rem 0;
            }
            .dropdown-menu {
                border: none;
                padding-left: 1rem;
                background-color: transparent;
            }
            .dropdown-submenu .dropdown-menu {
                padding-left: 2rem;
            }
            .dropdown-item {
                color: rgba(255,255,255,.55) !important;
            }
            .dropdown-item:hover {
                background-color: transparent;
                color: rgba(255,255,255,.75) !important;
            }
            .dropdown-menu.show {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-content">
            <div class="container d-flex align-items-center">
                <img src="<?php echo $site_root; ?>/media/logo.png" alt="Travel Booking Portal Logo" class="header-logo me-3">
            </div>
        </div>
        
        <nav class="navbar navbar-expand-lg navbar-dark nav-menu">
            <div class="container">
                <a class="navbar-brand d-none d-scroll-block" href="#">
                    <img src="<?php echo $site_root; ?>/media/logo.png" alt="Travel Booking Portal Logo" class="logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="show" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav align-items-lg-center w-100">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $site_root; ?>/home.php">Home</a>
                        </li>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $site_root; ?>/trip.php">My Trips</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Profile
                                </a>
                                <ul class="dropdown-menu bg-dark">
                                    <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/profile.php">My Profile</a></li>
                                    <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/upgrade.php">Upgrade Account</a></li>
                                </ul>
                            </li>
                            <?php if(isset($_SESSION['authlevel']) && $_SESSION['authlevel'] === 'admin'): ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Admin
                                    </a>
                                    <ul class="dropdown-menu bg-dark">
                                        <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin.php">Admin Panel</a></li>
                                        <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_users.php">Users</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_logs.php">Logs</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_categories.php">Categories</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_items.php">Items</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_airlinelist.php">Airlines</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_airportlist.php">Airports</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_settings.php">Settings</a></li>
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/admin_trips.php">Trips</a></li>  
                                                <li><a class="dropdown-item text-white" href="<?php echo $site_root; ?>/mask/fetchemail.php">Emails</a></li>
                                       
                                    </ul>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item ms-lg-auto">
                                <a class="nav-link" href="<?php echo $site_root; ?>/logout.php">Logout</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item ms-lg-auto">
                                <a class="nav-link" href="<?php echo $site_root; ?>/login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $site_root; ?>/register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle scroll effects
            window.addEventListener('scroll', function() {
                const header = document.querySelector('.site-header');
                const scrolledLogo = document.querySelector('.d-scroll-block');
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                    scrolledLogo.classList.remove('d-none');
                } else {
                    header.classList.remove('scrolled');
                    scrolledLogo.classList.add('d-none');
                }
            });

            // Handle hamburger menu
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            var testA = document.getElementById("navbarNav");

            navbarToggler.addEventListener('click', function(e) {
                e.preventDefault();
                const navbarCollapse = document.querySelector('.navbar-collapse');
                if (navbarCollapse.classList.contains('show')) {
                    navbarCollapse.classList.remove('show');
                    testA.classList.remove('show');
                } else {
                    navbarCollapse.classList.add('show');
                }
                console.log(navbarCollapse.classList.contains('show') ? "show class added." : "show class removed.");
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                    navbarCollapse.classList.remove('show');
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show'); 
                    });
                }
               
            });

            // Handle dropdown submenus for mobile
            document.querySelectorAll('.dropdown-submenu > a').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const submenu = this.nextElementSibling;
                    const allSubmenus = document.querySelectorAll('.dropdown-submenu .dropdown-menu');
                    
                    // Close other submenus
                    allSubmenus.forEach(menu => {
                        if (menu !== submenu) {
                            menu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current submenu
                    submenu.classList.toggle('show');
                });
            });

            // Close menu when clicking outside
           

            // Close menu when clicking nav links
            document.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(link => {
                link.addEventListener('click', () => {
                    navbarCollapse.classList.remove('show');
                    // Close all dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                    });
                });
            });

            // Initialize Bootstrap dropdowns
            const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
            dropdownElementList.forEach(function(dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    if (window.innerWidth < 992) { // Mobile view
                        e.preventDefault();
                        e.stopPropagation();
                        const dropdownMenu = this.nextElementSibling;
                        dropdownMenu.classList.toggle('show');
                    }
                });
            });
        });
    </script>
    
    <div class="main-content">
        <div class="container">
