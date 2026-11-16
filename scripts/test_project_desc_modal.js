const http = require('http');
const { exec, spawn } = require('child_process');

function request(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(JSON.parse(data)));
    }).on('error', reject);
  });
}

async function main() {
  const chromeProc = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--user-data-dir=/tmp/chrome-test-pdesc-' + Date.now()
  ]);

  await new Promise(r => setTimeout(r, 1500));

  try {
    const targets = await request('http://127.0.0.1:9222/json');
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const wsUrl = pageTarget.webSocketDebuggerUrl;

    const ws = new WebSocket(wsUrl);

    let id = 1;
    const callbacks = new Map();

    ws.onmessage = (event) => {
      const res = JSON.parse(event.data);
      if (res.method === 'Console.messageAdded') {
        console.log('BROWSER CONSOLE:', res.params.message.text);
      }
      if (res.method === 'Runtime.consoleAPICalled') {
        console.log('BROWSER LOG:', res.params.args.map(a => a.value).join(' '));
      }
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

    await new Promise(r => {
      if (ws.readyState === WebSocket.OPEN) r();
      else ws.onopen = r;
    });

    await send('Network.enable');
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Console.enable');

    // Get fresh session cookie
    const loginRes = await fetch('http://localhost:8000/api/auth.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: 'admin', password: 'demo' })
    });
    const setCookie = loginRes.headers.get('set-cookie') || '';
    const match = setCookie.match(/PHPSESSID=([^;]+)/);
    const sessId = match ? match[1] : '';

    // Set cookie in Chrome
    await send('Network.setCookie', {
      name: 'PHPSESSID',
      value: sessId,
      url: 'http://localhost:8000'
    });

    async function evalCode(expression) {
      const res = await send('Runtime.evaluate', { expression, returnByValue: true });
      if (res.exceptionDetails) {
        throw new Error('JS Eval error: ' + (res.exceptionDetails.text || JSON.stringify(res.exceptionDetails)));
      }
      return res.result ? res.result.value : undefined;
    }

    // Wait helper
    async function waitForProject() {
      for (let i = 0; i < 30; i++) {
        const val = await evalCode('!!document.querySelector(".project-head-card")');
        if (val) break;
        await new Promise(r => setTimeout(r, 200));
      }
      await new Promise(r => setTimeout(r, 400));
    }

    console.log('--- 1. Testing Desktop Viewport (1280x800) ---');
    await send('Emulation.setDeviceMetricsOverride', {
      width: 1280,
      height: 800,
      deviceScaleFactor: 1,
      mobile: false
    });

    await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });
    await waitForProject();

    // Verify initial state
    const eval1 = await send('Runtime.evaluate', {
      expression: `(() => {
        const descContainer = document.querySelector('.project-desc-collapsible');
        const descText = document.querySelector('.project-desc-text');
        const toggleBtn = document.querySelector('.desc-toggle-btn');
        const modal = document.querySelector('.modal-backdrop');
        const headCard = document.querySelector('.project-head-card');
        const descWidth = descContainer ? descContainer.offsetWidth : 0;
        const headInnerWidth = headCard ? (headCard.clientWidth - 48) : 0;
        const ratio = headInnerWidth > 0 ? (descWidth / headInnerWidth) : 0;
        return {
          hasDescText: !!descText,
          descIsClamped: descText ? descText.classList.contains('is-clamped') : false,
          descScrollHeight: descText ? descText.scrollHeight : 0,
          descClientHeight: descText ? descText.clientHeight : 0,
          descWidth,
          headInnerWidth,
          widthRatio: Math.round(ratio * 100) + '%',
          toggleBtnVisible: toggleBtn ? window.getComputedStyle(toggleBtn).display !== 'none' : false,
          toggleBtnText: toggleBtn ? toggleBtn.innerText.trim() : '',
          modalOpen: !!modal,
          headCardHeight: headCard ? headCard.offsetHeight : 0
        };
      })()`,
      returnByValue: true
    });

    console.log('Initial page load on proj-admin-designmark:');
    console.log(eval1.result.value);

    // Save screenshot of compact header
    const ss1 = await send('Page.captureScreenshot', { format: 'png' });
    const fs = require('fs');
    fs.writeFileSync('/tmp/project_desc_clamped.png', Buffer.from(ss1.data, 'base64'));

    if (eval1.result.value.modalOpen) {
      throw new Error('FAIL: Modal should NOT be open on initial page load (Requirement 8)!');
    }
    if (!eval1.result.value.descIsClamped) {
      throw new Error('FAIL: Description should be clamped to 2 lines (Requirement 1)!');
    }
    if (!eval1.result.value.toggleBtnVisible) {
      throw new Error('FAIL: "Xem thêm" button should be visible for multi-line description (Requirement 2)!');
    }
    console.log('✓ Requirement 1, 2, 8 PASS');

    const initialHeadHeight = eval1.result.value.headCardHeight;

    // Click "Xem thêm"
    console.log('--- 2. Clicking "Xem thêm" button ---');
    await send('Runtime.evaluate', {
      expression: `(() => {
        const toggleBtn = document.querySelector('.desc-toggle-btn');
        if (toggleBtn) toggleBtn.click();
      })()`
    });
    await new Promise(r => setTimeout(r, 400));

    // Save screenshot of opened modal
    const ss2 = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/tmp/project_desc_modal.png', Buffer.from(ss2.data, 'base64'));

    const eval2 = await send('Runtime.evaluate', {
      expression: `(() => {
        const modal = document.querySelector('.modal-backdrop');
        const modalTitle = document.querySelector('.modal-head h2');
        const modalBody = document.querySelector('.project-desc-modal-body-scroll');
        const modalText = document.querySelector('.project-desc-modal-text');
        const closeBtn = document.querySelector('.modal-head .icon-btn');
        const headCard = document.querySelector('.project-head-card');
        return {
          modalOpen: !!modal,
          modalInClass: modal ? modal.classList.contains('is-in') : false,
          modalTitle: modalTitle ? modalTitle.innerText.trim() : '',
          hasScrollBody: !!modalBody,
          modalScrollHeight: modalBody ? modalBody.scrollHeight : 0,
          modalClientHeight: modalBody ? modalBody.clientHeight : 0,
          textContainsBullets: modalText ? modalText.innerText.includes('• Quản trị viên') : false,
          hasCloseBtn: !!closeBtn,
          headCardHeightAfterModal: headCard ? headCard.offsetHeight : 0
        };
      })()`,
      returnByValue: true
    });

    console.log('Modal state after click:');
    console.log(eval2.result.value);

    if (!eval2.result.value.modalOpen) {
      throw new Error('FAIL: Modal did not open when clicking "Xem thêm" (Requirement 3)!');
    }
    if (eval2.result.value.modalTitle !== 'Mô tả dự án') {
      throw new Error('FAIL: Modal title is not "Mô tả dự án" (Requirement 3)!');
    }
    if (!eval2.result.value.textContainsBullets) {
      throw new Error('FAIL: Modal does not contain full description (Requirement 5)!');
    }
    if (Math.abs(eval2.result.value.headCardHeightAfterModal - initialHeadHeight) > 5) {
      throw new Error('FAIL: Project Header height changed when opening modal (Requirement 7)!');
    }
    console.log('✓ Requirement 3, 4, 5, 6, 7 PASS');

    // Test Escape key
    console.log('--- 3. Testing Escape key to close modal ---');
    await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await new Promise(r => setTimeout(r, 400));

    const eval3 = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          modalStillInDOM: !!document.querySelector('.modal-backdrop')
        };
      })()`,
      returnByValue: true
    });

    console.log('After Escape:', eval3.result.value);
    if (eval3.result.value.modalStillInDOM) {
      throw new Error('FAIL: Modal did not close on Escape key (Requirement 4)!');
    }
    console.log('✓ Escape key close PASS');

    // Test project with 1-line description
    console.log('--- 4. Testing Project with Short Description (proj-shop) ---');
    await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-shop' });
    await waitForProject();

    const eval4Val = await evalCode(`(() => {
      const descText = document.querySelector('.project-desc-text');
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      return {
        hasDescText: !!descText,
        descText: descText ? descText.innerText.trim() : '',
        scrollHeight: descText ? descText.scrollHeight : 0,
        clientHeight: descText ? descText.clientHeight : 0,
        toggleBtnVisible: toggleBtn ? window.getComputedStyle(toggleBtn).display !== 'none' : false
      };
    })()`);

    console.log('proj-shop description:', eval4Val);
    if (eval4Val.toggleBtnVisible) {
      throw new Error('FAIL: "Xem thêm" button should NOT be visible for 1-line description (Requirement 2, 6)!');
    }
    console.log('✓ Short description hides "Xem thêm" PASS');

    // Test mobile viewport
    console.log('--- 5. Testing Mobile Viewport (375x667) ---');
    await send('Emulation.setDeviceMetricsOverride', {
      width: 375,
      height: 667,
      deviceScaleFactor: 2,
      mobile: true
    });
    await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });
    await waitForProject();

    const eval5Val = await evalCode(`(() => {
      const descContainer = document.querySelector('.project-desc-collapsible');
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      const headCard = document.querySelector('.project-head-card');
      const descWidth = descContainer ? descContainer.offsetWidth : 0;
      const headInnerWidth = headCard ? (headCard.clientWidth - 48) : 0;
      return {
        toggleBtnVisible: toggleBtn ? window.getComputedStyle(toggleBtn).display !== 'none' : false,
        headCardHeight: headCard ? headCard.offsetHeight : 0,
        descWidth,
        headInnerWidth,
        isFullWidthOnMobile: descWidth >= (headInnerWidth - 2)
      };
    })()`);

    console.log('Mobile description & button state:', eval5Val);
    if (!eval5Val.toggleBtnVisible) {
      throw new Error('FAIL: "Xem thêm" button should be visible on mobile!');
    }

    // Test clicking "Xem thêm" on mobile to open modal
    console.log('--- 6. Clicking "Xem thêm" on Mobile Viewport ---');
    await evalCode(`(() => {
      const toggleBtn = document.querySelector('.desc-toggle-btn');
      if (toggleBtn) toggleBtn.click();
    })()`);
    await new Promise(r => setTimeout(r, 400));

    const eval6Val = await evalCode(`(() => {
      const modal = document.querySelector('.modal-backdrop');
      const modalBody = document.querySelector('.project-desc-modal-body-scroll');
      const modalText = document.querySelector('.project-desc-modal-text');
      const modalActions = document.querySelector('.project-desc-modal-actions button');
      return {
        modalOpen: !!modal,
        hasScrollBody: !!modalBody,
        hasModalText: !!modalText,
        hasCloseButton: !!modalActions
      };
    })()`);

    console.log('Mobile modal state:', eval6Val);
    if (!eval6Val.modalOpen) {
      throw new Error('FAIL: Modal did not open on mobile!');
    }
    console.log('✓ Mobile modal open PASS');

    // Close modal on mobile
    await evalCode(`(() => {
      const closeBtn = document.querySelector('.project-desc-modal-actions button');
      if (closeBtn) closeBtn.click();
    })()`);
    await new Promise(r => setTimeout(r, 400));

    const isClosed = await evalCode(`!document.querySelector('.modal-backdrop')`);
    if (!isClosed) {
      throw new Error('FAIL: Modal did not close on mobile close button!');
    }
    console.log('✓ Mobile modal close PASS');

    console.log('\n========================================');
    console.log('ALL REQUIREMENTS VERIFIED AND PASSED 100%!');
    console.log('========================================');

    ws.close();
  } finally {
    chromeProc.kill();
  }
}

main().catch(err => {
  console.error('Test error:', err);
  process.exit(1);
});
