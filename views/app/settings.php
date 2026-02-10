<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">Settings</h1>
<?php if (!empty($_GET['tz']) && $_GET['tz'] === 'updated'): ?>
  <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    Timezone updated.
  </div>
<?php elseif (!empty($_GET['tz']) && $_GET['tz'] === 'invalid'): ?>
  <div class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    Invalid timezone selection.
  </div>
<?php endif; ?>
<div class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <h2 class="font-semibold">Profile</h2>
    <form method="post" action="<?= App\Core\Helpers::baseUrl('/settings/profile') ?>" class="mt-4 space-y-3">
      <?= App\Core\Helpers::csrfField() ?>
      <input name="name" type="text" value="<?= App\Core\Helpers::e($user['name']) ?>" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
      <input name="email" type="email" value="<?= App\Core\Helpers::e($user['email']) ?>" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
      <button class="bg-brand text-white px-4 py-2 rounded">Save profile</button>
    </form>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <h2 class="font-semibold">Password</h2>
    <form method="post" action="<?= App\Core\Helpers::baseUrl('/settings/password') ?>" class="mt-4 space-y-3">
      <?= App\Core\Helpers::csrfField() ?>
      <input name="password" type="password" placeholder="New password" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
      <button class="bg-brand text-white px-4 py-2 rounded">Update password</button>
    </form>
  </div>
</div>
<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm max-w-2xl">
  <h2 class="font-semibold">Timezone</h2>
  <div class="mt-2 text-sm text-slate-600">
    Server default: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($server_timezone ?? App\Core\Helpers::appTimezone()) ?></span> (UTC+02:00)
  </div>
  <form method="post" action="<?= App\Core\Helpers::baseUrl('/settings/timezone') ?>" class="mt-4 space-y-3">
    <?= App\Core\Helpers::csrfField() ?>
    <select name="timezone" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
      <?php foreach (($common_timezones ?? []) as $tzKey => $tzLabel): ?>
        <option value="<?= App\Core\Helpers::e($tzKey) ?>" <?= ($active_timezone ?? '') === $tzKey ? 'selected' : '' ?>>
          <?= App\Core\Helpers::e($tzLabel) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="text-xs text-slate-500">Used for dashboard and display translations. Server operations remain anchored to Harare time.</div>
    <?php if (empty($timezone_persistence_db)): ?>
      <div class="text-xs text-amber-700">Database timezone column is not installed yet. Override is saved in browser/session on this device.</div>
    <?php endif; ?>
    <button class="bg-brand text-white px-4 py-2 rounded">Save timezone</button>
  </form>
</div>
<div class="mt-6 text-slate-500 text-sm">2FA setup (stub)</div>
<?php $content = ob_get_clean(); $pageTitle = 'Settings'; include __DIR__ . '/../layouts/app.php'; ?>
