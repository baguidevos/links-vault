/**
 * Links Vault Clipper - Options Logic
 */

document.addEventListener('DOMContentLoaded', async () => {
  const serverUrlInput = document.getElementById('server-url');
  const apiTokenInput = document.getElementById('api-token');
  const btnPasteToken = document.getElementById('btn-paste-token');
  const tokenStatusBadge = document.getElementById('token-status-badge');
  const btnTestConnection = document.getElementById('btn-test-connection');
  const btnSaveSettings = document.getElementById('btn-save-settings');
  const statusMessage = document.getElementById('status-message');
  const userInfoCard = document.getElementById('user-info-card');
  const connectedUserName = document.getElementById('connected-user-name');
  const connectedUserEmail = document.getElementById('connected-user-email');
  const btnDisconnect = document.getElementById('btn-disconnect');
  const defaultAiSummary = document.getElementById('default-ai-summary');
  const defaultFavorite = document.getElementById('default-favorite');

  // Stored state in memory
  let storedConfig = {
    serverUrl: 'http://localhost:8000',
    apiToken: '',
    user: null,
  };

  // Load existing settings
  const stored = await getStorage([
    'serverUrl',
    'apiToken',
    'user',
    'defaultAiSummary',
    'defaultFavorite',
  ]);

  if (stored.serverUrl) storedConfig.serverUrl = stored.serverUrl;
  if (stored.apiToken) storedConfig.apiToken = stored.apiToken;
  if (stored.user) storedConfig.user = stored.user;

  serverUrlInput.value = storedConfig.serverUrl;
  
  // Never display the plain-text token!
  apiTokenInput.value = '';

  if (storedConfig.apiToken) {
    tokenStatusBadge.style.display = 'inline-flex';
    apiTokenInput.placeholder = 'Collez un nouveau token pour remplacer...';
  } else {
    tokenStatusBadge.style.display = 'none';
    apiTokenInput.placeholder = 'Collez votre token ici...';
  }

  if (stored.defaultAiSummary !== undefined) {
    defaultAiSummary.checked = stored.defaultAiSummary;
  }
  if (stored.defaultFavorite !== undefined) {
    defaultFavorite.checked = stored.defaultFavorite;
  }

  if (storedConfig.user) {
    showConnectedUser(storedConfig.user);
  }

  // Connect & Save Token Function
  async function connectAndSaveToken(token) {
    const serverUrl = cleanUrl(serverUrlInput.value);
    if (!serverUrl) {
      showStatus('Veuillez d\'abord spécifier une URL de serveur valide.', 'error');
      return;
    }

    setButtonLoading(btnPasteToken, true, 'Connexion...');
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

      await saveStorage({
        serverUrl,
        apiToken: token,
        user: data.user,
      });

      storedConfig.apiToken = token;
      storedConfig.user = data.user;

      // Always ensure token is wiped from the input
      apiTokenInput.value = '';
      apiTokenInput.placeholder = 'Collez un nouveau token pour remplacer...';
      tokenStatusBadge.style.display = 'inline-flex';
      showConnectedUser(data.user);

      showStatus(`Connexion réussie ! Connecté en tant que ${data.user.name || data.user.email}. Le token est enregistré en toute sécurité.`, 'success');
    } catch (err) {
      showStatus(`Échec de connexion : ${err.message}`, 'error');
    } finally {
      setButtonLoading(btnPasteToken, false, 'Coller & Connecter');
    }
  }

  // Handle paste event directly on apiTokenInput: auto-connect and clear input
  apiTokenInput.addEventListener('paste', (e) => {
    const pasted = (e.clipboardData || window.clipboardData)?.getData('text');
    if (pasted && pasted.trim()) {
      e.preventDefault();
      apiTokenInput.value = '';
      connectAndSaveToken(pasted.trim());
    }
  });

  // Handle click on "Coller & Connecter"
  btnPasteToken.addEventListener('click', async () => {
    let token = apiTokenInput.value.trim();
    apiTokenInput.value = '';

    if (!token && navigator.clipboard && navigator.clipboard.readText) {
      try {
        token = (await navigator.clipboard.readText()).trim();
      } catch {
        // Clipboard read permission might be restricted
      }
    }

    if (!token) {
      showStatus('Veuillez coller un Personal Access Token dans le champ.', 'error');
      return;
    }

    await connectAndSaveToken(token);
  });

  // Test Connection
  btnTestConnection.addEventListener('click', async () => {
    const serverUrl = cleanUrl(serverUrlInput.value);
    const token = apiTokenInput.value.trim() || storedConfig.apiToken;
    apiTokenInput.value = '';

    if (!serverUrl) {
      showStatus('Veuillez spécifier une URL de serveur valide.', 'error');
      return;
    }

    setButtonLoading(btnTestConnection, true, 'Test en cours...');
    try {
      const endpoint = token ? `${serverUrl}/api/me` : `${serverUrl}/api/context`;
      const headers = { 'Accept': 'application/json' };
      if (token) headers['Authorization'] = `Bearer ${token}`;

      const res = await fetch(endpoint, { headers });
      const data = await res.json();

      if (res.ok) {
        if (data.user) {
          showConnectedUser(data.user);
          await saveStorage({ user: data.user });
          showStatus(`Connexion réussie ! Connecté en tant que ${data.user.name || data.user.email}.`, 'success');
        } else {
          showStatus('Serveur accessible ! (Non authentifié)', 'success');
        }
      } else {
        throw new Error(data.message || 'Serveur accessible mais authentification rejetée.');
      }
    } catch (err) {
      showStatus(`Échec de connexion : ${err.message}`, 'error');
    } finally {
      setButtonLoading(btnTestConnection, false, 'Tester la connexion');
    }
  });

  // Disconnect
  btnDisconnect.addEventListener('click', async () => {
    apiTokenInput.value = '';
    storedConfig.apiToken = '';
    storedConfig.user = null;
    tokenStatusBadge.style.display = 'none';
    userInfoCard.style.display = 'none';
    apiTokenInput.placeholder = 'Collez votre token ici...';
    await saveStorage({ apiToken: '', user: null });
    showStatus('Session déconnectée. Le token a été supprimé.', 'success');
  });

  // Save Settings
  btnSaveSettings.addEventListener('click', async () => {
    const serverUrl = cleanUrl(serverUrlInput.value);
    const newApiToken = apiTokenInput.value.trim();
    const effectiveToken = newApiToken || storedConfig.apiToken;
    apiTokenInput.value = '';

    if (!serverUrl) {
      showStatus('L\'URL du serveur est obligatoire.', 'error');
      return;
    }

    setButtonLoading(btnSaveSettings, true, 'Enregistrement...');
    try {
      let user = storedConfig.user;
      if (effectiveToken) {
        const res = await fetch(`${serverUrl}/api/me`, {
          headers: {
            'Accept': 'application/json',
            'Authorization': `Bearer ${effectiveToken}`,
          },
        });
        if (res.ok) {
          const data = await res.json();
          user = data.user;
          storedConfig.user = user;
          storedConfig.apiToken = effectiveToken;
          tokenStatusBadge.style.display = 'inline-flex';
        }
      }

      await saveStorage({
        serverUrl,
        apiToken: effectiveToken,
        user,
        defaultAiSummary: defaultAiSummary.checked,
        defaultFavorite: defaultFavorite.checked,
      });

      if (user) showConnectedUser(user);

      showStatus('Paramètres enregistrés avec succès !', 'success');
    } catch (err) {
      showStatus(`Erreur : ${err.message}`, 'error');
    } finally {
      setButtonLoading(btnSaveSettings, false, 'Enregistrer les paramètres');
    }
  });

  // Helper functions
  function showConnectedUser(user) {
    connectedUserName.textContent = user.name || 'Utilisateur';
    connectedUserEmail.textContent = user.email || '';
    userInfoCard.style.display = 'flex';
  }

  function showStatus(msg, type = 'success') {
    statusMessage.className = `status-box ${type}`;
    statusMessage.textContent = msg;
    statusMessage.style.display = 'block';
    setTimeout(() => {
      statusMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 50);
  }

  function setButtonLoading(btn, isLoading, originalText) {
    btn.disabled = isLoading;
    const textSpan = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner');
    if (textSpan) textSpan.textContent = originalText;
    else if (!spinner) btn.textContent = originalText;
    if (spinner) spinner.style.display = isLoading ? 'block' : 'none';
  }

  function cleanUrl(url) {
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

  function saveStorage(obj) {
    return new Promise((resolve) => {
      if (chrome.storage && chrome.storage.sync) {
        chrome.storage.sync.set(obj, () => resolve());
      } else {
        Object.entries(obj).forEach(([k, v]) => localStorage.setItem(k, typeof v === 'object' ? JSON.stringify(v) : v));
        resolve();
      }
    });
  }
});
