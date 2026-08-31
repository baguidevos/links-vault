/**
 * Links Vault Clipper - Background Service Worker (Manifest V3)
 */

chrome.runtime.onInstalled.addListener(() => {
  // Context Menu: Save current page
  chrome.contextMenus.create({
    id: 'save-current-page',
    title: 'Enregistrer cette page dans Links Vault',
    contexts: ['page'],
  });

  // Context Menu: Save clicked link
  chrome.contextMenus.create({
    id: 'save-target-link',
    title: 'Enregistrer ce lien dans Links Vault',
    contexts: ['link'],
  });

  // Context Menu: Save selected text as note
  chrome.contextMenus.create({
    id: 'save-selection-note',
    title: 'Enregistrer la sélection dans Links Vault',
    contexts: ['selection'],
  });
});

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
  const stored = await chrome.storage.sync.get(['serverUrl', 'apiToken']);
  const serverUrl = (stored.serverUrl || 'http://127.0.0.1:8100').replace(/\/+$/, '');
  const apiToken = stored.apiToken;

  if (!apiToken) {
    chrome.runtime.openOptionsPage();
    return;
  }

  let targetUrl = tab.url;
  let title = tab.title || '';
  if (targetUrl && (targetUrl.includes('youtube.com') || targetUrl.includes('youtu.be'))) {
    title = title.replace(/\s*-\s*YouTube.*$/i, '').trim();
  }
  let description = null;

  if (info.menuItemId === 'save-target-link' && info.linkUrl) {
    targetUrl = info.linkUrl;
    title = info.selectionText || info.linkUrl;
  } else if (info.menuItemId === 'save-selection-note') {
    description = info.selectionText ? `> "${info.selectionText}"` : null;
  }

  try {
    const res = await fetch(`${serverUrl}/api/links`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${apiToken}`,
      },
      body: JSON.stringify({
        url: targetUrl,
        title: title,
        description: description,
        generate_ai_summary: true,
      }),
    });

    if (res.ok || res.status === 409) {
      chrome.action.setBadgeText({ text: '✓', tabId: tab.id });
      chrome.action.setBadgeBackgroundColor({ color: '#10B981', tabId: tab.id });
      setTimeout(() => {
        chrome.action.setBadgeText({ text: '', tabId: tab.id });
      }, 3000);
    } else {
      chrome.action.setBadgeText({ text: '!', tabId: tab.id });
      chrome.action.setBadgeBackgroundColor({ color: '#EF4444', tabId: tab.id });
    }
  } catch (err) {
    console.error('Quick save error:', err);
    chrome.action.setBadgeText({ text: '!', tabId: tab.id });
    chrome.action.setBadgeBackgroundColor({ color: '#EF4444', tabId: tab.id });
  }
});
