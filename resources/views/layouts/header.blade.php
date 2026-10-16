<!-- header dealer -->
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- @laravelPWA --}}
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="shortcut icon"  href="{{url('images/aaa.png')}}">
    <link rel="icon"  href="{{url('images/aaa.png')}}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400&display=swap" rel="stylesheet">

    <style>
        .loader {
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: url("{{ asset('login_css/images/loader.gif')}}") 50% 50% no-repeat white;
            opacity: .8;
            background-size:120px 120px;
        }
        
        .bg-overlay {
            background: linear-gradient(to right, #c3c3c3, #c3c3c3) !important;
        }
    </style>
    @yield('css')

    <style>
        body {
            margin: 0;
            background: #f8f9fa;
            font-family: 'Poppins', Arial, sans-serif;
            padding-top: 80px;
            padding-bottom: 120px;
        }

        /* Topbar Styles */
        .topbar {
            background: #ffffff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 0 32px !important;
            height: 80px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            z-index: 999 !important;
            font-family: 'Poppins', Arial, sans-serif !important;
            border-radius: 0 !important;
            transition: all 0.3s ease !important;
        }

        .topbar-left {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }

        .sidebar-toggle {
            background: none !important;
            border: none !important;
            padding: 8px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            color: #1A72DD !important;
            transition: all 0.2s ease !important;
        }

        .sidebar-toggle:hover {
            color: #FFFF !important;
            color: var(--primary-color) !important;
        }

        .welcome-message h6 {
            margin: 0 !important;
            color: #000000ff !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .welcome-message small {
            color: #000000ff !important;
            font-size: 12px !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .topbar-right {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
        }

        .chat-btn {
            width: 44px;
            height: 44px;
            background: #5BC2E7;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            position: relative;
        }

        .chat-btn:hover {
            background: #4AB3D8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 194, 231, 0.3);
        }

        .chat-btn i {
            color: white;
            font-size: 20px;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transform: scale(0);
            transition: transform 0.2s ease;
            z-index: 1;
        }

        .notification-badge.show {
            transform: scale(1);
        }

        .notification-badge.pulse {
            animation: notificationPulse 1.5s infinite;
        }

        @keyframes notificationPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
            }
        }

        .chat-btn {
            position: relative;
        }

        .points-dropdown {
            position: relative;
        }

        .points-btn {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            padding: 8px 20px 8px 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 160px;
            text-decoration: none;
        }

        .points-btn:hover {
            border-color: #5BC2E7;
            box-shadow: 0 2px 8px rgba(91, 194, 231, 0.2);
        }

        .points-icon-wrapper {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .profile-img {
            width: 80%;
            height: 80%;
            object-fit: cover;
            border-radius: 50%;
        }

        .points-text {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            white-space: nowrap;
        }

        .dropdown-menu {
            border: none !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            border-radius: 12px !important;
            padding: 16px 0 !important;
            min-width: 280px !important;
            z-index: 99999 !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .dropdown-item {
            padding: 12px 20px !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            transition: all 0.2s ease !important;
            font-family: 'Poppins', Arial, sans-serif !important;
            font-size: 14px !important;
            color: #334155 !important;
        }

        .dropdown-item:hover {
            background: #f1f5f9 !important;
            color: #5BC2E7 !important;
        }

        .profile-info {
            padding: 16px 20px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            margin-bottom: 8px !important;
        }

        .profile-info h6 {
            margin: 0 !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            font-size: 14px !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .profile-info small {
            color: #64748b !important;
            font-size: 12px !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        @media (max-width: 768px) {
            .topbar {
                padding: 0 16px !important;
            }

            .points-btn {
                min-width: auto;
                padding: 2px 9px 2px 2px;
            }

            .points-text {
                font-size: 12px;
            }

            .chat-btn {
                width: 40px;
                height: 40px;
            }

            .welcome-message h6 {
                font-size: 14px !important;
            }

            .welcome-message small {
                font-size: 10px !important;
            }
        }

        @media (max-width: 480px) {
            .welcome-message {
                font-size: 5px !important;
            }
        }

        .content-area {
            padding-top: 0;
        }

        .page-header {
            background: #fff;
            padding: 20px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: -10px !important;
            margin-bottom: 10px !important;
            position: relative;
            outline: 0.2px solid #e1e1e1ff;
        }

        .back-btn-item {
            background: rgba(255, 255, 255, 1) !important;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: absolute;
            left: 20px;
            margin: -70px 0 0 0;
        }

        .back-btn-item:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .back-btn {
            background: none;
            border: none;
            color: #666;
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s ease;
        }

        .back-btn:hover {
            color: #4A90E2;
        }

        .floating-cart-btn {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #5BC2E7;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(79, 172, 254, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50%) translateY(-20px);
            font-family: 'Poppins', Arial, sans-serif;
            text-decoration: none;
            min-width: 180px;
            justify-content: center;
        }

        .floating-cart-btn.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .floating-cart-btn:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 6px 25px rgba(79, 172, 254, 0.5);
            background: #46bce7ff;
            color: white;
            text-decoration: none;
        }

        .floating-cart-btn:active {
            transform: translateX(-50%) translateY(-1px);
        }

        .floating-cart-btn .cart-icon {
            font-size: 16px;
        }

        .floating-cart-btn .cart-text {
            flex: 1;
            text-align: left;
        }

        .floating-cart-btn .cart-total {
            font-weight: 700;
            font-size: 15px;
        }

        .floating-cart-btn .cart-badge {
            position: absolute;
            top: -5px;
            left: 15px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        @keyframes cartButtonSlideDown {
            from {
                opacity: 0;
                transform: translateX(0%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0%) translateY(0);
            }
        }

        .floating-cart-btn.animate-in {
            animation: cartButtonSlideDown 0.4s ease-out;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 80px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -2px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 0;
        }
        
        .bottom-nav .under {
            margin-bottom: 5px;
        }
        
        .nav-item {
            flex: 1;
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #999;
            font-size: 11px;
            font-weight: 500;
            height: 100%;
            padding: 8px 2px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            position: relative;
            min-width: 0;
        }

        .nav-item i {
            font-size: 20px !important;
            margin-bottom: 4px !important;
            line-height: 1 !important;
            width: 20px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bottom-nav .nav-item.active,
        .bottom-nav .nav-item:hover {
            color: #4facfe;
        }

        .bottom-nav .nav-item.active i,
        .bottom-nav .nav-item:hover i {
            color: #4facfe;
        }

        .nav-icon-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-icon-up {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            text-decoration: none;
            color: #5bc2e7;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1100;
            cursor: pointer;
        }

        .icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #5BC2E7 0%, #4facfe 100%);
            border: 4px solid #f8f9faff;
            transition: all 0.3s ease;
        }

        .card-icon-up i {
            font-size: 22px;
            color: white;
        }

        .card-icon-up span {
            font-size: 12px;
            font-weight: 500;
            color: #5bc2e7;
        }

        .card-icon-up:hover .icon-wrapper {
            background-color: #5bc2e7;
        }

        .card-icon-up:hover i {
            color: #fff;
        }

        .card-icon-up:hover span {
            color: #5bc2e7;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ca1f1f;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transform: scale(0);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .cart-badge.show {
            transform: scale(1);
        }

        .cart-badge.animate {
            animation: cartBounce 0.4s ease;
        }

        .loyalty-card-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            animation: loyaltyCardFadeIn 0.3s ease-out;
            border: none !important;
        }

        @keyframes loyaltyCardFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes loyaltyCardSlideUp {
            from { 
                opacity: 0;
                transform: translate(-50%, -40%) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .loyalty-card-modal-content {
            background: none !important;
            border: none !important;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 900px;
            min-width: 320px;
            animation: loyaltyCardSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .loyalty-card-close-btn {
            color: white;
            position: absolute;
            top: -60px;
            right: 10px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        .loyalty-card-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .loyalty-card-container {
            width: 100%;
            max-width: 900px;
            margin: auto;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .loyalty-card-front {
            width: 100%;
            background: #ffffff;
            color: #000;
            overflow: visible;
            padding: 0;
            border: 3px solid #000000;
            box-sizing: border-box;
        }

        .loyalty-front-content {
            padding: 30px 40px;
        }

        /* Logo Section */
        .loyalty-logo-area img {
            width: 100%;
            max-width: 200px;
            height: auto;
            margin-bottom: 5px;
        }

        .loyalty-card-text {
            font-size: 22px;
            font-weight: 700;
            color: #E63946;
            letter-spacing: 6px;
            margin: 0;
        }

        /* QR Code Section */
        .loyalty-qr-code-section {
            position: absolute;
            top: -10px;
            right: 10px;
        }

        .loyalty-qr-frame {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .loyalty-qr-code-image {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 85%;
            height: 85%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loyalty-qr-code-image img {
            width: 100% !important;
            height: 100% !important;
            max-width: 128px;
            max-height: 128px;
        }

        .loyalty-qr-corner {
            position: absolute;
            width: 30px;
            height: 30px;
            border: 4px solid #E63946;
        }

        .loyalty-qr-corner.top-left {
            top: 0;
            left: 0;
            border-right: none;
            border-bottom: none;
        }

        .loyalty-qr-corner.top-right {
            top: 0;
            right: 0;
            border-left: none;
            border-bottom: none;
        }

        .loyalty-qr-corner.bottom-left {
            bottom: 0;
            left: 0;
            border-right: none;
            border-top: none;
        }

        .loyalty-qr-corner.bottom-right {
            bottom: 0;
            right: 0;
            border-left: none;
            border-top: none;
        }

        /* Customer Info Fields */
        .loyalty-info-field {
            border: 2px solid #5BC2E7;
            padding: 8px 20px;
            margin-bottom: 20px;
        }

        .loyalty-info-label {
            font-size: 10px;
            color: #000000;
            font-weight: 600;
            margin-bottom: 2px;
            display: block;
        }

        .loyalty-info-value {
            font-size: 15px;
            color: #000000;
            font-weight: 500;
            letter-spacing: 0.5px;
            display: block;
        }

        /* Footer */
        .loyalty-card-footer {
            font-size: 12px;
            color: #000000;
            margin-top: 0px;
        }

        .loyalty-footer-text {
            margin: 0 0 3px 0;
            line-height: 1.4;
        }

        .loyalty-footer-link {
            font-weight: 600;
            margin: 0;
        }

        .loyalty-qr-code-image {
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .loyalty-qr-code-image:hover {
            transform: translate(-50%, -50%) scale(1.05);
        }

        .qr-zoom-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .qr-zoom-overlay.active {
            display: flex;
        }

        .qr-zoom-container {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            animation: zoomIn 0.3s ease;
        }

        .qr-zoom-container img {
            width: 100%;
            height: 100%;
            max-width: 500px;
            max-height: 500px;
            object-fit: contain;
        }

        .qr-zoom-close {
            position: absolute;
            top: -50px;
            right: -10px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .qr-zoom-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @media (max-width: 576px) {
            .qr-zoom-close {
                top: -45px;
                width: 35px;
                height: 35px;
                font-size: 20px;
            }
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .loyalty-card-close-btn {
                top: -50px;
                width: 35px;
                height: 35px;
                font-size: 20px;
            }

            .loyalty-front-content {
                padding: 25px 30px;
            }

            .loyalty-logo-area img {
                max-width: 160px;
            }

            .loyalty-card-text {
                font-size: 18px;
                letter-spacing: 4px;
            }

            .loyalty-qr-code-section {
                top: 0px;
                right: 15px;
            }

            .loyalty-qr-frame {
                width: 120px;
                height: 120px;
            }

            .loyalty-qr-code-image {
                top: 60px;
                right: 26px;
            }

            .loyalty-qr-corner {
                width: 25px;
                height: 25px;
                border-width: 3px;
            }

            .loyalty-info-field {
                padding: 6px 15px;
            }

            .loyalty-info-label {
                font-size: 9px;
            }

            .loyalty-info-value {
                font-size: 13px;
            }

            .loyalty-card-footer {
                font-size: 11px;
            }
        }

        @media (max-width: 576px) {
            .loyalty-card-modal-content {
                width: 95%;
            }

            .loyalty-card-close-btn {
                top: -45px;
                width: 32px;
                height: 32px;
                font-size: 18px;
            }

            .loyalty-front-content {
                padding: 20px 20px;
            }

            .loyalty-logo-area img {
                max-width: 140px;
            }

            .loyalty-card-text {
                font-size: 16px;
                letter-spacing: 3px;
            }

            .loyalty-qr-code-section {
                top: 10px;
                right: 10px;
            }

            .loyalty-qr-frame {
                width: 100px;
                height: 100px;
            }

            .loyalty-qr-code-image {
                top: 50px;
                right: 22px;
            }

            .loyalty-qr-corner {
                width: 20px;
                height: 20px;
                border-width: 2px;
            }

            .loyalty-info-field {
                padding: 5px 12px;
                margin-bottom: 15px;
            }

            .loyalty-info-label {
                font-size: 8px;
            }

            .loyalty-info-value {
                font-size: 12px;
            }

            .loyalty-card-footer {
                font-size: 7px;
            }
        }

        @media (max-width: 400px) {
            .loyalty-logo-area img {
                max-width: 120px;
            }

            .loyalty-card-text {
                font-size: 14px;
                letter-spacing: 2px;
            }

            .loyalty-qr-frame {
                width: 80px;
                height: 80px;
            }

            .loyalty-qr-code-image {
                top: 40px;
                right: 18px;
            }

            .loyalty-qr-corner {
                width: 18px;
                height: 18px;
            }
        }

        
        @keyframes cartBounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .nav-item .nav-icon-container + .under {
            margin-top: 4px;
        }

        @media (max-width: 480px) {
            .floating-cart-btn {
                bottom: 110px;
                left: 15px;
                right: 15px;
                transform: none;
                width: calc(100% - 30px);
            }
            
            .floating-cart-btn.show {
                transform: translateX(0) translateY(0);
            }
            
            .floating-cart-btn:hover {
                transform: translateX(0) translateY(-3px);
            }
            
            .floating-cart-btn:active {
                transform: translateX(0) translateY(-1px);
            }
        }
        .offline-mode {
            filter: grayscale(20%);
        }
        
        .offline-mode::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FF9800, #FF5722);
            z-index: 10000;
            animation: pulse 2s ease-in-out infinite;
        }

        .swal-top-margin {
            margin-top: -155px !important;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>

    <script>
    window.addEventListener('online', () => {
        console.log('🌐 You are online');
        document.body.classList.remove('offline-mode');
        
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ 
                type: 'RESET_OFFLINE_STATE' 
            });
        }
        
        showOnlineNotification();
    });

    window.addEventListener('offline', () => {
        console.log('📵 You are offline');
        document.body.classList.add('offline-mode');
        showOfflineChoiceDialog();
    });

    function showOfflineChoiceDialog() {
        Swal.fire({
            icon: 'warning',
            title: 'Connection Lost',
            html: `
                <p style="font-size: 16px; margin-bottom: 20px;">
                    You have lost your internet connection.
                </p>
                <p style="font-size: 14px; color: #666;">
                    Choose an option to continue:
                </p>
            `,
            showDenyButton: true,
            showCancelButton: false,
            confirmButtonText: '<i class="fas fa-sync-alt"></i> Retry Connection',
            denyButtonText: '<i class="fas fa-wifi-slash"></i> Go Offline',
            confirmButtonColor: '#5DADE2',
            denyButtonColor: '#95a5a6',
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
            popup: 'swal-top-margin'
        }
        }).then((result) => {
            if (result.isConfirmed) {
                retryConnection();
            } else if (result.isDenied) {
                redirectToOfflineMode();
            }
        });
    }

    function retryConnection() {
        Swal.fire({
            title: 'Checking Connection...',
            html: 'Please wait while we verify your internet connection.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                popup: 'swal-top-margin'
            },
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        Promise.all([
            fetch('https://www.google.com/generate_204', {
                method: 'HEAD',
                mode: 'no-cors',
                cache: 'no-store',
                signal: controller.signal
            }),
            fetch('https://1.1.1.1/cdn-cgi/trace', {
                method: 'GET',
                cache: 'no-store',
                signal: controller.signal
            })
        ])
            .then(responses => {
                clearTimeout(timeoutId);
                
                const currentTime = new Date().getTime();
                const connectionValid = responses.some(response => {
                    return response && response.ok;
                });

                if (connectionValid && navigator.onLine) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Connected!',
                        text: 'Your internet connection has been restored.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error('No valid connection');
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.log('Connection check failed:', error.message);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Failed',
                    html: `
                        <p style="font-size: 16px; margin-bottom: 15px;">
                            Unable to establish a connection.
                        </p>
                        <p style="font-size: 14px; color: #666;">
                            ${error.name === 'AbortError' ? 'Connection timeout.' : 'No internet access detected.'}<br>
                            Redirecting to offline mode...
                        </p>
                    `,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                }).then(() => {
                    redirectToOfflineMode();
                });
            });
    }

    function redirectToOfflineMode() {
        const getBasePath = () => {
            const path = window.location.pathname;
            if (path.includes('/crms-risev2/public')) {
                return '/crms-risev2/public';
            }
            return '';
        };
        
        const offlinePath = `${getBasePath()}/offline/login.html`;
        
        Swal.fire({
            icon: 'info',
            title: 'Switching to Offline Mode',
            html: 'Redirecting to offline mode...<br><small>Limited features available</small>',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        }).then(() => {
            window.location.href = offlinePath;
        });
    }

    function showOnlineNotification() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: 'success',
            title: 'You are back online!'
        });
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            const { type } = event.data;
            
            switch(type) {
                case 'OFFLINE_CHOICE_DIALOG':
                    showOfflineChoiceDialog();
                    break;
                    
                case 'ONLINE_STATUS':
                    if (event.data.status === 'online') {
                        showOnlineNotification();
                    }
                    break;
            }
        });
    }

    if (!navigator.onLine) {
        document.body.classList.add('offline-mode');
        setTimeout(() => {
            showOfflineChoiceDialog();
        }, 500);
    }
</script>
</head>
<body>

    <!-- Topbar -->
    @if (!request()->is(['account', 'history', 'products', 'cart', 'place_order', 'rewards', 'redeem', 'voucher', 'chat', 'notification', 'settlement', 'points-history', 'notifications/*']))
    <header class="topbar">
        <div class="topbar-left">
            <div class="welcome-message">
                <h6>Hello {{current(explode(' ',auth()->user()->name))}}!</h6>
                <small>Welcome back to dashboard</small>
            </div>
        </div>

        <div class="topbar-right">
            <button class="chat-btn" onclick="window.location.href='{{url('notification')}}'">
                <i class="bi bi-bell-fill"></i>
                @if($unreadNotifications > 0)
                    <span class="notification-badge show pulse" id="notificationBadge">
                        {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                    </span>
                @else
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                @endif
            </button>

            <div class="points-dropdown">
                <a class="points-btn" href="{{ url('rewards') }}" aria-label="View rewards and available points">
                    <div class="points-icon-wrapper">
                        <img src="{{ url('images/icon.png') }}" alt="Points" class="profile-img">
                    </div>
                    <span class="points-text">{{ number_format(max(0, (int) ($availablePoints ?? 0))) }} points</span>
                </a>
            </div>
        </div>
    </header>
    @endif

    <!-- Main Content Area -->
    @yield('content')

    @if (!request()->is(['account', 'history', 'cart', 'place_order', 'rewards', 'redeem', 'voucher', 'chat', 'notification', 'settlement', 'points-history', 'notifications/*']))
    @if(auth()->user()->isDealerLike())
    <a href="{{ url('cart') }}" class="floating-cart-btn" id="floatingCartBtn">
        <i class="bi bi-cart cart-icon"></i>
        <div class="" id="floatingCartBadge">0</div>
        <span class="cart-text">View your cart</span>
        <span class="cart-total" id="floatingCartTotal">₱ 0.00</span>
    </a>
    @endif
    @endif

    @if (!request()->is(['account', 'history', 'cart', 'place_order', 'rewards', 'redeem', 'voucher', 'chat' , 'notification', 'settlement', 'points-history', 'notifications/*']))
    <!-- Bottom Navbar -->
    <nav class="bottom-nav">
        <a href="{{url('/')}}" class="nav-item {{ request()->is('/') ? 'active' : '' }}">
            <i class="bi bi-house-door"></i>
            <span class="under">Home</span>
        </a>
        @if(auth()->user()->role == "Client")
        <a href="#" class="nav-item" id="loyaltyCardBtn">
            <i class="bi bi-credit-card"></i>
            <span class="under">Card</span>
        </a>
        @endif

        @if(auth()->user()->isDealerLike())
        <a href="{{url('/products')}}" class="nav-item {{ request()->is('products') ? 'active' : '' }}">
            <i class="bi bi-bag"></i>
            <span class="under">Products</span>
        </a>
        
        <!-- QR Scanner Icon -->
        <div class="card-icon-up" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
            <div class="icon-wrapper">
                <i class="bi bi-qr-code-scan"></i>
            </div>
            <span>Scan</span>
        </div>
        <a href="#" class="nav-item">
            <i class=""></i>
            <span style='color:white;'></span>
        </a>
        @endif
        <a href="{{url('/history')}}" class="nav-item {{ request()->is('history') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i>
            <span class="under">History</span>
        </a>

        <a href="{{url('/account')}}" class="nav-item {{ request()->is('account') ? 'active' : '' }}">
            <i class="bi bi-person"></i>
            <span class="under">Profile</span>
        </a>
    </nav>
    @endif

    <div id="loyaltyCardModal" class="loyalty-card-modal">
        <div class="loyalty-card-modal-content">
            <span class="loyalty-card-close-btn">&times;</span>
            <div class="loyalty-card-container">
                <div class="loyalty-card-front">
                    <div class="loyalty-front-content">
                        <!-- Top Section: Logo and QR Code -->
                        <div class="row position-relative mb-4">
                            <div class="col-12">
                                <div class="loyalty-logo-area">
                                    <img src="images/logo_mo.png" alt="Gaz Lite">
                                    <p class="loyalty-card-text">LOYALTY CARD</p>
                                </div>
                                
                                <!-- QR Code Section -->
                                <div class="loyalty-qr-code-section">
                                    <div class="loyalty-qr-frame">
                                        <div class="loyalty-qr-corner top-left"></div>
                                        <div class="loyalty-qr-corner top-right"></div>
                                        <div class="loyalty-qr-corner bottom-left"></div>
                                        <div class="loyalty-qr-corner bottom-right"></div>
                                    </div>
                                    <div id="qrcode" class="loyalty-qr-code-image"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Information Section -->
                        <div class="row mt-5">
                            <div class="col-12">
                                <div class="loyalty-info-field">
                                    <span class="loyalty-info-label">Customer Name</span>
                                    <span class="loyalty-info-value">{{ auth()->user()->name }}</span>
                                </div>
                                
                                <div class="loyalty-info-field">
                                    <span class="loyalty-info-label">Address</span>
                                    <span class="loyalty-info-value">ALBAY</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer Section -->
                        <div class="row">
                            <div class="col-6">
                                <div class="loyalty-card-footer text-start">
                                    <p class="loyalty-footer-text">Visit our website</p>
                                    <p class="loyalty-footer-link">www.gazlite.com.ph</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="loyalty-card-footer text-end">
                                    <p class="loyalty-footer-text">For inquiries, visit us on Facebook</p>
                                    <p class="loyalty-footer-link">facebook/GazLitePH</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Zoom Overlay -->
    <div class="qr-zoom-overlay" id="qrZoomOverlay">
        <div class="qr-zoom-container">
            <button class="qr-zoom-close" id="qrZoomClose">&times;</button>
            <img id="qrZoomImage" src="" alt="QR Code">
        </div>
    </div>

    <!-- Include QR Scanner Modal -->
    @include('qr_scanner')

    <!-- Hidden logout form -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        {{ csrf_field() }}
    </form>
  
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrCodeElement = document.querySelector('.loyalty-qr-code-image');
            const qrZoomOverlay = document.getElementById('qrZoomOverlay');
            const qrZoomImage = document.getElementById('qrZoomImage');
            const qrZoomClose = document.getElementById('qrZoomClose');

            if (qrCodeElement) {
                qrCodeElement.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    const qrImage = this.querySelector('img');
                    if (qrImage) {
                        qrZoomImage.src = qrImage.src;
                        qrZoomOverlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                });
            }

            function closeQrZoom() {
                qrZoomOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }

            if (qrZoomClose) {
                qrZoomClose.addEventListener('click', closeQrZoom);
            }

            if (qrZoomOverlay) {
                qrZoomOverlay.addEventListener('click', function(e) {
                    if (e.target === qrZoomOverlay) {
                        closeQrZoom();
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && qrZoomOverlay.classList.contains('active')) {
                    closeQrZoom();
                }
            });
        });
    </script>
    
    @yield('js')
    @if(auth()->user()->role == "Client")
        <script>
            const qrcode = new QRCode(document.getElementById('qrcode'), {
                text: "{{ $customer->serial->serial_number ?? "Unknown"}}",
                width: 70,
                height: 70,
                colorDark : '#000',
                colorLight : '#fff',
                correctLevel : QRCode.CorrectLevel.H
            });
        </script>
    @endif

    <script>
    function logout() {
        event.preventDefault();
        document.getElementById('logout-form').submit();
    }

    function openChat() {
        // Add your chat functionality here
        console.log('Chat opened');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const loyaltyModal = document.getElementById("loyaltyCardModal");
        const loyaltyButton = document.getElementById("loyaltyCardBtn");
        const loyaltyCloseBtn = loyaltyModal ? loyaltyModal.querySelector(".loyalty-card-close-btn") : null;

        if (loyaltyButton && loyaltyModal) {
            loyaltyButton.addEventListener("click", function (e) {
                e.preventDefault();
                loyaltyModal.style.display = "block";
                document.body.style.overflow = "hidden";
            });
        }

        function closeLoyaltyModal() {
            if (loyaltyModal) {
                loyaltyModal.style.display = "none";
                document.body.style.overflow = "auto";
            }
        }

        if (loyaltyCloseBtn) {
            loyaltyCloseBtn.addEventListener("click", closeLoyaltyModal);
        }

        window.addEventListener("click", (event) => {
            if (event.target === loyaltyModal) {
                closeLoyaltyModal();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && loyaltyModal && loyaltyModal.style.display === "block") {
                closeLoyaltyModal();
            }
        });
    });
    </script>

    <!-- Cart Badge Update Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        updateCartBadge();
        updateFloatingCartButton();
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'dealerCartData' || e.key === 'dealerCartItems') {
                updateCartBadge();
                updateFloatingCartButton();
            }
        });
    });

    function updateCartBadge() {
        try {
            let totalItems = 0;

            const cartData = localStorage.getItem('dealerCartData');
            if (cartData) {
                const parsedData = JSON.parse(cartData);
                if (Array.isArray(parsedData)) {
                    totalItems = parsedData.length;
                }
            } else {
                const storedItems = localStorage.getItem('dealerCartItems');
                totalItems = storedItems ? parseInt(storedItems) || 0 : 0;
            }

            const badge = document.getElementById('cartBadge');
            if (badge) {
                const currentCount = parseInt(badge.textContent) || 0;

                if (totalItems > 0) {
                    badge.textContent = totalItems;
                    badge.style.display = 'flex';
                    badge.classList.add('show');

                    if (totalItems > currentCount) {
                        badge.classList.add('animate');
                        setTimeout(() => {
                            badge.classList.remove('animate');
                        }, 400);
                    }
                } else {
                    badge.textContent = '';
                    badge.style.display = 'none';
                    badge.classList.remove('show');
                }
            }

            console.log('Cart badge updated:', totalItems);
        } catch (error) {
            console.error('Error updating cart badge:', error);
            const badge = document.getElementById('cartBadge');
            if (badge) {
                badge.textContent = '';
                badge.style.display = 'none';
                badge.classList.remove('show');
            }
        }
    }

    function updateFloatingCartButton() {
        try {
            const floatingBtn = document.getElementById('floatingCartBtn');
            const floatingBadge = document.getElementById('floatingCartBadge');
            const floatingTotal = document.getElementById('floatingCartTotal');
            
            if (!floatingBtn || !floatingBadge || !floatingTotal) return;

            let totalItems = 0;
            let totalAmount = 0;

            const cartData = localStorage.getItem('dealerCartData');
            if (cartData) {
                const parsedData = JSON.parse(cartData);
                if (Array.isArray(parsedData)) {
                    totalItems = parsedData.length;
                    
                    totalAmount = parsedData.reduce((sum, item) => {
                        const price = parseFloat(item.price) || 0;
                        const quantity = parseInt(item.quantity) || 0;
                        return sum + (price * quantity);
                    }, 0);
                }
            }

            floatingBadge.textContent = totalItems;
            floatingTotal.textContent = `₱ ${totalAmount.toFixed(2)}`;

            if (totalItems > 0) {
                if (!floatingBtn.classList.contains('show')) {
                    floatingBtn.classList.add('show', 'animate-in');
                    setTimeout(() => {
                        floatingBtn.classList.remove('animate-in');
                    }, 400);
                }
            } else {
                floatingBtn.classList.remove('show');
            }

            console.log('Floating cart button updated:', { totalItems, totalAmount });
        } catch (error) {
            console.error('Error updating floating cart button:', error);
        }
    }

    function triggerCartBadgeUpdate() {
        setTimeout(() => {
            updateCartBadge();
            updateFloatingCartButton();
        }, 50);
    }

    function updateCartBadgeAfterAction() {
        setTimeout(() => {
            updateCartBadge();
            updateFloatingCartButton();
        }, 100);
    }

    function addItemToCart(productData) {
        try {
            var cartData = getCartData();
            var existingIndex = -1;
            
            for (var i = 0; i < cartData.length; i++) {
                if (cartData[i].name === productData.name) {
                    existingIndex = i;
                    break;
                }
            }
            
            if (existingIndex >= 0) {
                cartData[existingIndex].quantity += productData.quantity;
            } else {
                cartData.push(productData);
            }

            const result = saveCartData(cartData);
            
            if (result) {
                requestAnimationFrame(() => {
                    if (typeof updateCartBadge === 'function') {
                        updateCartBadge();
                    }
                    if (typeof updateFloatingCartButton === 'function') {
                        updateFloatingCartButton();
                    }
                });
            }
            
            return result;
        } catch (error) {
            console.error('Error in addItemToCart:', error);
            return false;
        }
    }

    function getCartData() {
        try {
            const stored = localStorage.getItem('dealerCartData');
            return stored ? JSON.parse(stored) : [];
        } catch (error) {
            console.error('Error getting cart data:', error);
            return [];
        }
    }

    function saveCartData(cartData) {
        try {
            localStorage.setItem('dealerCartData', JSON.stringify(cartData));
            
            const totalItems = cartData.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
            localStorage.setItem('dealerCartItems', totalItems.toString());
            
            return true;
        } catch (error) {
            console.error('Error saving cart data:', error);
            return false;
        }
    }

    function handleQuantityModalConfirm(qty, cartItem) {
        if (qty > 0) {
            const success = addItemToCart(cartItem);
            
            if (success) {
                displayToast(`${qty} item(s) added to cart!`, 'success');
            } else {
                displayToast('Failed to add item to cart', 'error');
            }
        }
        
        closeQtyModal();
    }

    function triggerFloatingCartUpdate() {
        setTimeout(() => {
            updateFloatingCartButton();
        }, 50);
    }
    </script>
    

</body>
</html>
