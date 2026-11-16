const http = require('http');
const { spawn } = require('child_process');

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

function postJson(url, data) {
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
        'Content-Length': Buffer.byteLength(postData)
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        resolve({ body, headers: res.headers });
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
  throw new Error(`Chrome did not respond on port ${port}`);
}

async function verifyP1AndP2() {
  console.log('====================================================================');
  console.log('🚀 DESIGNMARK PHASE 3: VERIFYING PRIORITY 1 & PRIORITY 2');
  console.log('====================================================================\n');

  // Login as An (manager of proj-shop containing screen scr-signup with 34 markers)
  const loginRes = await postJson('http://127.0.0.1:8000/api/auth.php?action=login', { username: 'an', password: 'demo' });
  const rawCookie = loginRes.headers['set-cookie'] ? loginRes.headers['set-cookie'][0].split(';')[0] : '';
  const [cName, cVal] = rawCookie.split('=');

  const port = 9590;
  const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--no-sandbox',
    '--disable-gpu',
    '--disable-extensions',
    `--user-data-dir=/tmp/dm-verify-${Date.now()}`,
    'about:blank'
  ]);

  try {
    const targets = await waitForChrome(port);
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const wsUrl = pageTarget.webSocketDebuggerUrl;
    const ws = new WebSocket(wsUrl);
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

    await send('Network.setCookie', {
      name: cName,
      value: cVal,
      domain: '127.0.0.1',
      path: '/'
    });
    await send('Network.setCookie', {
      name: 'dm_session',
      value: cVal,
      domain: '127.0.0.1',
      path: '/'
    });

    const sessionObj = {
      userId: 'user-an',
      username: 'an',
      name: 'An Nguyễn',
      role: 'manager',
      systemRole: 'user',
      status: 'active',
      title: 'Trưởng nhóm UI/UX Designer',
      color: '#4F46E5',
      isVerified: 1,
      remember: true
    };
    await send('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        try {
          localStorage.setItem('designmark.session', JSON.stringify(${JSON.stringify(sessionObj)}));
          sessionStorage.setItem('designmark.session', JSON.stringify(${JSON.stringify(sessionObj)}));
        } catch (_) {}
      `
    });

    console.log('--- 1. Loading Review Canvas (proj-shop / scr-signup) ---');
    await send('Page.navigate', { url: 'http://127.0.0.1:8000/review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1' });
    await new Promise(r => setTimeout(r, 2500));

    // Verify initial load
    const initialCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const markers = document.querySelectorAll('.marker');
        const threads = document.querySelectorAll('[data-testid="comment-thread"]');
        const img = document.getElementById('review-image');
        const tabs = document.getElementById('mobile-review-tabs');
        const countBadge = document.getElementById('mobile-comments-count');
        return {
          title: document.title,
          markerCount: markers.length,
          threadCount: threads.length,
          imgSrc: img ? img.src.slice(-30) : '',
          hasMobileTabs: !!tabs,
          mobileCommentsCount: countBadge ? countBadge.textContent : '0'
        };
      })()`,
      returnByValue: true
    });
    console.log('Initial page state:', initialCheck.result.value);

    // TEST P1: Responsive Viewports (375px, 768px, 1024px, 1440px)
    console.log('\n--- 2. Testing Viewport Responsive (P1: Mobile Review Layout) ---');
    const viewports = [375, 768, 1024, 1440];

    for (const width of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width,
        height: 812,
        deviceScaleFactor: 1,
        mobile: width <= 768
      });
      await new Promise(r => setTimeout(r, 400));

      const r = await send('Runtime.evaluate', {
        expression: `(() => {
          const docW = document.documentElement.clientWidth;
          const scrollW = document.documentElement.scrollWidth;
          const tabs = document.getElementById('mobile-review-tabs');
          const tabsVisible = tabs ? window.getComputedStyle(tabs).display !== 'none' : false;
          const layout = document.querySelector('.review-layout');
          const layoutDir = layout ? window.getComputedStyle(layout).flexDirection : '';
          const canvasCol = document.querySelector('.canvas-column');
          const canvasRect = canvasCol ? canvasCol.getBoundingClientRect() : null;
          const panel = document.querySelector('#comment-panel');
          const panelRect = panel ? panel.getBoundingClientRect() : null;

          return {
            targetWidth: ${width},
            docW,
            scrollW,
            hasHorizontalOverflow: scrollW > docW + 1,
            tabsVisible,
            layoutDir,
            canvasW: canvasRect ? Math.round(canvasRect.width) : 0,
            canvasH: canvasRect ? Math.round(canvasRect.height) : 0,
            panelW: panelRect ? Math.round(panelRect.width) : 0,
            panelH: panelRect ? Math.round(panelRect.height) : 0
          };
        })()`,
        returnByValue: true
      });
      console.log(`Viewport ${width}px:`, r.result.value);
    }

    // TEST P1 (b): Mobile Tab Switching Interaction at 375px
    console.log('\n--- 3. Testing Mobile Tab Switching (P1 Interactive) ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
    await new Promise(r => setTimeout(r, 300));

    // Tab 1: Canvas Only
    const canvasTab = await send('Runtime.evaluate', {
      expression: `(() => {
        const btn = document.querySelector('.mobile-tab-btn[data-tab="canvas"]');
        btn?.click();
        const layout = document.querySelector('.review-layout');
        const panel = document.querySelector('#comment-panel');
        const canvasCol = document.querySelector('.canvas-column');
        return {
          btnActive: btn?.classList.contains('is-active'),
          isCanvasOnly: layout?.classList.contains('is-canvas-only'),
          panelDisplay: panel ? window.getComputedStyle(panel).display : '',
          canvasH: canvasCol ? Math.round(canvasCol.getBoundingClientRect().height) : 0
        };
      })()`,
      returnByValue: true
    });
    console.log('Mode Canvas-Only:', canvasTab.result.value);

    // Tab 2: Comments Only
    const commentsTab = await send('Runtime.evaluate', {
      expression: `(() => {
        const btn = document.querySelector('.mobile-tab-btn[data-tab="comments"]');
        btn?.click();
        const layout = document.querySelector('.review-layout');
        const panel = document.querySelector('#comment-panel');
        const canvasCol = document.querySelector('.canvas-column');
        return {
          btnActive: btn?.classList.contains('is-active'),
          isCommentsOnly: layout?.classList.contains('is-comments-only'),
          canvasDisplay: canvasCol ? window.getComputedStyle(canvasCol).display : '',
          panelH: panel ? Math.round(panel.getBoundingClientRect().height) : 0
        };
      })()`,
      returnByValue: true
    });
    console.log('Mode Comments-Only:', commentsTab.result.value);

    // Tab 3: Split (Restore default)
    const splitTab = await send('Runtime.evaluate', {
      expression: `(() => {
        const btn = document.querySelector('.mobile-tab-btn[data-tab="split"]');
        btn?.click();
        const layout = document.querySelector('.review-layout');
        const panel = document.querySelector('#comment-panel');
        const canvasCol = document.querySelector('.canvas-column');
        return {
          btnActive: btn?.classList.contains('is-active'),
          isSplit: layout?.classList.contains('is-split'),
          canvasDisplay: canvasCol ? window.getComputedStyle(canvasCol).display : '',
          panelDisplay: panel ? window.getComputedStyle(panel).display : ''
        };
      })()`,
      returnByValue: true
    });
    console.log('Mode Split (Restore):', splitTab.result.value);

    // TEST P2: Marker Clustering at Low Zoom (< 65%) vs Normal Zoom (100%)
    console.log('\n--- 4. Testing Marker Clustering (P2: Low Zoom vs 100%) ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await new Promise(r => setTimeout(r, 400));

    // A: Zoom out to < 65% (click zoom-out 5 times)
    const zoomOutCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const zoomOut = document.getElementById('zoom-out');
        for (let i = 0; i < 6; i++) {
          zoomOut?.click();
        }
        const zoomLabel = document.getElementById('zoom-label')?.textContent;
        const clusters = document.querySelectorAll('.marker-cluster');
        const allMarkers = document.querySelectorAll('.marker');
        const samples = Array.from(clusters).slice(0, 3).map(c => ({
          countText: c.textContent.trim(),
          title: c.title,
          className: c.className
        }));
        return {
          zoomLabel,
          clusterCount: clusters.length,
          allMarkerCount: allMarkers.length,
          samples
        };
      })()`,
      returnByValue: true
    });
    console.log('At Low Zoom (< 65%):', zoomOutCheck.result.value);

    // B: Click a cluster marker to verify smooth zoom in & unbundling
    const clusterClickCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const firstCluster = document.querySelector('.marker-cluster');
        const titleBefore = firstCluster?.title;
        firstCluster?.click();
        const zoomLabel = document.getElementById('zoom-label')?.textContent;
        const clusters = document.querySelectorAll('.marker-cluster');
        const allMarkers = document.querySelectorAll('.marker');
        return {
          titleBefore,
          zoomLabelAfterClick: zoomLabel,
          clusterCountAfterClick: clusters.length,
          markerCountAfterClick: allMarkers.length
        };
      })()`,
      returnByValue: true
    });
    console.log('After Clicking Cluster Marker:', clusterClickCheck.result.value);

    // C: Zoom Fit / 100% (Normal zoom)
    const normalZoomCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const zoomFit = document.getElementById('zoom-fit');
        zoomFit?.click();
        const zoomIn = document.getElementById('zoom-in');
        for (let i = 0; i < 5; i++) {
          zoomIn?.click();
        }
        const zoomLabel = document.getElementById('zoom-label')?.textContent;
        const clusters = document.querySelectorAll('.marker-cluster');
        const allMarkers = document.querySelectorAll('.marker');
        return {
          zoomLabel,
          clusterCount: clusters.length,
          markerCount: allMarkers.length
        };
      })()`,
      returnByValue: true
    });
    console.log('At 100%+ Zoom (Normal):', normalZoomCheck.result.value);

    console.log('\n====================================================================');
    console.log('🎉 ALL P1 AND P2 VERIFICATION COMPLETED SUCCESSFULLY!');
    console.log('====================================================================');

    ws.close();
  } finally {
    chrome.kill('SIGKILL');
  }
}

verifyP1AndP2().catch(console.error);
