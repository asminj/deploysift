<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>DeploySift — Every clue. One diagnosis.</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <style>
    body { margin: 0; background: #0B1220; color: #eaf4ff; font: 15px system-ui; display: flex; align-items: flex-start; }
    aside { width: 230px; min-height: 100vh; padding: 28px; background: #101827; border-right: 1px solid #1e3450; }
    aside a { color: #9fc2e3; display: block; margin: 10px 0; cursor: pointer; text-decoration: none; }
    aside a.on { color: #fff; font-weight: 700; }
    main { padding: 30px; width: 100%; max-width: 1150px; box-sizing: border-box; min-width: 0; }
    .brand { font-weight: 900; letter-spacing: 2px; }
    .brand span { color: #22D3EE; }
    .sub { color: #8aa0bb; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .card { background: #151F30; border: 1px solid #1e3450; border-radius: 12px; padding: 18px; margin-top: 18px; }
    .metric { font-size: 26px; font-weight: 800; }
    .badge { display: inline-block; padding: 2px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: #243b52; }
    .row { padding: 12px; border: 1px solid #1e3450; margin: 8px 0; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; gap: 10px; }
    .row small { color: #8aa0bb; }
    button { padding: 10px 14px; color: #0B1220; border: 0; border-radius: 7px; background: #22D3EE; cursor: pointer; margin: 2px; font-weight: 600; }
    button.danger { background: #F87171; color: #fff; }
    button.ghost { background: #243b52; color: #fff; }
    input, select, textarea { padding: 9px; background: #081526; color: #fff; border: 1px solid #29445f; border-radius: 6px; }
    pre { white-space: pre-wrap; }
    .drop { border: 2px dashed #29445f; border-radius: 10px; padding: 26px; text-align: center; color: #9fc2e3; cursor: pointer; margin-top: 10px; }
    .drop.over { border-color: #167fa9; background: #0a1a2e; color: #fff; }
    .prog { height: 8px; background: #081526; border: 1px solid #29445f; border-radius: 5px; margin-top: 8px; overflow: hidden; }
    .bar { display: block; height: 100%; width: 0; background: #167fa9; }
    .uprow { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .fileitem { border: 1px solid #1e3450; border-radius: 8px; padding: 10px; margin: 8px 0; }
    .fileitem .nm { font-weight: 600; }
    .fileitem small { color: #8aa0bb; }
    .st-ok { color: #7fe3a8; }
    .st-err { color: #ff9a9a; }
    .st-run { color: #7fd0ff; }
    .uperr { color: #ff9a9a; margin-top: 8px; }
    @media (max-width: 800px) {
      body { display: block; }
      aside { width: auto; min-height: auto; }
      .row { flex-direction: column; }
      .uprow { flex-direction: column; align-items: stretch; }
      .uprow select, .uprow button, .uprow input { width: 100%; }
    }
    @media print {
      aside, button, select { display: none; }
      body { display: block; }
      main { padding: 0; max-width: none; }
      .card { border: 0; margin-top: 8px; }
    }
  </style>
</head>
<body>
  <aside>
    <div class="brand">DEPLOY<span>SIFT</span></div>
    <p class="sub">Every clue. One diagnosis.</p>
    <p class="sub">Evidence-based AI investigation for deployment failures.</p>
    <a id="n-dash" onclick="nav('dash')">Dashboard</a>
    <a id="n-inv" onclick="nav('inv')">Investigations</a>
    <a id="n-rep" onclick="nav('rep')">Reports</a>
    <a id="n-ev" onclick="nav('ev')">Evidence</a>
    <a id="n-ai" onclick="nav('ai')">AI Analysis</a>
    <a id="n-set" onclick="nav('set')">Settings</a>
    <hr>
    <div class="sub">Workspace</div>
    <select id="ws" onchange="setWs(this.value)" style="width:100%"></select>
    <div id="authbox"></div>
  </aside>
  <main>
    <div class="sub" id="crumb">Dashboard</div>
    <h1 id="title">Every clue. One diagnosis.</h1>
    <p>
      <button onclick="create()">Start investigation</button>
      <button class="ghost" onclick="demo('php')">Demo: PHP 8.3 vs 8.1</button>
      <button class="ghost" onclick="demo('database')">Demo: DB failure</button>
      <button class="ghost" onclick="demo('vite')">Demo: Vite failure</button>
    </p>
    <div id="modal" style="display:none;position:fixed;inset:0;background:rgba(4,10,20,.7);align-items:center;justify-content:center;z-index:50">
      <div class="card" style="min-width:min(420px,90vw)">
        <h2>New investigation</h2>
        <form onsubmit="event.preventDefault();submitCreate()">
          <p><input id="newname" placeholder="e.g. Production 500 on checkout" style="width:100%;box-sizing:border-box"></p>
          <p><button type="submit">Create</button> <button type="button" class="ghost" onclick="closeModal()">Cancel</button></p>
        </form>
      </div>
    </div>
    <section id="app">
      <div class="card"><p class="sub">Loading…</p></div>
    </section>
  </main>
  <script>
    // ---- State ---------------------------------------------------------
    let view = 'dash';
    let selected = null;
    let highlight = [];
    let activeWs = localStorage.getItem('ws') ? Number(localStorage.getItem('ws')) : null;

    // ---- Helpers -------------------------------------------------------
    const app = document.querySelector('#app');
    const csrf = document.querySelector('meta[name=csrf-token]').content;

    const api = (u, o = {}) => fetch('/api/' + u, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      ...o,
    }).then(async r => {
      const x = await r.json().catch(() => ({}));
      if (!r.ok) throw Error(x.message || ('Request failed: ' + r.status));
      return x;
    });

    const web = (u, o = {}) => fetch(u, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      ...o,
    });

    const e = s => String(s ?? '').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

    const sevC = s => (
      { critical: '#F87171', high: '#F59E0B', medium: '#22D3EE', low: '#818CF8' }[String(s || '').toLowerCase()] || '#9fc2e3'
    );

    const stC = s => (
      { completed: '#22C55E', draft: '#8aa0bb', pending: '#22D3EE', analyzing: '#22D3EE', requires_review: '#F59E0B', failed: '#F87171' }[String(s || '').toLowerCase()] || '#9fc2e3'
    );

    // ---- Navigation ----------------------------------------------------
    function setWs(id) {
      activeWs = Number(id);
      localStorage.setItem('ws', String(id));
      load();
    }

    function nav(v) {
      view = v;
      highlight = [];
      load();
    }

    function gotoEvIds(ids) {
      highlight = Array.isArray(ids) ? ids : [];
      view = 'ev';
      load();
    }

    // ---- Workspace + auth ----------------------------------------------
    async function loadWs() {
      try {
        const xs = await api('workspaces');
        const sel = document.querySelector('#ws');
        sel.innerHTML = xs.map(x => `<option value="${x.id}">${e(x.name)} (${e(x.role)})</option>`).join('')
          || '<option value="">No workspace</option>';
        if (xs.length) {
          if (!xs.find(x => x.id === activeWs)) setWs(xs[0].id);
          sel.value = String(activeWs);
        } else {
          activeWs = null;
        }
      } catch {
        activeWs = null;
      }
      renderAuth();
    }

    async function signOut() {
      await web('/auth/logout', { method: 'POST' });
      localStorage.removeItem('ws');
      location.href = '/';
    }

    async function renderAuth() {
      const box = document.querySelector('#authbox');
      if (!box) return;
      try {
        const me = await api('me');
        box.innerHTML = "<p class=sub>" + e(me.name) + "<br>" + e(me.email) + "</p>"
          + '<p><button class=ghost onclick="signOut()">Sign out</button></p>';
      } catch {
        box.innerHTML = "<p><button onclick=\"location.href='/login'\">Sign in</button> "
          + "<button class=ghost onclick=\"location.href='/register'\">Register</button></p>";
      }
    }

    // ---- Investigations --------------------------------------------------
    function openModal() {
      document.querySelector('#modal').style.display = 'flex';
      document.querySelector('#newname').focus();
    }

    function closeModal() {
      document.querySelector('#modal').style.display = 'none';
    }

    async function submitCreate() {
      const v = document.querySelector('#newname').value.trim();
      if (!v) return;
      closeModal();
      const x = await api('investigations', {
        method: 'POST',
        body: JSON.stringify({ name: v, framework: 'Laravel / PHP', problem: 'Deployment issue', workspace_id: activeWs }),
      });
      selected = x.id;
      view = 'inv';
      load();
    }

    async function create() {
      if (!activeWs) { location.href = '/login'; return; }
      document.querySelector('#newname').value = '';
      openModal();
    }

    async function demo(s) {
      if (!activeWs) { alert('Sign in first.'); return; }
      const x = await api('demo/' + s, { method: 'POST', body: JSON.stringify({ workspace_id: activeWs }) });
      selected = x.id;
      view = 'inv';
      load();
    }

    async function removeInv() {
      if (!selected || !confirm('Delete this investigation?')) return;
      await api('investigations/' + selected, { method: 'DELETE' });
      selected = null;
      load();
    }

    async function delEv(id) {
      if (!confirm('Remove this evidence?')) return;
      await api(`investigations/${selected}/evidence/${id}`, { method: 'DELETE' });
      openI(selected);
    }

    // ---- Evidence upload ---------------------------------------------------
    const UPEXT = ['txt', 'log', 'json', 'lock', 'php', 'js', 'ts', 'png', 'jpg', 'jpeg', 'webp', 'zip'];
    const UPMAX = 10 * 1024 * 1024;
    let upQueue = [];
    let upSeq = 0;

    function upCheck(name, size) {
      const ext = String(name || '').split('.').pop().toLowerCase();
      if (!UPEXT.includes(ext)) return 'Unsupported evidence type.';
      if (size > UPMAX) return 'File exceeds the 10 MB limit.';
      return '';
    }

    function wireDrop() {
      const z = document.querySelector('#evdrop');
      if (!z) return;
      z.addEventListener('click', () => document.querySelector('#evfile').click());
      ['dragenter', 'dragover'].forEach(t => z.addEventListener(t, v => { v.preventDefault(); z.classList.add('over'); }));
      ['dragleave', 'drop'].forEach(t => z.addEventListener(t, v => { v.preventDefault(); z.classList.remove('over'); }));
      z.addEventListener('drop', v => queueUpload(v.dataTransfer.files));
    }

    function queueUpload(files) {
      const err = document.querySelector('#uperr');
      if (err) err.textContent = '';
      if (!selected) {
        if (err) err.textContent = 'Select an investigation first.';
        return;
      }
      Array.from(files || []).forEach(f => {
        const q = { i: upSeq++, file: f, name: f.name, size: f.size, status: 'queued', msg: '', pct: 0 };
        const bad = upCheck(f.name, f.size);
        if (bad) { q.status = 'error'; q.msg = bad; }
        upQueue.push(q);
        if (!bad) uploadOne(q);
      });
      renderUpQueue();
    }

    function renderUpQueue() {
      const q = document.querySelector('#upq');
      if (!q) return;
      q.innerHTML = upQueue.map(o => `
        <div class="fileitem">
          <span class="nm">${e(o.name)}</span> <small>${Math.round(o.size / 1024)} KB</small>
          <span class="${o.status === 'done' ? 'st-ok' : o.status === 'error' ? 'st-err' : 'st-run'}">${e(
            o.status === 'done' ? 'uploaded'
              : o.status === 'error' ? 'failed: ' + o.msg
              : o.status === 'uploading' ? ('uploading ' + o.pct + '%')
              : o.status
          )}</span>
          <div class="prog"><div class="bar" style="width:${o.pct}%"></div></div>
          ${o.status === 'error' ? `<button class="ghost" onclick="retryUpload(${o.i})">Retry</button>` : ''}
        </div>`).join('') || '<p><small>No files queued yet.</small></p>';
    }

    function retryUpload(i) {
      const o = upQueue.find(x => x.i === i);
      if (!o) return;
      const bad = upCheck(o.name, o.size);
      if (bad) { o.status = 'error'; o.msg = bad; renderUpQueue(); return; }
      o.status = 'queued'; o.msg = ''; o.pct = 0;
      uploadOne(o);
    }

    function uploadOne(o) {
      o.status = 'uploading';
      renderUpQueue();
      const fd = new FormData();
      fd.append('file', o.file, o.name);
      const cat = document.querySelector('#evcat');
      fd.append('category', cat ? cat.value : 'project');
      if (activeWs) fd.append('workspace_id', String(activeWs));
      const x = new XMLHttpRequest();
      x.open('POST', '/api/investigations/' + selected + '/upload', true);
      x.setRequestHeader('Accept', 'application/json');
      x.setRequestHeader('X-CSRF-TOKEN', csrf);
      x.withCredentials = true;
      x.upload.onprogress = v => {
        if (v.lengthComputable) o.pct = Math.round(v.loaded / v.total * 100);
        renderUpQueue();
      };
      x.onload = () => {
        let body = {};
        try { body = JSON.parse(x.responseText); } catch {}
        if (x.status === 201) {
          o.status = 'done'; o.pct = 100;
          renderUpQueue();
          refreshEvList();
        } else {
          o.status = 'error';
          o.msg = body.message || ('Upload failed: ' + x.status);
          o.pct = 0;
          renderUpQueue();
        }
      };
      x.onerror = () => { o.status = 'error'; o.msg = 'Network error during upload.'; renderUpQueue(); };
      x.send(fd);
    }

    function renderEvList(ev, err) {
      const l = document.querySelector('#evlist');
      if (!l) return;
      if (err) {
        l.innerHTML = `<p class="uperr">${e(err)}</p>`;
        return;
      }
      l.innerHTML = ev.map(f => `
        <div class="row">
          <span><b>[${e(f.category)}]</b> ${e(f.original_name)} <small>${e(f.state)}</small>${highlight.includes(f.id) ? ' <b>★ linked</b>' : ''}</span>
          <button class="danger" onclick="event.stopPropagation();delEv(${f.id})">Remove</button>
        </div>`).join('') || '<p>No evidence yet. Upload files above.</p>';
    }

    async function refreshEvList() {
      if (!selected || view !== 'ev') return;
      try {
        const ev = await api(`investigations/${selected}/evidence`);
        renderEvList(ev, '');
        const card = document.querySelectorAll('#app .card')[1];
        if (card && card.querySelector('h2')) card.querySelector('h2').textContent = 'Evidence (' + ev.length + ')';
      } catch (err) {
        renderEvList([], err.message || 'Could not load evidence.');
      }
    }

    async function processI() {
      await api('investigations/' + selected + '/process', { method: 'POST', body: '{}' });
      openI(selected);
    }

    function snapCat() {
      const s = document.querySelector('#evcat');
      if (s) window._evcat = s.value;
    }

    function restoreCat() {
      const s = document.querySelector('#evcat');
      if (s) s.value = window._evcat || 'project';
    }

    // ---- Investigation detail ----------------------------------------------
    function uploadPanel(title, intro) {
      return `
        <div class="card">
          <h2>${title}</h2>
          <div class="uprow">
            <label for="evcat">Category</label>
            <select id="evcat">
              <option value="project">project</option>
              <option value="logs">logs</option>
              <option value="hosting">hosting</option>
              <option value="browser">browser</option>
            </select>
            <button onclick="document.querySelector('#evfile').click()">Browse files</button>
            <input type="file" id="evfile" multiple style="display:none" onchange="queueUpload(this.files);this.value=''">
          </div>
          <div id="evdrop" class="drop">
            Drag and drop files here, or click to browse. Allowed: txt, log, json, lock, php, js, ts, png, jpg, jpeg, webp, zip. Max 10 MB each.${intro}
          </div>
          <div id="uperr" class="uperr"></div>
          <div id="upq"></div>
        </div>`;
    }

    async function openI(id) {
      if (selected !== id) upQueue = [];
      selected = id;
      snapCat();
      const x = await api('investigations/' + id);
      document.querySelector('#title').textContent = x.name;

      if (view === 'ev') return openEvidenceView(x);
      if (view === 'ai') return openAiView();
      if (view === 'rep') return openReportView(id);
      return openFindingsView(x);
    }

    async function openEvidenceView(x) {
      let ev = [];
      let evErr = '';
      try {
        ev = await api(`investigations/${x.id}/evidence`);
      } catch (err) {
        evErr = err.message || 'Could not load evidence.';
      }
      app.innerHTML = uploadPanel('Upload evidence', '')
        + `<div class="card"><h2>Evidence (${ev.length})</h2><div id="evlist"></div></div>`;
      wireDrop();
      renderUpQueue();
      renderEvList(ev, evErr);
      restoreCat();
    }

    async function openAiView() {
      const a = await api(`investigations/${selected}/ai`);
      app.innerHTML = `
        <div class="card">
          <h2>AI Analysis</h2>
          <p>Status: ${e(a.status)} (${e(a.provider)})</p>
          <p>${e(a.note)}</p>
        </div>`;
    }

    async function openReportView(id) {
      const r = await api(`investigations/${id}/report`);
      const findings = (r.findings || []).map(f => `
        <div class="row" onclick="gotoEvIds([${(f.evidence_ids || []).join(',')}])">
          <span>
            <b>${e(f.title)}</b> <span style="color:${sevC(f.severity)}">(${e(f.severity)})</span><br>
            <small>${(f.recommended_actions || []).map(a => e(a)).join('<br>')}</small>
          </span>
          <span>evidence</span>
        </div>`).join('') || '<p>No findings.</p>';
      app.innerHTML = `
        <div class="card">
          <h2>Report</h2>
          <p>
            <a href="/api/investigations/${id}/report/export" style="color:#818CF8">Download JSON</a>
            <button onclick="window.print()">Print / PDF</button>
          </p>
          ${findings}
          <h3>Verification checklist</h3>
          <p>${(r.verification_checklist || []).map(c => e(c)).join('<br>')}</p>
          <pre>${e(JSON.stringify(r, null, 2))}</pre>
        </div>`;
    }

    function openFindingsView(x) {
      const findings = (x.findings || []).map(f => `
        <div class="row" onclick="gotoEvIds([${(f.evidence_ids || []).join(',')}])">
          <b>${e(f.title)}</b>
          <span style="color:${sevC(f.severity)}">${e(f.severity)} — view evidence</span>
        </div>`).join('');
      const events = (x.events || []).map(v => `<div><small>${e(v.type)}: ${e(v.message)}</small></div>`).join('')
        || '<small>No events.</small>';
      app.innerHTML = `
        <div class="card">
          <h2>Findings</h2>
          ${findings}
          ${uploadPanel('Add evidence', ' Uploads start automatically.')}
          <p>
            <button onclick="processI()">Run diagnostics</button>
            <button class="danger" onclick="removeInv()">Delete</button>
          </p>
          <h3>Events</h3>
          ${events}
        </div>`;
      wireDrop();
      renderUpQueue();
      restoreCat();
    }

    // ---- Settings ------------------------------------------------------------
    async function openSettings() {
      try {
        const me = await api('me');
        const xs = await api('workspaces');
        app.innerHTML = `
          <div class="card">
            <h2>Profile</h2>
            <p><b>${e(me.name)}</b><br><small>${e(me.email)}</small></p>
            <p><button class="ghost" onclick="signOut()">Sign out</button></p>
          </div>
          <div class="card">
            <h2>Workspaces</h2>
            ${xs.map(x => `<div class="row"><span><b>${e(x.name)}</b></span><span>${e(x.role)}</span></div>`).join('')}
          </div>
          <div class="card">
            <h2>About DeploySift</h2>
            <p class="sub">Every clue. One diagnosis.</p>
            <p>Evidence-based AI investigation for deployment failures.</p>
          </div>`;
      } catch {
        app.innerHTML = `
          <div class="card">
            <p>Sign in to view settings.</p>
            <p><button onclick="location.href='/login'">Sign in</button></p>
          </div>`;
      }
    }

    // ---- Root view -------------------------------------------------------------
    const VIEW_TITLES = { dash: 'Dashboard', inv: 'Investigations', rep: 'Reports', ev: 'Evidence', ai: 'AI Analysis', set: 'Settings' };
    const NAV_IDS = { dash: 'n-dash', inv: 'n-inv', rep: 'n-rep', ev: 'n-ev', ai: 'n-ai', set: 'n-set' };

    async function load() {
      document.querySelectorAll('aside a').forEach(a => a.classList.remove('on'));
      document.querySelector('#' + NAV_IDS[view])?.classList.add('on');
      document.querySelector('#crumb').textContent = VIEW_TITLES[view] || view;
      if (view === 'set') return openSettings();
      if (selected && (view === 'inv' || view === 'ev' || view === 'ai' || view === 'rep')) return openI(selected);

      if (view === 'dash') {
        try {
          const d = await api('dashboard');
          const metric = (label, value) => `<div class="card"><div class="sub">${label}</div><div class="metric">${value}</div></div>`;
          const recent = (d.recent_investigations || []).map(x => `
            <div class="row" onclick="selected=${x.id};view='inv';load()">
              <b>${e(x.name)}</b><span class="badge" style="color:${stC(x.status)}">${e(x.status)}</span>
            </div>`).join('') || 'No investigations yet. Start one above.';
          app.innerHTML = `
            <div class="grid">
              ${metric('Total', d.total)}
              ${metric('Completed', d.completed)}
              ${metric('Needs review', d.requires_review)}
              ${metric('Confirmed', d.confirmed_issues)}
            </div>
            <div class="card"><h2>Recent investigations</h2>${recent}</div>`;
        } catch {
          app.innerHTML = '<div class="card">Sign in to load the dashboard.</div>';
        }
        return;
      }

      try {
        const xs = await api('investigations');
        app.innerHTML = '<div class="card"><h2>Investigations</h2>'
          + (xs.map(x => `
            <div class="row" onclick="selected=${x.id};load()">
              <b>${e(x.name)}</b><span class="badge" style="color:${stC(x.status)}">${e(x.status)}</span>
            </div>`).join('') || 'No investigations yet.')
          + '</div>';
      } catch {
        app.innerHTML = '<div class="card">Sign in to load investigations.</div>';
      }
    }

    // ---- Boot ------------------------------------------------------------------
    (async () => { await loadWs(); await load(); })();
  </script>
</body>
</html>
