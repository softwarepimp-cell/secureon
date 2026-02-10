<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <h1 class="text-2xl font-display font-semibold">Billing Request #<?= (int)$request['id'] ?></h1>
  <a href="<?= App\Core\Helpers::baseUrl('/admin/billing/requests') ?>" class="text-brand text-sm">Back to list</a>
</div>

<?php if (!empty($_GET['err'])): ?>
  <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
    <?= App\Core\Helpers::e($_GET['err']) ?>
  </div>
<?php endif; ?>

<div class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Request Details</div>
    <div class="mt-3 text-sm text-slate-600 space-y-1">
      <div>User: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['user_name']) ?></span> (<?= App\Core\Helpers::e($request['user_email']) ?>)</div>
      <div>Plan: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['plan_name']) ?></span></div>
      <div>Months: <span class="font-semibold text-ink"><?= (int)$request['months'] ?></span></div>
      <div>Requested systems: <span class="font-semibold text-ink"><?= (int)$request['requested_systems'] ?></span></div>
      <div>Total: <span class="font-semibold text-ink">$<?= number_format((float)$request['amount_total'], 2) ?> <?= App\Core\Helpers::e($request['currency']) ?></span></div>
      <div>Proof reference: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['proof_reference']) ?></span></div>
      <div>Proof note: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['proof_note'] ?? '') ?></span></div>
      <div>Status: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['status']) ?></span></div>
      <div>Submitted: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($request['created_at']) ?></span></div>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">User Usage + Current Subscription</div>
    <div class="mt-3 text-sm text-slate-600 space-y-1">
      <div>Current systems: <span class="font-semibold text-ink"><?= (int)$usage['systems_count'] ?></span></div>
      <div>Current storage: <span class="font-semibold text-ink"><?= App\Core\Helpers::formatBytes((int)$usage['storage_bytes']) ?></span></div>
      <div>Subscription status: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($latest_subscription['status'] ?? 'none') ?></span></div>
      <div>Subscription plan: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($latest_subscription['plan_name'] ?? 'N/A') ?></span></div>
      <div>Ends at: <span class="font-semibold text-ink"><?= App\Core\Helpers::e($latest_subscription['ends_at'] ?? 'N/A') ?></span></div>
      <div>Allowed systems: <span class="font-semibold text-ink"><?= (int)($latest_subscription['allowed_systems'] ?? 0) ?></span></div>
    </div>
  </div>
</div>

<?php if ($request['status'] === 'pending'): ?>
  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/billing/requests/' . $request['id'] . '/approve') ?>" class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
      <div class="font-semibold text-emerald-700">Approve Request</div>
      <?= App\Core\Helpers::csrfField() ?>
      <div>
        <label class="text-sm text-slate-600">Start date/time</label>
        <input type="datetime-local" name="approved_started_at" value="<?= date('Y-m-d\TH:i') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
      </div>
      <div>
        <label class="text-sm text-slate-600">End date/time (optional override)</label>
        <input type="datetime-local" name="approved_ends_at" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
      </div>
      <div>
        <label class="text-sm text-slate-600">Admin note (optional)</label>
        <textarea name="admin_note" rows="3" class="mt-1 w-full px-3 py-2 rounded border border-slate-300"></textarea>
      </div>
      <button class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700">Approve + Activate</button>
    </form>

    <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/billing/requests/' . $request['id'] . '/decline') ?>" class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
      <div class="font-semibold text-red-700">Decline Request</div>
      <?= App\Core\Helpers::csrfField() ?>
      <div>
        <label class="text-sm text-slate-600">Reason</label>
        <textarea name="admin_note" rows="4" required class="mt-1 w-full px-3 py-2 rounded border border-slate-300" placeholder="Reason for decline"></textarea>
      </div>
      <button class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Decline</button>
    </form>
  </div>
<?php endif; ?>

<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="font-semibold">Manual Subscription Adjustment</div>
  <form method="post" action="<?= App\Core\Helpers::baseUrl('/admin/billing/users/' . $request['user_id'] . '/adjust') ?>" class="mt-3 grid md:grid-cols-3 gap-3">
    <?= App\Core\Helpers::csrfField() ?>
    <div>
      <label class="text-sm text-slate-600">Plan</label>
      <select name="plan_id" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$request['plan_id'] ? 'selected' : '' ?>>
            <?= App\Core\Helpers::e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm text-slate-600">Status</label>
      <select name="status" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
        <option value="active">active</option>
        <option value="pending">pending</option>
        <option value="inactive">inactive</option>
        <option value="expired">expired</option>
        <option value="declined">declined</option>
        <option value="cancelled">cancelled</option>
      </select>
    </div>
    <div>
      <label class="text-sm text-slate-600">Allowed systems</label>
      <input type="number" min="0" step="1" name="allowed_systems" value="<?= (int)$request['requested_systems'] ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Start</label>
      <input type="datetime-local" name="started_at" value="<?= date('Y-m-d\TH:i') ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">End</label>
      <input type="datetime-local" name="ends_at" value="<?= date('Y-m-d\TH:i', strtotime('+1 month')) ?>" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
    </div>
    <div class="flex items-end">
      <button class="bg-brand text-white px-4 py-2 rounded hover:bg-brandDark">Apply Adjustment</button>
    </div>
  </form>
</div>
<?php $content = ob_get_clean(); $pageTitle = 'Billing Request'; include __DIR__ . '/../layouts/app.php'; ?>

