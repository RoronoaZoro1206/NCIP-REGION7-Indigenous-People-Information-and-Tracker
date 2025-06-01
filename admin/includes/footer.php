</div><!-- End of main content area -->
    </div><!-- End of container-fluid row -->
    
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© <?php echo date('Y'); ?> NCIP Admin Dashboard. All rights reserved.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Common JavaScript functions
        $(document).ready(function() {
            // Enable tooltips everywhere
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Enable popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });
            
            // Toggle sidebar on mobile
            $('#sidebarToggle').on('click', function() {
                $('.sidebar').toggleClass('show-mobile');
            });
            
            // Auto-dismiss alerts after 5 seconds
            $('.alert').each(function() {
                var alert = $(this);
                setTimeout(function() {
                    var closeBtn = alert.find('.btn-close');
                    if (closeBtn.length) {
                        closeBtn.click();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>