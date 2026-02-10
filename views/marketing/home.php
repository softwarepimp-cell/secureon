<?php ob_start(); ?>
<style>
[x-cloak] { display: none !important; }
</style>
<div class="bg-slate-50 text-ink">
  <div class="bg-ink text-white text-xs">
    <div class="max-w-7xl mx-auto px-6 py-2 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <span>Enterprise Ready</span>
        <span class="opacity-70">|</span>
        <span>99.9% Availability Target</span>
      </div>
      <div class="flex items-center gap-4">
        <span>Support 1-800-000-0000</span>
        <a class="underline" href="<?= App\Core\Helpers::baseUrl('/login') ?>">Sign in</a>
      </div>
    </div>
  </div>

  <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="<?= App\Core\Helpers::baseUrl('/') ?>" class="inline-flex items-center">
        <img src="<?= App\Core\Helpers::logoUrl() ?>" alt="Secureon logo" class="h-28 w-28 object-contain rounded">
      </a>
      <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-slate-700">
        <a href="#features" class="hover:text-brand">Features</a>
        <a href="#workflow" class="hover:text-brand">How it Works</a>
        <a href="#pricing" class="hover:text-brand">Pricing</a>
        <a href="#security" class="hover:text-brand">Security</a>
        <a href="#faq" class="hover:text-brand">FAQ</a>
      </nav>
      <div class="flex items-center gap-3">
        <a href="<?= App\Core\Helpers::baseUrl('/login') ?>" class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold hover:bg-slate-100">Sign in</a>
        <a href="<?= App\Core\Helpers::baseUrl('/register') ?>" class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brandDark">Get Started</a>
      </div>
    </div>
  </header>

  <section class="relative overflow-hidden bg-gradient-to-br from-sky to-white">
    <div class="absolute -left-24 top-16 h-72 w-72 rounded-full bg-brand/10 blur-3xl"></div>
    <div class="absolute -right-12 bottom-0 h-80 w-80 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="max-w-7xl mx-auto px-6 py-16 grid lg:grid-cols-2 gap-10 items-center">
      <div>
        <div class="inline-flex items-center gap-2 text-xs font-semibold text-brand bg-white border border-brand/20 rounded-full px-3 py-1">
          <span>Automated Encrypted Backups</span>
          <span class="text-accent">Server-Push Enabled</span>
        </div>
        <h1 class="mt-6 text-4xl md:text-6xl font-display font-semibold leading-tight">
          Protect MySQL workloads with less operational risk.
        </h1>
        <p class="mt-5 text-slate-600 text-lg leading-relaxed max-w-xl">
          Secureon.cloud centralizes backup orchestration, encryption, retention, and restore workflows for teams that need predictable recovery.
        </p>
        <div class="mt-8 flex flex-wrap gap-4">
          <a href="<?= App\Core\Helpers::baseUrl('/register') ?>" class="px-6 py-3 rounded-xl bg-brand text-white font-semibold hover:bg-brandDark">Start Free Setup</a>
          <a href="<?= App\Core\Helpers::baseUrl('/pricing') ?>" class="px-6 py-3 rounded-xl bg-white border border-slate-300 font-semibold hover:bg-slate-50">View Pricing</a>
        </div>
        <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div class="bg-white border border-slate-200 rounded-lg p-3 text-center font-semibold text-slate-700">Encrypted</div>
          <div class="bg-white border border-slate-200 rounded-lg p-3 text-center font-semibold text-slate-700">Automated</div>
          <div class="bg-white border border-slate-200 rounded-lg p-3 text-center font-semibold text-slate-700">Audit Logs</div>
          <div class="bg-white border border-slate-200 rounded-lg p-3 text-center font-semibold text-slate-700">Retention</div>
        </div>
      </div>

      <div x-data="{slide: 0, images: ['<?= App\Core\Helpers::assetUrl('assests/1.jpg') ?>','<?= App\Core\Helpers::assetUrl('assests/2.jpg') ?>','<?= App\Core\Helpers::assetUrl('assests/3.jpg') ?>']}" x-init="setInterval(() => slide = (slide + 1) % images.length, 4500)" class="relative">
        <div class="bg-white border border-slate-200 shadow-2xl rounded-3xl overflow-hidden">
          <img :src="images[slide]" alt="Secureon hero image" class="w-full h-[460px] object-cover transition-all duration-700">
        </div>
        <div class="absolute left-4 right-4 bottom-4 bg-white/90 backdrop-blur rounded-xl px-4 py-3 border border-slate-200">
          <div class="flex items-center justify-between text-sm">
            <div>
              <div class="font-semibold">Live Backup Visibility</div>
              <div class="text-slate-600">Track trigger health and completion in one place</div>
            </div>
            <div class="flex gap-2">
              <template x-for="(_, idx) in images" :key="idx">
                <button class="h-2.5 w-2.5 rounded-full" :class="slide === idx ? 'bg-brand' : 'bg-slate-300'" @click="slide = idx"></button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="max-w-7xl mx-auto px-6 py-16">
    <div class="text-center max-w-3xl mx-auto">
      <h2 class="text-3xl md:text-4xl font-display font-semibold">Built for reliability, not guesswork</h2>
      <p class="mt-3 text-slate-600">Every critical backup stage from trigger to encrypted archive is visible and controllable.</p>
    </div>

    <div class="mt-12 space-y-12">
      <div class="grid lg:grid-cols-2 gap-8 items-center">
        <div class="order-2 lg:order-1">
          <h3 class="text-2xl font-display font-semibold">Central trigger orchestration</h3>
          <p class="mt-3 text-slate-600">Secureon dispatches signed trigger calls from one control plane. Each system has unique secrets, replay protection, and optional IP allowlist.</p>
          <ul class="mt-4 text-sm text-slate-700 space-y-2">
            <li>HMAC-signed requests with timestamp and nonce</li>
            <li>Near real-time status updates</li>
            <li>Rate limiting and health checks built in</li>
          </ul>
        </div>
        <div class="order-1 lg:order-2 rounded-2xl overflow-hidden border border-slate-200 shadow-lg">
          <img src="<?= App\Core\Helpers::assetUrl('assests/1.jpg') ?>" alt="Feature image one" class="w-full h-80 object-cover">
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-8 items-center">
        <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-lg">
          <img src="<?= App\Core\Helpers::assetUrl('assests/2.jpg') ?>" alt="Feature image two" class="w-full h-80 object-cover">
        </div>
        <div>
          <h3 class="text-2xl font-display font-semibold">Operational clarity for teams</h3>
          <p class="mt-3 text-slate-600">Monitor quotas, failures, and backup frequency from one dashboard, with clean audit history for compliance and accountability.</p>
          <ul class="mt-4 text-sm text-slate-700 space-y-2">
            <li>Storage and system limits by plan</li>
            <li>Manual billing approvals with full audit trail</li>
            <li>Fast download + SQL restore paths</li>
          </ul>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-8 items-center">
        <div class="order-2 lg:order-1">
          <h3 class="text-2xl font-display font-semibold">Recovery-first workflow</h3>
          <p class="mt-3 text-slate-600">Backups are encrypted into `.scx`, and can be recovered through the dashboard or agent commands when the pressure is highest.</p>
          <ul class="mt-4 text-sm text-slate-700 space-y-2">
            <li>AES-256-GCM container format</li>
            <li>Signed downloads and permission checks</li>
            <li>Restore instructions for high-stress incidents</li>
          </ul>
        </div>
        <div class="order-1 lg:order-2 rounded-2xl overflow-hidden border border-slate-200 shadow-lg">
          <img src="<?= App\Core\Helpers::assetUrl('assests/3.jpg') ?>" alt="Feature image three" class="w-full h-80 object-cover">
        </div>
      </div>
    </div>
  </section>

  <section id="workflow" class="bg-white border-y border-slate-200">
    <div class="max-w-7xl mx-auto px-6 py-16">
      <h2 class="text-3xl font-display font-semibold text-center">How it works</h2>
      <div class="mt-10 grid md:grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <div class="text-xs font-semibold text-brand">Step 1</div>
          <div class="mt-2 font-semibold">Connect</div>
          <p class="mt-2 text-sm text-slate-600">Create a system, download the agent bundle, and set trigger URL.</p>
        </div>
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <div class="text-xs font-semibold text-brand">Step 2</div>
          <div class="mt-2 font-semibold">Schedule</div>
          <p class="mt-2 text-sm text-slate-600">Secureon dispatches signed trigger calls on your selected interval.</p>
        </div>
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <div class="text-xs font-semibold text-brand">Step 3</div>
          <div class="mt-2 font-semibold">Backup</div>
          <p class="mt-2 text-sm text-slate-600">Agent dumps, compresses, encrypts, then uploads backup container.</p>
        </div>
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <div class="text-xs font-semibold text-brand">Step 4</div>
          <div class="mt-2 font-semibold">Restore</div>
          <p class="mt-2 text-sm text-slate-600">Download `.scx` or `.sql` and restore with guided commands.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="pricing" class="max-w-7xl mx-auto px-6 py-16">
    <div class="flex items-end justify-between">
      <div>
        <h2 class="text-3xl font-display font-semibold">Pricing</h2>
        <p class="mt-2 text-slate-600">Flexible plans with manual approval and explicit limits.</p>
      </div>
      <a href="<?= App\Core\Helpers::baseUrl('/pricing') ?>" class="text-brand font-semibold">Full Pricing</a>
    </div>
    <div class="mt-8 grid md:grid-cols-3 gap-6">
      <?php if (empty($plans)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm text-slate-600">
          No active plans yet. Super Admin can create plans in the admin panel.
        </div>
      <?php endif; ?>
      <?php foreach ($plans as $p): ?>
        <?php
          $isPopular = (int)$p['id'] === (int)($popular_plan_id ?? 0);
          $quotaMb = (int)$p['storage_quota_mb'];
          $quotaText = $quotaMb >= 1024 ? (round($quotaMb / 1024, 1) . ' GB') : ($quotaMb . ' MB');
        ?>
        <div class="rounded-2xl <?= $isPopular ? 'border-2 border-brand bg-sky' : 'border border-slate-200 bg-white' ?> p-6 shadow-sm">
          <?php if ($isPopular): ?>
            <div class="text-brand text-xs font-semibold">Most Popular</div>
          <?php endif; ?>
          <div class="text-slate-500 text-sm mt-1"><?= App\Core\Helpers::e($p['name']) ?></div>
          <div class="mt-2 text-4xl font-semibold">$<?= number_format((float)$p['base_price_monthly'], 0) ?></div>
          <div class="text-xs text-slate-500">base / month</div>
          <div class="mt-4 text-sm <?= $isPopular ? 'text-slate-700' : 'text-slate-600' ?> space-y-2">
            <div><?= App\Core\Helpers::e($quotaText) ?> storage</div>
            <div>Up to <?= (int)$p['max_systems'] ?> systems</div>
            <div>Min interval: <?= (int)$p['min_backup_interval_minutes'] ?> minutes</div>
            <div>Retention: <?= (int)$p['retention_days'] ?> days</div>
            <div>+ $<?= number_format((float)$p['price_per_system_monthly'], 2) ?> / system / month</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="bg-white border-y border-slate-200">
    <div class="max-w-7xl mx-auto px-6 py-16">
      <h2 class="text-3xl font-display font-semibold text-center">What teams say</h2>
      <div class="mt-8 grid md:grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <p class="text-slate-700">“We moved from scattered scripts to predictable backup operations in days.”</p>
          <div class="mt-4 text-sm font-semibold">Operations Lead</div>
        </div>
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <p class="text-slate-700">“Audit logs and trigger visibility made compliance reviews far easier.”</p>
          <div class="mt-4 text-sm font-semibold">Security Manager</div>
        </div>
        <div class="rounded-xl border border-slate-200 p-5 bg-slate-50">
          <p class="text-slate-700">“Restore workflow is straightforward when incidents happen.”</p>
          <div class="mt-4 text-sm font-semibold">Platform Engineer</div>
        </div>
      </div>
    </div>
  </section>

  <section id="security" class="max-w-7xl mx-auto px-6 py-16">
    <div class="grid lg:grid-cols-2 gap-8 items-center">
      <div>
        <h2 class="text-3xl font-display font-semibold">Security first architecture</h2>
        <p class="mt-3 text-slate-600">Encryption, signatures, and scoped system secrets are standard across backup lifecycle operations.</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl border border-slate-200 p-4 bg-white">AES-256-GCM backup containers</div>
        <div class="rounded-xl border border-slate-200 p-4 bg-white">Signed trigger requests + nonce checks</div>
        <div class="rounded-xl border border-slate-200 p-4 bg-white">Scoped system tokens and revocation</div>
        <div class="rounded-xl border border-slate-200 p-4 bg-white">Comprehensive audit logging</div>
      </div>
    </div>
  </section>

  <section id="faq" class="bg-white border-t border-slate-200" x-data="{open: 1}">
    <div class="max-w-7xl mx-auto px-6 py-16">
      <h2 class="text-3xl font-display font-semibold">FAQ</h2>
      <div class="mt-6 space-y-3">
        <div class="border border-slate-200 rounded-xl p-4 cursor-pointer" @click="open = open === 1 ? null : 1">
          <div class="font-semibold">Can I restore if the source server is down?</div>
          <div class="text-slate-600 mt-2" x-show="open === 1" x-cloak>Yes. Download `.scx` or decrypted `.sql` from Secureon and run restore on a replacement environment.</div>
        </div>
        <div class="border border-slate-200 rounded-xl p-4 cursor-pointer" @click="open = open === 2 ? null : 2">
          <div class="font-semibold">How are schedules enforced?</div>
          <div class="text-slate-600 mt-2" x-show="open === 2" x-cloak>Dispatch runs centrally, and both server and agent enforce interval/rate limits.</div>
        </div>
        <div class="border border-slate-200 rounded-xl p-4 cursor-pointer" @click="open = open === 3 ? null : 3">
          <div class="font-semibold">Is manual billing supported?</div>
          <div class="text-slate-600 mt-2" x-show="open === 3" x-cloak>Yes. Users submit payment references, and Super Admin approves or declines requests before activation.</div>
        </div>
      </div>
    </div>
  </section>

  <footer class="bg-ink text-white">
    <div class="max-w-7xl mx-auto px-6 py-8 flex items-center justify-between">
      <img src="<?= App\Core\Helpers::logoUrl() ?>" alt="Secureon logo" class="h-14 w-14 object-contain rounded">
      <div class="text-sm opacity-80">(c) <?= date('Y') ?> | Privacy | Terms | Status</div>
    </div>
  </footer>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/marketing.php'; ?>
