<?php ob_start(); ?>
<section class="bg-mist min-h-screen">
  <div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex items-center justify-between">
      <a href="<?= App\Core\Helpers::baseUrl('/') ?>" class="inline-flex items-center">
        <img src="<?= App\Core\Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-28 w-28 object-contain rounded">
      </a>
      <a href="<?= App\Core\Helpers::baseUrl('/') ?>" class="text-brand">Back to home</a>
    </div>
    <h1 class="text-4xl font-display font-semibold mt-6">Transparent pricing for every team</h1>
    <p class="mt-2 text-slate-600">Choose a plan that matches your retention and frequency needs.</p>
    <div class="mt-10 grid md:grid-cols-3 gap-6">
      <?php if (empty($plans)): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm text-slate-600">
          No active plans available yet.
        </div>
      <?php endif; ?>
      <?php foreach ($plans as $p): ?>
        <?php
          $isPopular = (int)$p['id'] === (int)($popular_plan_id ?? 0);
          $quotaMb = (int)$p['storage_quota_mb'];
          $quotaText = $quotaMb >= 1024 ? (round($quotaMb / 1024, 1) . ' GB') : ($quotaMb . ' MB');
        ?>
        <div class="bg-white <?= $isPopular ? 'border-2 border-brand' : 'border border-slate-200' ?> rounded-xl p-6 shadow-sm">
          <?php if ($isPopular): ?>
            <div class="text-xs font-semibold text-brand">Most Popular</div>
          <?php endif; ?>
          <div class="text-lg font-semibold"><?= App\Core\Helpers::e($p['name']) ?></div>
          <div class="text-3xl font-semibold mt-2">$<?= number_format((float)$p['base_price_monthly'], 0) ?></div>
          <div class="text-xs text-slate-500">base / month</div>
          <ul class="mt-4 text-slate-600 space-y-1">
            <li><?= App\Core\Helpers::e($quotaText) ?> storage</li>
            <li><?= (int)$p['retention_days'] ?> day retention</li>
            <li>Min interval <?= (int)$p['min_backup_interval_minutes'] ?> minutes</li>
            <li>Up to <?= (int)$p['max_systems'] ?> systems</li>
            <li>+ $<?= number_format((float)$p['price_per_system_monthly'], 2) ?> / system / month</li>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/marketing.php'; ?>
