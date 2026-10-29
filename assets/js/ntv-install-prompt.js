/**
 * Nativly - PWA Service Worker Registration, Update Toast & Smart Install Prompt
 * Author: Arsalan Jabarzare
 */

(function () {
  'use strict';

  const config = window.ntvPwaData || {};

  /**
   * Helper: Detect iOS Safari environment.
   */
  function isIosSafari() {
    const ua = window.navigator.userAgent;
    const isIos = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isWebkit = /WebKit/i.test(ua);
    const isOtherBrowser = /CriOS|FxiOS|OPiOS|mercury/i.test(ua);
    return isIos && isWebkit && !isOtherBrowser;
  }

  /**
   * Helper: Detect standalone (already installed) mode.
   */
  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.navigator.standalone === true ||
      document.referrer.includes('android-app://')
    );
  }

  /**
   * Helper: Detect in-app browsers where PWA install is blocked by host app.
   */
  function isInAppBrowser() {
    const ua = window.navigator.userAgent || '';
    return /Instagram|Telegram|FBAN|FBAV|WhatsApp|Line/i.test(ua);
  }

  /**
   * Service Worker Registration & Update Toast Handler
   */
  function initServiceWorker() {
    if (!('serviceWorker' in navigator) || !config.swUrl) {
      return;
    }

    navigator.serviceWorker.register(config.swUrl, { scope: config.scope || '/' })
      .then(function (registration) {
        // If a waiting worker already exists, prompt user immediately
        if (registration.waiting) {
          showUpdateToast(registration.waiting);
          return;
        }

        // Detect new worker being installed
        registration.addEventListener('updatefound', function () {
          const newWorker = registration.installing;
          if (!newWorker) return;

          newWorker.addEventListener('statechange', function () {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdateToast(newWorker);
            }
          });
        });
      })
      .catch(function (err) {
        console.warn('[Nativly] SW Registration error:', err);
      });

    // Reload page once new service worker takes control
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (!refreshing) {
        refreshing = true;
        window.location.reload();
      }
    });
  }

  /**
   * Show bottom glassmorphic update toast.
   */
  function showUpdateToast(worker) {
    const toast = document.getElementById('ntv-update-toast');
    const updateBtn = document.getElementById('ntv-update-btn');
    if (!toast || !updateBtn) return;

    toast.style.display = 'flex';
    updateBtn.addEventListener('click', function () {
      if (worker) {
        worker.postMessage({ type: 'SKIP_WAITING' });
      }
    });
  }

  /**
   * Smart Install Prompt Engine (Android & iOS)
   */
  function initInstallPrompt() {
    if (!config.enablePrompt || isStandalone() || isInAppBrowser()) {
      return;
    }

    // 1. Page view tracking
    const storageKeyViews = 'ntv_pwa_page_views';
    const storageKeyDismissed = 'ntv_pwa_dismissed_at';

    let currentViews = parseInt(localStorage.getItem(storageKeyViews) || '0', 10) + 1;
    localStorage.setItem(storageKeyViews, currentViews.toString());

    // Check minimum page views requirement
    if (currentViews < (config.minPageViews || 1)) {
      return;
    }

    // 2. Dismissal cooldown check
    const dismissedAt = parseInt(localStorage.getItem(storageKeyDismissed) || '0', 10);
    if (dismissedAt) {
      const cooldownMs = (config.cooldownDays || 7) * 24 * 60 * 60 * 1000;
      if (Date.now() - dismissedAt < cooldownMs) {
        return;
      }
    }

    let deferredPrompt = null;
    const installCard = document.getElementById('ntv-install-card');
    const confirmBtn = document.getElementById('ntv-install-confirm-btn');
    const dismissBtn = document.getElementById('ntv-install-dismiss-btn');

    // Chromium / Android beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;

      // Show after configured delay
      setTimeout(function () {
        if (installCard) {
          installCard.style.display = 'block';
        }
      }, (config.delaySeconds || 3) * 1000);
    });

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (deferredPrompt) {
          installCard.style.display = 'none';
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then(function () {
            deferredPrompt = null;
          });
        }
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        if (installCard) {
          installCard.style.display = 'none';
        }
        localStorage.setItem(storageKeyDismissed, Date.now().toString());
      });
    }

    // iOS Safari specific modal prompt
    if (isIosSafari()) {
      const iosModal = document.getElementById('ntv-ios-modal-backdrop');
      const iosCloseBtn = document.getElementById('ntv-ios-close-btn');

      if (iosModal && iosCloseBtn) {
        setTimeout(function () {
          iosModal.style.display = 'flex';
        }, (config.delaySeconds || 3) * 1000);

        iosCloseBtn.addEventListener('click', function () {
          iosModal.style.display = 'none';
          localStorage.setItem(storageKeyDismissed, Date.now().toString());
        });

        iosModal.addEventListener('click', function (e) {
          if (e.target === iosModal) {
            iosModal.style.display = 'none';
            localStorage.setItem(storageKeyDismissed, Date.now().toString());
          }
        });
      }
    }
  }

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initServiceWorker();
      initInstallPrompt();
    });
  } else {
    initServiceWorker();
    initInstallPrompt();
  }
})();
