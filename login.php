<?php
require_once 'config.php';
session_start();
$email = '';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $login_type = $_POST['login_type'] ?? '';

    if (empty($email)) $errors[] = "Email address is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($login_type)) $errors[] = "Please select a login type.";

    if (empty($errors)) {
        if ($login_type === 'admin') {
            $stmt = $conn->prepare("SELECT id, full_name, password, trial_end_date FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    if (date('Y-m-d H:i:s') <= $user['trial_end_date']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_type'] = 'admin';
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $errors[] = "Your trial has expired. Please upgrade to continue.";
                    }
                } else {
                    $errors[] = "Incorrect password. Please try again.";
                }
            } else {
                $errors[] = "No admin account found with this email.";
            }
            $stmt->close();
        } elseif ($login_type === 'staff') {
            $stmt = $conn->prepare("SELECT id, name, password, status, created_by FROM create_users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $staff = $result->fetch_assoc();
                if (password_verify($password, $staff['password'])) {
                    if ($staff['status'] == 1) {
                        $_SESSION['user_id'] = $staff['id'];
                        $_SESSION['full_name'] = $staff['name'];
                        $_SESSION['user_type'] = 'staff';
                        $_SESSION['created_by'] = $staff['created_by'];
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $errors[] = "Your account is inactive. Contact your administrator.";
                    }
                } else {
                    $errors[] = "Incorrect password. Please try again.";
                }
            } else {
                $errors[] = "No employee account found with this email.";
            }
            $stmt->close();
        }
    }
}
$sel = (isset($_POST['login_type']) && in_array($_POST['login_type'], ['admin','staff'])) ? $_POST['login_type'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
<title>Login — Credible</title>
<link rel="icon" type="image/png" href="images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-font-smoothing: antialiased;
}

:root {
    --p: #0284c7;
    --p2: #0b1220;
    --p3: #0ea5e9;
    --p4: #0369a1;
    --p5: #93c5fd;
    --p6: #dbeafe;
    --p7: #f0f9ff;
    --gold: #38bdf8;
    --gold2: #7dd3fc;
    --green: #4ade80;
    --red: #EF4444;
    --red-bg: #FEF2F2;
    --red-border: #FECACA;
    --r: 14px;
    --r-lg: 20px;
    --r-xl: 28px;
}

html, body {
    height: 100%;
    overflow-x: hidden;
}

body {
    font-family: 'Outfit', sans-serif;
    min-height: 100vh;
    background: #05070d;
}

/* ━━━━ FULL-PAGE LAYOUT ━━━━ */
.page {
    display: flex;
    min-height: 100vh;
}

/* LEFT PANEL - Hidden on mobile, visible on tablet+ */
.page-left {
    display: none;
}

/* RIGHT PANEL - Full width on mobile */
.page-right {
    position: relative;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(160deg, #04060a 0%, #0b1220 45%, #0c2233 100%);
    padding: 24px 20px;
    min-height: 100vh;
}

/* Show left panel only on desktop/tablet landscape */
@media (min-width: 992px) {
    .page {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .page-left {
        display: flex;
        position: relative;
        overflow: hidden;
        background: linear-gradient(160deg, #04060a 0%, #0b1220 35%, #075985 65%, #0284c7 100%);
        flex-direction: column;
        justify-content: space-between;
        padding: 40px 44px;
    }
    .page-right {
        padding: 32px 24px;
    }
}

/* LEFT PANEL STYLES (only for desktop) */
.left-dots {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background-image: radial-gradient(rgba(255,255,255,0.13) 1.5px, transparent 1.5px);
    background-size: 32px 32px;
    animation: dotsMove 22s linear infinite;
}

@keyframes dotsMove {
    0% { background-position: 0 0; }
    100% { background-position: 32px 32px; }
}

.left-orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(64px);
}

.lo1 {
    width: 420px;
    height: 420px;
    top: -120px;
    right: -80px;
    background: radial-gradient(circle, rgba(14,165,233,0.45), transparent 70%);
    animation: orbF 18s ease-in-out infinite;
}

.lo2 {
    width: 300px;
    height: 300px;
    bottom: -60px;
    left: -60px;
    background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%);
    animation: orbF 24s ease-in-out infinite;
    animation-delay: -10s;
}

.lo3 {
    width: 180px;
    height: 180px;
    top: 45%;
    left: 30%;
    background: radial-gradient(circle, rgba(255,255,255,0.07), transparent 70%);
    animation: orbF 16s ease-in-out infinite;
    animation-delay: -5s;
}

@keyframes orbF {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-24px) scale(1.08); }
}

