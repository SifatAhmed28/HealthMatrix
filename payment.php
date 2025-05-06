<?php
session_start();
require 'config.php';

function encryptData($data) {
    $key = 'your-secret-key-123';
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $required = ['appointment_id', 'amount', 'payment_method', 'mobile', 'transaction_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) throw new Exception("Missing required field: $field");
        }

        if (!preg_match('/^\+8801[3-9]\d{8}$/', $_POST['mobile'])) {
            throw new Exception("Invalid Bangladeshi mobile number format");
        }

        if (!preg_match('/^[A-Z0-9]{8,12}$/', $_POST['transaction_id'])) {
            throw new Exception("Invalid transaction ID format");
        }

        $paymentVerified = ($_POST['transaction_id'] === '12345678');
        if (!$paymentVerified) {
            throw new Exception("Payment verification failed");
        }

        $stmt = $pdo->prepare("INSERT INTO Payments 
            (appointment_id, amount, payment_method, transaction_id, status, payment_date)
            VALUES (?, ?, ?, ?, 'Completed', NOW())");

        $encryptedTxnId = encryptData($_POST['transaction_id']);
        $stmt->execute([
            $_POST['appointment_id'],
            $_POST['amount'],
            $_POST['payment_method'],
            $encryptedTxnId
        ]);

        $success = "✅ Payment completed successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Payment</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #e0f7ff;
            background-image: url('logo.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 30%;
            opacity: 0.95;
        }
        .payment-container {
            background: rgba(255, 255, 255, 0.95);
            max-width: 480px;
            margin: 4rem auto;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            position: relative;
            z-index: 2;
        }
        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            background: linear-gradient(90deg, #5ac8fa, #0096c7);
            color: white;
            padding: 1rem;
            border-radius: 8px 8px 0 0;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            font-weight: 600;
            display: block;
            margin-bottom: 0.5rem;
        }
        input, select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #b5dff6;
            border-radius: 6px;
            background: #f9fcff;
            font-size: 1rem;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #5ac8fa;
            box-shadow: 0 0 3px rgba(90, 200, 250, 0.5);
        }
        .error {
            color: #d32f2f;
            background: #fce4e4;
            padding: 0.75rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 0.75rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1rem;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background-color: #00b4d8;
            color: white;
            font-size: 1.1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0096c7;
        }
        .back-link {
            display: block;
            margin-top: 1.2rem;
            text-align: center;
            color: #0077b6;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            body {
                background-size: 90%;
            }
            .payment-container {
                margin: 2rem 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h2>Mobile Payment</h2>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php elseif (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" id="paymentForm">
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? '') ?>">

            <div class="form-group">
                <label for="amount">Amount (BDT)</label>
                <input type="number" name="amount" step="0.01" required
                       value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" id="methodSelect" required>
                    <option value="">Choose Method</option>
                    <option value="bKash" <?= ($_POST['payment_method'] ?? '') === 'bKash' ? 'selected' : '' ?>>bKash</option>
                    <option value="Nagad" <?= ($_POST['payment_method'] ?? '') === 'Nagad' ? 'selected' : '' ?>>Nagad</option>
                </select>
            </div>

            <div id="mobileFields" style="<?= ($_POST['payment_method'] ?? '') ? '' : 'display:none;' ?>">
                <div class="form-group">
                    <label for="mobile">Mobile Number (+8801XXXXXXXXX)</label>
                    <input type="tel" name="mobile" pattern="\+8801[3-9]\d{8}"
                           value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="transaction_id">Transaction ID</label>
                    <input type="text" name="transaction_id" 
                           pattern="[A-Z0-9]{8,12}" title="8-12 characters" required
                           value="<?= htmlspecialchars($_POST['transaction_id'] ?? '') ?>">
                </div>
            </div>

            <button type="submit">💳 Complete Payment</button>
        </form>

        <a href="appointment_details.php" class="back-link">← Go Back to Appointment Details</a>
    </div>

    <script>
        const methodSelect = document.getElementById('methodSelect');
        const mobileFields = document.getElementById('mobileFields');

        methodSelect.addEventListener('change', () => {
            mobileFields.style.display = methodSelect.value ? 'block' : 'none';
        });

        // Optional: Real-time validation feedback
        const form = document.getElementById('paymentForm');
        form.addEventListener('submit', function(e) {
            const mobile = form.mobile.value;
            const txn = form.transaction_id.value;
            const mobileRegex = /^\+8801[3-9]\d{8}$/;
            const txnRegex = /^[A-Z0-9]{8,12}$/;

            if (!mobileRegex.test(mobile)) {
                alert("📵 Invalid mobile number format.");
                e.preventDefault();
            } else if (!txnRegex.test(txn)) {
                alert("❌ Invalid transaction ID format.");
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
