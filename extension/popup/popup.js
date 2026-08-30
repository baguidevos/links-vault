/**
 * Links Vault Clipper - Popup Logic (Manifest V3)
 */

document.addEventListener('DOMContentLoaded', async () => {
  // Elements
  const viewLoading = document.getElementById('view-loading');
  const viewAuth = document.getElementById('view-auth');
  const viewSave = document.getElementById('view-save');
  const alertBanner = document.getElementById('alert-banner');
  const alertMessage = document.getElementById('alert-message');
  const userBadge = document.getElementById('user-badge');
  const btnOptions = document.getElementById('btn-options');

  // Auth Form Elements
  const authServerUrl = document.getElementById('auth-server-url');
  const authEmail = document.getElementById('auth-email');
  const authPassword = document.getElementById('auth-password');
  const authToken = document.getElementById('auth-token');
  const tabLogin = document.getElementById('tab-login');
  const tabToken = document.getElementById('tab-token');
  const panelLogin = document.getElementById('panel-login');
  const panelToken = document.getElementById('panel-token');
  const btnLoginSubmit = document.getElementById('btn-login-submit');
  const btnTokenSubmit = document.getElementById('btn-token-submit');

  // Save Form Elements
  const linkForm = document.getElementById('link-form');
  const previewThumb = document.getElementById('preview-thumb');
  const previewThumbContainer = document.getElementById('preview-thumb-container');
  const previewFavicon = document.getElementById('preview-favicon');
  const previewDomain = document.getElementById('preview-domain');
  const previewType = document.getElementById('preview-type');
  const previewUrlDisplay = document.getElementById('preview-url-display');
  const linkTitle = document.getElementById('link-title');
  const linkTeam = document.getElementById('link-team');
  const linkFolder = document.getElementById('link-folder');
  const linkCategory = document.getElementById('link-category');
  const tagInputField = document.getElementById('tag-input-field');
  const tagsChipsContainer = document.getElementById('tags-chips-container');
  const suggestedTagsContainer = document.getElementById('suggested-tags-container');
  const linkDescription = document.getElementById('link-description');
  const btnInsertSelection = document.getElementById('btn-insert-selection');
  const toggleFavorite = document.getElementById('toggle-favorite');
  const toggleAiSummary = document.getElementById('toggle-ai-summary');
  const btnSaveLink = document.getElementById('btn-save-link');
  const successBox = document.getElementById('success-box');
  const successMessage = document.getElementById('success-message');
  const btnOpenVault = document.getElementById('btn-open-vault');
  const btnSaveAnother = document.getElementById('btn-save-another');

  // State
  const currentConfig = {
    serverUrl: 'http://localhost:8000',
    apiToken: '',
    user: null,
  };
  const currentTabData = {
    url: '',
    title: '',
    description: '',
    selectedText: '',
    favicon: '',
    image: '',
    type: 'article',
  };
  let currentTags = [];
  let availableFolders = [];
  let availableCategories = [];
  let availableTeams = [];

  // Initialize
  await init();

  // Navigation / Options
  btnOptions.addEventListener('click', () => {
    if (chrome.runtime && chrome.runtime.openOptionsPage) {
      chrome.runtime.openOptionsPage();
    } else {
      window.open(chrome.runtime.getURL('options/options.html'));
    }
  });

  // Auth Tabs Toggle
  tabLogin.addEventListener('click', () => {
    tabLogin.classList.add('active');
    tabToken.classList.remove('active');
    panelLogin.style.display = 'flex';
    panelToken.style.display = 'none';
  });

  tabToken.addEventListener('click', () => {
    tabToken.classList.add('active');
    tabLogin.classList.remove('active');
    panelToken.style.display = 'flex';
    panelLogin.style.display = 'none';
  });

  // Login Submit (Direct)
  btnLoginSubmit.addEventListener('click', async (e) => {
    e.preventDefault();
    const serverUrl = cleanServerUrl(authServerUrl.value);
    const email = authEmail.value.trim();
    const password = authPassword.value;

    if (!serverUrl || !email || !password) {
      showAlert('Veuillez remplir tous les champs', 'warning');
      return;
    }

    setButtonLoading(btnLoginSubmit, true);
    try {
      const res = await fetch(`${serverUrl}/api/auth/token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password, device_name: 'Chrome Extension Clipper' }),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Échec de connexion. Vérifiez vos identifiants.');
      }

      await saveConfig({
        serverUrl,
        apiToken: data.token,
        user: data.user,
      });

      hideAlert();
      await switchViewToSave();
    } catch (err) {
      showAlert(err.message, 'error');
    } finally {
      setButtonLoading(btnLoginSubmit, false);
    }
  });

  // Token Submit (Manual)
  btnTokenSubmit.addEventListener('click', async (e) => {
    e.preventDefault();
    const serverUrl = cleanServerUrl(authServerUrl.value);
    const token = authToken.value.trim();

    if (!serverUrl || !token) {
      showAlert('Veuillez renseigner l\'URL et le Token', 'warning');
      return;
    }

    setButtonLoading(btnTokenSubmit, true);
    try {
      const res = await fetch(`${serverUrl}/api/me`, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Token invalide ou non autorisé.');
      }

      await saveConfig({
        serverUrl,
        apiToken: token,
        user: data.user,
      });

      hideAlert();
      await switchViewToSave();
    } catch (err) {
      showAlert(err.message, 'error');
    } finally {
      setButtonLoading(btnTokenSubmit, false);
    }
  });

  // Tag Management
  tagInputField.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const val = tagInputField.value.trim().replace(/^,|,$/g, '');
      if (val) {
        addTag(val);
        tagInputField.value = '';
      }
    } else if (e.key === 'Backspace' && !tagInputField.value && currentTags.length > 0) {
      removeTag(currentTags[currentTags.length - 1]);
    }
  });

  // Insert Selection Text
  btnInsertSelection.addEventListener('click', () => {
    if (currentTabData.selectedText) {
      const currentDesc = linkDescription.value.trim();
      const quote = `> "${currentTabData.selectedText}"`;
      linkDescription.value = currentDesc ? `${currentDesc}\n\n${quote}` : quote;
      btnInsertSelection.style.display = 'none';
    }
  });

  // Change Team -> Reload Categories, Folders & Tags for this team
  linkTeam.addEventListener('change', async () => {
    const selectedTeamId = linkTeam.value;
    if (selectedTeamId) {
      linkFolder.innerHTML = '<option value="">Chargement...</option>';
      linkCategory.innerHTML = '<option value="">Chargement...</option>';
      await loadContext(selectedTeamId);
    }
  });

  // Change Folder -> Auto select linked category if available
  linkFolder.addEventListener('change', () => {
    const selectedFolderId = linkFolder.value;
    if (!selectedFolderId) return;

    const folder = availableFolders.find((f) => String(f.id) === String(selectedFolderId));
    if (folder && folder.category_id) {
      const catOption = linkCategory.querySelector(`option[value="${folder.category_id}"]`);
      if (catOption) {
        linkCategory.value = folder.category_id;
      }
    }
  });

  // Save Link Form Submit
  linkForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const payload = {
      url: currentTabData.url,
      title: linkTitle.value.trim(),
      description: linkDescription.value.trim() || null,
      team_id: linkTeam.value ? parseInt(linkTeam.value) : null,
      folder_id: linkFolder.value ? parseInt(linkFolder.value) : null,
      category_id: linkCategory.value ? parseInt(linkCategory.value) : null,
      tags: currentTags,
      content_type: currentTabData.type || 'other',
      favicon_url: currentTabData.favicon || null,
      thumbnail_url: currentTabData.image || null,
      is_favorite: toggleFavorite.checked,
      generate_ai_summary: toggleAiSummary.checked,
    };

    setButtonLoading(btnSaveLink, true);
    try {
      const res = await fetch(`${currentConfig.serverUrl}/api/links`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${currentConfig.apiToken}`,
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (res.status === 409) {
        // Duplicate
        showAlert('Ce lien est déjà présent dans votre Vault !', 'warning');
        btnOpenVault.href = data.vault_url || `${currentConfig.serverUrl}/app`;
        linkForm.style.display = 'none';
        successBox.style.display = 'flex';
        successMessage.textContent = 'Ce lien est déjà enregistré.';
        return;
      }

      if (!res.ok) {
        throw new Error(data.message || 'Erreur lors de l\'enregistrement.');
      }

      // Success
      linkForm.style.display = 'none';
      successBox.style.display = 'flex';
      successMessage.textContent = 'Le lien a été enregistré avec succès !';
      btnOpenVault.href = data.vault_url || `${currentConfig.serverUrl}/app`;
    } catch (err) {
      showAlert(err.message, 'error');
    } finally {
      setButtonLoading(btnSaveLink, false);
    }
  });

  // Save Another / Edit
  btnSaveAnother.addEventListener('click', () => {
    successBox.style.display = 'none';
    linkForm.style.display = 'flex';
  });

  // --- CORE FUNCTIONS ---

  async function init() {
    showView('loading');

    // Load stored settings
    const stored = await getStorage(['serverUrl', 'apiToken', 'user']);
    if (stored.serverUrl) currentConfig.serverUrl = stored.serverUrl;
    if (stored.apiToken) currentConfig.apiToken = stored.apiToken;
    if (stored.user) currentConfig.user = stored.user;

    authServerUrl.value = currentConfig.serverUrl;

    if (!currentConfig.apiToken) {
      showView('auth');
      return;
    }

    await switchViewToSave();
  }

  async function switchViewToSave() {
    showView('loading');

    // Extract current tab DOM
    await extractTabInfo();

    // Verify token & load Context (Teams, Categories, Tags)
    try {
      await loadContext();
      showView('save');
      populateSaveForm();

      // Trigger backend preview in background to enrich metadata if needed
      enrichPreviewFromBackend();
    } catch (err) {
      console.error('switchViewToSave error:', err);
      showAlert(`Connexion échouée (${err.message}). Veuillez vous reconnecter.`, 'error');
      showView('auth');
    }
  }

  async function extractTabInfo() {
    try {
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (!tab || !tab.url) return;

      currentTabData.url = tab.url;
      currentTabData.title = tab.title || '';
      currentTabData.favicon = tab.favIconUrl || '';

      // Check if URL is inspectable (not chrome:// or extension pages)
      if (tab.url.startsWith('http://') || tab.url.startsWith('https://')) {
        const results = await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          func: () => {
            const getMeta = (prop) =>
              document.querySelector(`meta[property="${prop}"]`)?.content ||
              document.querySelector(`meta[name="${prop}"]`)?.content || '';

            return {
              ogTitle: getMeta('og:title') || getMeta('twitter:title'),
              ogDescription: getMeta('og:description') || getMeta('twitter:description') || getMeta('description'),
              ogImage: getMeta('og:image') || getMeta('twitter:image'),
              favicon: document.querySelector('link[rel~="icon"]')?.href || '',
              selectedText: window.getSelection()?.toString()?.trim() || '',
            };
          },
        });

        if (results && results[0] && results[0].result) {
          const dom = results[0].result;
          if (dom.ogTitle) currentTabData.title = dom.ogTitle;
          if (dom.ogDescription) currentTabData.description = dom.ogDescription;
          if (dom.ogImage) currentTabData.image = dom.ogImage;
          if (dom.favicon) currentTabData.favicon = dom.favicon;
          if (dom.selectedText) currentTabData.selectedText = dom.selectedText;
        }
      }

      // Detect content type
      if (currentTabData.url.includes('youtube.com/') || currentTabData.url.includes('youtu.be/')) {
        currentTabData.type = 'youtube';
      } else if (currentTabData.url.includes('drive.google.com') || currentTabData.url.includes('docs.google.com')) {
        currentTabData.type = 'drive';
      } else if (currentTabData.url.endsWith('.pdf')) {
        currentTabData.type = 'pdf';
      } else {
        currentTabData.type = 'article';
      }
    } catch (e) {
      console.warn('DOM extraction skipped:', e);
    }
  }

  async function loadContext(teamId = null) {
    let url = `${currentConfig.serverUrl}/api/context`;
    if (teamId) url += `?team_id=${teamId}`;

    const res = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${currentConfig.apiToken}`,
      },
    });

    if (!res.ok) {
      let msg = `Erreur HTTP ${res.status}`;
      try {
        const errData = await res.json();
        if (errData.message) msg = errData.message;
      } catch (_) {}
      throw new Error(msg);
    }

    const data = await res.json();
    if (data.user) {
      currentConfig.user = data.user;
      userBadge.textContent = data.user.name || data.user.email;
      userBadge.style.display = 'inline-block';
    }

    availableTeams = data.teams || [];
    availableCategories = data.categories || [];
    availableFolders = data.folders || [];
    const availableTags = data.tags || [];

    // Populate Teams
    const targetTeamId = teamId !== null && teamId !== undefined
      ? String(teamId)
      : (data.current_team_id ? String(data.current_team_id) : (availableTeams[0] ? String(availableTeams[0].id) : ''));

    linkTeam.innerHTML = '';
    if (availableTeams.length > 0) {
      availableTeams.forEach((t) => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name + (t.is_personal ? ' (Perso)' : '');
        if (String(t.id) === targetTeamId) opt.selected = true;
        linkTeam.appendChild(opt);
      });
      linkTeam.value = targetTeamId;
      document.getElementById('team-group').style.display = availableTeams.length > 1 ? 'flex' : 'none';
    } else {
      document.getElementById('team-group').style.display = 'none';
    }

    // Populate Folders
    linkFolder.innerHTML = '<option value="">Aucun dossier (racine)</option>';
    availableFolders.forEach((folder) => {
      const opt = document.createElement('option');
      opt.value = folder.id;
      const icon = folder.icon ? `${folder.icon} ` : '📁 ';
      opt.textContent = `${icon}${folder.name}`;
      linkFolder.appendChild(opt);
    });

    // Populate Categories
    linkCategory.innerHTML = '<option value="">Aucune catégorie</option>';
    availableCategories.forEach((cat) => {
      const opt = document.createElement('option');
      opt.value = cat.id;
      opt.textContent = cat.name;
      linkCategory.appendChild(opt);
    });

    // Suggested Tags
    suggestedTagsContainer.innerHTML = '';
    if (availableTags.length > 0) {
      suggestedTagsContainer.style.display = 'flex';
      availableTags.slice(0, 6).forEach((tag) => {
        const tagBtn = document.createElement('button');
        tagBtn.type = 'button';
        tagBtn.className = 'suggested-tag-btn';
        tagBtn.textContent = `+ ${tag.name}`;
        tagBtn.addEventListener('click', () => addTag(tag.name));
        suggestedTagsContainer.appendChild(tagBtn);
      });
    } else {
      suggestedTagsContainer.style.display = 'none';
    }
  }

  function populateSaveForm() {
    linkTitle.value = currentTabData.title;
    linkDescription.value = currentTabData.description;
    previewUrlDisplay.textContent = currentTabData.url;

    // Domain & Favicon
    try {
      const u = new URL(currentTabData.url);
      previewDomain.textContent = u.hostname.replace('www.', '');
    } catch {
      previewDomain.textContent = currentTabData.url;
    }

    previewType.textContent = currentTabData.type;

    if (currentTabData.favicon) {
      previewFavicon.src = currentTabData.favicon;
      previewFavicon.style.display = 'inline-block';
    }

    if (currentTabData.image) {
      previewThumb.src = currentTabData.image;
      previewThumbContainer.style.display = 'block';
    } else {
      previewThumbContainer.style.display = 'none';
    }

    // Show selection action if text selected
    if (currentTabData.selectedText) {
      btnInsertSelection.style.display = 'inline-block';
    }
  }

  async function enrichPreviewFromBackend() {
    if (!currentTabData.url) return;

    try {
      const res = await fetch(`${currentConfig.serverUrl}/api/links/preview`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${currentConfig.apiToken}`,
        },
        body: JSON.stringify({ url: currentTabData.url }),
      });

      if (!res.ok) return;
      const data = await res.json();

      if (data.title && (!linkTitle.value || linkTitle.value === currentTabData.url)) {
        linkTitle.value = data.title;
      }
      if (data.description && !linkDescription.value) {
        linkDescription.value = data.description;
      }
      if (data.thumbnail_url) {
        currentTabData.image = data.thumbnail_url;
        previewThumb.src = data.thumbnail_url;
        previewThumbContainer.style.display = 'block';
      }
      if (data.content_type) {
        currentTabData.type = data.content_type;
        previewType.textContent = data.content_type;
      }
      if (data.favicon_url) {
        currentTabData.favicon = data.favicon_url;
        previewFavicon.src = data.favicon_url;
        previewFavicon.style.display = 'inline-block';
      }
    } catch (e) {
      console.warn('Backend preview enrichment error:', e);
    }
  }

  // Tag helper functions
  function addTag(tagName) {
    const clean = tagName.trim().toLowerCase();
    if (!clean || currentTags.includes(clean)) return;

    currentTags.push(clean);
    renderTags();
  }

  function removeTag(tagName) {
    currentTags = currentTags.filter((t) => t !== tagName);
    renderTags();
  }

  function renderTags() {
    tagsChipsContainer.innerHTML = '';
    currentTags.forEach((tag) => {
      const chip = document.createElement('span');
      chip.className = 'tag-chip';
      chip.innerHTML = `${tag} <span class="remove-tag">&times;</span>`;
      chip.querySelector('.remove-tag').addEventListener('click', () => removeTag(tag));
      tagsChipsContainer.appendChild(chip);
    });
  }

  // Utility Helpers
  function showView(viewName) {
    viewLoading.style.display = viewName === 'loading' ? 'block' : 'none';
    viewAuth.style.display = viewName === 'auth' ? 'block' : 'none';
    viewSave.style.display = viewName === 'save' ? 'block' : 'none';
  }

  function showAlert(msg, type = 'warning') {
    alertBanner.className = `alert-banner ${type}`;
    alertMessage.textContent = msg;
    alertBanner.style.display = 'block';
  }

  function hideAlert() {
    alertBanner.style.display = 'none';
  }

  function setButtonLoading(btn, isLoading) {
    btn.disabled = isLoading;
    const textSpan = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner');
    if (textSpan) textSpan.style.display = isLoading ? 'none' : 'inline';
    if (spinner) spinner.style.display = isLoading ? 'block' : 'none';
  }

  function cleanServerUrl(url) {
    return (url || '').trim().replace(/\/+$/, '');
  }

  function getStorage(keys) {
    return new Promise((resolve) => {
      if (chrome.storage && chrome.storage.sync) {
        chrome.storage.sync.get(keys, (res) => resolve(res || {}));
      } else {
        const res = {};
        keys.forEach((k) => (res[k] = localStorage.getItem(k)));
        resolve(res);
      }
    });
  }

  function saveConfig(config) {
    Object.assign(currentConfig, config);
    return new Promise((resolve) => {
      if (chrome.storage && chrome.storage.sync) {
        chrome.storage.sync.set(config, () => resolve());
      } else {
        Object.entries(config).forEach(([k, v]) => localStorage.setItem(k, typeof v === 'object' ? JSON.stringify(v) : v));
        resolve();
      }
    });
  }
});
