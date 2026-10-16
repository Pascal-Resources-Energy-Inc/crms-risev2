<!DOCTYPE html>
<html lang="en" dir="ltr" data-bs-theme="light" data-color-theme="Green_Theme" data-layout="vertical">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Favicon icon-->
    <link rel="shortcut icon" type="image/png" href="{{asset('images/icon.png')}}" />

    <!-- Core Css -->
    <link rel="stylesheet" href="{{asset('design/assets/css/styles.css')}}" />
    <link rel="stylesheet" href="{{asset('design/assets/libs/jvectormap/jquery-jvectormap.css')}}">
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 80px;
            --bg-light: #f8fafc;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --transition-duration: 0.3s;
            --content-padding: 32px;
        }

        /* Layout Reset - Higher specificity to prevent conflicts */
        .main-layout * {
            box-sizing: border-box;
        }

        .main-layout {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #D8E9F0;
            min-height: 100vh;
            display: flex; /* Add this */
            flex-direction: column; /* Add this */
        }

        body.main-layout {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background-color: #D8E9F0 !important;
            overflow-x: hidden !important;
        }

        /* Override existing layout styles */
        .container-scroller {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        .layout-container {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
        }


        /* Enhanced Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #FFFCFC;
            border-right: 1px solid var(--border-color);
            transition: width var(--transition-duration) cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Collapsed state */
        .sidebar.sidebar-offcanvas.collapsed {
            width: var(--sidebar-collapsed-width) !important;
        }

        /* Sidebar content adjustments for collapsed state */
        .sidebar.collapsed .nav .nav-item .nav-link .menu-title,
        .sidebar.collapsed .nav li h5 {
            opacity: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            transition: all var(--transition-duration) ease !important;
        }

        .sidebar.collapsed .nav .nav-item .nav-link {
            justify-content: center !important;
            padding: 12px !important;
        }

        .sidebar.collapsed .navbar-brand .brand-logo {
            display: none !important;
        }

        .sidebar.collapsed .navbar-brand .brand-logo-mini {
            display: block !important;
        }

        /* Main content responsive to sidebar */

        /* Navbar adjustments */
        .navbar.fixed-top {
            position: fixed !important;
            top: 0 !important;
            left: var(--sidebar-width) !important;
            width: calc(100% - var(--sidebar-width)) !important;
            transition: left var(--transition-duration) ease, width var(--transition-duration) ease !important;
            z-index: 999 !important;
        }

        .sidebar.collapsed ~ * .navbar.fixed-top {
            left: var(--sidebar-collapsed-width) !important;
            width: calc(100% - var(--sidebar-collapsed-width)) !important;
        }

        /* Content area */
        .main-panel {
            width: 100% !important;
            padding-top: var(--topbar-height) !important;
            min-height: calc(100vh - var(--topbar-height)) !important;
        }

        /* Enhanced toggle button */
        .navbar-toggler[data-toggle="minimize"] {
            background: transparent !important;
            border: none !important;
            color: #6c757d !important;
            font-size: 18px !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            transition: all 0.2s ease !important;
        }

        .navbar-toggler[data-toggle="minimize"]:hover {
            background: #f8f9fa !important;
            color: #495057 !important;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar.sidebar-offcanvas {
                transform: translateX(-100%) !important;
                width: var(--sidebar-width) !important;
            }

            .sidebar.sidebar-offcanvas.show {
                transform: translateX(0) !important;
            }

            .navbar.fixed-top {
                left: 0 !important;
                width: 100% !important;
            }

            /* Mobile overlay */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        /* Smooth transitions for nav items */
        .nav .nav-item .nav-link {
            transition: all 0.2s ease !important;
        }

        .nav .nav-item .nav-link:hover {
            transform: translateX(2px) !important;
            background: #f1f5f9 !important;
        }

        /* Logo transitions */
        .navbar-brand .brand-logo,
        .navbar-brand .brand-logo-mini {
            transition: all var(--transition-duration) ease !important;
        }

        .navbar-brand .brand-logo-mini {
            display: none !important;
        }

        /* Submenu handling for collapsed state */
        .sidebar.collapsed .nav .nav-item .collapse {
            display: none !important;
        }

        /* Badge positioning for collapsed state */
        .sidebar.collapsed .nav .nav-item .nav-link .badge {
            position: absolute !important;
            top: 8px !important;
            right: 8px !important;
            transform: scale(0.8) !important;
        }

        /* Section titles in collapsed state */
        .sidebar.collapsed .nav li h5 {
            font-size: 7px !important;
            text-align: center !important;
            padding: 0 0.5rem !important;
            margin: 10px 0 5px 0 !important;
            line-height: 1.2 !important;
        }

        .loader {
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: url("{{ asset('/images/loader.gif') }}") 50% 50% no-repeat white;
            opacity: .8;
            background-size: 120px 120px;
        }

        /* Protected Sidebar Styles - Higher specificity */
        .main-layout .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            width: var(--sidebar-width) !important;
            background: #FFFCFC !important;
            border-right: 1px solid var(--border-color) !important;
            transition: all 0.3s ease !important;
            z-index: 1000 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        .main-layout .sidebar.collapsed {
            width: var(--sidebar-collapsed-width) !important;
        }

        .main-layout .sidebar-header {
            padding: 20px !important;
            border-bottom: 1px solid var(--border-color) !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            min-height: var(--topbar-height) !important;
        }

        .notification-dropdown
        {
            cursor: pointer;
            background: none !important;
        }

        .notification-dropdown:hover
        {
            background: none !important;
        }

        .notification-dropdown .notif-badge
        {
            width: 12px; 
            height: 12px; 
            font-size: 7px; 
            border-radius: 50%;
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            background-color: red;
        }

        .main-layout .logo {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            text-decoration: none !important;
            color: var(--primary-color) !important;
            font-weight: 700 !important;
            font-size: 20px !important;
        }

       .main-layout .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0 16px; /* Add padding if you want spacing inside */
            box-sizing: border-box;
        }

        .main-layout .logo img {
            height: 40px !important;
            width: auto !important;
            flex-shrink: 0 !important;
            display: block;
            margin: 0 auto; /* Optional if using justify-content */
        }

        .main-layout .logo-text {
            transition: all 0.3s ease !important;
        }

        .main-layout .sidebar.collapsed .logo-text {
            opacity: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }

        .sidebar-nav {
            padding: 24px 0;
        }

        .nav-section {
            margin-bottom: 32px;
        }

        .nav-section-title {
            padding: 0 20px 8px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .nav-section-title {
            opacity: 0;
        }

        .nav-item {
            margin: 4px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #334155;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
            position: relative;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-color);
            transform: translateX(2px);
        }

        .nav-link.active {
            background: #dbeafe;
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-text {
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px;
        }

        /* User Profile Section */
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: white;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
        }

        .user-info {
            flex: 1;
            min-width: 0;
            transition: all 0.3s ease;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            margin: 0;
        }
        

        .user-role {
            color: var(--text-muted);
            font-size: 12px;
            margin: 0;
        }

        .user-profile {
        position: relative;
        }
        
        .user-profile::after {
        position: absolute;
        left: 35px !important;
        top: 15px;
        content: "";
        width: 15px;
        height: 15px;
        background-color: var(--bs-success);
        border-radius: 50%;
        border: 2px solid var(--bs-white);
        }

        .sidebar.collapsed .user-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .sidebar-footer {
            padding: 16px 8px;
        }

        .sidebar.collapsed .user-profile {
            flex-direction: column;
            gap: 8px;
            padding: 12px 8px;
            align-items: center;
            justify-content: center;
        }

        .logout-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
            padding-top: 80px; /* Add top padding to account for fixed topbar */
        }

                .sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
                .main-layout .topbar {
            background: #5BC2E7 !important;
            border-bottom: 1px solid var(--border-color) !important;
            padding: 0 32px !important;
            height: 80px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            position: fixed !important;
            top: 0 !important;
            left: var(--sidebar-width) !important;
            width: calc(100% - var(--sidebar-width)) !important;
            z-index: 999 !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            border-radius: 0 !important;
            transition: all 0.3s ease !important;
        }

        .sidebar.collapsed ~ .main-content .topbar {
            left: var(--sidebar-collapsed-width) !important;
            width: calc(100% - var(--sidebar-collapsed-width)) !important;
        }

        .main-layout .topbar-left {
            display: flex !important;
            align-items: center !important;
            gap: 24px !important;
        }

        .main-layout .sidebar-toggle {
            background: none !important;
            border: none !important;
            padding: 8px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            color: #FFFF !important;
            transition: all 0.2s ease !important;
        }

        .main-layout .sidebar-toggle:hover {
            color: #FFFF !important;
            color: var(--primary-color) !important;
        }

        .main-layout .welcome-message h6 {
            margin: 0 !important;
            color: #FFFFFF !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        .main-layout .welcome-message small {
            color: #FFFFFF !important;
            font-size: 13px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        .main-layout .topbar-right {
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
        }

        .main-layout .search-container {
            position: relative !important;
        }

        .main-layout .search-input {
            width: 300px !important;
            height: 40px !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 20px !important;
            padding: 0 16px 0 44px !important;
            background: #D8E9F0 !important;
            transition: all 0.2s ease !important;
            font-size: 14px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            outline: none !important;
        }

        .main-layout .search-input::placeholder{
            color: #5BC2E7 !important;
        }

        .main-layout .search-input:focus {
            border-color: var(--primary-color) !important;
            background: white !important;
        }

        .main-layout .search-icon {
            position: absolute !important;
            right: 16px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #5BC2E7 !important;
        }

        .main-layout .profile-dropdown {
            position: relative !important;
            background: none !important;
            border: none !important;
            padding: 8px !important;
            border-radius: 50% !important;
            cursor: pointer !important;
            color: #FFFF !important;
            transition: all 0.2s ease !important;
        }

        .main-layout .profile-dropdown:hover {
            color: var(--primary-color) !important;
        }

        .main-layout .notification-badge {
            position: absolute !important;
            top: 0 !important;
            right: 0 !important;
            width: 18px !important;
            height: 18px !important;
            background: #ef4444 !important;
            color: white !important;
            border-radius: 50% !important;
            font-size: 10px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 600 !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        .main-layout .profile-img {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            border: 2px solid var(--border-color) !important;
        }

        .content-area.rewards-page {
            padding: 20px;
            position: static !important;
            left: auto !important;
            right: auto !important;
            transition: none !important;
        }

        /* Content Area */
        .content-area {
            padding: 32px;
            position: absolute;
            left: var(--sidebar-width);
            right: 0;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content .content-area {
            left: var(--sidebar-collapsed-width);
        }

        @media (max-width: 768px) {
            .content-area {
                left: 0;
            }
        }

        .main-layout .dropdown-menu {
            border: none !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            border-radius: 12px !important;
            padding: 16px 0 !important;
            min-width: 280px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        .main-layout .dropdown-item {
            padding: 12px 20px !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            transition: all 0.2s ease !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            font-size: 14px !important;
            color: #334155 !important;
        }

        .main-layout .dropdown-item:hover {
            background: #f1f5f9 !important;
            color: var(--primary-color) !important;
        }

        .main-layout .profile-info {
            padding: 16px 20px !important;
            border-bottom: 1px solid var(--border-color) !important;
            margin-bottom: 8px !important;
        }

        .main-layout .profile-info h6 {
            margin: 0 !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            font-size: 14px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        .main-layout .profile-info small {
            color: var(--text-muted) !important;
            font-size: 12px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .welcome-message {
                display: none;
            }

            .topbar {
                padding: 0 16px;
            }

            .content-area {
                padding: 16px;
            }
            .main-layout .topbar {
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                padding: 0 16px !important;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 80px;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .sidebar-overlay.active {
                display: block;
            }
        }

        /* Custom responsive font sizes */
        @media (max-width: 480px) {
            .font {
                font-size: 8px;
            }
        }

        @media (max-width: 768px) {
            .font {
                font-size: 12px;
            }
        }
        transaction-item .row {
            min-height: 40px !important; /* Adjust this value */
            padding: 11px 15px !important; /* Reduce padding */
        }

        .transaction-item .avatar-circle {
            width: 35px !important;
            height: 35px !important;
        }

        .transaction-item h6 {
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .footer {
            border-top: 1px solid #676565ff;
            padding: 2rem 0 1.5rem 0;
            margin-top: 170px !important; /* Change this from 170px to auto */
            margin-left: 0;
            width: 100%;
            background: none;
            position: relative; /* Add this to ensure proper positioning */
            clear: both; /* Add this to clear any floating elements */
        }

      .page-wrapper .footer {
          position: relative;
          width: 100%;
      }

      .footer .container-fluid {
          padding-left: 1rem;
          padding-right: 1rem;
      }

      .footer .footer-content {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 1.5rem;
          position: relative;
      }

      .footer .company-info {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          position: absolute;
          left: 0;
          top: 0;
      }


      .footer-right-image img {
        position: absolute; 
        width: 150px; /* Adjust size as needed */
        height: auto;
        margin-left: 435px;
        margin-top: -43px;
      }

      @media (max-width: 768px) {
         .footer-right-image img {
           margin-left: -75px;
           margin-top: -2px;
        }

        .footer-right-image {
          margin-top: 1rem;
        }
      }


      .footer .nav-links-container {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 0.4rem;
          margin-top: 0;
      }
      .footer .company-logo img {
          width: 350px !important;
          height: 70px !important;
      }

      .footer .company-logo {
          width: 24px;
          height: 24px;
          border-radius: 4px;
          margin-left: 80px; 
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-weight: bold;
          font-size: 12px;
      }

      @media (max-width: 768px) {
         .footer .company-logo img {
           margin-left: -57px;
           margin-top: -5px;
        }
      }

      .footer .company-text {
          color: #6c757d;
          font-size: 14px;
          margin: 0;
      }

      .footer .nav-links {
          display: flex;
          gap: 2rem;
          margin: 0;
          padding: 0;
          list-style: none;
          flex-wrap: wrap;
          justify-content: center;
      }

      .footer .nav-links a {
          color: #6c757d;
          text-decoration: none;
          font-size: 14px;
          transition: color 0.2s ease;
          font-weight: 500;
      }

      .footer .nav-links a:hover {
          color: #2e7fe1ff;
      }

      .footer .divider {
          width: 100%;
          max-width: 300px;
          height: 1px;
          background-color: #ffffff4b;
          margin: 0.5rem 0;
      }

      .footer .social-links {
          display: flex;
          gap: 1.5rem;
          margin: 0;
          padding: 0;
          list-style: none;
      }

      .footer .social-links a {
          color: #6c757d;
          font-size: 20px;
          transition: color 0.2s ease;
          display: flex;
          align-items: center;
          justify-content: center;
      }

      .footer .social-links a:hover {
          color: #2e7fe1ff;
      }

      @media (max-width: 768px) {
          .footer .company-info {
              position: static;
              align-self: center;
              margin-bottom: 1rem;
          }
          
          .footer .footer-content {
              align-items: center;
          }
          
          .footer .nav-links-container {
              margin-top: 0;
          }
          
          .footer .nav-links {
              gap: 1.5rem;
          }
          
          .footer .nav-links a {
              font-size: 13px;
          }
          
          .footer .social-links {
              gap: 1rem;
          }
          
          .footer .social-links a {
              font-size: 18px;
          }
      }

      @media (max-width: 480px) {
          .footer .nav-links {
              gap: 1rem;
          }
          
          .footer .nav-links a {
              font-size: 12px;
          }
      }
    </style>
    
    @yield('css')
</head>
<body class="main-layout">
    <div id="loader" style="display:none;" class="loader"></div>
    
        @if (!request()->is('rewards') && !Request::is('redeem') && !Request::is('voucher') && !Request::is('settlement/*') && !Request::is('points-history'))
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header d-flex align-items-center justify-content-between text-center">
            <a href="{{url('/')}}" class="logo text-center text-nowrap">
                <!-- Full logo (for expanded sidebar) -->
                <img src="{{asset('images/logo_mo.png')}}"
                    class="logo-full"
                    alt="Logo-Full" />

                <!-- Mini logo (for collapsed sidebar) -->
                <img src="{{asset('images/logo_nya.png')}}"
                    style="height:43px;width:43px;display:none;"
                    class="logo-mini"
                    alt="Logo-Mini" />
            </a>
        </div>
        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">HOME</div>
                <div class="nav-item">
                    <a href="{{url('/')}}" class="nav-link @if(Route::currentRouteName() == 'home')active @endif">
                        <div class="nav-icon">
                            <i class="bi bi-grid-1x2"></i>
                        </div>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                @if((auth()->user()->role == "Admin") || auth()->user()->isDealerLike())
                <div class="nav-item">
                    <a href="{{url('/transactions')}}" class="nav-link @if(Route::currentRouteName() == 'transactions')active @endif">
                        <div class="nav-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <span class="nav-text">Transactions</span>
                    </a>
                </div>
                @endif
                
                @if(auth()->user()->role == "Admin")
                <div class="nav-item">
                    <a href="{{url('/dealers')}}" class="nav-link @if(Route::currentRouteName() == 'dealers')active @endif">
                        <div class="nav-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <span class="nav-text">Dealers</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="{{url('/customers')}}" class="nav-link @if(Route::currentRouteName() == 'customers')active @endif">
                        <div class="nav-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <span class="nav-text">Customers</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="{{url('/users')}}" class="nav-link @if(Route::currentRouteName() == 'users')active @endif">
                        <div class="nav-icon">
                            <i class="bi bi-gear"></i>
                        </div>
                        <span class="nav-text">Users</span>
                    </a>
                </div>
                @endif
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="user-profile">
                <img src="{{auth()->user()->avatar}}" onerror="this.src='{{url('design/assets/images/profile/user-1.png')}}';" alt="User" class="user-avatar">
                <div class="user-info">
                    <p class="user-name">{{current(explode(' ',auth()->user()->name))}}</p>
                    <p class="user-role">{{auth()->user()->role}}</p>
                </div>
                <!-- <button class="logout-btn" onclick="logout(); show();">
                    <i class="bi bi-box-arrow-right"></i>
                </button> -->
            </div>
        </div>
    </nav>
    

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list" style="font-size: 20px;"></i>
                </button>
                <div class="welcome-message">
                    <h6>Hello {{current(explode(' ',auth()->user()->name))}}!</h6>
                    <small>Welcome back to dashboard</small>
                </div>
            </div>
            @if((auth()->user()->role == "Admin"))
            <div class="topbar-right">
                <li class="nav-item d-none d-md-block me-2 mt-3 search-container">
                    <form action="{{ url('/search') }}" method="GET" class="position-relative">
                        <input 
                        type="search" 
                        name="q"
                        id="searchInput"
                        class="search-input" 
                        style="width: 300px; height: 40px;" 
                        placeholder="Search by name..." 
                        value="{{ request('q') }}"
                        autocomplete="off"
                        />
                        <iconify-icon icon="solar:magnifer-linear" 
                                    class="search-icon position-absolute top-50 translate-middle-y ms-3 fs-6 text-muted"></iconify-icon>
                        
                        <div id="searchSuggestions" 
                            class="position-absolute bg-white border rounded-3 shadow-lg" 
                            style="display: none; z-index: 1000; max-height: 350px; overflow-y: auto; top: 100%; left: 0; right: 0; margin-top: 5px;">
                        </div>
                        
                        <button type="submit" style="display: none;"></button>
                    </form>
                </li>
                

               <div class="dropdown me-2 notification-dropdown">
                    @php
                    $notificationData = app('App\Http\Controllers\NotificationController')->getNotificationData();
                    extract($notificationData); // This gives you all the variables like $notifications, $totalUnreadCount, etc.
                    @endphp
                    
                    <a class="" id="notificationDropdown" 
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle danger">
                        <iconify-icon icon="solar:bell-linear" class="fs-7" style="color: #DFDFEC"></iconify-icon>
                        @if($totalUnreadCount > 0)
                        <span class="position-absolute bottom-45 start-100 translate-middle badge rounded-pill notif-badge" 
                            id="notification-badge">
                            {{$totalUnreadCount ?? ''}}
                        </span>
                        @endif
                    </div>
                    </a>
                    
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up p-0" 
                        aria-labelledby="notificationDropdown" style="width: 380px;">
                        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            Notifications 
                        </h6>
                        @if($totalUnreadCount > 0)
                            <form action="{{ route('notifications.markAllAsRead') }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-link p-0 text-primary">
                                    Mark all as read
                                </button>
                            </form>
                            @endif
                        </div>
                        
                        <div style="max-height: 350px; overflow-y: auto;">
                                @if($notifications->count() > 0)
                                    <div class="px-3 py-2">
                                        @foreach($notifications as $notification)
                                        @if($notification['type'] === 'client')
                                            @php 
                                                $client = $notification['data'];
                                                $isUnread = !in_array('client_' . $client->id, $readNotifications);
                                                $isSaved = in_array('customer_' . $client->id, $savedNotifications);
                                                $redirectUrl = url('/customers'); // Dynamic URL for clients
                                            @endphp
                                            <div class="notification-item border-bottom py-2">
                                                <div class="d-flex align-items-center" 
                                                    style="cursor: pointer;" 
                                                    onclick="markAsReadAndRedirect('client_{{ $client->id }}', '{{ $redirectUrl }}')"
                                                    onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                                    onmouseout="this.style.backgroundColor=''">
                                                    <div class="flex-shrink-0">
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                            style="width: 35px; height: 35px;">
                                                            <iconify-icon icon="solar:user-plus-broken" class="text-white fs-5"></iconify-icon>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="mb-0 fs-3">
                                                            <strong>{{ $client->name }}</strong> registered
                                                            @if($isUnread)
                                                                <span class="badge bg-primary ms-2" style="font-size: 0.6rem;">NEW</span>
                                                            @endif
                                                        </p>
                                                        <small class="text-muted">{{ $client->created_at->diffForHumans() }}</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end mt-2">
                                                    @if(!$isSaved)
                                                        <form action="{{ route('notification.save') }}" method="POST" style="display: inline;">
                                                            @csrf
                                                            <input type="hidden" name="type" value="client">
                                                            <input type="hidden" name="record_id" value="{{ $client->id }}">
                                                            <button type="submit" class="btn btn-sm btn-success" style="font-size: 0.75rem;">
                                                                View
                                                            </button>
                                                        </form>
                                                    @else
                                                        <a href="{{ $redirectUrl }}" class="btn btn-sm btn-success" style="font-size: 0.75rem;">
                                                            View
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                                                    @elseif($notification['type'] === 'transaction')
                                            @php 
                                                $transaction = $notification['data'];
                                                $isUnread = !in_array('transaction_' . $transaction->id, $readNotifications);
                                                $isSaved = in_array('transaction_' . $transaction->id, $savedNotifications);
                                                $redirectUrl = url('/transactions'); // Dynamic URL for transactions
                                            @endphp
                                            <div class="notification-item border-bottom py-2">
                                                <div class="d-flex align-items-center" 
                                                    style="cursor: pointer;" 
                                                    onclick="markAsReadAndRedirect('transaction_{{ $transaction->id }}', '{{ $redirectUrl }}')"
                                                    onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                                    onmouseout="this.style.backgroundColor=''">
                                                    <div class="flex-shrink-0">
                                                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" 
                                                            style="width: 35px; height: 35px;">
                                                            <iconify-icon icon="solar:dollar-minimalistic-broken" class="text-white fs-5"></iconify-icon>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="mb-0 fs-3">
                                                            @if($transaction->customer)
                                                                <strong>{{ $transaction->customer->name }}</strong> 
                                                            @else
                                                                Customer
                                                            @endif
                                                            made transaction
                                                            @if($isUnread)
                                                                <span class="badge bg-primary ms-2" style="font-size: 0.6rem;">NEW</span>
                                                            @endif
                                                            @if($transaction->product)
                                                                <br><small class="text-muted">{{ $transaction->product->name }} - ₱{{ number_format($transaction->price, 2) }}</small>
                                                            @endif
                                                        </p>
                                                        <small class="text-muted">{{ $transaction->created_at->diffForHumans() }}</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end mt-2">
                                                    @if(!$isSaved)
                                                        <form action="{{ route('notification.save') }}" method="POST" style="display: inline;">
                                                            @csrf
                                                            <input type="hidden" name="type" value="transaction">
                                                            <input type="hidden" name="record_id" value="{{ $transaction->id }}">
                                                            <button type="submit" class="btn btn-sm btn-success" style="font-size: 0.75rem;">
                                                                View
                                                            </button>
                                                        </form>
                                                    @else
                                                        <a href="{{ $redirectUrl }}" class="btn btn-sm btn-success" style="font-size: 0.75rem;">
                                                            View
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                        @endforeach
                                    </div>
                                @else
                                <div class="px-3 py-4 text-center">
                                    <iconify-icon icon="solar:bell-off-broken" class="fs-1 text-muted"></iconify-icon>
                                    <p class="text-muted mb-0">No recent notifications</p>
                                </div>
                            @endif

                        </div>
                        
                        <div class="px-3 py-2 border-top text-center">
                        <a href="{{ url('/transactions') }}" class="btn btn-sm btn-outline-primary">View All Transactions</a>
                        </div>
                    </div>
                </div>
                @endif

                <div class="dropdown">
                    <button class="profile-dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{auth()->user()->avatar}}" onerror="this.src='{{url('design/assets/images/profile/user-1.png')}}';" alt="Profile" class="profile-img">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="profile-info">
                            <div class="d-flex align-items-center">
                                <img src="{{auth()->user()->avatar}}" onerror="this.src='{{url('design/assets/images/profile/user-1.png')}}';" alt="user" width="40" class="rounded-circle me-3" />
                                <div>
                                    <h6 class="mb-0">{{auth()->user()->name}}</h6>
                                    <small class="text-muted">{{auth()->user()->email}}</small>
                                </div>
                            </div>
                        </li>
                        @if(auth()->user()->role != "Admin")
                        <li><a class="dropdown-item" href="{{url('user-profile')}}">
                            <i class="bi bi-person"></i>
                            <span>My Profile</span>
                        </a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout(); show();">
                            <i class="bi bi-box-arrow-right text-danger"></i>
                            <span>Log Out</span>
                        </a></li>
                    </ul>
                </div>
            </div>
        </header>
        @endif

        <!-- Redeem Area -->
        @yield('contents')

        <!-- Content Area -->
         <div class="content-area @if(Request::is('rewards')) rewards-page @endif">
                @yield('content')

                @if ( !Request::is('rewards') && !Request::is('redeem') && !Request::is('voucher') && !Request::is('settlement/*') && !Request::is('points-history'))
                <footer class="footer">
                <div class="container-fluid">
                    <div class="footer-content">
                    
                    <!-- Left-aligned Logo -->
                    <div class="company-info">
                        <div class="company-logo">
                        <img src="{{ asset('images/footer.png') }}" alt="Company Logo" />
                        </div>
                    </div>

                    
                    <!-- Center Nav + Social -->
                    <div class="nav-links-container">
                        <nav>
                        
                        </nav>

                        <div class="divider"></div>

                        <div class="social-links">
                        <a href="https://www.tiktok.com/@gazliteofficial" aria-label="Tiktok">
                            <iconify-icon icon="simple-icons:tiktok"></iconify-icon>
                        </a>
                        <a href="https://www.instagram.com/gazliteph/#" aria-label="Instagram">
                            <iconify-icon icon="mdi:instagram"></iconify-icon>
                        </a>
                        <a href="https://www.facebook.com/GazLitePH/" aria-label="Facebook">
                            <iconify-icon icon="mdi:facebook"></iconify-icon>
                        </a>
                        </div>
                        <div class="footer-right-image">
                        <img src="{{ asset('images/footer1.png') }}" alt="Right Footer Image" />
                    </div>
                    </div>
                    </div>
                </div>
                </footer>
                @endif
            </div>
        </div>
    </div>

    <!-- Hidden logout form -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        {{ csrf_field() }}
    </form> 

    <!-- Bootstrap JS -->
    
    <!-- Original scripts -->
    <script src="{{asset('design/assets/js/vendor.min.js')}}"></script>
    <script src="{{asset('design/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{asset('design/assets/libs/simplebar/dist/simplebar.min.js')}}"></script>
    <script src="{{asset('design/assets/js/theme/app.init.js')}}"></script>
    <script src="{{asset('design/assets/js/theme/theme.js')}}"></script>
    <script src="{{asset('design/assets/js/theme/app.min.js')}}"></script>
    <script src="{{asset('design/assets/js/theme/sidebarmenu.js')}}"></script>
    <script src="{{asset('design/assets/js/theme/feather.min.js')}}"></script>

    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    @yield('javascript')

    <script>
            function logout() {
            event.preventDefault();
            document.getElementById('logout-form').submit();
        }
    
    </script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const suggestionsDiv = document.getElementById('searchSuggestions');
      let debounceTimer;

      if (searchInput) {
          searchInput.addEventListener('input', function() {
              const query = this.value.trim();
              
              clearTimeout(debounceTimer);
              
              if (query.length < 2) {
                  suggestionsDiv.style.display = 'none';
                  return;
              }

              debounceTimer = setTimeout(() => {
                  fetch(`{{ url('/search/suggestions') }}?q=${encodeURIComponent(query)}`)
                      .then(response => response.json())
                      .then(data => {
                          suggestionsDiv.innerHTML = '';
                          
                          if (data.length > 0) {
                              data.forEach(item => {
                                  const div = document.createElement('div');
                                  div.className = 'p-3 search-suggestion-item border-bottom';
                                  div.style.cursor = 'pointer';
                                  div.innerHTML = `
                                      <div class="d-flex align-items-center">
                                          <div class="me-3">
                                              <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                                  style="width: 35px; height: 35px; background-color: ${item.type === 'client' ? '#e3f2fd' : '#f3e5f5'};">
                                                  <iconify-icon icon="${item.type === 'client' ? 'solar:user-linear' : 'solar:shop-linear'}" 
                                                              class="fs-5" style="color: ${item.type === 'client' ? '#1976d2' : '#7b1fa2'};"></iconify-icon>
                                              </div>
                                          </div>
                                          <div class="flex-grow-1">
                                              <div class="fw-semibold text-dark">${item.name}</div>
                                              <small class="text-muted">${item.type === 'client' ? 'Customer' : 'Dealer'}</small>
                                          </div>
                                          <div>
                                              <iconify-icon icon="solar:arrow-right-linear" class="text-muted"></iconify-icon>
                                          </div>
                                      </div>
                                  `;
                                  
                                  div.addEventListener('click', function() {
                                      searchInput.value = item.name;
                                      suggestionsDiv.style.display = 'none';
                                      window.location.href = `{{ url('/profile') }}/${item.id}/${item.type}`;
                                  });

                                  div.addEventListener('mouseenter', function() {
                                      this.style.backgroundColor = '#f8f9fa';
                                  });

                                  div.addEventListener('mouseleave', function() {
                                      this.style.backgroundColor = 'transparent';
                                  });
                                  
                                  suggestionsDiv.appendChild(div);
                              });
                              
                              if (data.length > 0) {
                                  const viewAllDiv = document.createElement('div');
                                  viewAllDiv.className = 'p-3 text-center border-top';
                                  viewAllDiv.innerHTML = `
                                      <small class="text-primary fw-semibold" style="cursor: pointer;">
                                          Press Enter to search for "${query}"
                                      </small>
                                  `;
                                  suggestionsDiv.appendChild(viewAllDiv);
                              }
                              
                              suggestionsDiv.style.display = 'block';
                          } else {
                              suggestionsDiv.innerHTML = `
                                  <div class="p-3 text-center text-muted">
                                      <iconify-icon icon="solar:magnifer-linear" class="fs-3"></iconify-icon>
                                      <div class="mt-2">No users found for "${query}"</div>
                                      <small>Try a different search term</small>
                                  </div>
                              `;
                              suggestionsDiv.style.display = 'block';
                          }
                      })
                      .catch(error => {
                          console.error('Search error:', error);
                          suggestionsDiv.style.display = 'none';
                      });
              }, 300);
          });

          document.addEventListener('click', function(event) {
              if (!searchInput.closest('.position-relative').contains(event.target)) {
                  suggestionsDiv.style.display = 'none';
              }
          });

          searchInput.addEventListener('keydown', function(event) {
              if (event.key === 'Escape') {
                  suggestionsDiv.style.display = 'none';
              }
          });

          searchInput.addEventListener('focus', function() {
              if (this.value.length >= 2) {
                  this.dispatchEvent(new Event('input'));
              }
          });
      }
  });
  </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const logoFull = document.querySelector('.logo-full');
    const logoMini = document.querySelector('.logo-mini');

    // Logo switching function
    function updateLogoVisibility() {
        if (!logoFull || !logoMini || !sidebar) {
            console.log('Missing logo elements');
            return;
        }

        const isCollapsed = sidebar.classList.contains('collapsed');
        console.log('Logo update - Sidebar collapsed:', isCollapsed);
        
        if (isCollapsed) {
            logoFull.style.display = 'none';
            logoMini.style.display = 'inline-block';
        } else {
            logoFull.style.display = 'inline-block';
            logoMini.style.display = 'none';
        }
    }

    function initializeSidebar() {
        if (window.innerWidth > 768) {
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        } else {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        }
        updateLogoVisibility();
    }

    initializeSidebar();

    function toggleSidebar() {
        console.log('Toggle clicked, window width:', window.innerWidth);
        
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
            console.log('Mobile toggle - sidebar mobile-open:', sidebar.classList.contains('mobile-open'));
        } else {
            sidebar.classList.toggle('collapsed');
            console.log('Desktop toggle - sidebar collapsed:', sidebar.classList.contains('collapsed'));
            
            updateLogoVisibility();
            
            if (sidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
    }

    window.addEventListener('resize', function() {
        setTimeout(function() {
            initializeSidebar();
        }, 100);
    });

    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    }

    if (sidebar) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    setTimeout(updateLogoVisibility, 10);
                }
            });
        });

        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    console.log('Sidebar script initialized');
});
    </script>
    
</body>
</html>
