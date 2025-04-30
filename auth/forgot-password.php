<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT user_id, email, first_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate password reset token (in a real system, you would store this in the database with an expiry)
                $token = bin2hex(random_bytes(32));
                $resetLink = "https://{$_SERVER['HTTP_HOST']}/auth/reset-password.php?token=$token";
                
                // In a real system, you would:
                // 1. Store the token in the database with an expiry timestamp
                // 2. Send an email with the reset link
                // 3. Show a message that instructions have been sent
                
                $message = "If an account exists with this email, we've sent password reset instructions.";
            } else {
                // Don't reveal whether the email exists in the system
                $message = "If an account exists with this email, we've sent password reset instructions.";
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $message = 'A system error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Reset Your Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Reset Instructions</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="./login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>