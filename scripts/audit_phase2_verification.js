const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');

function getJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); } catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

function postJson(url, data, cookies = '') {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const postData = JSON.stringify(data);
    const req = http.request({
      hostname: u.hostname,
      port: u.port,
      path: u.pathname + u.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData),
        ...(cookies ? { 'Cookie': cookies } : {})
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        const setCookie = res.headers['set-cookie'];
        let cookieStr = '';
        if (setCookie) {
          cookieStr = setCookie.map(c => c.split(';')[0]).join('; ');
        }
        try {
          resolve({ status: res.statusCode, json: JSON.parse(body), cookies: cookieStr });
        } catch (_) {
          resolve({ status: res.statusCode, text: body, cookies: cookieStr });
        }
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

async function waitForChrome(port, maxTries = 30) {
  for (let i = 0; i < maxTries; i++) {
    try {
      const list = await getJson(`http://127.0.0.1:${port}/json`);
      if (list && list.length > 0) return list;
    } catch (_) { }
    await new Promise(r => setTimeout(r, 200));
  }
  throw new Error('Chrome did not respond on port ' + port);
}

async function runAudit() {
  console.log('====================================================================');
  console.log('🔍 DESIGNMARK FULL SYSTEM AUDIT & VERIFICATION SUITE');
  console.log('====================================================================\n');

  const auditResults = {};

  // 1. API Role & Auth Verification
  console.log('--- 1. Testing Auth & RBAC via API ---');
  const loginAdmin = await postJson('http://127.0.0.1:8000/api/auth.php?action=login', { username: 'admin', password: 'demo' });
  const loginAn = await postJson('http://127.0.0.1:8000/api/auth.php?action=login', { username: 'an', password: 'demo' });
  const loginBinh = await postJson('http://127.0.0.1:8000/api/auth.php?action=login', { username: 'binh', password: 'demo' });
  const loginChi = await postJson('http://127.0.0.1:8000/api/auth.php?action=login', { username: 'chi', password: 'demo' });

  auditResults.auth = {
    admin: loginAdmin.status === 200 && (loginAdmin.json.systemRole === 'admin' || loginAdmin.json.user?.systemRole === 'admin'),
    an: loginAn.status === 200 && (loginAn.json.username === 'an' || loginAn.json.user?.username === 'an'),
    binh: loginBinh.status === 200 && (loginBinh.json.username === 'binh' || loginBinh.json.user?.username === 'binh'),
    chi: loginChi.status === 200 && (loginChi.json.username === 'chi' || loginChi.json.user?.username === 'chi')
  };
  console.log('Auth check:', auditResults.auth);

  // Viewer cannot create annotation
  const viewerCreate = await postJson('http://127.0.0.1:8000/api/annotations.php?action=create', {
    imageVersionId: 'ver-signup-1',
    type: 'point',
    x: 100, y: 100,
    body: 'Viewer audit attempt'
  }, loginChi.cookies);

  auditResults.rbac = {
    viewerBlockedCreate: viewerCreate.status === 403
  };
  console.log('RBAC check:', auditResults.rbac);

  // 2. Headless Chrome Browser UI Audit
  console.log('\n--- 2. Launching Chrome for Real UI Verification ---');
  const port = 9444;
  const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--no-sandbox',
    '--disable-gpu',
    '--user-data-dir=/tmp/dm-audit-' + Date.now(),
    'about:blank'
  ]);

  try {
    const targets = await waitForChrome(port);
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);

    let id = 1;
    const callbacks = new Map();
    ws.onmessage = (event) => {
      const res = JSON.parse(event.data);
      if (res.id && callbacks.has(res.id)) {
        const { resolve, reject } = callbacks.get(res.id);
        callbacks.delete(res.id);
        if (res.error) reject(res.error);
        else resolve(res.result);
      }
    };

    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const curId = id++;
      callbacks.set(curId, { resolve, reject });
      ws.send(JSON.stringify({ id: curId, method, params }));
    });

    await new Promise(r => ws.onopen = r);
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');

    // Set cookie for admin session
    const rawCookie = loginAdmin.cookies.split(';')[0];
    const [cName, cVal] = rawCookie.split('=');
    await send('Network.setCookie', {
      name: cName,
      value: cVal,
      domain: '127.0.0.1',
      path: '/'
    });

    // TEST: Dashboard UI
    console.log('Navigating to dashboard.php...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8000/dashboard.php' });
    await new Promise(r => setTimeout(r, 2000));

    const dashboardCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const titleRow = document.querySelector('.dashboard-title-row');
        const h2 = titleRow?.querySelector('h2')?.textContent || '';
        const pendingRows = document.querySelectorAll('.feedback-row');
        const hasMyBadge = Array.from(document.querySelectorAll('.badge-info')).some(b => b.textContent.includes('Của tôi'));
        const statCards = document.querySelectorAll('.stat-card');
        return {
          headerText: h2,
          pendingCount: pendingRows.length,
          hasMyBadge,
          statCardCount: statCards.length
        };
      })()`,
      returnByValue: true
    });
    auditResults.dashboard = dashboardCheck.result.value;
    console.log('Dashboard UI Check:', auditResults.dashboard);

    // TEST: Projects Page & Back Navigation Filter Persistence
    console.log('Navigating to projects.php...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8000/projects.php' });
    await new Promise(r => setTimeout(r, 2000));

    // Type query "Mobile" in search
    await send('Runtime.evaluate', {
      expression: `(() => {
        const input = document.querySelector('.projects-search-wrap input');
        if (input) {
          input.value = 'Mobile';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      })()`
    });
    await new Promise(r => setTimeout(r, 500));

    const projectsFilterCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const urlParams = new URLSearchParams(location.search);
        return {
          urlHasQuery: urlParams.get('q') === 'Mobile',
          inputValue: document.querySelector('.projects-search-wrap input')?.value
        };
      })()`,
      returnByValue: true
    });
    auditResults.projectsFilter = projectsFilterCheck.result.value;
    console.log('Projects Search & URL State Check:', auditResults.projectsFilter);

    // TEST: Project Detail (project.php) Dropdown & Stat Filter
    console.log('Navigating to project.php?id=proj-admin-designmark...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8000/project.php?id=proj-admin-designmark' });
    await new Promise(r => setTimeout(r, 2500));

    const projectDetailCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const moreBtn = document.querySelector('.project-kebab-btn');
        if (moreBtn) moreBtn.click();
        const dropdown = document.querySelector('.project-dropdown-menu');
        const headCard = document.querySelector('.project-head-card');
        const dropRect = dropdown?.getBoundingClientRect();
        const headRect = headCard?.getBoundingClientRect();
        
        // Check stat cards
        const statCards = document.querySelectorAll('.project-stats-grid .stat-box');
        
        return {
          kebabOpen: dropdown?.classList.contains('is-open'),
          headCardOverflow: headCard ? getComputedStyle(headCard).overflow : '',
          dropdownBottom: dropRect ? dropRect.bottom : 0,
          headCardBottom: headRect ? headRect.bottom : 0,
          statCardCount: statCards.length
        };
      })()`,
      returnByValue: true
    });
    auditResults.projectDetail = projectDetailCheck.result.value;
    console.log('Project Detail UI Check:', auditResults.projectDetail);

    // Check Upload Dropzone Modal
    const uploadModalCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const uploadBtn = document.querySelector('[data-testid="upload-image"]');
        if (uploadBtn) uploadBtn.click();
        const dropzone = document.querySelector('.file-dropzone-box');
        const modal = document.querySelector('.modal-card');
        return {
          modalOpen: !!modal,
          dropzoneExists: !!dropzone
        };
      })()`,
      returnByValue: true
    });
    auditResults.uploadModal = uploadModalCheck.result.value;
    console.log('Upload Dropzone Modal Check:', auditResults.uploadModal);

    // Close modal
    await send('Runtime.evaluate', {
      expression: `(() => {
        const cancelBtn = document.querySelector('.modal-actions .btn-secondary');
        if (cancelBtn) cancelBtn.click();
      })()`
    });
    await new Promise(r => setTimeout(r, 300));

    // TEST: Review Canvas (review.php)
    console.log('Navigating to review.php?project=proj-admin-designmark&screen=scr-proj-admin-designmark-1&version=ver-scr-proj-admin-designmark-1-1...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8000/review.php?project=proj-admin-designmark&screen=scr-proj-admin-designmark-1&version=ver-scr-proj-admin-designmark-1-1' });
    await new Promise(r => setTimeout(r, 2500));

    const reviewCanvasCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const toggleBtn = document.getElementById('toggle-markers-btn');
        const toggleLabel = document.getElementById('toggle-markers-label')?.textContent;
        const toolSelect = document.getElementById('tool-select');
        const toolPoint = document.getElementById('tool-point');
        const toolRect = document.getElementById('tool-rectangle');
        const overlay = document.getElementById('image-overlay');
        const markers = document.querySelectorAll('.marker');
        const threads = document.querySelectorAll('[data-testid="comment-thread"]');
        
        // Test toggle marker
        if (toggleBtn) toggleBtn.click();
        const isHiddenAfterClick = overlay?.classList.contains('markers-hidden');
        if (toggleBtn) toggleBtn.click(); // restore
        
        return {
          hasToggleBtn: !!toggleBtn,
          toggleLabel,
          hasPointTool: !!toolPoint,
          hasRectTool: !!toolRect,
          markerCount: markers.length,
          threadCount: threads.length,
          toggleHiddenClassToggled: isHiddenAfterClick
        };
      })()`,
      returnByValue: true
    });
    auditResults.reviewCanvas = reviewCanvasCheck.result.value;
    console.log('Review Canvas Check:', auditResults.reviewCanvas);

    // TEST: Responsive Viewports (375px, 768px, 1024px, 1440px)
    console.log('\n--- 3. Testing Viewport Dimensions ---');
    const viewports = [375, 768, 1024, 1440];
    auditResults.responsive = {};

    for (const width of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width,
        height: 812,
        deviceScaleFactor: 1,
        mobile: width <= 768
      });
      await new Promise(r => setTimeout(r, 300));
      const overflowCheck = await send('Runtime.evaluate', {
        expression: `(() => {
          return {
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            hasHorizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2
          };
        })()`,
        returnByValue: true
      });
      auditResults.responsive[width] = overflowCheck.result.value;
      console.log(`Viewport ${width}px:`, overflowCheck.result.value);
    }

    ws.close();
  } finally {
    chrome.kill('SIGKILL');
  }

  console.log('\n====================================================================');
  console.log('📊 AUDIT SUMMARY COMPLETED SUCCESSFULLY');
  console.log('====================================================================');
  return auditResults;
}

runAudit().then(r => {
  fs.writeFileSync(__dirname + '/audit_evidence.json', JSON.stringify(r, null, 2));
  console.log('Evidence saved to scripts/audit_evidence.json');
}).catch(err => {
  console.error('Audit failed:', err);
  process.exit(1);
});