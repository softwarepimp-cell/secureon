<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-display font-semibold">Billing Approvals</h1>
    <div class="text-sm text-slate-500 mt-1">Review pending payment requests and activate subscriptions.</div>
  </div>
  <div class="flex items-center gap-2 text-sm">
    <a class="px-3 py-1 rounded border <?= $status === 'pending' ? 'border-brand text-brand' : 'border-slate-300 text-slate-600' ?>" href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests?status=pending') ?>">Pending</a>
    <a class="px-3 py-1 rounded border <?= $status === 'approved' ? 'border-brand text-brand' : 'border-slate-300 text-slate-600' ?>" href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests?status=approved') ?>">Approved</a>
    <a class="px-3 py-1 rounded border <?= $status === 'declined' ? 'border-brand text-brand' : 'border-slate-300 text-slate-600' ?>" href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests?status=declined') ?>">Declined</a>
    <a class="px-3 py-1 rounded border <?= $status === 'all' ? 'border-brand text-brand' : 'border-slate-300 text-slate-600' ?>" href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests?status=all') ?>">All</a>
  </div>
</div>

<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-slate-500">
        <tr class="text-left">
          <th class="py-2">ID</th>
          <th class="py-2">User</th>
          <th class="py-2">Plan</th>
          <th class="py-2">Months</th>
          <th class="py-2">Systems</th>
          <th class="py-2">Total</th>
          <th class="py-2">Proof Ref</th>
          <th class="py-2">Status</th>
          <th class="py-2">Created</th>
          <th class="py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($requests)): ?>
          <tr><td colspan="10" class="py-4 text-slate-500">No requests found.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <tr class="border-t border-slate-100">
            <td class="py-3 font-semibold">#<?= (int)$r['id'] ?></td>
            <td class="py-3">
              <div class="font-semibold"><?= App\Core\Helpers::e($r['user_name']) ?></div>
              <div class="text-slate-500"><?= App\Core\Helpers::e($r['user_email']) ?></div>
            </td>
            <td class="py-3"><?= App\Core\Helpers::e($r['plan_name']) ?></td>
            <td class="py-3"><?= (int)$r['months'] ?></td>
            <td class="py-3"><?= (int)$r['requested_systems'] ?></td>
            <td class="py-3">$<?= number_format((float)$r['amount_total'], 2) ?> <?= App\Core\Helpers::e($r['currency']) ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($r['proof_reference']) ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($r['status']) ?></td>
            <td class="py-3"><?= App\Core\Helpers::e($r['created_at']) ?></td>
            <td class="py-3">
              <a href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests/' . $r['id']) ?>" class="text-brand">Review</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Billing Approvals'; include __DIR__ . '/../layouts/app.php'; ?>

