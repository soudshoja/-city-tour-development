<!-- start footer section -->
<!-- desktok footer section -->
<div class="p-5 mt-5 text-center dark:text-[#f3f4f6] bg-white dark:bg-gray-900 rounded-md shadow-md">
    © <span id="footer-year">2024 - 2025</span> City Tour. <span id="footer-version">Version 1.0</span>
</div>
<!-- desktok footer section end-->
<!-- Mobile footer section -->
<div class="CityDisplaayNoneDesk">

    <div class="mt-auto p-6 pt-0 text-center dark:text-white-dark ltr:sm:text-left rtl:sm:text-right">
        <!-- Mobile navigation bar -->




    </div>
</div>
<!-- Mobile footer section ends -->
<!-- end footer section -->

<!-- Scripts -->

<script>
    fetch("<?php echo e(route('version.getCurrent')); ?>")
        .then(response => response.json())
        .then(data => {
            if (data && data.value) {
                // Assuming `value` holds the version, update the version dynamically
                const versionElement = document.getElementById('footer-version');
                if (versionElement) {
                    versionElement.textContent = `Version ${data.value}`;
                }
            }
        })
        .catch(error => console.error("Error fetching version:", error));

    // Check localStorage for the dark mode setting before the page is fully loaded
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- Scripts -->

<script src="https://code.jquery.com/jquery-3.7.1.slim.js"
    integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>
<!-- new js added here -->
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/footer.blade.php ENDPATH**/ ?>