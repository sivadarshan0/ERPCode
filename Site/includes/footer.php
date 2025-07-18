        </main>
    </div>
</div>

<!-- Footer -->
<!-- File: includes/footer.php -->
<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <span class="text-muted">&copy; <?php echo date('Y'); ?> My ERP System. All rights reserved.</span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="/privacy.php" class="text-muted">Privacy Policy</a></li>
                    <li class="list-inline-item"><a href="/terms.php" class="text-muted">Terms of Service</a></li>
                    <li class="list-inline-item"><a href="/contact.php" class="text-muted">Contact</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom JavaScript with cache busting -->
<?php
    $appJsPath = __DIR__ . '/../assets/js/app.js';
    $appJsVersion = file_exists($appJsPath) ? filemtime($appJsPath) : time();
?>
<script src="/assets/js/app.js?v=<?= $appJsVersion ?>"></script>

<?php if (isset($custom_js)): ?>
    <?php
        $customJsPath = __DIR__ . '/../assets/js/' . basename($custom_js) . '.js';
        $customJsVersion = file_exists($customJsPath) ? filemtime($customJsPath) : time();
    ?>
    <script src="/assets/js/<?= htmlspecialchars($custom_js) ?>.js?v=<?= $customJsVersion ?>"></script>
<?php endif; ?>

<!-- Dark/Light Mode Toggle -->
<script>
    (function () {
        const storedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        const setTheme = (theme) => {
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('theme', theme);
        };

        if (storedTheme) {
            setTheme(storedTheme);
        } else {
            setTheme(prefersDark ? 'dark' : 'light');
        }
    })();
</script>

</body>
</html>
