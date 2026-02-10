<?php ob_start(); ?>
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-display font-semibold"><?= App\Core\Helpers::e($system['name']) ?></h1>
    <div class="text-slate-500 text-sm"><?= App\Core\Helpers::e($system['environment']) ?> | <?= App\Core\Helpers::e($system['timezone']) ?></div>
  </div>
  <div class="flex items-center gap-3">
    <?php if (empty($entitlements['active'])): ?>
      <div class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">Billing required</div>
    <?php else: ?>
      <div class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700"><?= App\Core\Helpers::e($system['status']) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/delete') ?>" onsubmit="return confirm('Delete this system and all its backups? This cannot be undone.');">
      <?= App\Core\Helpers::csrfField() ?>
      <button class="text-xs px-3 py-2 rounded border border-red-300 text-red-600 hover:bg-red-50">Delete System</button>
    </form>
  </div>
</div>

<?php if (empty($entitlements['active'])): ?>
  <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3">
    Billing required: trigger, upload, and download actions are blocked until subscription is active.
  </div>
<?php endif; ?>

<?php if (!empty($_GET['new'])): ?>
  <div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Next steps</div>
    <ol class="mt-2 text-sm text-slate-600 list-decimal ml-5">
      <li>Download the agent bundle and upload it to your server.</li>
      <li>Edit `secureon-agent-config.php` with DB credentials.</li>
      <li>Set the trigger URL below once the trigger file is reachable.</li>
      <li>Click Test Trigger.</li>
    </ol>
    <div class="mt-3">
      <a class="bg-brand text-white px-4 py-2 rounded" href="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/download-agent') ?>">Download Agent Bundle</a>
    </div>
  </div>
<?php endif; ?>

<div class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm" x-data="systemStatus('<?= $system['id'] ?>')" x-init="start()">
    <div class="font-semibold">Latest backup status</div>
    <div class="mt-2 text-slate-600" x-text="statusText"></div>
    <div class="mt-3 h-2 bg-slate-200 rounded">
      <div class="h-2 bg-brand rounded" :style="'width:' + progress + '%'"></div>
    </div>
    <div class="mt-3 text-xs text-slate-500">Next scheduled trigger: <?= App\Core\Helpers::e($next_trigger ?? 'N/A') ?></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Trigger status</div>
    <div class="mt-2 text-sm text-slate-600">Last trigger: <?= App\Core\Helpers::e($system['last_trigger_at'] ?? 'N/A') ?></div>
    <div class="text-sm text-slate-600">Status: <?= App\Core\Helpers::e($system['last_trigger_status'] ?? 'N/A') ?></div>
    <div class="text-sm text-slate-600">Latency: <?= App\Core\Helpers::e($system['last_trigger_latency_ms'] ?? 'N/A') ?> ms</div>
    <div class="text-sm text-slate-600">Message: <?= App\Core\Helpers::e($system['last_trigger_message'] ?? '') ?></div>
    <div class="mt-3 flex gap-3">
      <button class="bg-brand text-white px-3 py-2 rounded" onclick="triggerNow(<?= $system['id'] ?>)">Trigger Now</button>
      <button class="border border-slate-300 px-3 py-2 rounded" onclick="testTrigger(<?= $system['id'] ?>)">Test Trigger</button>
    </div>
    <div id="trigger-result" class="mt-2 text-sm text-slate-600"></div>
  </div>
</div>

