/**
 * Nativly - PWA Service Worker Registration & Smart Install Prompt
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
   * Service Worker Registration (silent background updates)
   */
  function initServiceWorker() {
    if (!('serviceWorker' in navigator) || !config.swUrl) {
      return;
    }

    navigator.serviceWorker.register(config.swUrl, { scope: config.scope || '/' })
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
   * Smart Install Prompt Engine (Android, iOS & Mobile Viewports)
   */
  function initInstallPrompt() {
    if (!config.enablePrompt || isStandalone() || isInAppBrowser() || !isMobileOrTablet()) {
      return;
    }

    const versionKey = (config.cacheVersion || '2.1.0').toString().replace(/[^a-zA-Z0-9_.-]/g, '_');
    const storageKeyDismissed = 'ntv_pwa_dismissed_' + versionKey;
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
    const installBanner = document.getElementById('ntv-install-prompt-banner') || document.getElementById('ntv-install-card');
    const installCard = installBanner;
    const installDesc = document.getElementById('ntv-install-desc');
    const confirmBtn = document.getElementById('ntv-install-confirm-btn');
    const dismissBtn = document.getElementById('ntv-install-dismiss-btn');
    const closeXBtn = document.getElementById('ntv-install-close-x');
    const iosModal = document.getElementById('ntv-ios-guide-modal') || document.getElementById('ntv-ios-modal-backdrop') || document.querySelector('.ntv-ios-guide-modal');
    const iosCloseBtn = document.getElementById('ntv-ios-close-btn');

    // Chromium beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
    });

    function dismissPrompt() {
      const banner = document.getElementById('ntv-install-prompt-banner') || installCard;
      if (banner) {
        banner.classList.remove('is-visible');
        banner.setAttribute('aria-hidden', 'true');
        banner.style.display = 'none';
      }
      if (installCard && installCard !== banner) {
        installCard.classList.remove('is-visible');
        installCard.setAttribute('aria-hidden', 'true');
        installCard.style.display = 'none';
      }
      if (iosModal) {
        iosModal.classList.remove('is-visible');
        iosModal.setAttribute('aria-hidden', 'true');
        iosModal.style.cssText = 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important;';
      }
      localStorage.setItem(storageKeyDismissed, Date.now().toString());
    }

    function openIosGuide() {
      const banner = document.getElementById('ntv-install-prompt-banner') || installCard;
      if (banner) {
        banner.classList.remove('is-visible');
        banner.setAttribute('aria-hidden', 'true');
        banner.style.display = 'none';
      }
      if (installCard && installCard !== banner) {
        installCard.classList.remove('is-visible');
        installCard.setAttribute('aria-hidden', 'true');
        installCard.style.display = 'none';
      }
      if (iosModal) {
        if (iosModal.parentNode !== document.body) {
          document.body.appendChild(iosModal);
        }
        iosModal.setAttribute('aria-hidden', 'false');
        iosModal.classList.add('is-visible');
        iosModal.style.cssText = 'position: fixed !important; inset: 0 !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 2147483647 !important; display: flex !important; align-items: center !important; justify-content: center !important; background: rgba(10, 10, 18, 0.78) !important; backdrop-filter: blur(10px) !important; -webkit-backdrop-filter: blur(10px) !important; opacity: 1 !important; visibility: visible !important; pointer-events: auto !important; padding: 20px !important; box-sizing: border-box !important;';
      }
    }
    const openIosModal = openIosGuide;

    const modalCloseBtns = document.querySelectorAll('.ntv-modal-close, .ntv-ios-got-it, .ntv-ios-got-it-btn, #ntv-ios-close-btn');
    modalCloseBtns.forEach(function (btn) {
      btn.addEventListener('click', dismissPrompt);
    });

    if (dismissBtn) dismissBtn.addEventListener('click', dismissPrompt);
    if (closeXBtn) closeXBtn.addEventListener('click', dismissPrompt);
    if (iosCloseBtn) iosCloseBtn.addEventListener('click', dismissPrompt);

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
