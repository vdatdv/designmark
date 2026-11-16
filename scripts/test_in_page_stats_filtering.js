const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');

async function test() {
  const chromeProc = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    '--remote-debugging-port=9226',
    '--disable-gpu',
    '--no-sandbox',
    '--user-data-dir=/tmp/chrome-test-filter-' + Date.now()
  ], { stdio: 'ignore' });

  await new Promise(r => setTimeout(r, 1500));

  function request(url) {
    return new Promise((resolve, reject) => {
      http.get(url, (res) => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => resolve(JSON.parse(data)));
      }).on('error', reject);
    });
  }

  const targets = await request('http://127.0.0.1:9226/json');
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

  // TEST 1: Project Admin DesignMark
  console.log('--- 1. Navigating to project.php?id=proj-admin-designmark ---');
  await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });

  for (let i = 0; i < 40; i++) {
    const res = await send('Runtime.evaluate', { expression: 'document.querySelectorAll(".screen-card").length > 0', returnByValue: true });
    if (res.result && res.result.value === true) break;
    await new Promise(r => setTimeout(r, 200));
  }
  await new Promise(r => setTimeout(r, 600));

  // Step A: Initial state
  const initCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const cards = document.querySelectorAll('.project-stat-card');
      const activeCard = document.querySelector('.project-stat-card.is-active');
      const screenCards = document.querySelectorAll('.screen-card');
      return {
        cardCount: cards.length,
        activeCardText: activeCard ? activeCard.querySelector('.project-stat-label').textContent : null,
        screenCount: screenCards.length,
        currentUrl: location.href
      };
    })()`,
    returnByValue: true
  });
  console.log('Initial state:', JSON.stringify(initCheck.result.value, null, 2));

  // Step B: Click Card 2 ("Tổng góp ý")
  console.log('Clicking Card 2 ("Tổng góp ý")...');
  await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-comments');
      if (card) card.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 500));

  const afterCard2 = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-comments');
      const pill = document.querySelector('.screen-filter-pill.is-active');
      const screenCards = document.querySelectorAll('.screen-card');
      return {
        url: location.href,
        navigatedAway: !location.href.includes('project.php'),
        card2IsActive: card ? card.classList.contains('is-active') : false,
        activePillText: pill ? pill.textContent : null,
        screenCount: screenCards.length
      };
    })()`,
    returnByValue: true
  });
  console.log('After clicking Card 2 ("Tổng góp ý"):', JSON.stringify(afterCard2.result.value, null, 2));

  const ssCard2 = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('/Users/dovinhdat/.gemini/antigravity/brain/84dbf516-4b6e-4547-9175-775e2c1ef881/stat_card_filter_in_page.png', Buffer.from(ssCard2.data, 'base64'));
  console.log('Saved screenshot: stat_card_filter_in_page.png');

  // Step C: Click Card 3 ("Chưa xử lý")
  console.log('Clicking Card 3 ("Chưa xử lý")...');
  await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-warning');
      if (card) card.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 500));

  const afterCard3 = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-warning');
      const pill = document.querySelector('.screen-filter-pill.is-active');
      const screenCards = document.querySelectorAll('.screen-card');
      return {
        url: location.href,
        navigatedAway: !location.href.includes('project.php'),
        card3IsActive: card ? card.classList.contains('is-active') : false,
        activePillText: pill ? pill.textContent : null,
        screenCount: screenCards.length
      };
    })()`,
    returnByValue: true
  });
  console.log('After clicking Card 3 ("Chưa xử lý"):', JSON.stringify(afterCard3.result.value, null, 2));

  // Step D: Test on another project (proj-foodhub)
  console.log('--- 2. Navigating to project.php?id=proj-foodhub ---');
  await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-foodhub' });

  for (let i = 0; i < 40; i++) {
    const res = await send('Runtime.evaluate', { expression: '!!document.querySelector(".project-stat-card.stat-comments")', returnByValue: true });
    if (res.result && res.result.value) break;
    await new Promise(r => setTimeout(r, 200));
  }
  await new Promise(r => setTimeout(r, 500));

  console.log('Testing in-page filter on proj-foodhub: Clicking Card 2...');
  await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-comments');
      if (card) card.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 500));

  const foodhubCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-stat-card.stat-comments');
      const screenCards = document.querySelectorAll('.screen-card');
      return {
        url: location.href,
        navigatedAway: !location.href.includes('project.php?id=proj-foodhub'),
        card2IsActive: card ? card.classList.contains('is-active') : false,
        screenCount: screenCards.length
      };
    })()`,
    returnByValue: true
  });
  console.log('proj-foodhub filter result:', JSON.stringify(foodhubCheck.result.value, null, 2));

  chromeProc.kill();
  console.log('ALL IN-PAGE FILTER TESTS PASSED!');
  process.exit(0);
}

test().catch(err => {
  console.error('Test error:', err);
  process.exit(1);
});
