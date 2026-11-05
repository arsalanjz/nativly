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
   * Helper: Detect mobile or tablet viewport/device
   */
  function isMobileOrTablet() {
    const ua = window.navigator.userAgent || '';
    const isMobileUa = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS/i.test(ua);
    const isMobileWidth = window.innerWidth <= 992;
    return isMobileUa || isMobileWidth;
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
   * Page view counter exposed globally for SPA navigations
   */
  function incrementPageView() {
    const storageKeyViews = 'ntv_pwa_page_views';
    const current = parseInt(localStorage.getItem(storageKeyViews) || '0', 10) + 1;
    localStorage.setItem(storageKeyViews, current.toString());
    return current;
  }
  window.ntvIncrementPageView = incrementPageView;

  /**
   * Service Worker Registration & Update Toast Handler
   */
  function initServiceWorker() {
    if (!('serviceWorker' in navigator) || !config.swUrl) {
      return;
    }

    navigator.serviceWorker.register(config.swUrl, { scope: config.scope || '/' })
      .then(function (registration) {
        if (registration.waiting) {
          showUpdateToast(registration.waiting);
          return;
        }

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
   * Smart Install Prompt Engine (Android, iOS & Mobile Viewports)
   */
  function initInstallPrompt() {
    if (!config.enablePrompt || isStandalone() || isInAppBrowser() || !isMobileOrTablet()) {
      return;
    }

    const storageKeyDismissed = 'ntv_pwa_dismissed_at';
    const dismissedAt = parseInt(localStorage.getItem(storageKeyDismissed) || '0', 10);
    if (dismissedAt) {
      const cooldownMs = (config.cooldownDays || 7) * 24 * 60 * 60 * 1000;
      if (Date.now() - dismissedAt < cooldownMs) {
        return;
      }
    }

    // Track views
    const currentViews = incrementPageView();
    const minViews = config.minPageViews || 2;

    // Trigger prompt if minViews requirement met or if minViews <= 2 (allowing first session view)
    const shouldSchedule = (minViews <= 2) || (currentViews >= minViews);
    if (!shouldSchedule) {
      return;
    }

    let deferredPrompt = null;
    const installCard = document.getElementById('ntv-install-card');
    const installDesc = document.getElementById('ntv-install-desc');
    const confirmBtn = document.getElementById('ntv-install-confirm-btn');
    const dismissBtn = document.getElementById('ntv-install-dismiss-btn');
    const closeXBtn = document.getElementById('ntv-install-close-x');
    const iosModal = document.getElementById('ntv-ios-modal-backdrop');
    const iosCloseBtn = document.getElementById('ntv-ios-close-btn');

    // Chromium beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
    });

    function dismissPrompt() {
      if (installCard) {
        installCard.classList.remove('is-visible');
        installCard.setAttribute('aria-hidden', 'true');
        installCard.style.display = 'none';
      }
      if (iosModal) {
        iosModal.classList.remove('is-visible');
        iosModal.setAttribute('aria-hidden', 'true');
        iosModal.style.display = 'none';
      }
      localStorage.setItem(storageKeyDismissed, Date.now().toString());
    }

    function openIosModal() {
      if (installCard) {
        installCard.classList.remove('is-visible');
        installCard.setAttribute('aria-hidden', 'true');
        installCard.style.display = 'none';
      }
      if (iosModal) {
        iosModal.classList.add('is-visible');
        iosModal.setAttribute('aria-hidden', 'false');
        iosModal.style.display = 'flex';
      }
    }

    const iosGotItBtn = document.getElementById('ntv-ios-got-it-btn') || document.querySelector('.ntv-ios-got-it-btn');

    if (dismissBtn) dismissBtn.addEventListener('click', dismissPrompt);
    if (closeXBtn) closeXBtn.addEventListener('click', dismissPrompt);
    if (iosCloseBtn) iosCloseBtn.addEventListener('click', dismissPrompt);
    if (iosGotItBtn && iosGotItBtn !== iosCloseBtn) iosGotItBtn.addEventListener('click', dismissPrompt);

    if (iosModal) {
      iosModal.addEventListener('click', function (e) {
        if (e.target === iosModal) {
          dismissPrompt();
        }
      });
    }

    // Schedule banner display after configured delay
    const delayMs = (config.delaySeconds || 3) * 1000;
    setTimeout(function () {
      if (!installCard) return;

      // If deferredPrompt did not fire or device is iOS, show friendly instructions on banner
      if (!deferredPrompt || isIosSafari()) {
        if (installDesc) {
          installDesc.textContent = 'برای دسترسی سریع‌تر و تجربه روان‌تر، این اپلیکیشن را به صفحه اصلی خود اضافه کنید.';
        }
      }

      installCard.classList.add('is-visible');
      installCard.setAttribute('aria-hidden', 'false');
      installCard.style.display = 'block';

      if (confirmBtn) {
        confirmBtn.onclick = function (e) {
          e.preventDefault();
          if (deferredPrompt) {
            dismissPrompt();
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function () {
              deferredPrompt = null;
            });
            localStorage.setItem(storageKeyDismissed, Date.now().toString());
          } else {
            // For iOS or browsers without native prompt, open the iOS 2-step visual modal
            openIosModal();
          }
        };
      }
    }, delayMs);
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
