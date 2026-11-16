const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');

async function test() {
  const chromeProc = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--disable-gpu',
    '--no-sandbox',
    '--user-data-dir=/tmp/chrome-test-trash-' + Date.now()
  ], { stdio: 'ignore' });

  await new Promise(r => setTimeout(r, 2000));

  function request(url) {
    return new Promise((resolve, reject) => {
      http.get(url, (res) => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => resolve(JSON.parse(data)));
      }).on('error', reject);
    });
  }

  const targets = await request('http://127.0.0.1:9222/json');
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

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const msgId = id++;
      callbacks.set(msgId, { resolve, reject });
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Network.enable');

  const loginRes = await fetch('http://localhost:8000/api/auth.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'admin', password: 'demo' })
  });
  const setCookie = loginRes.headers.get('set-cookie') || '';
  const match = setCookie.match(/PHPSESSID=([^;]+)/);
  const sessId = match ? match[1] : '';

  await send('Network.setCookie', {
    name: 'PHPSESSID',
    value: sessId,
    url: 'http://localhost:8000'
  });

  await send('Emulation.setDeviceMetricsOverride', {
    width: 1280,
    height: 800,
    deviceScaleFactor: 2,
    mobile: false
  });

  // TEST 1: Check project.php kebab menu & "Chuyển vào thùng rác"
  console.log('--- 1. Navigating to project.php?id=proj-admin-designmark ---');
  await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });

  for (let i = 0; i < 40; i++) {
    const res = await send('Runtime.evaluate', { expression: '!!document.querySelector(".project-head-card")', returnByValue: true });
    if (res.result && res.result.value) break;
    await new Promise(r => setTimeout(r, 200));
  }
  await new Promise(r => setTimeout(r, 600));

  // Open kebab menu
  console.log('Clicking 3-dots button...');
  await send('Runtime.evaluate', {
    expression: `(() => {
      const btn = document.querySelector('.project-kebab-btn');
      if (btn) btn.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 400));

  const kebabCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-head-card');
      const menu = document.querySelector('.project-dropdown-menu');
      const trashItem = menu ? menu.querySelector('.project-dropdown-item.is-danger') : null;
      const cardRect = card ? card.getBoundingClientRect() : null;
      const menuRect = menu ? menu.getBoundingClientRect() : null;
      const trashRect = trashItem ? trashItem.getBoundingClientRect() : null;
      const cardOverflow = window.getComputedStyle(card).overflow;

      return {
        cardOverflow,
        menuOpen: menu ? menu.classList.contains('is-open') : false,
        trashText: trashItem ? trashItem.textContent.trim() : null,
        trashItemVisible: !!trashItem && trashRect.height > 20,
        menuExtendsBelowCard: menuRect && cardRect ? (menuRect.bottom > cardRect.bottom) : null,
        menuBottom: menuRect ? menuRect.bottom : null,
        cardBottom: cardRect ? cardRect.bottom : null
      };
    })()`,
    returnByValue: true
  });
  console.log('Kebab Menu & Trash Button Check:', JSON.stringify(kebabCheck.result.value, null, 2));

  // Capture screenshot of open dropdown menu
  const ssKebab = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('/Users/dovinhdat/.gemini/antigravity/brain/84dbf516-4b6e-4547-9175-775e2c1ef881/kebab_menu_not_cut_off.png', Buffer.from(ssKebab.data, 'base64'));
  console.log('Saved screenshot: kebab_menu_not_cut_off.png');

  // TEST 2: Navigate to feedback.php?project=proj-admin-designmark
  console.log('--- 2. Navigating to feedback.php?project=proj-admin-designmark ---');
  await send('Page.navigate', { url: 'http://localhost:8000/feedback.php?project=proj-admin-designmark' });

  for (let i = 0; i < 40; i++) {
    const res = await send('Runtime.evaluate', { expression: '!!document.querySelector(".feedback-page-head")', returnByValue: true });
    if (res.result && res.result.value) break;
    await new Promise(r => setTimeout(r, 200));
  }
  await new Promise(r => setTimeout(r, 800));

  const fbCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const banner = document.querySelector('.active-project-filter-banner');
      const items = Array.from(document.querySelectorAll('.accordion-item'));
      const activeItem = items.find(it => it.querySelector('.accordion-body'));
      const title = document.querySelector('.feedback-title');
      return {
        hasBanner: !!banner,
        bannerText: banner ? banner.textContent.trim() : null,
        projectCount: items.length,
        projectNames: items.map(it => it.querySelector('.accordion-project-name')?.textContent?.trim()),
        activeProjectOpen: !!activeItem,
        feedbackTitle: title ? title.textContent.trim() : null
      };
    })()`,
    returnByValue: true
  });
  console.log('Feedback Filter by Current Project Check:', JSON.stringify(fbCheck.result.value, null, 2));

  // Capture screenshot of feedback.php filtered by project
  const ssFeedback = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('/Users/dovinhdat/.gemini/antigravity/brain/84dbf516-4b6e-4547-9175-775e2c1ef881/feedback_filtered_current_project.png', Buffer.from(ssFeedback.data, 'base64'));
  console.log('Saved screenshot: feedback_filtered_current_project.png');

  chromeProc.kill('SIGKILL');
  process.exit(0);
}

test().catch(err => {
  console.error('Test error:', err);
  process.exit(1);
});
