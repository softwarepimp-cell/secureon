<?php ob_start(); ?>
<div x-data="billingPage()" x-init="init()">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-display font-semibold">Billing</h1>
    <a href="<?= App\Core\Helpers::baseUrl('/billing/requests') ?>" class="text-brand text-sm">View request history</a>
  </div>

  <?php if (!empty($_GET['requested'])): ?>
    <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded px-4 py-3">
      Payment request #<?= (int)$_GET['requested'] ?> submitted. Waiting for Super Admin approval.
    </div>
  <?php endif; ?>

  <?php if (!$subscription): ?>
    <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
      Billing required: backups, triggers, downloads, and new systems are blocked until a payment request is approved.
      <?php if (!empty($latest_subscription['ends_at'])): ?>
        Last subscription ended at <?= App\Core\Helpers::formatDateTime($latest_subscription['ends_at']) ?>.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="mt-6 grid md:grid-cols-3 gap-4">
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Current Plan</div>
      <div class="text-xl font-semibold mt-1"><?= App\Core\Helpers::e($subscription['plan_name'] ?? 'Inactive') ?></div>
      <div class="text-slate-500 text-sm mt-2">Expiry: <?= App\Core\Helpers::formatDateTime($subscription['ends_at'] ?? null) ?></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Usage</div>
      <div class="text-xl font-semibold mt-1"><?= (int)$usage['systems_count'] ?> systems</div>
      <div class="text-slate-500 text-sm mt-2"><?= App\Core\Helpers::formatBytes((int)$usage['storage_bytes']) ?> used</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Entitlements</div>
      <div class="text-xl font-semibold mt-1"><?= (int)($subscription['allowed_systems'] ?? 0) ?> allowed systems</div>
      <div class="text-slate-500 text-sm mt-2"><?= (int)($subscription['storage_quota_mb'] ?? 0) ?> MB quota</div>
    </div>
  </div>

  <div class="mt-8">
    <div class="font-semibold text-lg">Select Package</div>
    <div class="mt-4 grid md:grid-cols-3 gap-4">
      <?php foreach ($plans as $p): ?>
        <button type="button"
          class="text-left bg-white border rounded-xl p-5 shadow-sm transition hover:shadow-md"
          :class="selectedPlanId === <?= (int)$p['id'] ?> ? 'border-brand ring-2 ring-brand/20' : 'border-slate-200'"
          @click="pickPlan(<?= (int)$p['id'] ?>)">
          <div class="font-semibold"><?= App\Core\Helpers::e($p['name']) ?></div>
          <div class="text-2xl font-semibold mt-2">$<?= number_format((float)$p['base_price_monthly'], 2) ?></div>
          <div class="text-slate-500 text-sm">base / month</div>
          <div class="text-slate-600 text-sm mt-2">$<?= number_format((float)$p['price_per_system_monthly'], 2) ?> per system / month</div>
          <div class="text-slate-600 text-sm"><?= (int)$p['storage_quota_mb'] ?> MB quota</div>
          <div class="text-slate-600 text-sm">Max <?= (int)$p['max_systems'] ?> systems</div>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mt-8 bg-white border border-slate-200 rounded-xl p-6 shadow-sm" x-show="selectedPlanId" x-cloak>
    <div class="font-semibold text-lg">Payment Request</div>
    <div class="mt-4 grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm text-slate-600">Duration (months)</label>
        <input type="number" min="1" max="<?= (int)$max_months ?>" x-model.number="months" @input.debounce.250="estimate()" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
      </div>
      <div>
        <label class="text-sm text-slate-600">Requested systems</label>
        <input type="number" min="1" :max="selectedPlan.max_systems || 1" x-model.number="requestedSystems" @input.debounce.250="estimate()" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
      </div>
      <div>
        <label class="text-sm text-slate-600">Currency</label>
        <input type="text" value="<?= App\Core\Helpers::e($currency) ?>" readonly class="mt-1 w-full px-3 py-2 rounded border border-slate-300 bg-slate-50">
      </div>
    </div>

    <div class="mt-4 bg-mist rounded-lg p-4 text-sm text-slate-700">
      <div>Base: <span class="font-semibold" x-text="money(estimateData.base_total)"></span></div>
      <div>Systems: <span class="font-semibold" x-text="money(estimateData.systems_total)"></span></div>
      <div class="mt-1 text-lg text-ink">Total: <span class="font-semibold" x-text="money(estimateData.total)"></span></div>
    </div>

    <form method="post" action="<?= App\Core\Helpers::baseUrl('/billing/request') ?>" class="mt-5 space-y-3">
      <?= App\Core\Helpers::csrfField() ?>
      <input type="hidden" name="plan_id" :value="selectedPlanId">
      <input type="hidden" name="months" :value="months">
      <input type="hidden" name="requested_systems" :value="requestedSystems">
      <div>
        <label class="text-sm text-slate-600">Proof/reference code</label>
        <input name="proof_reference" required maxlength="120" class="mt-1 w-full px-3 py-2 rounded border border-slate-300" placeholder="Bank reference / transfer ID">
      </div>
      <div>
        <label class="text-sm text-slate-600">Note (optional)</label>
        <textarea name="proof_note" rows="3" class="mt-1 w-full px-3 py-2 rounded border border-slate-300" placeholder="Any billing note for admin review"></textarea>
      </div>
      <label class="text-sm text-slate-700 flex items-center gap-2">
        <input type="checkbox" name="ack_manual" value="1" required>
        I understand service activates only after manual approval.
      </label>
      <button class="bg-brand text-white px-4 py-2 rounded hover:bg-brandDark">Submit Payment Request</button>
    </form>
  </div>

  <div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Recent Requests</div>
    <div class="mt-3 space-y-2">
      <?php if (empty($requests)): ?>
        <div class="text-sm text-slate-500">No payment requests yet.</div>
      <?php endif; ?>
      <?php foreach (array_slice($requests, 0, 5) as $r): ?>
        <div class="flex items-center justify-between bg-mist rounded p-3 text-sm">
          <div>
            <div class="font-semibold">#<?= (int)$r['id'] ?> | <?= App\Core\Helpers::e($r['plan_name']) ?></div>
            <div class="text-slate-500"><?= (int)$r['months'] ?> months, <?= (int)$r['requested_systems'] ?> systems</div>
          </div>
          <div class="text-right">
            <div class="font-semibold">$<?= number_format((float)$r['amount_total'], 2) ?> <?= App\Core\Helpers::e($r['currency']) ?></div>
            <div class="text-slate-500"><?= App\Core\Helpers::e($r['status']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function billingPage() {
  return {
    plans: <?= json_encode(array_values($plans), JSON_UNESCAPED_SLASHES) ?>,
    selectedPlanId: 0,
    selectedPlan: {},
    months: 1,
    requestedSystems: 1,
    estimateData: {base_total: 0, systems_total: 0, total: 0},
    csrf: '<?= App\Core\CSRF::token() ?>',
    init() {
      if (this.plans.length > 0) {
        this.pickPlan(this.plans[0].id);
      }
    },
    pickPlan(id) {
      const found = this.plans.find(p => Number(p.id) === Number(id));
      if (!found) return;
      this.selectedPlanId = Number(found.id);
      this.selectedPlan = found;
      this.months = 1;
      this.requestedSystems = Math.min(Math.max(1, <?= (int)$usage['systems_count'] ?> || 1), Number(found.max_systems || 1));
      this.estimate();
    },
    estimate() {
      if (!this.selectedPlanId) return;
      const maxSystems = Number(this.selectedPlan.max_systems || 1);
      if (this.requestedSystems < 1) this.requestedSystems = 1;
      if (this.requestedSystems > maxSystems) this.requestedSystems = maxSystems;
      if (this.months < 1) this.months = 1;
      if (this.months > <?= (int)$max_months ?>) this.months = <?= (int)$max_months ?>;
      fetch('<?= App\Core\Helpers::baseUrl('/billing/estimate') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF': this.csrf},
        body: JSON.stringify({
          plan_id: this.selectedPlanId,
          months: this.months,
          requested_systems: this.requestedSystems
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data && data.breakdown) this.estimateData = data.breakdown;
      });
    },
    money(v) {
      return '$' + Number(v || 0).toFixed(2) + ' <?= App\Core\Helpers::e($currency) ?>';
    }
  }
}
</script>
<?php $content = ob_get_clean(); $pageTitle = 'Billing'; include __DIR__ . '/../layouts/app.php'; ?>
