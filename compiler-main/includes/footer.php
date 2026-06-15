</main>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div>&copy; 2025 CompilerHub. Final Year Project - Computer Science Department.</div>
            <div>Developed by AGABA OLIVIER &amp; IRADI ARINDA</div>
            <div><a href="https://github.com/Agabaofficial/compiler-visualizer-hub" target="_blank" style="color:#00ff9d;">GitHub</a></div>
        </div>
        <div class="copyright">Advanced Compiler Visualization Platform</div>
    </div>
</footer>

<script>
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            if(this.getAttribute('href') === '#') return;
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if(target) window.scrollTo({ top: target.offsetTop - 80, behavior: 'smooth' });
        });
    });
</script>
</body>
</html>