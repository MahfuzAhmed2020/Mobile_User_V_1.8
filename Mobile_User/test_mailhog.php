<?php
$result = mail(
    "test@example.com",
    "MailHog Test",
    "If you see this email, MailHog is working!",
    "From: test@local.test"
);

var_dump($result);
