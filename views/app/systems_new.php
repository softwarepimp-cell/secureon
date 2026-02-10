<?php ob_start(); ?>
<h1 class="text-2xl font-display font-semibold">New System</h1>
<?php if (!empty($error)): ?>
  <div class="mt-4 text-red-500"><?= App\Core\Helpers::e($error) ?></div>
<?php endif; ?>
<?php if (isset($allowed_systems, $systems_count)): ?>
  <div class="mt-4 text-sm text-slate-600">
    Systems usage: <?= (int)$systems_count ?> / <?= (int)$allowed_systems ?> allowed.
  </div>
<?php endif; ?>
<form method="post" class="mt-6 space-y-4 max-w-lg">
  <?= App\Core\Helpers::csrfField() ?>
  <input name="name" type="text" placeholder="System name" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
  <select name="environment" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
    <option value="production">Production</option>
    <option value="staging">Staging</option>
    <option value="dev">Development</option>
  </select>
  <select name="timezone" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
    <?php foreach (($common_timezones ?? App\Core\Helpers::commonTimezones()) as $tzKey => $tzLabel): ?>
      <option value="<?= App\Core\Helpers::e($tzKey) ?>" <?= (($default_timezone ?? App\Core\Helpers::appTimezone()) === $tzKey) ? 'selected' : '' ?>>
        <?= App\Core\Helpers::e($tzLabel) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="interval_minutes" class="w-full px-4 py-2 rounded bg-white border border-slate-300">
    <option value="30">Every 30 minutes</option>
    <option value="60" selected>Every 60 minutes</option>
    <option value="1440">Daily</option>
  </select>
  <?php if (!empty($min_interval)): ?>
    <div class="text-xs text-slate-500">Plan minimum interval: <?= (int)$min_interval ?> minutes</div>
  <?php endif; ?>
  <button class="bg-brand text-white px-4 py-2 rounded">Create system</button>
</form>
<?php $content = ob_get_clean(); $pageTitle = 'New System'; include __DIR__ . '/../layouts/app.php'; ?>
