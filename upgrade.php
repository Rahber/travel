<?php
require_once 'common.php';
requireLogin();

includeHeader();

$user_id = $_SESSION['user_id'];
$csrf_token = initCSRFToken();
$conn = getDbConnectionMysqli();


// Get current user's membership tier
$stmt = $conn->prepare("SELECT tier FROM users WHERE id = ?");
if (!$stmt) {
    throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$currentTier = $result->fetch_column();
$stmt->close();

// Define membership tiers and prices
$tiers = [
    'basic' => [
        'name' => 'Basic',
        'price' => 0,
        'features' => ['Limited access', 'Basic features']
    ],
    'premium' => [
        'name' => 'Premium', 
        'price' => 4.99,
        'features' => ['Full access', 'Premium features', 'Priority support']
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 9.99,
        'features' => ['Full access', 'Premium features', 'Priority support', 'Advanced analytics']
    ]
];

// Handle successful payment and tier upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stripeToken'])) {
    try {
        validateCSRFToken($_POST['csrf_token'] ?? '');

        // Validate tier exists
        if (!isset($_POST['tier']) || !array_key_exists($_POST['tier'], $tiers)) {
            throw new Exception('Invalid tier selected');
        }

        // Validate amount matches tier price
        $selectedTier = $tiers[$_POST['tier']];
        if (!isset($_POST['amount']) || floatval($_POST['amount']) !== $selectedTier['price']) {
            throw new Exception('Invalid amount');
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/stripe/init.php';
        \Stripe\Stripe::setApiKey(PAYMENT_API_SECRET);

        mysqli_begin_transaction($conn);

        // Create Stripe charge
        $charge = \Stripe\Charge::create([
            'amount' => intval($selectedTier['price'] * 100),
            'currency' => 'usd',
            'description' => 'Membership Upgrade to ' . $selectedTier['name'],
            'source' => $_POST['stripeToken'],
        ]);

        // Update user's membership tier
        $stmt = $conn->prepare("UPDATE users SET tier = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $tier = $_POST['tier'];
        $stmt->bind_param("si", $tier, $user_id);
        $stmt->execute();
        $stmt->close();

        // Log the upgrade
        logUserActivity($user_id, 'upgrade', "Upgraded to " . htmlspecialchars($_POST['tier']) . " membership");

        mysqli_commit($conn);
        $success = "Successfully upgraded to " . htmlspecialchars($selectedTier['name']) . " membership!";

    } catch (\Stripe\Exception\CardException $e) {
        if (mysqli_get_autocommit($conn) === false) {
            mysqli_rollback($conn);
        }
        $error = $e->getMessage();
        error_log("Stripe payment error: " . $e->getMessage());
    } catch (Exception $e) {
        if (mysqli_get_autocommit($conn) === false) {
            mysqli_rollback($conn);
        }
        $error = "An error occurred during upgrade";
        error_log("Upgrade error: " . $e->getMessage());
    }
}
?>

<div class="container mt-4">
    <h1>Upgrade Membership</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row mt-4">
        <?php foreach ($tiers as $tierId => $tier): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($tier['name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <h4 class="text-center">$<?php echo number_format($tier['price'], 2); ?>/month</h4>
                        <ul class="list-unstyled">
                            <?php foreach ($tier['features'] as $feature): ?>
                                <li><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if ($currentTier !== $tierId): ?>
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="tier" value="<?php echo htmlspecialchars($tierId); ?>">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($tier['price']); ?>">
                                <script
                                    src="https://checkout.stripe.com/checkout.js" class="stripe-button"
                                    data-key="<?php echo PAYMENT_API_KEY; ?>"
                                    data-amount="<?php echo intval($tier['price'] * 100); ?>"
                                    data-name="Membership Upgrade"
                                    data-description="Upgrade to <?php echo htmlspecialchars($tier['name']); ?>"
                                    data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
                                    data-locale="auto"
                                    data-currency="usd">
                                </script>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-success w-100" disabled>Current Plan</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php includeFooter(); ?>
