<?php
/**
 * about.php — project information and privacy notice.
 */
require_once __DIR__ . '/includes/init.php';

$pageTitle     = 'About';
$currentPublic = 'about';
require __DIR__ . '/includes/public_header.php';
?>

<section class="public-hero" style="padding: 2.5rem 0;">
    <div class="public-hero-inner" style="padding-top:0;padding-bottom:0;">
        <span class="public-hero-eyebrow">About this system</span>
        <h1 style="font-size: clamp(1.6rem, 3vw, 2.2rem);">About TrafStat</h1>
        <p class="public-hero-lead" style="font-size:1rem;">
            The Pagadian City traffic violation records and statistical
            analytics system.
        </p>
    </div>
</section>

<section class="public-section">
    <div class="row g-4">
        <div class="col-12 col-lg-8">

            <div data-reveal>
                <h2 style="font-size:1.4rem;">Purpose</h2>
                <p>
                    <?= e(APP_NAME) ?> is a web-based information system for the
                    authorized traffic management office of Pagadian City. It
                    supports the office in recording, organizing, and analyzing
                    traffic violation apprehension records for operational and
                    statistical purposes.
                </p>
                <p>
                    The system has two distinct sides. The <strong>administrative
                    application</strong> is available only to individually
                    authenticated personnel and processes personal information in
                    accordance with the office's privacy governance. The
                    <strong>public website</strong> — which you are viewing now —
                    publishes only approved aggregated statistics and general
                    violation information.
                </p>
            </div>

            <div data-reveal>
                <h2 style="font-size:1.4rem;margin-top:2rem;">Objectives</h2>
                <ul>
                    <li>Maintain accurate, up-to-date traffic violation apprehension records for Pagadian City.</li>
                    <li>Support authorized personnel with reliable operational tools for recording and verifying incidents.</li>
                    <li>Provide statistical insight into traffic violation patterns for planning and policy purposes.</li>
                    <li>Publish appropriately aggregated public information consistent with the office's transparency commitments.</li>
                    <li>Protect personal information through controlled access, encryption, retention rules, and accountability.</li>
                </ul>
            </div>

            <div data-reveal>
                <h2 style="font-size:1.4rem;margin-top:2rem;">Coverage</h2>
                <p>
                    TrafStat currently covers the geographic and operational scope
                    of Pagadian City. Records reflect apprehensions recorded by
                    authorized personnel of the city's traffic management office.
                </p>
                <p class="text-muted-sm">
                    <i class="bi bi-geo-alt me-1"></i>
                    Pagadian City, Zamboanga del Sur, Philippines.
                </p>
            </div>

            <div data-reveal>
                <h2 style="font-size:1.4rem;margin-top:2rem;" id="privacy">Privacy notice</h2>
                <div class="public-notice">
                    <p style="margin-top:0;">
                        <strong>What this public website shows:</strong>
                        aggregated statistics and approved category descriptions.
                    </p>
                    <p>
                        <strong>What this public website never shows:</strong>
                        individual violator names, addresses, contact numbers,
                        plate numbers, apprehension histories, incident timestamps,
                        precise incident coordinates, or any other information that
                        could identify a specific person or incident.
                    </p>
                    <p style="margin-bottom:0;">
                        <strong>Suppression:</strong> statistical buckets with very
                        small group sizes are not shown on public pages. This
                        protects individuals from being re-identified through
                        small-group analysis. The suppression threshold is
                        determined by the office's Data Protection Officer.
                    </p>
                </div>
                <p>
                    The administrative application is designed in reference to the
                    <em>Data Privacy Act of 2012</em> (Republic Act No. 10173) and
                    applicable National Privacy Commission regulations. Individual
                    administrator accounts are used; there are no shared
                    credentials. Personal information is accessible only to
                    authorized personnel and is subject to retention and disposal
                    rules established by the office.
                </p>
            </div>

            <div data-reveal>
                <h2 style="font-size:1.4rem;margin-top:2rem;">Contact</h2>
                <p>
                    For questions about a specific recorded apprehension, a
                    correction request, or general inquiries, please contact the
                    Pagadian City traffic management office directly.
                    <span class="text-muted-sm">
                        (Contact details will appear here once provided by the office.)
                    </span>
                </p>
            </div>

        </div>
        <div class="col-12 col-lg-4">
            <div class="public-chart-card" data-reveal>
                <h3>Quick facts</h3>
                <dl style="margin:0;font-size:0.9rem;">
                    <dt class="text-muted-sm">System name</dt>
                    <dd style="margin-bottom:0.75rem;"><strong><?= e(APP_NAME) ?></strong></dd>

                    <dt class="text-muted-sm">Coverage area</dt>
                    <dd style="margin-bottom:0.75rem;">Pagadian City</dd>

                    <dt class="text-muted-sm">Operator</dt>
                    <dd style="margin-bottom:0.75rem;">
                        Authorized traffic management office personnel
                    </dd>

                    <dt class="text-muted-sm">Data subject rights</dt>
                    <dd style="margin-bottom:0.75rem;">
                        Exercised through the office's DPO
                    </dd>

                    <dt class="text-muted-sm">Administrator access</dt>
                    <dd style="margin-bottom:0;">
                        <a href="<?= e(url('admin/login.php')) ?>">Administrator login &rarr;</a>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>