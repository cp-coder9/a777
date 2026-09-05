<?php

declare(strict_types=1);

// Synthetic renderer fixture only; no application data or database access.
require dirname(__DIR__,2).'/vendor/autoload.php';

echo \Architex\Platform\CPD\CPDCertificatePdf::render([
    'Course: '.str_repeat('Long professional development course title ',6),
    'Learner: Zoë Šimek – Αθήνα '.str_repeat('Example learner ',15),
    'Professional body: SACPLAN',
    'Registration: '.str_repeat('A',180),
    'Category: '.str_repeat('Long category ',13),
    'Credits / units: 1.5',
    'Accreditation: '.str_repeat('B',180),
    'Provider: '.str_repeat('Example provider ',14),
    'Assessment status: passed',
    'Verification code: '.str_repeat('A',32),
    'Issued: 2026-09-05',
    'Verification status: valid',
], 'https://example.test/api/v1/cpd/certificates/verify/'.str_repeat('A',32));
