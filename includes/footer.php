<!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fab fa-linkedin me-2"></i><?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-light">
                        Automate your LinkedIn success with AI-powered content generation and smart scheduling.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light"><i class="fab fa-linkedin fa-2x"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-twitter fa-2x"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-facebook fa-2x"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram fa-2x"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-3">
                    <h6 class="fw-bold mb-3">Product</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/#features" class="text-light text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/#pricing" class="text-light text-decoration-none">Pricing</a></li>
                        <?php if (isCustomerLoggedIn()): ?>
                            <li class="mb-2"><a href="dashboard.php" class="text-light text-decoration-none">Dashboard</a></li>
                            <li class="mb-2"><a href="create-automation.php" class="text-light text-decoration-none">Automations</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-3">
                    <h6 class="fw-bold mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Blog</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Careers</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-3">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Documentation</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Community</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Contact Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-3">
                    <h6 class="fw-bold mb-3">Legal</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Cookie Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">GDPR</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 border-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-light mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-light mb-0">
                        Available in: 
                        <?php 
                        $currentCountry = getCustomerCountry();
                        foreach (SUPPORTED_COUNTRIES as $country) {
                            $flag = $country === 'in' ? '🇮🇳' : '🇺🇸';
                            $name = $country === 'in' ? 'India' : 'USA';
                            $active = $country === $currentCountry ? 'fw-bold' : '';
                            echo "<span class='$active me-2'>$flag $name</span>";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Common JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Form validation helpers
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validatePassword(password) {
            return password.length >= 8;
        }

        // Loading spinner for forms
        function showLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            button.disabled = true;
            
            // Restore after 10 seconds as fallback
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 10000);
        }
    </script>
</body>
</html>