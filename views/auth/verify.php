<?php ob_start(); ?>
<div class="min-h-screen flex items-center justify-center">
  <div class="bg-white border border-slate-200 rounded-xl p-8 w-full max-w-md shadow-sm">
    <div class="mb-4 flex items-center gap-3">
      <img src="<?= App\Core\Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-28 w-28 object-contain rounded">
    </div>
    <h1 class="text-2xl font-display font-semibold">Verify email</h1>
    <p class="mt-4 text-slate-600">Verification flow is stubbed for MVP.</p>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/auth.php'; ?>
