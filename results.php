<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Live Results';
require __DIR__ . '/includes/site_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0" id="resultsTitle">Live Results</h3>
  <span class="text-muted small">Auto-refreshes every 5 seconds — last updated <span id="updatedAt">—</span></span>
</div>

<div id="resultsContainer">
  <div class="text-center text-muted py-5">Loading results…</div>
</div>

<script>
const DATA_URL = '<?php echo BASE_URL; ?>/results_data.php';
const container = document.getElementById('resultsContainer');
const titleEl = document.getElementById('resultsTitle');
const updatedEl = document.getElementById('updatedAt');

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

async function loadResults() {
  try {
    const res = await fetch(DATA_URL, { cache: 'no-store' });
    const data = await res.json();

    if (!data.visible) {
      container.innerHTML = '<div class="alert alert-secondary">Results are not currently public.</div>';
      return;
    }

    titleEl.textContent = data.title + ' — Results';
    updatedEl.textContent = data.updated_at;

    let html = '';
    const positions = data.positions || {};
    const posNames = Object.keys(positions);

    if (posNames.length === 0) {
      html = '<div class="text-muted py-5 text-center">No results to display yet.</div>';
    }

    for (const posName of posNames) {
      const candidates = positions[posName];
      const maxVotes = Math.max(1, ...candidates.map(c => c.total_votes));

      html += `<div class="card mb-4"><div class="card-header"><strong>${escapeHtml(posName)}</strong></div><div class="card-body">`;
      for (const c of candidates) {
        const pct = Math.round((c.total_votes / maxVotes) * 100);
        html += `
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>${escapeHtml(c.candidate_name)}</span>
              <span class="fw-bold">${c.total_votes}</span>
            </div>
            <div class="bg-light rounded"><div class="results-bar" style="width:${pct}%"></div></div>
          </div>`;
      }
      html += `</div></div>`;
    }

    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = '<div class="alert alert-danger">Could not load results right now.</div>';
  }
}

loadResults();
setInterval(loadResults, 5000);
</script>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
