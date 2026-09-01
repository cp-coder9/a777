<?php

declare(strict_types=1);

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/terms'), PHP_URL_PATH) ?: '/terms';
$sharedBoundary = 'Architex may assist, prepare, check, route and recommend. Humans must verify, approve, sign, submit, certify, purchase and release funds.';
$pages = [
    '/terms' => [
        'Terms of Service',
        'Terms governing access to the Architex AI-assisted operating system and marketplace.',
        [
            ['Nature of the platform', 'Architex is a technology platform, not an architect, engineer, quantity surveyor, contractor, municipality, legal practitioner, bank, professional council or guarantor of project outcomes. Users remain responsible for decisions, appointments, submissions, contracts, construction work, payments and statutory duties.'],
            ['AI and human approval', 'AI can make mistakes. Outputs may be incomplete, inaccurate, outdated or unsuitable. Professional appointments, technical briefs, contracts, compliance declarations, municipal submission packs, drawing issues, quantities, orders, claims, invoices, payment releases, certificates and final professional documents require authorised human review.'],
            ['Accounts, roles and verification', 'Users must provide accurate and current information. Verification records a point-in-time review and does not guarantee quality, suitability, availability or future compliance. Role permissions and project membership govern access.'],
            ['Independent services', 'Professionals, contractors, subcontractors, suppliers and freelancers provide services independently and retain their own legal, contractual, professional, safety, insurance and statutory responsibilities.'],
            ['Third-party services', 'Payment, escrow, signing, verification, municipal, CPD, supplier, storage, notification, AI and remote-session services may have separate terms, fees, availability and privacy rules.'],
            ['No guaranteed outcome', 'Architex does not guarantee municipal or statutory approval, project completion, professional or contractor performance, supplier availability, budget or timeline accuracy, CPD recognition, dispute outcomes, payment release or error-free AI output.'],
            ['South African law', 'These draft terms are intended for South African use and must be reviewed for the Consumer Protection Act, Electronic Communications and Transactions Act, POPIA and applicable built-environment duties before publication.'],
        ],
    ],
    '/privacy' => [
        'Privacy Policy',
        'How Architex proposes to process personal and project information under POPIA.',
        [
            ['Responsible-party context', 'Depending on the workflow, Architex may act as responsible party, operator, or an independent or joint responsible party. The final role allocation, operator terms and Information Officer details remain subject to legal review.'],
            ['Information collected', 'Account, profile, verification, project, document, AI interaction, marketplace, communication, payment, CPD, resource-session, device, security and audit data may be processed when required for the selected workflow.'],
            ['Purpose and lawful basis', 'Information may be processed by consent, contract, legal obligation or legitimate interest to operate accounts, projects, verification, AI tools, payments, disputes, CPD, security, notifications and audit controls.'],
            ['AI processing', 'Prompts, project context, files, drawings and corrections may be sent to configured AI providers to provide requested assistance. Do not upload information you are not authorised to share, and verify every AI output before reliance.'],
            ['Sharing and transfers', 'Relevant data may be shared with authorised project participants and configured service providers. Cross-border processing requires appropriate safeguards under POPIA. Private project information is not a public knowledge source.'],
            ['Security, retention and rights', 'Architex uses access controls, tenant scoping, audit trails and secure storage controls. Retention varies by legal, professional, tax, audit, payment, project and dispute obligations. Data subjects may request access or correction and may exercise other rights where applicable.'],
            ['Sign-up notice', 'By creating an account, you confirm that information is accurate and lawfully submitted, and acknowledge that relevant service providers may process it for the selected workflows.'],
        ],
    ],
    '/disclaimer' => [
        'Platform Disclaimer',
        'The operational boundary for Architex tools, automation and project information.',
        [
            ['Assistance only', 'Architex provides workflow tools, AI assistance, marketplace access, coordination, payment facilitation, compliance support, resource sharing and knowledge services. It does not replace competent professionals or authorised decision-makers.'],
            ['No certification or approval', 'A check, score, status or generated report is not municipal approval, statutory approval, professional certification, construction certification or legal advice.'],
            ['Human responsibility', $sharedBoundary],
        ],
    ],
    '/ai-disclaimer' => [
        'AI Disclaimer',
        'AI-assisted output — human verification required.',
        [
            ['AI can make mistakes', 'AI output may contain errors, omissions, outdated sources, unsupported assumptions or misunderstood project context.'],
            ['Before relying', 'Review the cited source, source version, project inputs, permissions and limitations before using, submitting, signing, issuing, purchasing, paying, certifying or relying on an output.'],
            ['Authority boundary', $sharedBoundary],
        ],
    ],
    '/payment-escrow-terms' => [
        'Payment & Escrow Terms',
        'Draft controls for payment requests, certificates, milestones and third-party fund movement.',
        [
            ['Provider required', 'Any real fund movement requires a configured and authorised third-party payment or escrow provider. Architex is not a bank and does not represent that funds have moved without provider confirmation.'],
            ['Release conditions', 'Release may require client, professional, quantity surveyor, principal agent, contractor or administrator approval, evidence, certification, a contractual milestone and dispute checks. Funding is not proof that work is complete or approved.'],
            ['Review before payment', 'Confirm payee, invoice, amount, scope, milestone, evidence, tax treatment, retention and approval state. Provider terms govern failures, refunds, chargebacks and reversals.'],
        ],
    ],
    '/cpd-terms' => [
        'CPD Terms',
        'Draft rules for learning, assessment, accreditation and certificate evidence.',
        [
            ['Accreditation truth', 'Only content supported by current accreditation evidence may be presented as accredited. Display the provider, accreditation reference, credit value and validity dates.'],
            ['Assessment and issue', 'Completion, server-owned scoring and any required manual review must pass before certificate issue. AI assistance cannot award credits or approve accreditation.'],
            ['Professional-body sync', 'Submission or sync requires user authorisation and a configured provider. Architex does not guarantee statutory acceptance.'],
        ],
    ],
    '/resource-sharing-terms' => [
        'Resource Sharing Terms',
        'Draft rules for listings, bookings, software seats, equipment and remote sessions.',
        [
            ['Owner duties', 'Resource owners must describe availability and pricing accurately, maintain the resource, and have the legal and licence rights to list or share it.'],
            ['User duties', 'Users must protect credentials, use authorised files, comply with licence restrictions, end sessions on time and pay approved charges.'],
            ['Remote desktop boundary', 'Remote access requires a configured host agent, broker, viewer and allowlist. Sessions may be logged for security, billing and misuse prevention; the current unconfigured state is integration required.'],
        ],
    ],
    '/dispute-resolution-rules' => [
        'Dispute Resolution Rules',
        'Draft evidence and response workflow for project, payment, resource and platform disputes.',
        [
            ['Evidence workflow', 'Parties may record a notice, link project evidence, create a timeline, respond, negotiate, refer and close a dispute through versioned human-governed states.'],
            ['No tribunal claim', 'Architex is not a court, arbitrator or legal tribunal unless separately appointed under an approved agreement. Platform workflows do not remove legal or contractual rights.'],
            ['Human outcome', 'No AI agent determines entitlement, liability or the final dispute outcome. Reasons, authority and evidence must be recorded for every material transition.'],
        ],
    ],
];