.left-top {
    position: relative;
    z-index: 1;
}

.left-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 48px;
}

.left-logo-box {
    width: 46px;
    height: 46px;
    background: rgba(255,255,255,0.12);
    border: 1.5px solid rgba(255,255,255,0.22);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    backdrop-filter: blur(6px);
}

.left-logo-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.left-logo-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.3px;
}

.left-logo-name span {
    color: var(--gold2);
}

.left-heading {
    font-size: clamp(1.8rem, 3vw, 2.8rem);
    font-weight: 900;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 16px;
}

.left-heading em {
    font-style: normal;
    color: var(--gold2);
}

.left-sub {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.6;
    max-width: 380px;
    margin-bottom: 28px;
}

/* Features Grid - Only visible on desktop */
.feat-icons-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    position: relative;
    z-index: 1;
}

.fi-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 12px;
    padding: 12px 6px;
    transition: all 0.3s;
    cursor: default;
}

.fi-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold2);
    font-size: 0.9rem;
}

.fi-item span {
    font-size: 0.65rem;
    font-weight: 600;
    color: rgba(255,255,255,0.75);
    text-align: center;
    line-height: 1.3;
}

.left-bottom {
    position: relative;
    z-index: 1;
}

.left-stats {
    display: flex;
    gap: 28px;
}

.ls-item .ls-num {
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--gold2);
}

.ls-item .ls-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
    font-weight: 500;
}

/* Mobile Brand Section - Shows on mobile only */
.mobile-brand {
    display: none;
    text-align: center;
    margin-bottom: 24px;
    padding: 0 8px;
}

.mobile-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 16px;
}

.mobile-logo-icon {
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.1);
    border: 1.5px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.mobile-logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.mobile-logo-text {
    font-size: 1.6rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.5px;
}

.mobile-logo-text span {
    color: var(--gold2);
}

.mobile-heading {
    font-size: 1.4rem;
    font-weight: 900;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 8px;
}

.mobile-heading em {
    font-style: normal;
    color: var(--gold2);
}

.mobile-sub {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.5;
    margin-bottom: 8px;
}

.mobile-tagline {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.45);
}

@media (max-width: 991px) {
    .mobile-brand {
        display: block;
    }
}

/* RIGHT PANEL DECORATIONS */
.right-dots {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 24px 24px;
}

.right-orb1 {
    position: absolute;
    width: 300px;
    height: 300px;
    top: -80px;
    right: -80px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(2,132,199,0.35), transparent 70%);
    filter: blur(50px);
    pointer-events: none;
}

.right-orb2 {
    position: absolute;
    width: 240px;
    height: 240px;
    bottom: -60px;
    left: -60px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.08), transparent 70%);
    filter: blur(50px);
    pointer-events: none;
}

#rcanvas {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.4;
}

/* ━━━━ FORM CARD ━━━━ */
.form-wrap {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    animation: cardSlide 0.5s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}

@keyframes cardSlide {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-card {
    background: #fff;
    border-radius: var(--r-xl);
    box-shadow: 0 25px 50px rgba(0,0,0,0.5), 0 0 0 1px rgba(2,132,199,0.2);
    overflow: hidden;
}

/* CARD TOP BAR - Hide on mobile since we show mobile brand above */
.ctop {
    background: linear-gradient(135deg, #0b1220 0%, #0284c7 55%, #0ea5e9 100%);
    padding: 20px 20px 16px;
    position: relative;
    overflow: hidden;
}

@media (min-width: 992px) {
    .ctop {
        padding: 24px 30px 20px;
    }
}

.ctop-dots {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,0.08) 1.2px, transparent 1.2px);
    background-size: 20px 20px;
}