<div class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Agent bundle</div>
    <div class="mt-2 text-sm text-slate-600">Trigger filename: <span class="text-brand"><?= App\Core\Helpers::e($system['trigger_path']) ?>.php</span></div>
    <div class="mt-1 text-sm text-slate-600">Example URL: https://clientdomain.com/secureon-agent/<?= App\Core\Helpers::e($system['trigger_path']) ?>.php</div>
    <div class="mt-4 grid md:grid-cols-2 gap-3">
      <input id="bundle-db-host" type="text" value="127.0.0.1" class="px-3 py-2 rounded border border-slate-300" placeholder="DB Host">
      <input id="bundle-db-user" type="text" value="root" class="px-3 py-2 rounded border border-slate-300" placeholder="DB User">
      <input id="bundle-db-pass" type="text" value="" class="px-3 py-2 rounded border border-slate-300" placeholder="DB Password">
      <input id="bundle-db-name" type="text" value="your_db_name" class="px-3 py-2 rounded border border-slate-300" placeholder="DB Name">
      <input id="bundle-mysqldump-path" type="text" value="mysqldump" class="px-3 py-2 rounded border border-slate-300" placeholder="mysqldump path">
      <input id="bundle-mysql-path" type="text" value="mysql" class="px-3 py-2 rounded border border-slate-300" placeholder="mysql path">
    </div>
    <div class="mt-3 flex items-center gap-3">
      <button class="bg-brand text-white px-4 py-2 rounded" onclick="prepareBundle(<?= $system['id'] ?>)">Prepare + Download Bundle</button>
      <a class="border border-slate-300 px-4 py-2 rounded" href="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/download-agent') ?>">Download with placeholders</a>
    </div>
    <div id="bundle-result" class="mt-2 text-sm text-slate-600"></div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Trigger URL + Allowlist</div>
    <form method="post" action="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/trigger-config') ?>" class="mt-3 space-y-3">
      <?= App\Core\Helpers::csrfField() ?>
      <input name="trigger_url" type="text" value="<?= App\Core\Helpers::e($system['trigger_url'] ?? '') ?>" placeholder="https://clientdomain.com/.../trigger-xxxx.php" class="w-full px-3 py-2 rounded border border-slate-300">
      <input name="agent_ip_allowlist" type="text" value="<?= App\Core\Helpers::e($system['agent_ip_allowlist'] ?? '') ?>" placeholder="Allowlist IPs (comma-separated, optional)" class="w-full px-3 py-2 rounded border border-slate-300">
      <button class="bg-brand text-white px-4 py-2 rounded">Save</button>
    </form>
  </div>
</div>

<div class="mt-6 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="flex items-center justify-between">
    <div class="font-semibold">Secureon Status Badge</div>
    <div class="text-xs text-slate-500">Lightweight include for client platforms</div>
  </div>
  <div class="mt-3 text-sm text-slate-600">
    Upload <code>secureon-badge.php</code> to your target platform, then include it in your header.
  </div>
  <div class="mt-2 rounded bg-slate-100 p-3 text-xs text-slate-700">
    &lt;?php include __DIR__ . '/includes/secureon-badge.php'; ?&gt;
  </div>

  <div class="mt-4 grid md:grid-cols-2 gap-6">
    <div class="space-y-3">
      <form method="post" action="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/badge-token/create') ?>">
        <?= App\Core\Helpers::csrfField() ?>
        <button class="bg-brand text-white px-4 py-2 rounded">Generate Badge Token</button>
      </form>
      <form method="post" action="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/badge-token/revoke') ?>" onsubmit="return confirm('Revoke all badge tokens for this system? Existing badge scripts will stop working.');">
        <?= App\Core\Helpers::csrfField() ?>
        <button class="border border-red-300 text-red-600 px-4 py-2 rounded hover:bg-red-50">Revoke Badge Tokens</button>
      </form>
      <?php if (!empty($badge_plain_token)): ?>
        <div class="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm">
          <div class="font-semibold text-emerald-700">New badge token (shown once):</div>
          <div class="mt-1 break-all text-emerald-800"><?= App\Core\Helpers::e($badge_plain_token) ?></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($badge_token_preview)): ?>
        <div class="text-xs text-slate-500">Latest active badge token: <?= App\Core\Helpers::e($badge_token_preview['token_prefix'] ?? '') ?>**** created <?= App\Core\Helpers::e($badge_token_preview['created_at'] ?? '') ?></div>
      <?php endif; ?>
    </div>

    <form method="get" action="<?= App\Core\Helpers::baseUrl('/systems/' . $system['id'] . '/download-badge') ?>" class="space-y-3">
      <div>
        <label class="text-xs text-slate-500">Position</label>
        <select name="position" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
          <option value="bottom-right" selected>Bottom Right</option>
          <option value="bottom-left">Bottom Left</option>
          <option value="top-right">Top Right</option>
          <option value="top-left">Top Left</option>
        </select>
      </div>
      <div>
        <label class="text-xs text-slate-500">Theme</label>
        <select name="theme" class="mt-1 w-full px-3 py-2 rounded border border-slate-300">
          <option value="auto" selected>Auto</option>
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="link_to_dashboard" value="0">
        <input type="checkbox" name="link_to_dashboard" value="1" checked> Link to dashboard
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="minimal_mode" value="0">
        <input type="checkbox" name="minimal_mode" value="1" checked> Minimal mode
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="show_powered_by" value="0">
        <input type="checkbox" name="show_powered_by" value="1" checked> Show "Powered by Secureon"
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="show_tooltip" value="0">
        <input type="checkbox" name="show_tooltip" value="1" checked> Show tooltip on hover
      </label>
      <button class="bg-brand text-white px-4 py-2 rounded">Download secureon-badge.php</button>
    </form>
  </div>
