<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">Settings</h1>
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
<div class="mt-6 text-slate-500 text-sm">2FA setup (stub)</div>
<?php $content = ob_get_clean(); $pageTitle = 'Settings'; include __DIR__ . '/../layouts/app.php'; ?>
