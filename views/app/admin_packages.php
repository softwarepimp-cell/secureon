<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-display font-semibold">Packages</h1>
    <div class="text-sm text-slate-500 mt-1">Create, update, and disable billing plans.</div>
  </div>
  <a href="<?= App\Core\Helpers::baseUrl('/admin/packages/new') ?>" class="bg-brand text-white px-4 py-2 rounded">New Package</a>
</div>

<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-slate-500">
        <tr class="text-left">
          <th class="py-2">Name</th>
          <th class="py-2">Base/mo</th>
          <th class="py-2">Per system/mo</th>
          <th class="py-2">Quota</th>
          <th class="py-2">Max systems</th>
          <th class="py-2">Retention</th>
          <th class="py-2">Min interval</th>
          <th class="py-2">Status</th>
          <th class="py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $p): ?>
          <tr class="border-t border-slate-100">
            <td class="py-3 font-semibold"><?= App\Core\Helpers::e($p['name']) ?></td>
            <td class="py-3">$<?= number_format((float)$p['base_price_monthly'], 2) ?></td>
            <td class="py-3">$<?= number_format((float)$p['price_per_system_monthly'], 2) ?></td>
            <td class="py-3"><?= (int)$p['storage_quota_mb'] ?> MB</td>
            <td class="py-3"><?= (int)$p['max_systems'] ?></td>
            <td class="py-3"><?= (int)$p['retention_days'] ?> days</td>
            <td class="py-3"><?= (int)$p['min_backup_interval_minutes'] ?> min</td>
            <td class="py-3">
              <?php if ((int)$p['is_active'] === 1): ?>
                <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700">active</span>
              <?php else: ?>
                <span class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-700">inactive</span>
              <?php endif; ?>
            </td>
            <td class="py-3">
              <div class="flex items-center gap-3">
                <a href="<?= App\Core\Helpers::baseUrl('/admin/packages/' . $p['id'] . '/edit') ?>" class="text-brand">Edit</a>
                <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/packages/' . $p['id'] . '/toggle') ?>">
                  <?= App\Core\Helpers::csrfField() ?>
                  <button class="text-slate-600"><?= (int)$p['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Packages'; include __DIR__ . '/../layouts/app.php'; ?>

