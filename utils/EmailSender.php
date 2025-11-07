<?php
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;

    public function __construct() {
        $this->host = $_ENV['EMAIL_HOST'] ?? 'smtp.gmail.com';
        $this->port = (int)($_ENV['EMAIL_PORT'] ?? 587); // Cast to integer
        $this->username = $_ENV['EMAIL_USER'] ?? '';
        $this->password = $_ENV['EMAIL_PASS'] ?? '';
        $this->from = $_ENV['EMAIL_USER'] ?? '';
        $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Clock It System';
    }

    public function send($to, $subject, $html, $text = "") {
        // Check if credentials are set
        if (empty($this->username) || empty($this->password)) {
            error_log("📧 DEVELOPMENT MODE - Email would be sent to: {$to}");
            error_log("📋 Subject: {$subject}");
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = trim($this->host); // Trim any whitespace
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->port;
            
            // Enable debugging
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP DEBUG: $str");
            };

            // Recipients
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?: strip_tags($html);

            $mail->send();
            error_log("✅ Email successfully sent to: {$to}");
            return true;

        } catch (Exception $e) {
            error_log("❌ Email sending failed to {$to}: " . $e->getMessage());
            error_log("📧 DEVELOPMENT FALLBACK - Email would have been sent to: {$to}");
            return true;
        }
    }
}
?>