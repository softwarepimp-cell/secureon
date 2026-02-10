<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <h1 class="text-2xl font-display font-semibold"><?= App\Core\Helpers::e($title) ?></h1>
  <a href="<?= App\Core\Helpers::baseUrl('/admin/packages') ?>" class="text-brand text-sm">Back to Packages</a>
</div>

<?php if (!empty($_GET['err'])): ?>
  <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
    <?= App\Core\Helpers::e($_GET['err']) ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= App\Core\Helpers::e($action) ?>" class="mt-6 bg-white border border-slate-200 rounded-xl p-6 shadow-sm max-w-3xl space-y-4">
  <?= App\Core\Helpers::csrfField() ?>
  <div>
    <label class="text-sm text-slate-600">Name</label>
    <input name="name" required value="<?= App\Core\Helpers::e($plan['name'] ?? '') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
  </div>
  <div>
    <label class="text-sm text-slate-600">Description</label>
    <textarea name="description" rows="3" class="mt-1 w-full px-3 py-2 rounded border border-slate-300"><?= App\Core\Helpers::e($plan['description'] ?? '') ?></textarea>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-slate-600">Base price / month</label>
      <input type="number" min="0" step="0.01" name="base_price_monthly" required value="<?= App\Core\Helpers::e($plan['base_price_monthly'] ?? '0.00') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Price per system / month</label>
      <input type="number" min="0" step="0.01" name="price_per_system_monthly" required value="<?= App\Core\Helpers::e($plan['price_per_system_monthly'] ?? '0.00') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Storage quota (MB)</label>
      <input type="number" min="1" step="1" name="storage_quota_mb" required value="<?= App\Core\Helpers::e($plan['storage_quota_mb'] ?? '1024') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Max systems</label>
      <input type="number" min="1" step="1" name="max_systems" required value="<?= App\Core\Helpers::e($plan['max_systems'] ?? '1') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Retention days</label>
      <input type="number" min="1" step="1" name="retention_days" required value="<?= App\Core\Helpers::e($plan['retention_days'] ?? '30') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Min backup interval (minutes)</label>
      <input type="number" min="1" step="1" name="min_backup_interval_minutes" required value="<?= App\Core\Helpers::e($plan['min_backup_interval_minutes'] ?? '60') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
  </div>
  <label class="text-sm text-slate-700 flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" <?= empty($plan) || (int)($plan['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
    Active package
  </label>
  <button class="bg-brand text-white px-4 py-2 rounded hover:bg-brandDark">Save Package</button>
</form>
<?php $content = ob_get_clean(); $pageTitle = $title; include __DIR__ . '/../layouts/app.php'; ?>

