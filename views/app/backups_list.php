<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">Backups</h1>
<div class="mt-6 space-y-3">
  <?php foreach ($backups as $b): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between shadow-sm">
      <div>
        <div class="font-semibold">Backup #<?= $b['id'] ?> | <?= App\Core\Helpers::e($b['system_name']) ?></div>
        <div class="text-slate-500 text-sm"><?= App\Core\Helpers::e($b['status']) ?> | <?= App\Core\Helpers::formatDateTime($b['created_at']) ?></div>
      </div>
      <div class="flex gap-3">
        <button class="text-brand" onclick="signDownload(<?= $b['id'] ?>)">Download .scx</button>
        <a class="text-emerald-700" href="<?= App\Core\Helpers::baseUrl('/backups/' . $b['id'] . '/download-sql') ?>">Download .sql</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div id="download-error" class="mt-3 text-sm text-red-600"></div>
<script>
function signDownload(id) {
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/backups/') ?>' + id + '/sign-download', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (data.url) {
        window.location = data.url;
        return;
      }
      document.getElementById('download-error').innerText = data.error || 'Unable to sign download.';
    });
}
</script>
<?php $content = ob_get_clean(); $pageTitle = 'Backups'; include __DIR__ . '/../layouts/app.php'; ?>
