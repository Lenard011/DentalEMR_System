<?php
// EmailHelper.php
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $mail;
    private $debugMode;
    
    public function __construct($debugMode = false) {
        $this->debugMode = $debugMode;
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    private function configureSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USER;
            $this->mail->Password = SMTP_PASS;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = SMTP_PORT;
            
            // Timeout settings
            $this->mail->Timeout = 30;
            
            // Debug output
            if ($this->debugMode) {
                $this->mail->SMTPDebug = 2;
            } else {
                $this->mail->SMTPDebug = 0;
            }
            
            // SSL/TLS configuration
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => !$this->debugMode,
                    'verify_peer_name' => !$this->debugMode,
                    'allow_self_signed' => $this->debugMode
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function sendMFACode($toEmail, $toName, $mfaCode) {
        try {
            // Clear all recipients
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearReplyTos();
            $this->mail->clearAllRecipients();
            
            // Recipients
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your Daily Verification Code - MHO Dental Clinic';
            
            $mailBody = $this->generateMFABody($toName, $mfaCode);
            $this->mail->Body = $mailBody;
            $this->mail->AltBody = $this->generateMFAAltBody($toName, $mfaCode);
            
            // Send email
            if ($this->mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email',
                    'error' => $this->mail->ErrorInfo
                ];
            }
            
        } catch (Exception $e) {
            error_log("Email sending error for {$toEmail}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Email sending failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateMFABody($name, $code) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verification Code</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h2 { color: #2563EB; margin: 0; }
                .content { background-color: #f8fafc; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
                .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2563EB; background: white; 
                        padding: 15px; border-radius: 8px; border: 2px dashed #dbeafe; margin: 20px auto; display: inline-block; }
                .warning { color: #dc2626; font-size: 14px; margin-top: 15px; }
                .note { margin-top: 30px; padding: 15px; background-color: #f0f9ff; border-radius: 8px; border-left: 4px solid #3b82f6; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>MHO Dental Clinic</h2>
                    <p>Daily Verification Code</p>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$name}</strong>,</p>
                    <p>Your verification code is:</p>
                    <div class='code'>{$code}</div>
                    <p class='warning'>⏰ This code will expire in 5 minutes</p>
                </div>
                
                <div class='note'>
                    <p><strong>Note:</strong> You only need to verify once daily. The verification resets at 11:59 PM.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from MHO Dental Clinic System.<br>
                    If you didn't request this code, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function generateMFAAltBody($name, $code) {
        return "Hello {$name},\n\n" .
               "Your MHO Dental Clinic verification code is: {$code}\n\n" .
               "This code will expire in 5 minutes.\n\n" .
               "Note: You only need to verify once daily (resets at 11:59 PM).\n\n" .
               "If you didn't request this code, please ignore this message.";
    }
    
    public function testConnection() {
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return true;
        } catch (Exception $e) {
            error_log("SMTP connection test failed: " . $e->getMessage());
            return false;
        }
    }
}
?>