.ctop-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ctop-logo {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    background: rgba(255,255,255,0.14);
    border: 1.5px solid rgba(255,255,255,0.25);
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (min-width: 992px) {
    .ctop-logo {
        width: 52px;
        height: 52px;
    }
}

.ctop-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.ctop-name {
    font-size: 1.2rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.4px;
    line-height: 1.2;
}

@media (min-width: 992px) {
    .ctop-name {
        font-size: 1.45rem;
    }
}

.ctop-name span {
    color: var(--gold2);
}

.ctop-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.85);
    font-size: 0.65rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 50px;
    margin-top: 4px;
}

@media (min-width: 992px) {
    .ctop-badge {
        font-size: 0.72rem;
        padding: 4px 10px;
        margin-top: 5px;
    }
}

.ctop-badge .bdot {
    width: 5px;
    height: 5px;
    background: var(--green);
    border-radius: 50%;
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.back-link {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.8);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.25s;
}

.back-link:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

/* CARD BODY */
.cbody {
    padding: 20px 20px 16px;
}

@media (min-width: 992px) {
    .cbody {
        padding: 28px 30px 24px;
    }
}

/* type selector */
.type-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #0369a1;
    text-transform: uppercase;
    letter-spacing: 0.9px;
    margin-bottom: 12px;
}

.type-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.tcard {
    border: 1.5px solid #d5e8f2;
    border-radius: var(--r);
    background: linear-gradient(160deg, #f8fcff, #eef6fa);
    padding: 14px 10px 12px;
    cursor: pointer;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: all 0.28s;
    font-family: 'Outfit', sans-serif;
    width: 100%;
}

@media (min-width: 992px) {
    .tcard {
        padding: 20px 12px 16px;
        gap: 9px;
    }
}

.tc-ico-wrap {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: #0b1220;
    border: 1.5px solid #0b1220;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.28s;
}

@media (min-width: 992px) {
    .tc-ico-wrap {
        width: 48px;
        height: 48px;
    }
}

.tc-ico {
    font-size: 1.1rem;
    color: #fff;
    transition: color 0.28s;
}

.tc-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--p2);
}

.tc-sub {
    font-size: 0.65rem;
    color: #0369a1;
    font-weight: 500;
}

/* step indicator */
.step-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #f5faff, #e8f4fb);
    border: 1.5px solid #cbe4f2;
    border-radius: var(--r);
    padding: 10px 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

@media (min-width: 992px) {
    .step-bar {
        padding: 14px 16px;
        gap: 12px;
        flex-wrap: nowrap;
    }
}

.sb-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.sb-text {
    flex: 1;
}

.sb-lbl {
    font-size: 0.65rem;
    font-weight: 700;
    color: #0369a1;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.sb-val {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--p2);
    margin-top: 1px;
}

