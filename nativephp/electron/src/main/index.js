import NativePHP from '#plugin';
import { app, BrowserWindow, Menu } from 'electron';
import path from 'path';

// Inherit User's PATH in Process & ChildProcess
import fixPath from 'fix-path';
fixPath();

// Prevent bundled static PHP from attempting to load system PHP's php.ini (which crashes with 0xc0000005)
delete process.env.PHPRC;
delete process.env.PHP_INI_SCAN_DIR;

// Hook Application Menu to make 'Navigation -> Retour / Précédent' work natively
const originalSetApplicationMenu = Menu.setApplicationMenu;
Menu.setApplicationMenu = function (menu) {
    if (menu && menu.items) {
        function attachNavigationHandlers(items) {
            for (const item of items) {
                if (item.submenu) {
                    attachNavigationHandlers(item.submenu.items);
                }
                const label = (item.label || '').toLowerCase();
                const accelerator = (item.accelerator || '').toLowerCase();

                if (label.includes('précédent') || label.includes('retour') || accelerator === 'alt+left') {
                    const originalClick = item.click;
                    item.click = (menuItem, focusedWindow, event) => {
                        const win = focusedWindow || BrowserWindow.getFocusedWindow();
                        if (win && (win.webContents.navigationHistory?.canGoBack() ?? win.webContents.canGoBack())) {
                            win.webContents.goBack();
                        }
                        if (originalClick) {
                            originalClick(menuItem, focusedWindow, event);
                        }
                    };
                } else if (label.includes('suivant') || accelerator === 'alt+right') {
                    const originalClick = item.click;
                    item.click = (menuItem, focusedWindow, event) => {
                        const win = focusedWindow || BrowserWindow.getFocusedWindow();
                        if (win && (win.webContents.navigationHistory?.canGoForward() ?? win.webContents.canGoForward())) {
                            win.webContents.goForward();
                        }
                        if (originalClick) {
                            originalClick(menuItem, focusedWindow, event);
                        }
                    };
                }
            }
        }
        attachNavigationHandlers(menu.items);
    }
    return originalSetApplicationMenu.call(Menu, menu);
};

// Global navigation shortcuts & mouse buttons on all windows (even on error 500 / blank pages)
app.on('browser-window-created', (_, window) => {
    window.webContents.on('before-input-event', (event, input) => {
        if (input.type === 'keyDown') {
            // Alt + Left or BrowserBack key -> Navigate Back
            if ((input.alt && input.key === 'ArrowLeft') || input.key === 'BrowserBack') {
                if (window.webContents.navigationHistory?.canGoBack() ?? window.webContents.canGoBack()) {
                    window.webContents.goBack();
                    event.preventDefault();
                }
            }
            // Alt + Right or BrowserForward key -> Navigate Forward
            if ((input.alt && input.key === 'ArrowRight') || input.key === 'BrowserForward') {
                if (window.webContents.navigationHistory?.canGoForward() ?? window.webContents.canGoForward()) {
                    window.webContents.goForward();
                    event.preventDefault();
                }
            }
        }
    });

    // Mouse back/forward thumb buttons
    window.on('app-command', (e, cmd) => {
        if (cmd === 'browser-backward') {
            if (window.webContents.navigationHistory?.canGoBack() ?? window.webContents.canGoBack()) {
                window.webContents.goBack();
            }
        } else if (cmd === 'browser-forward') {
            if (window.webContents.navigationHistory?.canGoForward() ?? window.webContents.canGoForward()) {
                window.webContents.goForward();
            }
        }
    });
});

const buildPath = path.resolve(import.meta.dirname, import.meta.env.MAIN_VITE_NATIVEPHP_BUILD_PATH);
const defaultIcon = path.join(buildPath, 'icon.png');
const certificate = path.join(buildPath, 'cacert.pem');

const executable = process.platform === 'win32' ? 'php.exe' : 'php';
const phpBinary = path.join(buildPath, 'php', executable);
const appPath = app.isPackaged ? path.join(buildPath, 'app') : (process.env.APP_PATH || path.resolve(buildPath, '../../../../..'));

/**
 * Turn on the lights for the NativePHP app.
 */
NativePHP.bootstrap(app, defaultIcon, phpBinary, certificate, appPath);
