<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">Alerts</h1>
<div class="mt-4 text-slate-600">Recent alert events (mail log stub).</div>
<div class="mt-6 space-y-2">
  <?php if (empty($alerts)): ?>
    <div class="text-slate-500">No alerts yet.</div>
  <?php endif; ?>
  <?php foreach ($alerts as $line): ?>
    <div class="bg-white border border-slate-200 rounded p-3 text-sm text-slate-600"><?= App\Core\Helpers::e($line) ?></div>
  <?php endforeach; ?>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Alerts'; include __DIR__ . '/../layouts/app.php'; ?>
