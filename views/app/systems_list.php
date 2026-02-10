<?php ob_start(); ?>
<div class="flex justify-between items-center">
  <h1 class="text-2xl font-display font-semibold">Systems</h1>
  <a href="<?= App\Core\Helpers::baseUrl('/systems/new') ?>" class="bg-brand text-white px-4 py-2 rounded">Add system</a>
</div>
<?php if (empty($entitlements['active'])): ?>
  <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
    Billing required: add/trigger/upload actions are blocked until subscription is active.
  </div>
<?php else: ?>
  <div class="mt-4 text-sm text-slate-600">
    Allowed systems: <?= (int)$entitlements['allowed_systems'] ?> | Current systems: <?= (int)$usage['systems_count'] ?>
  </div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
  <div class="mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded px-4 py-3">
    System deleted successfully.
  </div>
<?php endif; ?>
<div class="mt-6 space-y-3">
  <?php foreach ($systems as $s): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between shadow-sm">
      <div>
        <div class="font-semibold"><?= App\Core\Helpers::e($s['name']) ?></div>
        <div class="text-slate-500 text-sm"><?= App\Core\Helpers::e($s['environment']) ?> | <?= App\Core\Helpers::e($s['timezone']) ?></div>
        <div class="text-slate-500 text-sm">Last backup: <?= App\Core\Helpers::formatDateTime($s['last_backup'] ?? null) ?></div>
        <div class="text-slate-500 text-sm">Next expected: <?= App\Core\Helpers::formatDateTime($s['next_expected'] ?? null) ?></div>
      </div>
      <?php if (empty($entitlements['active'])): ?>
        <div class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">Billing required</div>
      <?php else: ?>
        <div class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700"><?= App\Core\Helpers::e($s['status']) ?></div>
      <?php endif; ?>
      <a href="<?= App\Core\Helpers::baseUrl('/systems/' . $s['id']) ?>" class="text-brand">Details</a>
    </div>
  <?php endforeach; ?>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Systems'; include __DIR__ . '/../layouts/app.php'; ?>
