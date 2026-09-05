<?php

declare(strict_types=1);

return [
    'password_init' => [
        'subject' => 'Init Password Notification',
        'line1' => 'You are receiving this email because we received a password init request for your account.',
        'action' => 'Init Password',
        'line2' => 'This password init link will expire in :count minutes.',
        'line3' => 'If you did not request a password init, no further action is required.',
    ],
    'password_reset' => [
        'subject' => 'Reset Password Notification',
        'line1' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset Password',
        'line2' => 'This password reset link will expire in :count minutes.',
        'line3' => 'If you did not request a password reset, no further action is required.',
    ],
];
