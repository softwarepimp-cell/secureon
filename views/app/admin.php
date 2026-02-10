<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">Super Admin</h1>
<div class="mt-2 text-slate-600 text-sm">Moderate accounts, monitor bottlenecks, and manage subscriptions.</div>
<div class="mt-3 flex items-center gap-3 text-sm">
  <a href="<?= App\Core\Helpers::baseUrl('/admin/packages') ?>" class="text-brand">Manage Packages</a>
  <a href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests') ?>" class="text-brand">Billing Approvals</a>
</div>

<div class="mt-6 grid md:grid-cols-4 gap-4">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Total Users</div>
    <div class="text-2xl font-semibold"><?= (int)$stats['total_users'] ?></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Active Users</div>
    <div class="text-2xl font-semibold"><?= (int)$stats['active_users'] ?></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Suspended</div>
    <div class="text-2xl font-semibold"><?= (int)$stats['suspended_users'] ?></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Failures 24h</div>
    <div class="text-2xl font-semibold"><?= (int)$stats['failures_24h'] ?></div>
  </div>
</div>

<div class="mt-4 grid md:grid-cols-2 gap-4">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Total Backups</div>
    <div class="text-2xl font-semibold"><?= (int)$stats['total_backups'] ?></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="text-slate-500 text-sm">Total Storage Used</div>
    <div class="text-2xl font-semibold"><?= App\Core\Helpers::formatBytes((int)$stats['total_storage']) ?></div>
  </div>
</div>

<div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="font-semibold">Users</div>
  <div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-slate-500">
        <tr class="text-left">
          <th class="py-2">User</th>
          <th class="py-2">Role</th>
          <th class="py-2">Status</th>
          <th class="py-2">Plan</th>
          <th class="py-2">Systems</th>
          <th class="py-2">Storage</th>
          <th class="py-2">Last Backup</th>
          <th class="py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="border-t border-slate-100">
            <td class="py-3">
              <div class="font-semibold"><?= App\Core\Helpers::e($u['name']) ?></div>
              <div class="text-slate-500"><?= App\Core\Helpers::e($u['email']) ?></div>
            </td>
            <td class="py-3">
              <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/users/' . $u['id'] . '/role') ?>">
                <?= App\Core\Helpers::csrfField() ?>
                <select name="role" class="border border-slate-300 rounded px-2 py-1">
                  <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                  <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>super_admin</option>
                </select>
                <button class="ml-2 text-brand">Save</button>
              </form>
            </td>
            <td class="py-3">
              <?php if (($u['status'] ?? 'active') === 'suspended'): ?>
                <span class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">suspended</span>
              <?php else: ?>
                <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700">active</span>
              <?php endif; ?>
            </td>
            <td class="py-3">
              <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/users/' . $u['id'] . '/plan') ?>">
                <?= App\Core\Helpers::csrfField() ?>
                <select name="plan_id" class="border border-slate-300 rounded px-2 py-1">
                  <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ((int)($u['plan_id'] ?? 0)) === (int)$p['id'] ? 'selected' : '' ?>><?= App\Core\Helpers::e($p['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="ml-2 text-brand">Save</button>
              </form>
            </td>
            <td class="py-3"><?= (int)$u['systems_count'] ?></td>
            <td class="py-3"><?= App\Core\Helpers::formatBytes((int)$u['storage_used']) ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($u['last_backup'] ?? 'N/A') ?></td>
            <td class="py-3">
              <?php if (($u['status'] ?? 'active') === 'suspended'): ?>
                <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/users/' . $u['id'] . '/unsuspend') ?>">
                  <?= App\Core\Helpers::csrfField() ?>
                  <button class="text-emerald-700">Unsuspend</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/users/' . $u['id'] . '/suspend') ?>" class="flex items-center gap-2">
                  <?= App\Core\Helpers::csrfField() ?>
                  <input name="reason" placeholder="Reason" class="border border-slate-300 rounded px-2 py-1 text-xs" />
                  <button class="text-red-600">Suspend</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $content = ob_get_clean(); $pageTitle = 'Admin'; include __DIR__ . '/../layouts/app.php'; ?>
