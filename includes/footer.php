    </main>

    <script>
        // Close mobile nav when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('navLinks');
            const hamburger = document.getElementById('navHamburger');
            if (nav && hamburger && !nav.contains(e.target) && !hamburger.contains(e.target)) {
                nav.classList.remove('open');
            }
        });
    </script>
</body>
</html>
