const http = require('http');
const { spawn } = require('child_process');

function getJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(e);
        }
      });
    }).on('error', reject);
  });
}

async function waitForChrome(port, maxTries = 20) {
  for (let i = 0; i < maxTries; i++) {
    try {
      const list = await getJson(`http://127.0.0.1:${port}/json`);
      if (list && list.length > 0) return list;
    } catch (e) {}
    await new Promise(r => setTimeout(r, 200));
  }
  throw new Error('Chrome did not respond on debugging port ' + port);
}

async function run() {
  const port = 9333;
  const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--user-data-dir=/tmp/chrome-test-' + Date.now(),
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

    await send('Page.enable');
    await send('Runtime.enable');
    await send('DOM.enable');

    console.log('1. Setting up admin session cookie...');
    await send('Network.enable');
    // First navigate to login to set origin
    await send('Page.navigate', { url: 'http://localhost:8000/index.php' });
    await new Promise(r => setTimeout(r, 800));

    // Log in via evaluate
    const loginRes = await send('Runtime.evaluate', {
      expression: `
        (async () => {
          const res = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: 'admin', password: 'demo' })
          });
          return await res.json();
        })()
      `,
      awaitPromise: true,
      returnByValue: true
    });
    console.log('Login result:', loginRes.result.value?.ok ? 'SUCCESS' : loginRes.result.value);

    console.log('2. Navigating to project.php?id=proj-admin-designmark...');
    await send('Page.navigate', { url: 'http://localhost:8000/project.php?id=proj-admin-designmark' });
    
    // Poll until loaded and members panel rendered
    for (let i = 0; i < 50; i++) {
      const chk = await send('Runtime.evaluate', {
        expression: '(() => (document.getElementById("panel-members")?.children.length || 0) >= 3)()',
        returnByValue: true
      });
      if (chk && chk.result && chk.result.value === true) {
        console.log(`Page & panels fully loaded in ${(i + 1) * 200}ms`);
        break;
      }
      await new Promise(r => setTimeout(r, 200));
    }

    // Test 1: Project Header Description & Clamp
    const headerTest = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const descEl = document.querySelector('.project-desc-text');
          const toggleBtn = document.querySelector('.desc-toggle-btn');
          const isClamped = descEl ? descEl.classList.contains('is-clamped') : false;
          const text = descEl ? descEl.textContent.trim() : '';
          const btnVisible = toggleBtn ? !toggleBtn.hidden && getComputedStyle(toggleBtn).display !== 'none' : false;
          return { exists: !!descEl, isClamped, btnVisible, textLength: text.length };
        })()
      `,
      returnByValue: true
    });
    console.log('Test 1 (Header Desc):', headerTest.result.value);

    // Test 2: Click "Xem thêm" -> Modal Opens with Suggestion & Edit button
    console.log('3. Clicking "Xem thêm" button...');
    const modalTest = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const toggleBtn = document.querySelector('.desc-toggle-btn');
          if (toggleBtn) toggleBtn.click();
          const modalBox = document.querySelector('.project-desc-modal-box');
          const isOpen = !!modalBox;
          const editBtn = modalBox ? (modalBox.querySelector('.btn-edit-desc') || Array.from(modalBox.querySelectorAll('button')).find(b => b.textContent.includes('Chỉnh sửa mô tả'))) : null;
          const suggestBox = modalBox ? modalBox.querySelector('.project-desc-suggest-box') : null;
          return {
            modalOpen: isOpen,
            hasEditBtn: !!editBtn,
            hasSuggestBox: !!suggestBox,
            suggestText: suggestBox ? suggestBox.textContent.slice(0, 100) : ''
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Test 2 (Modal Open & Elements):', modalTest.result.value);

    // Test 3: Close modal by Escape
    console.log('4. Pressing Escape key to close modal...');
    const closeTest = await send('Runtime.evaluate', {
      expression: `
        (async () => {
          document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
          await new Promise(r => setTimeout(r, 250));
          const modalBox = document.querySelector('.project-desc-modal-box');
          return { modalClosed: !modalBox };
        })()
      `,
      awaitPromise: true,
      returnByValue: true
    });
    console.log('Test 3 (Modal Close):', closeTest.result.value);

    // Test 4: Members Tab Structure
    console.log('5. Switching to Members Tab...');
    const membersTest = await send('Runtime.evaluate', {
      expression: `
        (async () => {
          const tabButtons = Array.from(document.querySelectorAll('.tabs [role="tab"]'));
          const membersTabBtn = tabButtons.find(b => b.textContent.includes('Thành viên'));
          if (membersTabBtn) membersTabBtn.click();
          for (let i = 0; i < 40; i++) {
            const p = document.getElementById('panel-members');
            if (p && p.children.length >= 3) break;
            await new Promise(r => setTimeout(r, 200));
          }

          const panel = document.getElementById('panel-members');
          const toolbar = panel ? panel.querySelector('.members-header-toolbar') : null;
          const addBtn = toolbar ? toolbar.querySelector('.btn-add-member') : null;
          const searchInput = toolbar ? toolbar.querySelector('.member-search-input') : null;
          const table = panel ? panel.querySelector('.members-table') : null;
          const accordion = panel ? panel.querySelector('.role-guide-accordion') : null;
          const accordionToggle = accordion ? accordion.querySelector('.role-guide-accordion-toggle') : null;
          const accordionBody = accordion ? accordion.querySelector('.role-guide-accordion-body') : null;

          // Check DOM ordering: accordion should appear at top (before table)
          let accordionBeforeTable = false;
          if (table && accordion) {
            accordionBeforeTable = (accordion.compareDocumentPosition(table) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
          }

          const accordionInitiallyHidden = accordionBody ? accordionBody.hidden : false;

          // Click accordion to expand
          if (accordionToggle) accordionToggle.click();
          const accordionExpandedAfterClick = accordionBody ? !accordionBody.hidden : false;

          // Check "+ Thêm thành viên" toggles invite panel
          const inviteCard = panel ? panel.querySelector('#project-invite-panel') : null;
          const inviteInitiallyHidden = inviteCard ? inviteCard.hidden : false;
          if (addBtn) addBtn.click();
          const inviteExpandedAfterClick = inviteCard ? !inviteCard.hidden : false;

          return {
            tabFound: !!membersTabBtn,
            panelVisible: panel && !panel.hidden,
            hasToolbar: !!toolbar,
            hasAddBtn: !!addBtn,
            hasSearchInput: !!searchInput,
            hasTable: !!table,
            accordionBeforeTable,
            accordionInitiallyHidden,
            accordionExpandedAfterClick,
            inviteInitiallyHidden,
            inviteExpandedAfterClick
          };
        })()
      `,
      awaitPromise: true,
      returnByValue: true
    });
    console.log('Test 4 (Members Tab):', membersTest.result.value);

    // Scroll to members tab and capture full view
    await send('Runtime.evaluate', {
      expression: `document.getElementById('panel-members').scrollIntoView({ behavior: 'instant', block: 'start' })`
    });
    await new Promise(r => setTimeout(r, 400));

    const fs = require('fs');
    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/Users/dovinhdat/.gemini/antigravity/brain/84dbf516-4b6e-4547-9175-775e2c1ef881/members_tab_accordion_top_verified.png', Buffer.from(screenshot.data, 'base64'));
    console.log('SCREENSHOT_SAVED: members_tab_accordion_top_verified.png');

    ws.close();
  } finally {
    chrome.kill('SIGKILL');
  }
}

run().catch(err => {
  console.error('Test error:', err);
  process.exit(1);
});
