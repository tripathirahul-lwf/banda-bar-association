<?php
/**
 * Modern Dashboard Reusable Footer Layout
 * District Bar Association, Banda
 * Established: 1937
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}
?>
            </div> <!-- / .dashboard-main-card -->
        </main> <!-- / .dashboard-main-body -->
    </div> <!-- / .dashboard-content-area -->
</div> <!-- / .dashboard-wrapper -->

<!-- Bootstrap 5 Bundle JS CDN (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Custom App JS -->
<script src="<?php echo SITE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
