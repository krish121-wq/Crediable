<?php
// Header-specific PHP logic
$nav_items = [
    'Features' => ['link' => '#features', 'icon' => 'fa-rocket', 'title' => 'Features Section'],
    'Pricing' => ['link' => '#pricing', 'icon' => 'fa-dollar-sign', 'title' => 'Pricing Plans'],
    'Blog' => ['link' => '#blog', 'icon' => 'fa-newspaper', 'title' => 'Blog Posts'],
    'Contact Us' => ['link' => '#contact', 'icon' => 'fa-headset', 'title' => 'Contact Support']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Grow Biller'); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpg" href="images/logo.jpg">
    <link rel="apple-touch-icon" sizes="180x180" href="images/logo.jpg">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Grow Biller - Simple Billing & Business Solutions">
    <meta property="og:description" content="Streamline your business with Grow Biller’s all-in-one solution for invoicing, inventory, payroll, and more. Start your free trial today!">
    <meta property="og:image" content="https://growbiller.com/images/logo.jpg">
    <meta property="og:url" content="https://growbiller.com">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Grow Biller - Simple Billing & Business Solutions">
    <meta name="twitter:description" content="Streamline your business with Grow Biller’s all-in-one solution for invoicing, inventory, payroll, and more. Start your free trial today!">
    <meta name="twitter:image" content="https://growbiller.com/images/logo.jpg">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Animate.css for enhanced animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <style>
        /* Navbar Styles */
        .navbar {
            transition: all 0.3s ease;
            background-color: #fff;
            box-shadow: none;
            z-index: 1000;
        }
        .navbar.sticky {
            background: linear-gradient(180deg, #fff, #f8fafc);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
        }
        .navbar-brand img {
            height: 65px;
            transition: transform 0.3s ease;
        }
        .navbar-brand img:hover {
            transform: scale(1.05);
        }
        .navbar-nav .nav-link {
            position: relative;
            color: #2d3748 !important;
            font-weight: 500;
            margin-left: 1rem;
            font-size: clamp(0.95rem, 2vw, 1.125rem);
            display: flex;
            align-items: center;
            transition: color 0.3s ease;
        }
        .navbar-nav .nav-link i {
            margin-right: 0.75rem;
            font-size: clamp(1rem, 1.5vw, 1.125rem);
            color: #571F5A;
            transition: transform 0.3s ease, color 0.3s ease;
        }
        .navbar-nav .nav-link:hover i {
            transform: scale(1.1);
            color: #6D2F6A;
        }
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: #571F5A;
            transition: width 0.3s ease;
        }
        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }
        .navbar-nav .nav-link:hover {
            color: #571F5A !important;
        }
        .btn-signup {
            background: linear-gradient(135deg, #571F5A, #6D2F6A);
            color: #fff;
            font-weight: bold;
            padding: 8px 20px;
            border-radius: 0.375rem;
            margin-left: 1rem;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .btn-signup:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(87, 31, 90, 0.4);
        }
        /* Sidebar Styles for Mobile */
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background: #fff;
            z-index: 2000;
            transition: all 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            font-family: 'Roboto', sans-serif;
        }
        .sidebar.active {
            left: 0;
            transform: scale(1);
        }
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, #571F5A, #6D2F6A);
            color: #fff;
            border-radius: 0 0 10px 10px;
        }
        .sidebar-header img {
            height: 50px;
            transition: transform 0.3s ease;
        }
        .sidebar-header img:hover {
            transform: scale(1.1);
        }
        .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .close-btn:hover {
            transform: rotate(90deg);
        }
        .sidebar-nav {
            padding: 1.5rem;
        }
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #2d3748;
            font-size: clamp(0.95rem, 2vw, 1.125rem);
            font-weight: 500;
            text-decoration: none;
            min-height: 48px;
            border-radius: 5px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .sidebar-nav .nav-link i {
            margin-right: 1rem;
            font-size: clamp(1rem, 1.5vw, 1.125rem);
            color: #571F5A;
            padding: 0.5rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 5px;
            transition: transform 0.3s ease, color 0.3s ease, background 0.3s ease;
        }
        .sidebar-nav .nav-link:hover i {
            transform: scale(1.1);
            color: #fff;
            background: #571F5A;
        }
        .sidebar-nav .nav-link:hover {
            background: #f8f9fa;
            color: #571F5A;
        }
        .sidebar-nav .btn-signup {
            width: 100%;
            text-align: center;
            margin-top: 1rem;
        }
        .back-to-top {
            display: block;
            padding: 0.75rem 1rem;
            color: #2d3748;
            font-size: clamp(0.95rem, 2vw, 1.125rem);
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 1rem;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .back-to-top i {
            margin-right: 0.75rem;
            font-size: clamp(1rem, 1.5vw, 1.125rem);
            color: #571F5A;
        }
        .back-to-top:hover {
            background: #571F5A;
            color: #fff;
        }
        .back-to-top:hover i {
            color: #fff;
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 1500;
            display: none;
            transition: opacity 0.3s ease;
        }
        .overlay.active {
            display: block;
            opacity: 1;
        }
        /* Hamburger Menu */
        .hamburger {
            display: none;
            font-size: 1.5rem;
            color: #2d3748;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .hamburger:hover {
            transform: scale(1.1);
        }
        /* Responsive Adjustments */
        @media (max-width: 767px) {
            .navbar-nav, .btn-signup.navbar-btn {
                display: none;
            }
            .hamburger {
                display: block;
            }
            .navbar-brand img {
                height: 50px;
            }
            .navbar {
                padding: 0.5rem 1rem;
            }
        }
        @media (min-width: 768px) and (max-width: 991px) {
            .sidebar, .overlay, .hamburger {
                display: none;
            }
            .navbar-nav .nav-link {
                margin-left: 0.75rem;
                font-size: clamp(0.9rem, 1.8vw, 1rem);
            }
            .btn-signup {
                padding: 6px 16px;
                font-size: 0.95rem;
                margin-left: 0.75rem;
            }
        }
        @media (min-width: 992px) {
            .sidebar, .overlay, .hamburger {
                display: none;
            }
            .navbar-nav .nav-link {
                margin-left: 1.5rem;
                font-size: clamp(0.95rem, 2vw, 1.125rem);
            }
            .btn-signup {
                padding: 8px 20px;
                font-size: 1rem;
            }
        }
        /* Hero Section Styles (unchanged) */
        .hero-section {
            margin-top: 140px !important;
            max-width: 1280px;
            margin: 0 auto;
            padding: 80px 0 40px;
        }
        @media (min-width: 768px) {
            .hero-section {
                padding: 60px 0 20px;
                text-align: center;
            }
        }
        @media (min-width: 992px) {
            .hero-section {
                text-align: left;
            }
        }
        .badge-green {
            background: #571F5A;
            color: #fff;
            font-weight: bold;
            border: 1px solid #571F5A;
            padding: 4px 16px;
            border-radius: 9999px;
            display: inline-block;
            text-align: center;
            width: 224px;
        }
        .hero-title {
            font-size: 2rem;
            font-weight: bold;
            color: #000;
            line-height: 1.2;
            margin-top: 20px;
        }
        @media (min-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
        }
        @media (min-width: 992px) {
            .hero-title {
                font-size: 3.75rem;
            }
        }
        .words {
            position: relative;
            height: 1.5em;
            display: inline-block;
            min-width: 400px;
            overflow: hidden;
            margin-top: 16px;
        }
        .words span {
            position: absolute;
            width: 100%;
            text-align: left;
            visibility: hidden;
            opacity: 0;
            transform: translateY(100%);
            animation: wordCycle 16s infinite;
            font-weight: bold;
            transition: opacity 0.3s ease, transform 0.3s ease;
            color: #571F5A;
        }
        .words span:nth-child(1) { animation-delay: 0s; }
        .words span:nth-child(2) { animation-delay: 2s; }
        .words span:nth-child(3) { animation-delay: 4s; }
        .words span:nth-child(4) { animation-delay: 6s; }
        .words span:nth-child(5) { animation-delay: 8s; }
        .words span:nth-child(6) { animation-delay: 10s; }
        .words span:nth-child(7) { animation-delay: 12s; }
        .words span:nth-child(8) { animation-delay: 14s; }
        @keyframes wordCycle {
            0% { visibility: hidden; opacity: 0; transform: translateY(100%); }
            2% { visibility: visible; opacity: 1; transform: translateY(0); }
            10% { visibility: visible; opacity: 1; transform: translateY(0); }
            12% { visibility: hidden; opacity: 0; transform: translateY(-100%); }
            100% { visibility: hidden; opacity: 0; transform: translateY(-100%); }
        }
        .hero-text {
            margin-top: 1.5rem;
            font-size: 1rem;
            color: #000;
            line-height: 1.8rem;
            max-width: 90%;
        }
        @media (min-width: 768px) {
            .hero-text {
                margin-top: 1.25rem;
                font-size: 1.125rem;
                line-height: 2rem;
                max-width: 48rem;
            }
        }
        .btn-store-blue {
            background: #571F5A;
            color: #fff;
            font-weight: bold;
            padding: 12px 24px;
            border-radius: 0.375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 48px;
            font-size: 1.125rem;
            border: 1px solid transparent;
        }
        .btn-store-blue:hover {
            background: #6D2F6A;
            color: #fff;
            box-shadow: 0 5px 15px rgba(87, 31, 90, 0.4);
        }
        .hero-image {
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 0.375rem;
        }
        @media (max-width: 991px) {
            .hero-section .d-none.d-lg-flex {
                display: block !important;
                text-align: center;
                margin-top: 1.5rem;
            }
            .hero-image {
                max-width: 80%;
                margin: 0 auto;
            }
        }
        @media (max-width: 767px) {
            .words {
                min-width: 100%;
                text-align: center;
            }
            .hero-title {
                align-items: center;
            }
            .words span {
                font-size: 1.5rem;
            }
        }
        /* Animation for Sidebar and Navbar Links */
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px) scale(0.9) rotate(2deg);
            animation: fadeInUpEnhanced 0.8s ease-out forwards;
        }
        .sidebar-nav .nav-link:nth-child(1), .navbar-nav .nav-item:nth-child(1) { animation-delay: 0.3s; }
        .sidebar-nav .nav-link:nth-child(2), .navbar-nav .nav-item:nth-child(2) { animation-delay: 0.4s; }
        .sidebar-nav .nav-link:nth-child(3), .navbar-nav .nav-item:nth-child(3) { animation-delay: 0.5s; }
        .sidebar-nav .nav-link:nth-child(4), .navbar-nav .nav-item:nth-child(4) { animation-delay: 0.6s; }
        .sidebar-nav .btn-signup, .navbar-nav .btn-signup { animation-delay: 0.7s; }
        .sidebar-nav .back-to-top { animation-delay: 0.8s; }
        @keyframes fadeInUpEnhanced {
            0% { opacity: 0; transform: translateY(30px) scale(0.9) rotate(2deg); }
            50% { transform: translateY(10px) scale(1.05) rotate(-1deg); }
            100% { opacity: 1; transform: translateY(0) scale(1) rotate(0deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg fixed-top" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="images/logo.jpg" alt="Grow Biller Logo" loading="lazy">
            </a>
            <button class="hamburger" aria-label="Toggle Sidebar" aria-controls="sidebar" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <?php foreach ($nav_items as $name => $data): ?>
                        <li class="nav-item fade-in-up">
                            <a class="nav-link" href="<?php echo htmlspecialchars($data['link']); ?>" title="<?php echo htmlspecialchars($data['title']); ?>">
                                <i class="fas <?php echo htmlspecialchars($data['icon']); ?> mr-1" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($name); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item fade-in-up">
                        <a class="btn btn-signup navbar-btn" href="login.php" target="_blank" rel="noopener noreferrer" title="Login to Grow Biller">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar for Mobile -->
    <div class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Grow Biller Logo" loading="lazy">
            <button class="close-btn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sidebar-nav">
            <?php foreach ($nav_items as $name => $data): ?>
                <a class="nav-link fade-in-up" href="<?php echo htmlspecialchars($data['link']); ?>" title="<?php echo htmlspecialchars($data['title']); ?>">
                    <i class="fas <?php echo htmlspecialchars($data['icon']); ?>" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($name); ?>
                </a>
            <?php endforeach; ?>
            <a class="btn btn-signup fade-in-up" href="login.php" target="_blank" rel="noopener noreferrer" title="Login to Grow Biller">Login</a>
          
            </a>
        </div>
    </div>
    
    <!-- Overlay for Sidebar -->
    <div class="overlay" id="overlay"></div>
    
    <!-- jQuery and Custom Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Sticky Navbar
            $(window).scroll(function() {
                if ($(this).scrollTop() > 50) {
                    $('.navbar').addClass('sticky');
                } else {
                    $('.navbar').removeClass('sticky');
                }
            });
            // Toggle Sidebar
            $('.hamburger').click(function() {
                $('#sidebar').addClass('active').attr('aria-hidden', 'false');
                $('#overlay').addClass('active');
                $(this).attr('aria-expanded', 'true');
            });
            $('.close-btn, #overlay').click(function() {
                $('#sidebar').removeClass('active').attr('aria-hidden', 'true');
                $('#overlay').removeClass('active');
                $('.hamburger').attr('aria-expanded', 'false');
            });
            // Smooth Scrolling for Hash Links
            $('a[href*="#"]').not('[href="#"]').click(function(event) {
                if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
                    event.preventDefault();
                    var target = $(this.hash);
                    target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                    if (target.length) {
                        $('html, body').animate({
                            scrollTop: target.offset().top - 100
                        }, 800);
                        $('#sidebar').removeClass('active').attr('aria-hidden', 'true');
                        $('#overlay').removeClass('active');
                        $('.hamburger').attr('aria-expanded', 'false');
                        return false;
                    }
                }
            });
            // Keyboard Accessibility
            $(document).keydown(function(e) {
                if (e.key === "Escape" && $('#sidebar').hasClass('active')) {
                    $('#sidebar').removeClass('active').attr('aria-hidden', 'true');
                    $('#overlay').removeClass('active');
                    $('.hamburger').attr('aria-expanded', 'false');
                }
            });
        });
    </script>
</body>
</html>