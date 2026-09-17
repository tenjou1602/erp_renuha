<?php
/**
 * Footer include - Common footer for all pages
 */
?>
        </div>
    </div>
    
    <script src="<?php echo APP_URL; ?>assets/js/script.js"></script>
    <script>
        // Add menu toggle function if not already defined
        if (typeof toggleSidebar !== 'function') {
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('open');
                }
            }
        }
        
        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !toggle?.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>