</div>

<div class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Tokens</div>
    <button class="mt-3 bg-brand text-white px-3 py-2 rounded" onclick="createToken(<?= $system['id'] ?>)">Create token</button>
    <div id="token-result" class="mt-2 text-sm text-emerald-700"></div>
    <div class="mt-4 space-y-2">
      <?php foreach ($tokens as $t): ?>
        <div class="text-sm text-slate-500">
          <?= App\Core\Helpers::e($t['token_prefix']) ?>****
          (<?= App\Core\Helpers::e($t['token_type'] ?? 'agent') ?>)
          created <?= App\Core\Helpers::e($t['created_at']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
    <div class="font-semibold">Restore instructions</div>
    <div class="mt-2 text-slate-600 text-sm">Download a backup or run:</div>
    <div class="mt-2 text-xs text-slate-500">php secureon-agent.php restore --backup-id=XYZ --target-db=yourdb</div>
  </div>
</div>

<div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
  <div class="font-semibold">Backups</div>
  <div class="mt-4 space-y-2">
    <?php foreach ($backups as $b): ?>
      <div class="flex items-center justify-between bg-mist rounded p-3">
        <div>
          <div class="font-semibold">Backup #<?= $b['id'] ?></div>
          <div class="text-slate-500 text-sm"><?= App\Core\Helpers::e($b['status']) ?> | <?= App\Core\Helpers::e($b['created_at']) ?></div>
        </div>
        <div class="flex gap-3">
          <button class="text-brand" onclick="signDownload(<?= $b['id'] ?>)">Download .scx</button>
          <a class="text-emerald-700" href="<?= App\Core\Helpers::baseUrl('/backups/' . $b['id'] . '/download-sql') ?>">Download .sql</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function systemStatus(id) {
  return {
    statusText: 'Waiting for updates...',
    progress: 0,
    start() {
      this.fetchStatus();
      setInterval(() => this.fetchStatus(), 5000);
    },
    fetchStatus() {
      fetch('<?= App\Core\Helpers::baseUrl('/api/v1/systems/') ?>' + id + '/latest-status')
        .then(r => r.json())
        .then(data => {
          if (!data || !data.status) return;
          this.statusText = data.status + ' - ' + (data.message || '');
          this.progress = data.status === 'RUNNING' ? 60 : (data.status === 'COMPLETED' ? 100 : 20);
        });
    }
  }
}

function createToken(id) {
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/systems/') ?>' + id + '/tokens', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (data.token) {
        document.getElementById('token-result').innerText = 'New token: ' + data.token;
      }
    });
}

function signDownload(id) {
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/backups/') ?>' + id + '/sign-download', { method: 'POST' })
    .then(r => r.json())
    .then(data => { if (data.url) window.location = data.url; });
}

function triggerNow(id) {
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/systems/') ?>' + id + '/trigger-now', { method: 'POST' })
    .then(r => r.json())
    .then(data => { document.getElementById('trigger-result').innerText = data.message || 'Triggered'; });
}

function testTrigger(id) {
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/systems/') ?>' + id + '/test-trigger', { method: 'POST' })
    .then(r => r.json())
    .then(data => { document.getElementById('trigger-result').innerText = data.message || 'Test sent'; });
}

function prepareBundle(id) {
  const payload = {
    db_host: document.getElementById('bundle-db-host').value,
    db_user: document.getElementById('bundle-db-user').value,
    db_pass: document.getElementById('bundle-db-pass').value,
    db_name: document.getElementById('bundle-db-name').value,
    mysqldump_path: document.getElementById('bundle-mysqldump-path').value,
    mysql_path: document.getElementById('bundle-mysql-path').value
  };
  document.getElementById('bundle-result').innerText = 'Preparing bundle...';
  fetch('<?= App\Core\Helpers::baseUrl('/api/v1/systems/') ?>' + id + '/prepare-agent-bundle', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF': '<?= App\Core\CSRF::token() ?>'
    },
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(data => {
      if (data.download_url) {
        document.getElementById('bundle-result').innerText = 'Bundle ready. Downloading...';
        window.location = data.download_url;
      } else {
        document.getElementById('bundle-result').innerText = data.error || data.message || 'Failed to prepare bundle';
      }
    })
    .catch(() => { document.getElementById('bundle-result').innerText = 'Request failed'; });
}
</script>
<?php $content = ob_get_clean(); $pageTitle = 'System Details'; include __DIR__ . '/../layouts/app.php'; ?>
