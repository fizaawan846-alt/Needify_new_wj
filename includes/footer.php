    </main>
    <!-- Main Content Area Ends -->

    <!-- SITE FOOTER -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Col 1: About & Tagline -->
                <div class="footer-about">
                    <a href="index.php" class="brand-logo" aria-label="Needify Home">
                        <span class="brand-icon">
                            <i class="fa-solid fa-heart"></i>
                        </span>
                        <span>Need<span class="dot">ify.</span></span>
                    </a>
                    <p style="font-weight: 600; color: var(--primary); margin-top: 6px; margin-bottom: 10px;">
                        "A Little More Joy in Every Need"
                    </p>
                    <p>
                        Needify connects students and community members across Sargodha to share needs, fulfill requests, and shop everyday essentials with ease.
                    </p>
                </div>

                <!-- Col 2: Quick Links -->
                <div>
                    <h4 class="footer-title">Explore</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Shop Products</a></li>
                        <li><a href="needs.php">Browse Needs</a></li>
                        <li><a href="post-need.php">Post a Need</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Col 3: Account & Support -->
                <div>
                    <h4 class="footer-title">Account</h4>
                    <ul class="footer-links">
                        <?php if (is_logged_in()): ?>
                            <li><a href="dashboard.php">My Dashboard</a></li>
                            <li><a href="my-orders.php">My Orders</a></li>
                            <li><a href="my-needs.php">My Needs</a></li>
                            <li><a href="cart.php">Shopping Cart</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Sign In</a></li>
                            <li><a href="signup.php">Create Account</a></li>
                            <li><a href="cart.php">Shopping Cart</a></li>
                            <li><a href="admin-login.php">Admin Portal</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Col 4: University Location & Contact -->
                <div>
                    <h4 class="footer-title">Get in Touch</h4>
                    <div class="footer-contact-info">
                        <div class="contact-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>University of Sargodha, Sargodha, Punjab, Pakistan</span>
                        </div>
                        <div class="contact-item">
                            <i class="fa-brands fa-whatsapp"></i>
                            <a href="https://wa.me/923350093455" target="_blank" rel="noopener noreferrer" style="color: var(--text-secondary);">
                                +92 335 0093455
                            </a>
                        </div>
                        <div class="contact-item">
                            <i class="fa-regular fa-envelope"></i>
                            <span>support@needify.local</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom Strip -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Needify. All rights reserved. Built with love in Sargodha.</p>
                <p>Cash on Delivery &bull; Verified Campus Needs &bull; Community Joy</p>
            </div>
        </div>
    </footer>

    <!-- FLOATING WHATSAPP BUTTON (Section 15) -->
    <a href="https://wa.me/923350093455?text=Hello%20Needify!%20I%20have%20an%20inquiry."
       class="floating-whatsapp"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="Chat with Needify on WhatsApp (+92 335 0093455)"
       title="Chat with us on WhatsApp">
        <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16.01 3C8.84 3 3 8.82 3 15.98c0 2.29.6 4.52 1.75 6.47L3 29l6.72-1.72a13 13 0 0 0 6.28 1.6h.01c7.16 0 13-5.82 13-12.98A12.9 12.9 0 0 0 16.01 3zm0 23.68h-.01c-1.91 0-3.79-.51-5.43-1.49l-.39-.23-3.99 1.02 1.06-3.89-.25-.4a10.66 10.66 0 0 1-1.64-5.71c0-5.89 4.8-10.68 10.7-10.68 2.85 0 5.53 1.11 7.55 3.13a10.6 10.6 0 0 1 3.13 7.54c0 5.89-4.8 10.69-10.73 10.69zm5.88-8.01c-.32-.16-1.9-.94-2.2-1.04-.3-.11-.51-.16-.73.16-.22.32-.84 1.04-1.03 1.26-.19.22-.38.24-.7.08-.32-.16-1.36-.5-2.59-1.59-.96-.85-1.61-1.9-1.8-2.22-.19-.32-.02-.49.14-.65.14-.14.32-.38.49-.57.16-.19.22-.32.32-.54.11-.22.06-.41-.03-.57-.08-.16-.73-1.75-1-2.39-.26-.63-.53-.54-.73-.55h-.62c-.22 0-.57.08-.86.41-.3.32-1.13 1.1-1.13 2.68s1.16 3.11 1.32 3.32c.16.22 2.28 3.48 5.52 4.88.77.33 1.37.53 1.84.68.77.24 1.47.21 2.02.13.62-.09 1.9-.78 2.17-1.53.27-.75.27-1.39.19-1.53-.08-.14-.29-.22-.61-.38z"/>
        </svg>
        <span>WhatsApp</span>
    </a>

    <!-- FLOATING SCROLL CONTROLS (Top & Bottom) -->
    <div class="scroll-controls" role="group" aria-label="Page scroll controls">
        <button type="button" class="scroll-btn" id="pageScrollUp" title="Scroll to top" aria-label="Scroll to top">
            <i class="fa-solid fa-arrow-up"></i>
        </button>
        <button type="button" class="scroll-btn" id="pageScrollDown" title="Scroll to bottom" aria-label="Scroll to bottom">
            <i class="fa-solid fa-arrow-down"></i>
        </button>
    </div>

    <!-- NEEDIFY AI CHATBOT WIDGET (Single instance per page) -->
    <?php
    $aiWidgetPath = __DIR__ . '/../ai-chat-widget.php';
    if (file_exists($aiWidgetPath)) {
        include $aiWidgetPath;
    }
    ?>

    <!-- Needify Main Client JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
