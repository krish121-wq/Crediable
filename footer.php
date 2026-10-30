<!-- Footer Section -->
<footer class="footer py-3" style="background: linear-gradient(180deg, #f8fafc, #e2e8f0);">
    <style>
        /* Footer Specific Styles */
        .footer {
            font-family: 'Roboto', sans-serif;
            overflow: hidden;
        }
        .footer a {
            color: #2d3748;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover, .footer a:active {
            color: #571F5A;
        }
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
            font-size: clamp(1.125rem, 2vw, 1.5rem);
            color: #2d3748;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 5px;
            margin-right: 0.75rem;
            transition: transform 0.3s ease, color 0.3s ease, background 0.3s ease;
        }
        .social-icons a:hover, .social-icons a:active {
            transform: scale(1.15);
            color: #fff;
            background: linear-gradient(135deg, #571F5A, #6D2F6A);
            box-shadow: 0 3px 6px rgba(87, 31, 90, 0.2);
        }
        .secure-badge, .client-badge {
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            padding: 0.625rem 0.875rem;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUpEnhanced 0.6s ease-out forwards;
        }
        .secure-badge:hover, .client-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(87, 31, 90, 0.15);
        }
        @keyframes fadeInUpEnhanced {
            0% { opacity: 0; transform: translateY(30px) scale(0.9) rotate(2deg); }
            50% { transform: translateY(10px) scale(1.05) rotate(-1deg); }
            100% { opacity: 1; transform: translateY(0) scale(1) rotate(0deg); }
        }
        /* Icons */
        .footer-icon {
            margin-right: 1rem;
            font-size: clamp(1rem, 1.5vw, 1.125rem);
            color: #571F5A;
            padding: 0.5rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 5px;
            transition: transform 0.3s ease, color 0.3s ease, background 0.3s ease;
        }
        .footer-icon:hover {
            transform: scale(1.1);
            color: #fff;
            background: linear-gradient(135deg, #571F5A, #6D2F6A);
        }
        /* Spacing and Typography */
        .footer h5 {
            font-size: clamp(1.5rem, 2.5vw, 2rem);
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #2d3748;
        }
        .footer p, .footer li {
            font-size: clamp(0.95rem, 1.5vw, 1.125rem);
            font-weight: 500;
            line-height: 1.6;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }
        .py-3 {
            padding-top: 2rem;
            padding-bottom: 2(rem;
        }
        /* Container Padding */
        .container {
            padding-left: 15px;
            padding-right: 15px;
        }
        /* Responsive Design */
        @media (max-width: 575px) {
            .footer .row {
                gap: 1rem;
            }
            .footer .row > div {
                text-align: center;
                margin-bottom: 1rem !important;
            }
            .social-icons {
                justify-content: center;
                gap: 0.5rem;
            }
            .secure-badge, .client-badge {
                max-width: 90vw;
                margin-top: 0.5rem !important;
            }
            .footer-logo {
                max-height: 45px;
            }
            .footer h5 {
                font-size: clamp(1.25rem, 2vw, 1.75rem);
            }
            .footer p, .footer li {
                font-size: clamp(0.875rem, 1.5vw, 1rem);
            }
            .social-icons a {
                font-size: clamp(1rem, 1.8vw, 1.25rem);
            }
        }
        @media (min-width: 576px) and (max-width: 767px) {
            .footer .row {
                gap: 1rem;
            }
            .social-icons {
                gap: 0.5rem;
            }
            .secure-badge, .client-badge {
                padding: 0.5rem 0.75rem;
                margin-top: 0.5rem !important;
            }
        }
        @media (min-width: 768px) {
            .footer .row {
                gap: 1.5rem;
            }
            .footer .row > div {
                margin-bottom: 1rem !important;
            }
        }
        /* Gradient for Badges */
        .client-badge {
            background: linear-gradient(135deg, #571F5A, #6D2F6A);
            color: #fff;
        }
        .secure-badge {
            background: linear-gradient(135deg, #ffffff, #e2e8f0);
            color: #2d3748;
        }
        /* Section Animation */
        .footer-section {
            animation: fadeInUpEnhanced 0.5s ease-out forwards;
        }
        .footer-section:nth-child(1) { animation-delay: 0.3s; }
        .footer-section:nth-child(2) { animation-delay: 0.5s; }
        .footer-section:nth-child(3) { animation-delay: 0.7s; }
        .footer-section:nth-child(4) { animation-delay: 0.9s; }
    </style>
    <div class="container">
        <div class="row">
            <!-- Logo and Description -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3 footer-section">
                <img src="images/logo.jpg" alt="Grow Biller Logo" class="mb-2 footer-logo" style="height: 60px; max-width: 100%;">
                <h5 class="mb-1">Grow Biller</h5>
                <p>Simplify your business with Grow Biller’s GST billing, invoicing, and financial management solutions.</p>
            </div>
            <!-- Quick Links -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3 footer-section">
                <h5 class="mb-1">Explore</h5>
                <ul class="list-unstyled">
                    <li><a href="#features" title="Our Features"><i class="fas fa-circle-chevron-right footer-icon" aria-hidden="true"></i>Our Features</a></li>
                    <li><a href="#pricing" title="Pricing Plans"><i class="fas fa-circle-chevron-right footer-icon" aria-hidden="true"></i>Pricing Plans</a></li>
                    <li><a href="#blog" title="Blog Posts"><i class="fas fa-circle-chevron-right footer-icon" aria-hidden="true"></i>Blog</a></li>
                    <li><a href="#contact" title="Contact Us"><i class="fas fa-circle-chevron-right footer-icon" aria-hidden="true"></i>Get in Touch</a></li>
                </ul>
            </div>
            <!-- Contact Info -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3 footer-section">
                <h5 class="mb-1">Contact Us</h5>
                <ul class="list-unstyled">
                    <li><a href="mailto:support@growbiller.com" title="Email Support"><i class="fas fa-envelope footer-icon" aria-hidden="true"></i>support@growbiller.com</a></li>
                    <li><a href="tel:+917003078182" title="Call Support"><i class="fas fa-phone footer-icon" aria-hidden="true"></i>+91 70030 78182</a></li>
                </ul>
                <div class="mt-2 secure-badge d-inline-block" aria-label="100% Secure Platform">
                    <i class="fas fa-shield-alt mr-1" style="font-size: 0.875rem;"></i>100% Secure Platform
                </div>
            </div>
            <!-- Social Media and Client Satisfaction -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3 footer-section">
                <h5 class="mb-1">Connect with Us</h5>
                <div class="social-icons mb-3 d-flex flex-wrap">
                    <a href="https://www.facebook.com/people/AI-Digital-Solution/61566878229134/" target="_blank" rel="noopener noreferrer" aria-label="Follow Grow Biller on Facebook" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/aidigitalsolution999/" target="_blank" rel="noopener noreferrer" aria-label="Follow Grow Biller on Instagram" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.linkedin.com/company/ai-digital-solution-company/posts/?feedView=all" target="_blank" rel="noopener noreferrer" aria-label="Follow Grow Biller on LinkedIn" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <div class="mt-2 client-badge d-inline-block" aria-label="More than 90 clients satisfied">
                    <i class="fas fa-check-circle mr-1" style="font-size: 0.875rem;"></i>90+ Clients Satisfied
                </div>
            </div>
        </div>
        <div class="text-center pt-2 border-top" style="border-color: #64748b;">
            <p style="font-size: clamp(0.875rem, 1.5vw, 1rem); line-height: 1.6; color: #2d3748;">
                © 2025 Grow Biller. All Rights Reserved.
                <br>
                Powered by <a href="https://ai-digitalsolution.com" target="_blank" rel="noopener noreferrer" title="AI Digital Solution" style="font-weight: bold; color: #2d3748;"><i class="fas fa-microchip footer-icon" aria-hidden="true"></i>AI Digital Solution</a>
            </p>
        </div>
    </div>
</footer>