.sb-change {
    background: none;
    border: 1.5px solid var(--p6);
    color: #0369a1;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 9px;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

/* form fields */
.fl {
    margin-bottom: 14px;
}

.fl-lbl {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--p2);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.fl-wrap {
    display: flex;
    align-items: center;
    border: 1.5px solid #c7e2f0;
    border-radius: var(--r);
    overflow: hidden;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.fl-wrap:focus-within {
    border-color: var(--p3);
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.fl-pfx {
    width: 44px;
    height: 46px;
    flex-shrink: 0;
    background: linear-gradient(160deg, var(--p), var(--p3));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.95rem;
}

.fl-input {
    flex: 1;
    height: 46px;
    border: none;
    outline: none;
    padding: 0 12px;
    font-family: 'Outfit', sans-serif;
    font-size: 0.9rem;
    color: #1a0d1c;
    background: transparent;
}

.fl-input::placeholder {
    color: #bbb;
}

.fl-sfx {
    width: 44px;
    height: 46px;
    flex-shrink: 0;
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 0.9rem;
    cursor: pointer;
    transition: color 0.2s;
}

.fl-sfx:hover {
    color: var(--p);
}

/* error box */
.err-box {
    background: var(--red-bg);
    border: 1px solid var(--red-border);
    border-left: 4px solid var(--red);
    border-radius: var(--r);
    padding: 10px 12px;
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.err-box i {
    color: var(--red);
    font-size: 0.85rem;
    margin-top: 2px;
    flex-shrink: 0;
}

.err-box p {
    font-size: 0.78rem;
    color: #991B1B;
    margin: 0;
    line-height: 1.4;
    font-weight: 500;
}

/* submit button */
.sub-btn {
    width: 100%;
    height: 50px;
    margin-top: 8px;
    border: none;
    border-radius: var(--r);
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    font-size: 0.92rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #0b1220 0%, #0284c7 45%, #0ea5e9 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
    box-shadow: 0 6px 20px rgba(2,132,199,0.4);
}

.sub-btn:active {
    transform: translateY(1px);
}

.sub-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.sub-btn .arrow {
    transition: transform 0.3s;
}

/* card footer */
.cfoot {
    padding: 14px 20px 18px;
    border-top: 1px solid var(--p6);
    text-align: center;
}

.cfoot-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 0.68rem;
    color: #0369a1;
}

.cfoot-row i {
    color: var(--p3);
    font-size: 0.7rem;
}

.cfoot-divider {
    width: 3px;
    height: 3px;
    background: var(--p6);
    border-radius: 50%;
}

.cfoot-brand {
    margin-top: 8px;
    font-size: 0.65rem;
    color: #bbb;
}

.cfoot-brand a {
    color: var(--p3);
    font-weight: 600;
    text-decoration: none;
}

/* secure note */
.secure-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 14px;
    font-size: 0.65rem;
    color: rgba(255,255,255,0.3);
}

.secure-note i {
    color: var(--gold2);
    font-size: 0.7rem;
}
</style>
</head>
<body>

<div class="page">

    <!-- LEFT PANEL - Only visible on desktop/tablet landscape -->
    <div class="page-left">
        <div class="left-dots"></div>
        <div class="left-orb lo1"></div>
        <div class="left-orb lo2"></div>
        <div class="left-orb lo3"></div>

        <div class="left-top">
            <div class="left-logo">
                <div class="left-logo-box">
                    <img src="images/logo1.png" alt="Credible" onerror="this.style.display='none'">
                </div>
                <span class="left-logo-name">Cred<span>ible</span></span>
            </div>

            <h1 class="left-heading">
                Smart Billing.<br><em>Every Business.</em><br>One Platform.
            </h1>
            <p class="left-sub">
                Trusted by 3,500+ Indian businesses. Pay once — own it forever.
            </p>

            <div class="feat-icons-grid">
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-file-invoice-dollar"></i></div><span>GST Billing</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-boxes"></i></div><span>Inventory</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-users-cog"></i></div><span>Payroll &amp; HR</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-gem"></i></div><span>Gold Billing</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-chart-line"></i></div><span>All Reports</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-university"></i></div><span>Banking</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-shield-alt"></i></div><span>Secure Backup</span></div>
                <div class="fi-item"><div class="fi-icon"><i class="fas fa-infinity"></i></div><span>Stocks Management</span></div>
            </div>
        </div>

        <div class="left-bottom">
            <div class="left-stats">
                <div class="ls-item"><div class="ls-num">3,500+</div><div class="ls-label">Active Businesses</div></div>
                <div class="ls-item"><div class="ls-num">₹0</div><div class="ls-label">Monthly Fee</div></div>
                <div class="ls-item"><div class="ls-num">24/7</div><div class="ls-label">Support</div></div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL - Login Form -->
    <div class="page-right">
        <div class="right-dots"></div>
        <div class="right-orb1"></div>
        <div class="right-orb2"></div>
        <canvas id="rcanvas"></canvas>

        <div class="form-wrap">
            
            <!-- Mobile Brand Section - Shows only on mobile -->
            <div class="mobile-brand">
                <div class="mobile-logo">
                    <div class="mobile-logo-icon">
                        <img src="images/logo1.png" alt="Credible" onerror="this.style.display='none'">
                    </div>
                    <div class="mobile-logo-text">Cred<span>ible</span></div>
                </div>
                <div class="mobile-heading">
                    Smart Billing.<br><em>Every Business.</em><br>One Platform.
                </div>
                <div class="mobile-sub">
                    Trusted by 3,500+ Indian businesses.
                </div>
                <div class="mobile-tagline">
                    Pay once — own it forever.
                </div>
            </div>

            <div class="form-card">

                <div class="ctop">
                    <div class="ctop-dots"></div>
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Home
                    </a>
                    <div class="ctop-inner">
                        <div class="ctop-logo">
                            <img src="images/logo1.png" alt="Credible" onerror="this.style.display='none'">
                        </div>
                        <div class="ctop-text">
                            <div class="ctop-name">Cred<span>ible</span></div>
                            <div class="ctop-badge">
                                <span class="bdot"></span>
                                Secure Login Portal
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cbody">

                    <?php if (!empty($errors)): ?>
                    <div class="err-box">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach ($errors as $e): ?>
                            <p><?php echo htmlspecialchars($e); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($sel): ?>
                    <form method="POST" action="" id="lform" autocomplete="off">
                        <input type="hidden" name="login_type" value="<?php echo htmlspecialchars($sel); ?>">

                        <div class="step-bar sb-<?php echo $sel; ?>">
                            <div class="sb-icon">
                                <i class="fas <?php echo $sel==='admin'?'fa-user-shield':'fa-id-badge'; ?>"></i>
                            </div>
                            <div class="sb-text">
                                <div class="sb-lbl">Signing in as</div>
                                <div class="sb-val"><?php echo $sel==='admin'?'Administrator — Owner / Manager':'Employee — Staff / Operator'; ?></div>
                            </div>
                            <button type="submit" class="sb-change" name="login_type" value="" formnovalidate>
                                <i class="fas fa-retweet"></i> Switch
                            </button>
                        </div>

                        <div class="fl">
                            <div class="fl-lbl"><i class="fas fa-envelope"></i> Email Address</div>
                            <div class="fl-wrap">
                                <div class="fl-pfx"><i class="fas fa-envelope"></i></div>
                                <input type="email" class="fl-input" name="email" id="email"
                                       placeholder="yourname@company.com"
                                       value="<?php echo htmlspecialchars($email); ?>"
                                       required autocomplete="email">
                            </div>
                        </div>

                        <div class="fl">
                            <div class="fl-lbl"><i class="fas fa-lock"></i> Password</div>
                            <div class="fl-wrap">
                                <div class="fl-pfx"><i class="fas fa-lock"></i></div>
                                <input type="password" class="fl-input" name="password" id="pwd"
                                       placeholder="Enter your password"
                                       required autocomplete="current-password">
                                <button type="button" class="fl-sfx" id="togPwd" tabindex="-1">
                                    <i class="fas fa-eye" id="eyeIco"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="sub-btn" id="subBtn">
                            <i class="fas <?php echo $sel==='admin'?'fa-user-shield':'fa-id-badge'; ?>"></i>
                            Sign in as <?php echo $sel==='admin'?'Administrator':'Employee'; ?>
                            <i class="fas fa-arrow-right arrow"></i>
                        </button>
                    </form>

                    <?php else: ?>
                    <div class="type-label"><i class="fas fa-circle" style="font-size:0.45rem;margin-right:4px"></i> Select your account type to continue</div>
                    <form method="POST" action="" style="margin:0">
                        <div class="type-row">
                            <button type="submit" class="tcard" name="login_type" value="admin">
                                <div class="tc-ico-wrap"><i class="fas fa-user-tie tc-ico"></i></div>
                                <div class="tc-title">Admin</div>
                                <div class="tc-sub">Owner / Manager</div>
                            </button>
                            <button type="submit" class="tcard" name="login_type" value="staff">
                                <div class="tc-ico-wrap" style="background:#0284C7;border-color:#0284C7">
                                    <i class="fas fa-users tc-ico" style="color:#fff"></i>
                                </div>
                                <div class="tc-title">Employee</div>
                                <div class="tc-sub">Staff / Operator</div>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                </div>

                <div class="cfoot">
                    <div class="cfoot-row">
                        <span><i class="fas fa-shield-alt"></i> 256-bit Encrypted</span>
                        <span class="cfoot-divider"></span>
                        <span><i class="fas fa-lock"></i> Secure Session</span>
                        <span class="cfoot-divider"></span>
                        <span><i class="fas fa-copyright"></i> All Rights Reserved</span>
                    </div>
                    <div class="cfoot-brand">
                        Powered by <a href="https://ai-digitalsolution.com/" target="_blank" rel="noopener">AI Digital Innovation</a>
                    </div>
                </div>

            </div>

            <div class="secure-note">
                <i class="fas fa-shield-alt"></i>
                <span>Your data is protected with enterprise-grade encryption</span>
            </div>
        </div>

    </div>
</div>

<script>
// Password toggle
document.getElementById('togPwd')?.addEventListener('click', function() {
    const p = document.getElementById('pwd');
    const ic = document.getElementById('eyeIco');
    if (p.type === 'password') {
        p.type = 'text';
        ic.className = 'fas fa-eye-slash';
    } else {
        p.type = 'password';
        ic.className = 'fas fa-eye';
    }
});

// Form disable on submit
document.getElementById('lform')?.addEventListener('submit', function() {
    const b = document.getElementById('subBtn');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Signing in...';
});

// Particle canvas animation
(function() {
    const c = document.getElementById('rcanvas');
    if (!c) return;
    const x = c.getContext('2d');
    let W, H, P = [];
    
    function rsz() {
        W = c.width = c.offsetWidth;
        H = c.height = c.offsetHeight;
    }
    
    function Pt() {
        this.x = Math.random() * W;
        this.y = Math.random() * H;
        this.vx = (Math.random() - 0.5) * 0.32;
        this.vy = (Math.random() - 0.5) * 0.32;
        this.r = Math.random() * 1.5 + 0.4;
        this.a = Math.random() * 0.4 + 0.08;
        this.col = Math.random() > 0.6 ? '56,189,248' : '255,255,255';
    }
    
    Pt.prototype.tick = function() {
        this.x += this.vx;
        this.y += this.vy;
        if (this.x < 0) this.x = W;
        if (this.x > W) this.x = 0;
        if (this.y < 0) this.y = H;
        if (this.y > H) this.y = 0;
    };
    
    function init() {
        rsz();
        P = [];
        for (let i = 0; i < 55; i++) P.push(new Pt());
    }
    
    function draw() {
        if (!x) return;
        x.clearRect(0, 0, W, H);
        for (let i = 0; i < P.length; i++) {
            for (let j = i + 1; j < P.length; j++) {
                const dx = P[i].x - P[j].x;
                const dy = P[i].y - P[j].y;
                const d = Math.sqrt(dx * dx + dy * dy);
                if (d < 100) {
                    x.strokeStyle = 'rgba(255,255,255,' + (0.06 * (1 - d / 100)) + ')';
                    x.lineWidth = 0.4;
                    x.beginPath();
                    x.moveTo(P[i].x, P[i].y);
                    x.lineTo(P[j].x, P[j].y);
                    x.stroke();
                }
            }
        }
        P.forEach(p => {
            p.tick();
            x.beginPath();
            x.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            x.fillStyle = 'rgba(' + p.col + ',' + p.a + ')';
            x.fill();
        });
        requestAnimationFrame(draw);
    }
    
    window.addEventListener('resize', () => { init(); });
    init();
    draw();
})();
</script>
</body>
</html>