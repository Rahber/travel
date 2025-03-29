<?php
require_once 'common.php';
requireLogin();

require_once 'header.php';
?>

<div class="container">
    <section class="welcome-section">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?>!</h2>
        <p>Start planning your next adventure with TravelApp. Discover new destinations, create itineraries, and make unforgettable memories.</p>
    </section>
</div>

<?php require_once 'footer.php'; ?>
