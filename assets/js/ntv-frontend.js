/**
 * Nativly - Frontend Expanding Glassmorphic Navbar & WooCommerce Sync
 * Author: Arsalan Jabarzare
 */

(function () {
  'use strict';

  function initNativlyNavbar() {
    const root = document.getElementById('ntv-navbar-root');
    if (!root) {
      return;
    }

    const navItems = root.querySelectorAll('.ntv-nav-item:not(.ntv-more-trigger)');
    const moreTrigger = document.getElementById('ntv-more-trigger');
    const bubblePopover = document.getElementById('ntv-glass-bubble-popover');

    // Instant visual feedback when tapping a nav item before browser navigation starts.
    navItems.forEach(function (item) {
      item.addEventListener('click', function () {
        const href = item.getAttribute('href');
        // Ignore empty or anchor-only links
        if (!href || href === '#' || href.startsWith('javascript:')) {
          return;
        }

        // Close bubble popover if open
        closeBubblePopover();

        // Deactivate siblings immediately for instant native app feel
        navItems.forEach(function (sibling) {
          sibling.classList.remove('is-active');
          sibling.removeAttribute('aria-current');
        });

        item.classList.add('is-active');
        item.setAttribute('aria-current', 'page');
      });
    });

    // Glass Bubble Popover Controller
    if (moreTrigger && bubblePopover) {
      function openBubblePopover() {
        bubblePopover.style.display = 'block';
        bubblePopover.setAttribute('aria-hidden', 'false');
        moreTrigger.setAttribute('aria-expanded', 'true');
        moreTrigger.classList.add('is-active');
      }

      function closeBubblePopover() {
        bubblePopover.style.display = 'none';
        bubblePopover.setAttribute('aria-hidden', 'true');
        moreTrigger.setAttribute('aria-expanded', 'false');
        moreTrigger.classList.remove('is-active');
      }

      moreTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const isOpen = bubblePopover.style.display === 'block';
        if (isOpen) {
          closeBubblePopover();
        } else {
          openBubblePopover();
        }
      });

      // Close on outside click
      document.addEventListener('click', function (e) {
        if (bubblePopover.style.display === 'block') {
          if (!bubblePopover.contains(e.target) && !moreTrigger.contains(e.target)) {
            closeBubblePopover();
          }
        }
      });

      // Close on Escape key press
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
          if (bubblePopover.style.display === 'block') {
            closeBubblePopover();
            moreTrigger.focus();
          }
        }
      });
    }

    // Initialize WooCommerce cart count syncing if present.
    initWooCommerceSync();
  }

  /**
   * Synchronize WooCommerce cart count badges from sessionStorage and WC fragment events.
   */
  function initWooCommerceSync() {
    const cartBadges = document.querySelectorAll('.ntv-cart-badge');
    if (!cartBadges.length) {
      return;
    }

    // 1. Sync from sessionStorage immediately to eliminate cached HTML badge discrepancies
    try {
      const sessionData = window.sessionStorage ? window.sessionStorage.getItem('wc_fragments') : null;
      if (sessionData) {
        const fragments = JSON.parse(sessionData);
        if (fragments && fragments['span.ntv-cart-badge']) {
          updateCartBadgesFromHtml(fragments['span.ntv-cart-badge']);
        }
      }
    } catch (err) {
      // Ignore JSON parse errors from sessionStorage
    }

    // 2. Listen to jQuery WooCommerce fragment events if jQuery is loaded
    if (window.jQuery) {
      window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', function (event, fragments) {
        if (fragments && fragments['span.ntv-cart-badge']) {
          updateCartBadgesFromHtml(fragments['span.ntv-cart-badge']);
        } else {
          refreshCartBadgeFromSession();
        }
      });
    }

    // 3. Native CustomEvent listener for headless or modern themes
    window.addEventListener('wc_fragments_refreshed', function () {
      refreshCartBadgeFromSession();
    });
  }

  /**
   * Update all .ntv-cart-badge elements using returned HTML string.
   */
  function updateCartBadgesFromHtml(fragmentHtml) {
    if (!fragmentHtml) {
      return;
    }

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = fragmentHtml.trim();
    const newBadge = tempDiv.firstElementChild;
    if (!newBadge) {
      return;
    }

    const count = parseInt(newBadge.getAttribute('data-count'), 10) || 0;
    const text = newBadge.textContent || '0';

    updateBadges(count, text);
  }

  /**
   * Helper to re-read sessionStorage and update badges.
   */
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

  /**
   * Update badge elements with count and visibility.
   */
  function updateBadges(count, text) {
    const cartBadges = document.querySelectorAll('.ntv-cart-badge');
    cartBadges.forEach(function (badge) {
      badge.setAttribute('data-count', count);
      badge.textContent = text;
      if (count > 0) {
        badge.style.display = 'inline-flex';
      } else {
        badge.style.display = 'none';
      }
    });
  }

  // Run on DOMContentLoaded or immediately if document is already ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNativlyNavbar);
  } else {
    initNativlyNavbar();
  }
})();
