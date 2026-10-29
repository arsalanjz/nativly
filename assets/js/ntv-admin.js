/**
 * Nativly - Admin Dashboard Controller, Live Mockup Preview & Icon Picker
 * Author: Arsalan Jabarzare
 */

(function ($) {
  'use strict';

  const data = window.ntvAdminData || {};
  let currentTargetRow = null;

  $(document).ready(function () {
    initTabs();
    initColorPickers();
    initSliders();
    initPresets();
    initSortableRepeater();
    initIconPicker();
    initMediaUploader();
    initAjaxPurge();
    initAjaxReset();
    initThemeHeaderHidingToggle();

    // Initial Live Preview Build
    updateLivePreview();
  });

  /**
   * Tab Navigation Switcher
   */
  function initTabs() {
    $('.ntv-tab-btn').on('click', function () {
      const tabId = $(this).data('tab');

      $('.ntv-tab-btn').removeClass('is-active');
      $(this).addClass('is-active');

      $('.ntv-tab-content').removeClass('is-active');
      $('#ntv-tab-' + tabId).addClass('is-active');
    });

    // Menu Source Radio Switcher
    $('input[name="ntv_settings[menu_source]"]').on('change', function () {
      if ($(this).val() === 'wp_menu') {
        $('#ntv-wp-menu-select-wrap').slideDown(200);
        $('#ntv-custom-repeater-wrap').slideUp(200);
      } else {
        $('#ntv-wp-menu-select-wrap').slideUp(200);
        $('#ntv-custom-repeater-wrap').slideDown(200);
      }
    });
  }

  /**
   * WordPress Color Pickers with live preview bindings
   */
  function initColorPickers() {
    if ($.fn.wpColorPicker) {
      $('.ntv-color-picker').wpColorPicker({
        change: function (event, ui) {
          setTimeout(updateLivePreview, 50);
        },
        clear: function () {
          setTimeout(updateLivePreview, 50);
        }
      });
    }

    $('#ntv-color-inactive-icon, #ntv-color-glass-bg').on('input change', function () {
      updateLivePreview();
    });
  }

  /**
   * Sliders (Blur & Border Radius)
   */
  function initSliders() {
    $('#ntv-blur-slider').on('input change', function () {
      $('#ntv-blur-val').text($(this).val() + 'px');
      updateLivePreview();
    });

    $('#ntv-radius-slider').on('input change', function () {
      $('#ntv-radius-val').text($(this).val() + 'px');
      updateLivePreview();
    });

    $('#ntv-offset-input').on('input change', function () {
      updateLivePreview();
    });
  }

  /**
   * One-Click Style Presets
   */
  function initPresets() {
    const presets = {
      dark_liquid: {
        pill_bg: '#0047FF',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(18, 18, 24, 0.82)',
        blur: 20,
        radius: 999
      },
      frosted_light: {
        pill_bg: '#0F172A',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(15, 23, 42, 0.7)',
        glass_bg: 'rgba(255, 255, 255, 0.75)',
        blur: 24,
        radius: 999
      },
      handicraft: {
        pill_bg: '#C88A32',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 235, 205, 0.7)',
        glass_bg: 'rgba(28, 20, 16, 0.85)',
        blur: 22,
        radius: 999
      },
      emerald: {
        pill_bg: '#10B981',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(6, 30, 20, 0.84)',
        blur: 20,
        radius: 999
      }
    };

    $('.ntv-preset-card').on('click', function () {
      const presetKey = $(this).data('preset');
      const p = presets[presetKey];
      if (!p) return;

      $('.ntv-preset-card').removeClass('is-selected');
      $(this).addClass('is-selected');
      $('#ntv-active-preset-input').val(presetKey);

      // Apply values to inputs
      $('#ntv-color-active-pill').val(p.pill_bg);
      if ($('#ntv-color-active-pill').iris) {
        $('#ntv-color-active-pill').iris('color', p.pill_bg);
      }

      $('#ntv-color-active-text').val(p.text_color);
      if ($('#ntv-color-active-text').iris) {
        $('#ntv-color-active-text').iris('color', p.text_color);
      }

      $('#ntv-color-inactive-icon').val(p.inactive_color);
      $('#ntv-color-glass-bg').val(p.glass_bg);

      $('#ntv-blur-slider').val(p.blur);
      $('#ntv-blur-val').text(p.blur + 'px');

      $('#ntv-radius-slider').val(p.radius);
      $('#ntv-radius-val').text(p.radius + 'px');

      updateLivePreview();
    });
  }

  /**
   * Sortable Drag & Drop Menu Repeater
   */
  function initSortableRepeater() {
    const list = $('#ntv-menu-items-list');

    if ($.fn.sortable) {
      list.sortable({
        handle: '.ntv-row-drag-handle',
        axis: 'y',
        opacity: 0.7,
        update: function () {
          reindexRepeater();
          updateLivePreview();
        }
      });
    }

    // Toggle extra options
    list.on('click', '.ntv-toggle-options-btn', function () {
      $(this).closest('.ntv-menu-item-row').find('.ntv-row-extra-options').slideToggle(200);
    });

    // Delete item
    list.on('click', '.ntv-delete-item-btn', function () {
      if (list.children('.ntv-menu-item-row').length <= 1) {
        alert('حداقل وجود یک آیتم در نوبار الزامی است.');
        return;
      }
      $(this).closest('.ntv-menu-item-row').remove();
      reindexRepeater();
      updateLivePreview();
    });

    // Live update on input change
    list.on('input change', '.ntv-item-title-input, .ntv-item-badge-type, .ntv-item-badge-text', function () {
      updateLivePreview();
    });

    // Add new item
    $('#ntv-add-menu-item-btn').on('click', function () {
      const newIndex = list.children('.ntv-menu-item-row').length;
      const defaultIcon = 'compass';
      const defaultIconSvg = (data.iconSvgs && data.iconSvgs[defaultIcon]) || '';

      const rowHtml = `
        <div class="ntv-menu-item-row" data-index="${newIndex}">
          <div class="ntv-row-drag-handle"><span class="dashicons dashicons-menu"></span></div>
          <div class="ntv-row-preview-icon">
            <button type="button" class="ntv-row-icon-trigger" title="${data.i18n.selectIcon || 'تغییر آیکون'}">
              <span class="ntv-row-current-icon">${defaultIconSvg}</span>
            </button>
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon]" class="ntv-item-icon-input" value="${defaultIcon}">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon_type]" class="ntv-item-icon-type-input" value="lucide">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][id]" value="item_${Date.now()}">
          </div>
          <div class="ntv-row-fields">
            <div class="ntv-row-main-inputs">
              <input type="text" name="ntv_settings[menu_items][${newIndex}][title]" class="ntv-item-title-input regular-text" value="آیتم جدید" placeholder="عنوان تب" required>
              <input type="url" name="ntv_settings[menu_items][${newIndex}][url]" class="ntv-item-url-input regular-text" value="/" placeholder="https://..." required>
            </div>
            <div class="ntv-row-options-toggle">
              <button type="button" class="ntv-toggle-options-btn">تنظیمات بیشتر (نشان و دسترسی) &darr;</button>
            </div>
            <div class="ntv-row-extra-options" style="display:none;">
              <div class="ntv-grid-3">
                <div class="ntv-field-group">
                  <label>نوع نشان (Badge):</label>
                  <select name="ntv_settings[menu_items][${newIndex}][badge_type]" class="ntv-item-badge-type">
                    <option value="none">بدون نشان</option>
                    <option value="wc_cart_count">شمارنده سبد خرید ووکامرس</option>
                    <option value="custom">متن دلخواه</option>
                  </select>
                </div>
                <div class="ntv-field-group">
                  <label>متن نشان دلخواه:</label>
                  <input type="text" name="ntv_settings[menu_items][${newIndex}][badge_text]" class="ntv-item-badge-text" value="" placeholder="NEW">
                </div>
                <div class="ntv-field-group">
                  <label>سطح دسترسی:</label>
                  <select name="ntv_settings[menu_items][${newIndex}][visibility]">
                    <option value="all">همه کاربران</option>
                    <option value="logged_in">فقط کاربران وارد شده</option>
                    <option value="guests">فقط کاربران مهمان</option>
                  </select>
                </div>
              </div>
              <div class="ntv-field-group ntv-mt-2">
                <label>کد SVG اختصاصی (اختیاری):</label>
                <textarea name="ntv_settings[menu_items][${newIndex}][custom_svg]" class="large-text ntv-item-svg-textarea" rows="2" placeholder="<svg>...</svg>"></textarea>
              </div>
            </div>
          </div>
          <div class="ntv-row-actions">
            <button type="button" class="ntv-delete-item-btn" title="حذف آیتم"><span class="dashicons dashicons-trash"></span></button>
          </div>
        </div>
      `;

      list.append(rowHtml);
      reindexRepeater();
      updateLivePreview();
    });
  }

  /**
   * Reindex repeater array names
   */
  function reindexRepeater() {
    $('#ntv-menu-items-list .ntv-menu-item-row').each(function (index) {
      $(this).attr('data-index', index);
      $(this).find('input, select, textarea').each(function () {
        const name = $(this).attr('name');
        if (name) {
          const updatedName = name.replace(/\[menu_items\]\[\d+\]/, `[menu_items][${index}]`);
          $(this).attr('name', updatedName);
        }
      });
    });
  }

  /**
   * Lucide Visual Icon Picker Modal
   */
  function initIconPicker() {
    const modal = $('#ntv-icon-picker-modal');
    const grid = $('#ntv-modal-icons-grid');
    const search = $('#ntv-icon-search-input');

    // Populate catalog
    let iconsHtml = '';
    if (data.iconSvgs) {
      Object.keys(data.iconSvgs).forEach(function (iconName) {
        iconsHtml += `
          <div class="ntv-modal-icon-card" data-icon="${iconName}">
            ${data.iconSvgs[iconName]}
            <span>${iconName}</span>
          </div>
        `;
      });
      grid.html(iconsHtml);
    }

    // Open Modal
    $('#ntv-menu-items-list').on('click', '.ntv-row-icon-trigger', function () {
      currentTargetRow = $(this).closest('.ntv-menu-item-row');
      search.val('').trigger('input');
      modal.fadeIn(150);
    });

    // Close Modal
    $('.ntv-modal-close-btn, #ntv-icon-picker-modal').on('click', function (e) {
      if (e.target === this) {
        modal.fadeOut(150);
      }
    });

    // Search filter
    search.on('input', function () {
      const q = $(this).val().toLowerCase().trim();
      grid.find('.ntv-modal-icon-card').each(function () {
        const iconName = $(this).data('icon');
        if (!q || iconName.includes(q)) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    });

    // Select icon
    grid.on('click', '.ntv-modal-icon-card', function () {
      const selectedIcon = $(this).data('icon');
      if (currentTargetRow) {
        currentTargetRow.find('.ntv-item-icon-input').val(selectedIcon);
        currentTargetRow.find('.ntv-item-icon-type-input').val('lucide');
        currentTargetRow.find('.ntv-row-current-icon').html(data.iconSvgs[selectedIcon] || '');
        modal.fadeOut(150);
        updateLivePreview();
      }
    });
  }

  /**
   * WP Media Uploader for 512x512 PWA Icon
   */
  function initMediaUploader() {
    let frame;
    $('#ntv-upload-icon-btn').on('click', function (e) {
      e.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: 'انتخاب آیکون اپلیکیشن (512x512 PNG)',
        button: { text: 'استفاده از این آیکون' },
        multiple: false
      });

      frame.on('select', function () {
        const attachment = frame.state().get('selection').first().toJSON();
        $('#ntv-pwa-icon-id').val(attachment.id);
        $('#ntv-pwa-icon-512').val(attachment.url);
        $('#ntv-icon-preview-box').html(`<img src="${attachment.url}" width="64" height="64" alt="App Icon">`);
      });

      frame.open();
    });
  }

  /**
   * AJAX: Purge PWA Cache
   */
  function initAjaxPurge() {
    $('#ntv-purge-cache-btn, #ntv-tools-purge-btn').on('click', function (e) {
      e.preventDefault();
      const btn = $(this);
      btn.prop('disabled', true).addClass('updating-message');

      $.post(data.ajaxUrl, {
        action: 'ntv_purge_cache',
        nonce: data.nonce
      }, function (res) {
        btn.prop('disabled', false).removeClass('updating-message');
        if (res.success) {
          showAjaxNotice(res.data.message || data.i18n.purgeSuccess, 'updated');
        } else {
          showAjaxNotice(res.data.message || 'خطا در پاک‌سازی کش', 'error');
        }
      }).fail(function () {
        btn.prop('disabled', false).removeClass('updating-message');
        showAjaxNotice('خطای ارتباط با سرور', 'error');
      });
    });
  }

  /**
   * AJAX: Factory Reset
   */
  function initAjaxReset() {
    $('#ntv-reset-settings-btn').on('click', function (e) {
      e.preventDefault();
      if (!confirm(data.i18n.resetConfirm)) {
        return;
      }

      $.post(data.ajaxUrl, {
        action: 'ntv_reset_settings',
        nonce: data.nonce
      }, function (res) {
        if (res.success) {
          window.location.reload();
        } else {
          showAjaxNotice(res.data.message || 'خطا در بازنشانی تنظیمات', 'error');
        }
      });
    });
  }

  /**
   * Theme Header Hiding Preset Toggle
   */
  function initThemeHeaderHidingToggle() {
    $('#ntv-header-preset').on('change', function () {
      if ($(this).val() === 'custom') {
        $('#ntv-custom-header-wrap').slideDown(200);
      } else {
        $('#ntv-custom-header-wrap').slideUp(200);
      }
    });
  }

  /**
   * Show AJAX Notice Bar
   */
  function showAjaxNotice(msg, type) {
    const notice = $('#ntv-ajax-notice');
    notice.removeClass('notice-success notice-error updated error')
          .addClass(type === 'updated' ? 'notice-success updated' : 'notice-error error');
    notice.find('p').text(msg);
    notice.slideDown(200);
    setTimeout(function () {
      notice.slideUp(300);
    }, 4000);
  }

  /**
   * UPDATE LIVE PREVIEW IN PHONE MOCKUP
   */
  function updateLivePreview() {
    const previewList = $('#ntv-preview-nav-list');
    const activePillColor = $('#ntv-color-active-pill').val() || '#0047FF';
    const activeTextColor = $('#ntv-color-active-text').val() || '#FFFFFF';
    const inactiveIconColor = $('#ntv-color-inactive-icon').val() || 'rgba(255, 255, 255, 0.7)';
    const glassBg = $('#ntv-color-glass-bg').val() || 'rgba(18, 18, 24, 0.82)';
    const blur = $('#ntv-blur-slider').val() || 20;
    const radius = $('#ntv-radius-slider').val() || 999;
    const offset = $('#ntv-offset-input').val() || 16;

    // Apply CSS Variables directly to Mockup
    const mockupNav = $('#ntv-preview-nav-root');
    const glassNav = mockupNav.find('.ntv-glass-nav');

    mockupNav.css('bottom', offset + 'px');
    glassNav.css({
      'background-color': glassBg,
      'backdrop-filter': `blur(${blur}px)`,
      '-webkit-backdrop-filter': `blur(${blur}px)`,
      'border-radius': radius + 'px'
    });

    document.documentElement.style.setProperty('--ntv-active-bg', activePillColor);
    document.documentElement.style.setProperty('--ntv-active-text', activeTextColor);
    document.documentElement.style.setProperty('--ntv-inactive-color', inactiveIconColor);

    // Build Preview Items from Repeater
    let itemsHtml = '';
    const activeIndex = previewList.find('.ntv-nav-item.is-active').data('index') || 0;

    $('#ntv-menu-items-list .ntv-menu-item-row').each(function (idx) {
      const title = $(this).find('.ntv-item-title-input').val() || 'تب';
      const iconKey = $(this).find('.ntv-item-icon-input').val() || 'home';
      const badgeType = $(this).find('.ntv-item-badge-type').val();
      const badgeText = $(this).find('.ntv-item-badge-text').val();
      const iconSvg = (data.iconSvgs && data.iconSvgs[iconKey]) || '';

      const isActive = (idx === parseInt(activeIndex, 10));
      const activeClass = isActive ? 'is-active' : '';

      let badgeHtml = '';
      if (badgeType === 'wc_cart_count') {
        badgeHtml = `<span class="ntv-badge ntv-cart-badge" data-count="3">3</span>`;
      } else if (badgeType === 'custom' && badgeText) {
        badgeHtml = `<span class="ntv-badge ntv-custom-badge">${badgeText}</span>`;
      }

      itemsHtml += `
        <a href="javascript:void(0)" class="ntv-nav-item ${activeClass}" data-index="${idx}">
          <span class="ntv-icon-wrap" aria-hidden="true">
            ${iconSvg}
            ${badgeHtml}
          </span>
          <span class="ntv-label">${title}</span>
        </a>
      `;
    });

    previewList.html(itemsHtml);

    // Interactive Preview: Click tab to expand pill
    previewList.find('.ntv-nav-item').off('click').on('click', function () {
      previewList.find('.ntv-nav-item').removeClass('is-active');
      $(this).addClass('is-active');
    });
  }

})(jQuery);
