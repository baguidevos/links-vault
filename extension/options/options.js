/**
 * Links Vault Clipper - Options Logic
 */

document.addEventListener('DOMContentLoaded', async () => {
  const serverUrlInput = document.getElementById('server-url');
  const apiTokenInput = document.getElementById('api-token');
  const btnToggleToken = document.getElementById('btn-toggle-token');
  const btnTestConnection = document.getElementById('btn-test-connection');
  const btnSaveSettings = document.getElementById('btn-save-settings');
  const statusMessage = document.getElementById('status-message');
  const userInfoCard = document.getElementById('user-info-card');
  const connectedUserName = document.getElementById('connected-user-name');
  const connectedUserEmail = document.getElementById('connected-user-email');
  const btnDisconnect = document.getElementById('btn-disconnect');
  const defaultAiSummary = document.getElementById('default-ai-summary');
  const defaultFavorite = document.getElementById('default-favorite');

  // Load existing settings
  const stored = await getStorage([
    'serverUrl',
    'apiToken',
    'user',
    'defaultAiSummary',
    'defaultFavorite',
  ]);

  serverUrlInput.value = stored.serverUrl || 'http://localhost:8000';
  apiTokenInput.value = stored.apiToken || '';
  if (stored.defaultAiSummary !== undefined) {
    defaultAiSummary.checked = stored.defaultAiSummary;
  }
  if (stored.defaultFavorite !== undefined) {
    defaultFavorite.checked = stored.defaultFavorite;
  }

  if (stored.user) {
    showConnectedUser(stored.user);
  }

  // Toggle show/hide token
  btnToggleToken.addEventListener('click', () => {
    const isPassword = apiTokenInput.type === 'password';
    apiTokenInput.type = isPassword ? 'text' : 'password';
    btnToggleToken.textContent = isPassword ? 'Masquer' : 'Afficher';
  });

  // Test Connection
  btnTestConnection.addEventListener('click', async () => {
    const serverUrl = cleanUrl(serverUrlInput.value);
    const token = apiTokenInput.value.trim();

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
    userInfoCard.style.display = 'none';
    await saveStorage({ apiToken: '', user: null });
    showStatus('Session déconnectée.', 'success');
  });

  // Save Settings
  btnSaveSettings.addEventListener('click', async () => {
    const serverUrl = cleanUrl(serverUrlInput.value);
    const apiToken = apiTokenInput.value.trim();

    if (!serverUrl) {
      showStatus('L\'URL du serveur est obligatoire.', 'error');
      return;
    }

    setButtonLoading(btnSaveSettings, true, 'Enregistrement...');
    try {
      let user = null;
      if (apiToken) {
        const res = await fetch(`${serverUrl}/api/me`, {
          headers: {
            'Accept': 'application/json',
            'Authorization': `Bearer ${apiToken}`,
          },
        });
        if (res.ok) {
          const data = await res.json();
          user = data.user;
        }
      }

      await saveStorage({
        serverUrl,
        apiToken,
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
