<?php ob_start(); ?>
<div x-data="dashboard()" x-init="init()">
  <?php if (empty($entitlements['active'])): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
      Billing required: submit a payment request to activate backups and triggers.
      <?php if (!empty($latest_subscription['ends_at'])): ?>
        Last subscription ended at <?= App\Core\Helpers::formatDateTime($latest_subscription['ends_at']) ?>.
      <?php endif; ?>
      <a href="<?= App\Core\Helpers::baseUrl('/billing') ?>" class="underline ml-2">Open Billing</a>
    </div>
  <?php endif; ?>
  <div class="grid md:grid-cols-4 gap-4">
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Systems</div>
      <div class="text-2xl font-semibold" x-text="metrics.systems_count"></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Backups 24h</div>
      <div class="text-2xl font-semibold" x-text="metrics.backups_24h"></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Failures 24h</div>
      <div class="text-2xl font-semibold" x-text="metrics.failures_24h"></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="text-slate-500 text-sm">Storage Used / Quota</div>
      <div class="text-2xl font-semibold" x-text="metrics.storage_used_display + ' / ' + metrics.storage_quota_display"></div>
    </div>
  </div>

  <div class="mt-8 grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="font-semibold">Storage usage</div>
      <canvas id="storageChart" class="mt-4"></canvas>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="font-semibold">Backup success rate</div>
      <canvas id="successChart" class="mt-4"></canvas>
    </div>
  </div>

  <div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Latest backup events</div>
    <div class="mt-3 space-y-2">
      <template x-for="ev in events" :key="ev.system_id">
        <div class="flex items-center justify-between bg-mist rounded p-3 text-sm">
          <div>
            <div class="font-semibold" x-text="ev.system_name"></div>
            <div class="text-slate-500" x-text="(ev.status || '') + ' | ' + (ev.message || '')"></div>
          </div>
          <div class="text-slate-500" x-text="ev.event_time || ''"></div>
        </div>
      </template>
      <div class="text-slate-500 text-sm" x-show="events.length === 0">No events yet.</div>
    </div>
  </div>

  <div class="mt-8 grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="font-semibold">Late systems</div>
      <div class="mt-3 space-y-2">
        <?php if (empty($late_systems)): ?>
          <div class="text-slate-500 text-sm">None</div>
        <?php endif; ?>
        <?php foreach ($late_systems as $ls): ?>
          <div class="flex items-center justify-between bg-mist rounded p-3 text-sm">
            <div class="font-semibold"><?= App\Core\Helpers::e($ls['name']) ?></div>
            <div class="text-slate-500"><?= App\Core\Helpers::formatDateTime($ls['last_completed']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <div class="font-semibold">Trigger failures</div>
      <div class="mt-3 space-y-2">
        <?php if (empty($trigger_failures)): ?>
          <div class="text-slate-500 text-sm">None</div>
        <?php endif; ?>
        <?php foreach ($trigger_failures as $tf): ?>
          <div class="flex items-center justify-between bg-mist rounded p-3 text-sm">
            <div class="font-semibold"><?= App\Core\Helpers::e($tf['name']) ?></div>
            <div class="text-slate-500"><?= App\Core\Helpers::e($tf['status']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Systems</div>
    <div class="mt-4 space-y-3">
      <?php foreach ($systems as $s): ?>
        <div class="flex items-center justify-between bg-mist rounded p-3">
          <div>
            <div class="font-semibold"><?= App\Core\Helpers::e($s['name']) ?></div>
            <div class="text-sm text-slate-500"><?= App\Core\Helpers::e($s['environment']) ?></div>
          </div>
          <div class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700"><?= App\Core\Helpers::e($s['status']) ?></div>
          <a href="<?= App\Core\Helpers::baseUrl('/systems/' . $s['id']) ?>" class="text-brand">View</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function dashboard() {
  return {
    metrics: { systems_count: 0, backups_24h: 0, failures_24h: 0, storage_used_display: '0 MB', storage_quota_display: '0 MB' },
    events: [],
    init() {
      fetch('<?= App\Core\Helpers::baseUrl('/api/v1/dashboard/metrics') ?>')
        .then(r => r.json())
        .then(data => {
          this.metrics = data;
          this.metrics.storage_used_display = this.formatBytes(data.storage_used);
          this.metrics.storage_quota_display = this.formatBytes(data.storage_quota);
          this.renderCharts();
        });
      this.loadEvents();
      setInterval(() => this.loadEvents(), 5000);
    },
    loadEvents() {
      fetch('<?= App\Core\Helpers::baseUrl('/api/v1/dashboard/latest-events') ?>')
        .then(r => r.json())
        .then(data => { this.events = data.events || []; });
    },
    formatBytes(bytes) {
      const units = ['B','KB','MB','GB','TB'];
      let i = 0; let val = bytes;
      while (val >= 1024 && i < units.length - 1) { val /= 1024; i++; }
      return val.toFixed(1) + ' ' + units[i];
    },
    renderCharts() {
      const storageChart = this.metrics.storage_chart || { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data_mb: [0,0,0,0,0,0,0] };
      const ctx1 = document.getElementById('storageChart');
      new Chart(ctx1, {
        type: 'line',
        data: {
          labels: storageChart.labels || [],
          datasets: [{
            label: 'MB',
            data: storageChart.data_mb || [],
            borderColor: '#0b74de',
            backgroundColor: 'rgba(11,116,222,0.15)',
            fill: true
          }]
        },
        options: { plugins: { legend: { display: false } } }
      });

      const successChart = this.metrics.success_chart || { completed: 0, failed: 0 };
      const hasSuccessData = (successChart.completed + successChart.failed) > 0;
      const ctx2 = document.getElementById('successChart');
      new Chart(ctx2, {
        type: 'doughnut',
        data: {
          labels: hasSuccessData ? ['Success','Failed'] : ['No Data'],
          datasets: [{
            data: hasSuccessData ? [successChart.completed, successChart.failed] : [1],
            backgroundColor: hasSuccessData ? ['#22c55e','#ef4444'] : ['#94a3b8']
          }]
        },
        options: { plugins: { legend: { display: false } } }
      });
    }
  }
}
</script>
<?php $content = ob_get_clean(); $pageTitle = 'Dashboard'; include __DIR__ . '/../layouts/app.php'; ?>
