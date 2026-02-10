<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <h1 class="text-2xl font-display font-semibold">Payment Requests</h1>
  <a href="<?= App\Core\Helpers::baseUrl('/billing') ?>" class="text-brand text-sm">Back to Billing</a>
</div>

<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-slate-500">
        <tr class="text-left">
          <th class="py-2">ID</th>
          <th class="py-2">Plan</th>
          <th class="py-2">Months</th>
          <th class="py-2">Systems</th>
          <th class="py-2">Amount</th>
          <th class="py-2">Proof Ref</th>
          <th class="py-2">Status</th>
          <th class="py-2">Created</th>
          <th class="py-2">Admin Note</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($requests)): ?>
          <tr><td colspan="9" class="py-4 text-slate-500">No requests found.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <tr class="border-t border-slate-100">
            <td class="py-3 font-semibold">#<?= (int)$r['id'] ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($r['plan_name']) ?></td>
            <td class="py-3"><?= (int)$r['months'] ?></td>
            <td class="py-3"><?= (int)$r['requested_systems'] ?></td>
            <td class="py-3">$<?= number_format((float)$r['amount_total'], 2) ?> <?= App\Core\Helpers::e($r['currency']) ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($r['proof_reference']) ?></td>
            <td class="py-3">
              <?php if ($r['status'] === 'approved'): ?>
                <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700">approved</span>
              <?php elseif ($r['status'] === 'declined'): ?>
                <span class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">declined</span>
              <?php else: ?>
                <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-700">pending</span>
              <?php endif; ?>
            </td>
            <td class="py-3"><?= App\Core\Helpers::e($r['created_at']) ?></td>
            <td class="py-3 text-slate-500"><?= App\Core\Helpers::e($r['admin_note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Billing Requests'; include __DIR__ . '/../layouts/app.php'; ?>

