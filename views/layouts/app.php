<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'Secureon.cloud Dashboard' ?></title>
  <link rel="icon" type="image/png" href="<?= Helpers::logoUrl() ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink: '#0b1320',
            brand: '#0b74de',
            brandDark: '#0a5fb5',
            accent: '#ff8a00',
            sky: '#eaf4ff',
            mist: '#f6f9ff',
          }
        },
        fontFamily: {
          display: ['Space Grotesk', 'ui-sans-serif', 'system-ui'],
          body: ['Manrope', 'ui-sans-serif', 'system-ui'],
        }
      }
    }
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-mist text-ink font-body">
  <div class="min-h-screen flex">
    <aside class="w-64 bg-white border-r border-slate-200 px-6 py-8 hidden md:block">
      <a href="<?= Helpers::baseUrl('/dashboard') ?>" class="inline-flex items-center">
        <img src="<?= Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-28 w-28 object-contain rounded">
      </a>
      <div class="mt-8 space-y-2 text-slate-600">
        <a href="<?= Helpers::baseUrl('/dashboard') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Dashboard</a>
        <a href="<?= Helpers::baseUrl('/systems') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Systems</a>
        <a href="<?= Helpers::baseUrl('/backups') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Backups</a>
        <a href="<?= Helpers::baseUrl('/alerts') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Alerts</a>
        <a href="<?= Helpers::baseUrl('/billing') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Billing</a>
        <a href="<?= Helpers::baseUrl('/settings') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Settings</a>
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
          <a href="<?= Helpers::baseUrl('/admin') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Admin</a>
          <a href="<?= Helpers::baseUrl('/admin/packages') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Packages</a>
          <a href="<?= Helpers::baseUrl('/admin/billing/requests') ?>" class="block px-3 py-2 rounded hover:bg-slate-100 hover:text-ink">Billing Approvals</a>
        <?php endif; ?>
      </div>
    </aside>
    <main class="flex-1">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-white">
        <div class="flex items-center gap-3">
          <img src="<?= Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-20 w-20 object-contain rounded">
          <div class="text-lg font-display font-semibold"><?= $pageTitle ?? 'Dashboard' ?></div>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-slate-500 text-sm">Workspace: <?= App\Core\Helpers::e($user['name'] ?? ''); ?></span>
          <form method="post" action="<?= Helpers::baseUrl('/logout') ?>">
            <?= App\Core\Helpers::csrfField() ?>
            <button class="text-sm px-3 py-2 rounded bg-brand text-white hover:bg-brandDark">Sign out</button>
          </form>
        </div>
      </div>
      <div class="p-6">
        <?= $content ?>
      </div>
    </main>
  </div>
</body>
</html>
