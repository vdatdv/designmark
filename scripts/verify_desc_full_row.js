const http = require('http');
const { spawn } = require('child_process');

async function test() {
  const chromeProc = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--user-data-dir=/tmp/chrome-test-verify-' + Date.now()
  ]);

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

  // Set desktop viewport 1280x800
  await send('Emulation.setDeviceMetricsOverride', {
    width: 1280,
    height: 800,
    deviceScaleFactor: 1,
    mobile: false
  });

  await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });

  for (let i = 0; i < 30; i++) {
    const res = await send('Runtime.evaluate', { expression: '!!document.querySelector(".project-head-card")', returnByValue: true });
    if (res.result && res.result.value) break;
    await new Promise(r => setTimeout(r, 200));
  }
  await new Promise(r => setTimeout(r, 600));

  // 1. Check desktop 100% width
  const desktopMetrics = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-head-card');
      const descWrap = document.querySelector('.project-desc-collapsible');
      const descText = document.querySelector('.project-desc-text');
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      const cardStyle = window.getComputedStyle(card);
      const cardInnerWidth = card.clientWidth - parseFloat(cardStyle.paddingLeft) - parseFloat(cardStyle.paddingRight);
      return {
        cardClientWidth: card.clientWidth,
        cardInnerWidth: cardInnerWidth,
        descWrapWidth: descWrap.getBoundingClientRect().width,
        descWrapMaxWidth: window.getComputedStyle(descWrap).maxWidth,
        descWrapPercentOfRow: (descWrap.getBoundingClientRect().width / cardInnerWidth * 100).toFixed(1) + '%',
        descTextScrollHeight: descText.scrollHeight,
        descTextClientHeight: descText.clientHeight,
        toggleBtnVisible: !toggleBtn.hidden && window.getComputedStyle(toggleBtn).display !== 'none'
      };
    })()`,
    returnByValue: true
  });

  console.log('1. DESKTOP 1280px METRICS:', JSON.stringify(desktopMetrics.result.value, null, 2));

  // 2. Test when description is long (exceeds 2 lines on desktop)
  const longDescMetrics = await send('Runtime.evaluate', {
    expression: `(() => {
      const descText = document.querySelector('.project-desc-text');
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      descText.textContent = 'Dự án DesignMark là nền tảng đánh giá và góp ý giao diện chuẩn pixel dành cho đội ngũ Product & Design, hỗ trợ tải lên bản vẽ màn hình, so sánh các phiên bản và thảo luận phản hồi trực quan theo thời gian thực. Hệ thống cung cấp các công cụ ghim tọa độ chính xác, phân quyền đa tầng và hỗ trợ xem tiến độ theo thời gian thực cho toàn bộ đội ngũ phát triển sản phẩm.';
      window.dispatchEvent(new Event('resize'));
      return {
        descTextScrollHeight: descText.scrollHeight,
        descTextClientHeight: descText.clientHeight,
        toggleBtnVisible: !toggleBtn.hidden && window.getComputedStyle(toggleBtn).display !== 'none',
        toggleBtnText: toggleBtn.textContent.trim()
      };
    })()`,
    returnByValue: true
  });

  console.log('2. LONG DESC (> 2 LINES) METRICS:', JSON.stringify(longDescMetrics.result.value, null, 2));

  // 3. Test clicking "Xem thêm" opens modal
  await send('Runtime.evaluate', {
    expression: `document.querySelector('.desc-toggle-btn').click()`
  });
  await new Promise(r => setTimeout(r, 400));

  const modalMetrics = await send('Runtime.evaluate', {
    expression: `(() => {
      const modal = document.querySelector('.modal-card.project-desc-modal-card');
      const modalText = modal ? modal.querySelector('.project-desc-modal-text') : null;
      return {
        modalOpened: !!modal,
        modalTitle: modal ? modal.querySelector('.modal-title').textContent : null,
        modalTextSnippet: modalText ? modalText.textContent.slice(0, 60) + '...' : null
      };
    })()`,
    returnByValue: true
  });

  console.log('3. MODAL OPENED CHECK:', JSON.stringify(modalMetrics.result.value, null, 2));

  // 4. Test mobile viewport
  await send('Emulation.setDeviceMetricsOverride', {
    width: 375,
    height: 667,
    deviceScaleFactor: 2,
    mobile: true
  });
  await send('Runtime.evaluate', {
    expression: `(() => {
      const closeBtn = document.querySelector('.modal-card .btn-icon-close');
      if (closeBtn) closeBtn.click();
      window.dispatchEvent(new Event('resize'));
    })()`
  });
  await new Promise(r => setTimeout(r, 400));

  const mobileMetrics = await send('Runtime.evaluate', {
    expression: `(() => {
      const card = document.querySelector('.project-head-card');
      const descWrap = document.querySelector('.project-desc-collapsible');
      const descText = document.querySelector('.project-desc-text');
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      return {
        cardWidth: card.getBoundingClientRect().width,
        descWrapWidth: descWrap.getBoundingClientRect().width,
        descWrapPercentOfRow: (descWrap.getBoundingClientRect().width / card.clientWidth * 100).toFixed(1) + '%',
        toggleBtnVisible: !toggleBtn.hidden && window.getComputedStyle(toggleBtn).display !== 'none'
      };
    })()`,
    returnByValue: true
  });

  console.log('4. MOBILE 375px METRICS:', JSON.stringify(mobileMetrics.result.value, null, 2));

  chromeProc.kill();
  process.exit(0);
}

test().catch(e => { console.error(e); process.exit(1); });