$page = $pages[$path] ?? $pages['/terms'];
$links = array_map(static fn (string $route): string => sprintf('<a%s href="%s">%s</a>', $route === $path ? ' aria-current="page"' : '', htmlspecialchars($route, ENT_QUOTES), htmlspecialchars($pages[$route][0], ENT_QUOTES)), array_keys($pages));
?>
<!doctype html>
<html lang="en-ZA">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($page[0], ENT_QUOTES) ?> — Architex</title>
  <style>
    :root{color-scheme:light;--ink:#102033;--muted:#62707d;--teal:#0a4540;--line:#dce5e6;--paper:#f4f7f7;--warning:#fff5d9}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.65 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}header{background:var(--teal);color:white;padding:26px clamp(22px,6vw,80px)}header a{color:white;text-decoration:none;font-weight:800;letter-spacing:.08em}.shell{display:grid;grid-template-columns:minmax(220px,290px) minmax(0,820px);gap:32px;max-width:1200px;margin:auto;padding:36px 22px 80px}nav{position:sticky;top:24px;align-self:start;background:white;border:1px solid var(--line);border-radius:18px;padding:14px}nav a{display:block;color:var(--muted);padding:9px 11px;border-radius:10px;text-decoration:none;font-size:14px}nav a[aria-current=page]{background:#e6f1ef;color:var(--teal);font-weight:750}main{background:white;border:1px solid var(--line);border-radius:22px;padding:clamp(24px,5vw,58px);box-shadow:0 20px 60px rgba(16,32,51,.06)}.kicker{color:#167e79;font-size:12px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}h1{font-size:clamp(36px,6vw,62px);line-height:1.02;letter-spacing:-.045em;margin:12px 0 16px}h2{font-size:21px;margin:38px 0 8px}p{color:var(--muted)}.draft{background:var(--warning);border:1px solid #e7cc80;border-radius:14px;padding:16px;margin:24px 0;color:#5f4b16}.boundary{border-left:4px solid #19b7b0;padding:12px 18px;margin:30px 0;background:#eff9f7}.meta{font-size:13px;color:var(--muted);border-top:1px solid var(--line);padding-top:20px;margin-top:44px}@media(max-width:760px){.shell{display:block}.shell nav{position:static;margin-bottom:20px}main{padding:26px 21px}}
  </style>
</head>
<body>
<header><a href="/">ARCHITEX</a></header>
<div class="shell">
  <nav aria-label="Legal centre"><?= implode('', $links) ?></nav>
  <main>
    <span class="kicker">Legal centre · Version 0.1-development</span>
    <h1><?= htmlspecialchars($page[0], ENT_QUOTES) ?></h1>
    <p><?= htmlspecialchars($page[1], ENT_QUOTES) ?></p>
    <div class="draft"><strong>Draft pending South African attorney review.</strong><br>This implementation is for development inspection and acceptance-flow testing. It is not approved legal advice and must not be represented as production-approved legal text.</div>
    <div class="boundary"><strong>Human authority</strong><br><?= htmlspecialchars($sharedBoundary, ENT_QUOTES) ?></div>
    <?php foreach ($page[2] as [$heading, $content]): ?>
      <section><h2><?= htmlspecialchars($heading, ENT_QUOTES) ?></h2><p><?= htmlspecialchars($content, ENT_QUOTES) ?></p></section>
    <?php endforeach; ?>
    <p class="meta">Jurisdiction focus: South Africa · Source: Architex Legal & Compliance Notices Pack · Development version: 0.1-development · Operator, effective date, Information Officer and contact details require approval before publication.</p>
  </main>
</div>
</body>
</html>
