<?php
// Central SMTP config for PHPMailer
// Fill ONE of the following configs and keep the unused one commented.

// === Option A: Gmail (requires App Password; 2FA enabled on the account) ===
// Generate an App Password at: Google Account → Security → 2-Step Verification → App passwords
$MAILER_CONFIG = [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,          // 587 (TLS) or 465 (SSL)
    'encryption' => 'tls',        // 'tls' or 'ssl'
    'username'   => 'yourgmail@gmail.com',
    'password'   => 'your-app-password-here', // NOT your normal Gmail password
    'from_email' => 'yourgmail@gmail.com',
    'from_name'  => 'MediQ Support',
    'reply_to'   => 'yourgmail@gmail.com',
];

// === Option B: Brevo (formerly Sendinblue) ===
// Create a free account → SMTP & API → SMTP
/*
$MAILER_CONFIG = [
    'host'       => 'smtp-relay.brevo.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'your-brevo-username',
    'password'   => 'your-brevo-smtp-key',
    'from_email' => 'no-reply@yourdomain.com',
    'from_name'  => 'MediQ Support',
    'reply_to'   => 'support@yourdomain.com',
];
*/
