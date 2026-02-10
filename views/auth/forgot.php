<?php ob_start(); ?>
<div class="min-h-screen flex items-center justify-center">
  <div class="bg-white border border-slate-200 rounded-xl p-8 w-full max-w-md shadow-sm">
    <div class="mb-4 flex items-center gap-3">
      <img src="<?= App\Core\Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-28 w-28 object-contain rounded">
    </div>
    <h1 class="text-2xl font-display font-semibold">Reset password</h1>
    <?php if (!empty($message)): ?>
      <div class="mt-4 text-emerald-600"><?= App\Core\Helpers::e($message) ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
      <?= App\Core\Helpers::csrfField() ?>
      <input name="email" type="email" placeholder="Email" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
      <button class="w-full bg-brand text-white px-4 py-2 rounded font-semibold">Send reset link</button>
    </form>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/auth.php'; ?>
