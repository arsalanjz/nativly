/**
 * Nativly - Frontend Expanding Glassmorphic Navbar, Native Navigation & WooCommerce Sync
 * Author: Arsalan Jabarzare
 */

(function () {
  'use strict';

  // Set of prefetched URLs to avoid duplicate requests
  const prefetchedUrls = new Set();

  /**
   * Get or create the top loader progress bar element
   */
  function getOrCreateLoader() {
    let loader = document.getElementById('ntv-top-loader');
    if (!loader) {
      loader = document.createElement('div');
      loader.id = 'ntv-top-loader';
      loader.setAttribute('aria-hidden', 'true');
      document.body.appendChild(loader);
    }
    return loader;
  }

  function startTopLoader() {
    const loader = getOrCreateLoader();
    loader.classList.remove('is-done');
    loader.classList.add('is-loading');
  }

  function finishTopLoader() {
    const loader = getOrCreateLoader();
    loader.classList.remove('is-loading');
    loader.classList.add('is-done');
    setTimeout(function () {
      loader.classList.remove('is-done');
    }, 450);
  }

  /**
   * Check if a URL is eligible for prefetching
   */
  function isPrefetchEligible(urlStr) {
    if (!urlStr || urlStr === '#' || urlStr.startsWith('javascript:') || urlStr.startsWith('tel:') || urlStr.startsWith('mailto:')) {
      return false;
    }

    try {
      const url = new URL(urlStr, window.location.origin);

      // Must be same origin
      if (url.origin !== window.location.origin) {
        return false;
      }

      const path = url.pathname.toLowerCase();
      const search = url.search.toLowerCase();

      // Exclude admin, login, logout, and preview modes
      if (
        path.includes('/wp-admin') ||
        path.includes('wp-login.php') ||
        search.includes('action=logout') ||
        search.includes('elementor-preview') ||
        search.includes('preview=true') ||
        search.includes('customize_changeset_uuid')
      ) {
        return false;
      }

      // Exclude WooCommerce checkout
      if (path.includes('/checkout') || search.includes('wc-api=')) {
        return false;
      }

      // Exclude static assets / uploads / file downloads
      const fileExtensions = /\.(pdf|zip|tar|gz|rar|exe|dmg|iso|mp3|mp4|avi|mov|docx?|xlsx?|pptx?|jpe?g|png|gif|webp|svg|ico)$/i;
      if (fileExtensions.test(path)) {
        return false;
      }

      return true;
    } catch (e) {
      return false;
    }
  }

  /**
   * Prefetch a URL via <link rel="prefetch">
   */
  function prefetchUrl(urlStr) {
    if (!isPrefetchEligible(urlStr) || prefetchedUrls.has(urlStr)) {
      return;
    }
    prefetchedUrls.add(urlStr);

    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = urlStr;
    link.as = 'document';
    document.head.appendChild(link);
  }

  /**
   * Main Navbar Initialization
   */
  function initNativlyNavbar() {
    const root = document.getElementById('ntv-navbar-root');
    if (!root) {
      return;
    }

    const navItems = root.querySelectorAll('.ntv-nav-item:not(.ntv-more-trigger)');
    const moreTrigger = document.getElementById('ntv-more-trigger');
    const bubblePopover = document.getElementById('ntv-glass-bubble-popover');

    // Popover helper functions
    function openBubblePopover() {
      if (!bubblePopover || !moreTrigger) return;
      bubblePopover.classList.add('is-open', 'is-visible');
      bubblePopover.style.display = 'block';
      bubblePopover.setAttribute('aria-hidden', 'false');
      moreTrigger.setAttribute('aria-expanded', 'true');
      moreTrigger.classList.add('is-open');
    }

    function closeBubblePopover() {
      if (!bubblePopover || !moreTrigger) return;
      bubblePopover.classList.remove('is-open', 'is-visible');
      bubblePopover.style.display = 'none';
      bubblePopover.setAttribute('aria-hidden', 'true');
      moreTrigger.setAttribute('aria-expanded', 'false');
      moreTrigger.classList.remove('is-open');
    }

    // Close popover when clicking any bubble item link
    const bubbleLinks = document.querySelectorAll('.ntv-bubble-item');
    bubbleLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        closeBubblePopover();
      });
    });

    // Attach More popover trigger
    if (moreTrigger && bubblePopover) {
      moreTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const isOpen =
          bubblePopover.classList.contains('is-open') ||
          bubblePopover.classList.contains('is-visible') ||
          bubblePopover.style.display === 'block';

        if (isOpen) {
          closeBubblePopover();
        } else {
          openBubblePopover();
        }
      });

      // Close on outside click
      document.addEventListener('click', function (e) {
        const isOpen =
          bubblePopover.classList.contains('is-open') ||
          bubblePopover.classList.contains('is-visible') ||
          bubblePopover.style.display === 'block';

        if (isOpen) {
          if (!bubblePopover.contains(e.target) && !moreTrigger.contains(e.target)) {
            closeBubblePopover();
          }
        }
      });

      // Close on Escape key press
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
          const isOpen =
            bubblePopover.classList.contains('is-open') ||
            bubblePopover.classList.contains('is-visible') ||
            bubblePopover.style.display === 'block';

          if (isOpen) {
            closeBubblePopover();
            moreTrigger.focus();
          }
        }
      });
    }

    // Prefetching & Native Click Listener for all Navbar and Bubble links
    const allLinks = document.querySelectorAll('.ntv-nav-item:not(.ntv-more-trigger), .ntv-nav-link, .ntv-bubble-item');

    allLinks.forEach(function (el) {
      const href = el.getAttribute('href');
      if (!href) return;

      // Prefetch on hover or touchstart
      el.addEventListener('mouseenter', function () {
        prefetchUrl(href);
      }, { passive: true });

      el.addEventListener('touchstart', function () {
        prefetchUrl(href);
      }, { passive: true });

      // Handle Click: Instant visual feedback and clean native navigation
      el.addEventListener('click', function (e) {
        if (!href || href === '#' || href.startsWith('javascript:')) {
          return;
        }

        try {
          const targetUrl = new URL(href, window.location.origin);
          // If clicking currently active page, just scroll to top
          if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
            e.preventDefault();
            closeBubblePopover();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
          }
        } catch (err) {
          return;
        }

        closeBubblePopover();

        // Immediately update active pill state for instant visual feedback
        navItems.forEach(function (sibling) {
          sibling.classList.remove('is-active');
          sibling.removeAttribute('aria-current');
        });

        const activeTarget = el.closest('.ntv-nav-item') || el;
        if (activeTarget && activeTarget.classList.contains('ntv-nav-item')) {
          activeTarget.classList.add('is-active');
          activeTarget.setAttribute('aria-current', 'page');
        }

        // Show top loader for brief progress indication before native page transitions
        startTopLoader();

        // Browser executes native navigation via regular link click
      });
    });

    // Initialize WooCommerce cart count syncing if present
    initWooCommerceSync();
  }

  /**
   * Synchronize WooCommerce cart count badges from sessionStorage and WC fragment events
   */
  function initWooCommerceSync() {
    const cartBadges = document.querySelectorAll('.ntv-cart-badge');
    if (!cartBadges.length) {
      return;
    }

    try {
      const sessionData = window.sessionStorage ? window.sessionStorage.getItem('wc_fragments') : null;
      if (sessionData) {
        const fragments = JSON.parse(sessionData);
        if (fragments && fragments['span.ntv-cart-badge']) {
          updateCartBadgesFromHtml(fragments['span.ntv-cart-badge']);
        }
      }
    } catch (err) {
      // Quiet fail
    }

    if (window.jQuery) {
      window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', function (event, fragments) {
        if (fragments && fragments['span.ntv-cart-badge']) {
          updateCartBadgesFromHtml(fragments['span.ntv-cart-badge']);
        } else {
          refreshCartBadgeFromSession();
        }
      });
    }

    window.addEventListener('wc_fragments_refreshed', function () {
      refreshCartBadgeFromSession();
    });
  }

  function updateCartBadgesFromHtml(fragmentHtml) {
    if (!fragmentHtml) return;

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = fragmentHtml.trim();
    const newBadge = tempDiv.firstElementChild;
    if (!newBadge) return;

    const count = parseInt(newBadge.getAttribute('data-count'), 10) || 0;
    const text = newBadge.textContent || '0';

    updateBadges(count, text);
  }

  function refreshCartBadgeFromSession() {
    try {
      const sessionData = window.sessionStorage ? window.sessionStorage.getItem('wc_fragments') : null;
      if (sessionData) {
        const fragments = JSON.parse(sessionData);
        if (fragments && fragments['span.ntv-cart-badge']) {
          updateCartBadgesFromHtml(fragments['span.ntv-cart-badge']);
        }
      }
    } catch (e) {
      // Quiet fail
    }
  }

  function updateBadges(count, text) {
    const cartBadges = document.querySelectorAll('.ntv-cart-badge');
    cartBadges.forEach(function (badge) {
      badge.setAttribute('data-count', count);
      badge.textContent = text;
      if (count > 0) {
        badge.classList.remove('is-empty');
        badge.style.display = 'inline-flex';
      } else {
        badge.classList.add('is-empty');
        badge.style.display = 'none';
      }
    });
  }

  // Run on DOMContentLoaded or immediately
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNativlyNavbar);
  } else {
    initNativlyNavbar();
  }
})();
