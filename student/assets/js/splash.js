// Digital Art School - Splash Screen JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // After 2 seconds, fade out and redirect to login
    setTimeout(function() {
        const splashContainer = document.querySelector('.splash-container');
        splashContainer.classList.add('fade-out');
        
        // Wait for animation to complete before redirecting
        setTimeout(function() {
            // Always redirect to login - let login.php handle session checking
            window.location.href = 'pages/login.php';
        }, 800);
    }, 2000);
});
