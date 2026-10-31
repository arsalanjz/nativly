/**
 * Nativly - Admin Dashboard Controller, Live Mockup Preview & Icon Picker
 * Author: Arsalan Jabarzare
 */

(function ($) {
  'use strict';

  const data = window.ntvAdminData || {};
  let currentTargetRow = null;

  /**
   * Helper: Safely extract SVG string from localized icon catalog
   */
  function getIconSvg(iconKey) {
    if (!iconKey) return '';

    // Check in iconSvgs object
    if (data.iconSvgs && data.iconSvgs[iconKey]) {
      const item = data.iconSvgs[iconKey];
      if (typeof item === 'string') return item;
      if (item && item.svg) return item.svg;
    }

    // Check in icons object
    if (data.icons && data.icons[iconKey]) {
      const item = data.icons[iconKey];
      if (typeof item === 'string') return item;
      if (item && item.svg) return item.svg;
    }

    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
  }

  /**
   * Helper: Get localized label for an icon
   */
  function getIconLabel(iconKey) {
    if (!iconKey) return '';
    if (data.iconSvgs && data.iconSvgs[iconKey] && data.iconSvgs[iconKey].label) {
      return data.iconSvgs[iconKey].label;
    }
    if (data.icons && data.icons[iconKey] && data.icons[iconKey].label) {
      return data.icons[iconKey].label;
    }
    return iconKey;
  }

  $(document).ready(function () {
    initTabs();
    initColorPickers();
    initSliders();
    initPresets();
    initSortableRepeater();
    initBubbleMenuRepeater();
    initNavbarWidthControl();
    initIconPicker();
    initMediaUploader();
    initAjaxPurge();
    initAjaxReset();
    initThemeHeaderHidingToggle();
    initDiagnosticsHelp();
    initFormSubmission();

    // Initial Live Preview Build
    updateLivePreview();
  });

  /**
   * Header & Sticky Save Button Form Submission Binding
   */
  function initFormSubmission() {
    $('#ntv-header-save-btn').on('click', function (e) {
      e.preventDefault();
      $('#ntv-settings-form').trigger('submit');
    });
  }

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
        change: function () {
          setTimeout(updateLivePreview, 50);
        },
        clear: function () {
          setTimeout(updateLivePreview, 50);
        }
      });
    }

    $('#ntv-inactive-icon-color, #ntv-color-inactive-icon, #ntv-glass-bg-color, #ntv-color-glass-bg, #ntv-glass-border-color').on('input change', function () {
      updateLivePreview();
    });
  }

  /**
   * Sliders (Blur & Border Radius & Offset)
   */
  function initSliders() {
    $('#ntv-glass-blur, #ntv-blur-slider').on('input change', function () {
      const val = $(this).val();
      $('#ntv-blur-val').text(val + 'px');
      updateLivePreview();
    });

    $('#ntv-border-radius, #ntv-radius-slider').on('input change', function () {
      const val = $(this).val();
      $('#ntv-radius-val').text(val + 'px');
      updateLivePreview();
    });

    $('#ntv-mobile-bottom-offset, #ntv-offset-input').on('input change', function () {
      const val = $(this).val();
      $('#ntv-offset-val').text(val + 'px');
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
        glass_border: 'rgba(255, 255, 255, 0.12)',
        blur: 20,
        radius: 999
      },
      light_glass: {
        pill_bg: '#2563EB',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(15, 23, 42, 0.65)',
        glass_bg: 'rgba(255, 255, 255, 0.85)',
        glass_border: 'rgba(0, 0, 0, 0.08)',
        blur: 24,
        radius: 999
      },
      cyber_emerald: {
        pill_bg: '#10B981',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(6, 30, 20, 0.84)',
        glass_border: 'rgba(16, 185, 129, 0.25)',
        blur: 20,
        radius: 999
      },
      sunset_violet: {
        pill_bg: '#8B5CF6',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(28, 16, 45, 0.85)',
        glass_border: 'rgba(139, 92, 246, 0.25)',
        blur: 22,
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
      const activePill = $('#ntv-active-pill-bg, #ntv-color-active-pill');
      activePill.val(p.pill_bg);
      if (activePill.wpColorPicker) {
        activePill.wpColorPicker('color', p.pill_bg);
      }

      const activeText = $('#ntv-active-text-color, #ntv-color-active-text');
      activeText.val(p.text_color);
      if (activeText.wpColorPicker) {
        activeText.wpColorPicker('color', p.text_color);
      }

      $('#ntv-inactive-icon-color, #ntv-color-inactive-icon').val(p.inactive_color);
      $('#ntv-glass-bg-color, #ntv-color-glass-bg').val(p.glass_bg);
      $('#ntv-glass-border-color').val(p.glass_border || 'rgba(255, 255, 255, 0.12)');

      $('#ntv-glass-blur, #ntv-blur-slider').val(p.blur);
      $('#ntv-blur-val').text(p.blur + 'px');

      $('#ntv-border-radius, #ntv-radius-slider').val(p.radius);
      $('#ntv-radius-val').text(p.radius + 'px');

      updateLivePreview();
    });
  }

  /**
   * Sortable Drag & Drop Main Menu Repeater
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

    // Badge Type Toggle (show/hide custom badge text field)
    list.on('change', '.ntv-item-badge-type-select', function () {
      const row = $(this).closest('.ntv-menu-item-row');
      const badgeWrap = row.find('.ntv-custom-badge-text-wrap');
      if ($(this).val() === 'custom') {
        badgeWrap.slideDown(150);
      } else {
        badgeWrap.slideUp(150);
      }
      updateLivePreview();
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
    list.on('input change', '.ntv-item-title-input, .ntv-item-badge-type-select, .ntv-item-badge-text-input', function () {
      updateLivePreview();
    });

    // Add new item
    $('#ntv-add-menu-item-btn').on('click', function () {
      const newIndex = list.children('.ntv-menu-item-row').length;
      const defaultIcon = 'compass';
      const defaultIconSvg = getIconSvg(defaultIcon);

      const rowHtml = `
        <div class="ntv-menu-item-row" data-index="${newIndex}">
          <div class="ntv-row-drag-handle" title="جابجایی آیتم"><span class="dashicons dashicons-menu"></span></div>
          <div class="ntv-row-preview-icon">
            <button type="button" class="ntv-row-icon-trigger" title="${data.i18n.selectIcon || 'انتخاب آیکون'}">
              <span class="ntv-row-current-icon">${defaultIconSvg}</span>
            </button>
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon]" class="ntv-item-icon-input" value="${defaultIcon}">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon_type]" class="ntv-item-icon-type-input" value="lucide">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][custom_svg]" class="ntv-item-custom-svg-input" value="">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][id]" value="item_${Date.now()}">
          </div>
          <div class="ntv-row-fields">
            <div class="ntv-row-main-inputs">
              <input type="text" name="ntv_settings[menu_items][${newIndex}][title]" class="ntv-item-title-input regular-text" value="آیتم جدید" placeholder="عنوان تب" required>
              <input type="url" name="ntv_settings[menu_items][${newIndex}][url]" class="ntv-item-url-input regular-text" value="/" placeholder="https://..." required>
            </div>
            <div class="ntv-row-options-toggle">
              <button type="button" class="ntv-toggle-options-btn"><span class="dashicons dashicons-arrow-down-alt2"></span> گزینه‌های پیشرفته (نشان، آیکون سفارشی و دسترسی)</button>
            </div>
            <div class="ntv-row-extra-options" style="display:none;">
              <div class="ntv-grid-2">
                <div class="ntv-field-group">
                  <label>نوع نشان (Badge):</label>
                  <select name="ntv_settings[menu_items][${newIndex}][badge_type]" class="ntv-item-badge-type-select">
                    <option value="none">بدون نشان</option>
                    <option value="wc_cart_count">تعداد سبد خرید ووکامرس (همگام زنده)</option>
                    <option value="custom">متن یا عدد سفارشی</option>
                  </select>
                </div>
                <div class="ntv-field-group ntv-custom-badge-text-wrap" style="display:none;">
                  <label>متن نشان:</label>
                  <input type="text" name="ntv_settings[menu_items][${newIndex}][badge_text]" class="ntv-item-badge-text-input" value="" placeholder="مثال: جدید یا 5">
                </div>
              </div>
              <div class="ntv-field-group ntv-mt-2">
                <label>کد SVG سفارشی (جایگزین آیکون‌های پیش‌فرض):</label>
                <textarea name="ntv_settings[menu_items][${newIndex}][custom_svg]" class="large-text ntv-item-custom-svg-textarea" rows="2" placeholder="<svg viewBox='0 0 24 24'>...</svg>"></textarea>
              </div>
              <div class="ntv-field-group ntv-mt-2">
                <label>سطح دسترسی نمایش:</label>
                <select name="ntv_settings[menu_items][${newIndex}][visibility]" class="ntv-item-visibility-select">
                  <option value="all">نمایش برای همه کاربران</option>
                  <option value="logged_in">فقط کاربران وارد شده</option>
                  <option value="guests">فقط کاربران مهمان</option>
                </select>
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
   * Reindex Main Menu repeater array names
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
   * Glass Bubble Popover Submenu ("More" Item) Repeater
   */
  function initBubbleMenuRepeater() {
    const list = $('#ntv-bubble-menu-items-list');

    // Toggle bubble menu settings panel
    $('#ntv-enable-more-menu').on('change', function () {
      if ($(this).is(':checked')) {
        $('#ntv-more-menu-settings-wrap').slideDown(200);
      } else {
        $('#ntv-more-menu-settings-wrap').slideUp(200);
      }
      updateLivePreview();
    });

    $('#ntv-more-menu-title').on('input change', function () {
      updateLivePreview();
    });

    if ($.fn.sortable) {
      list.sortable({
        handle: '.ntv-row-drag-handle',
        axis: 'y',
        opacity: 0.7,
        update: function () {
          reindexBubbleRepeater();
          updateLivePreview();
        }
      });
    }

    // Toggle extra options
    list.on('click', '.ntv-toggle-options-btn', function () {
      $(this).closest('.ntv-bubble-item-row').find('.ntv-row-extra-options').slideToggle(200);
    });

    // Delete bubble item
    list.on('click', '.ntv-delete-item-btn', function () {
      $(this).closest('.ntv-bubble-item-row').remove();
      reindexBubbleRepeater();
      updateLivePreview();
    });

    // Live update on change
    list.on('input change', '.ntv-item-title-input, .ntv-item-url-input', function () {
      updateLivePreview();
    });

    // Add new bubble item
    $('#ntv-add-bubble-item-btn').on('click', function () {
      const newIndex = list.children('.ntv-bubble-item-row').length;
      const defaultIcon = 'compass';
      const defaultIconSvg = getIconSvg(defaultIcon);

      const rowHtml = `
        <div class="ntv-menu-item-row ntv-bubble-item-row" data-index="${newIndex}">
          <div class="ntv-row-drag-handle" title="جابجایی آیتم"><span class="dashicons dashicons-menu"></span></div>
          <div class="ntv-row-preview-icon">
            <button type="button" class="ntv-row-icon-trigger" title="${data.i18n.selectIcon || 'انتخاب آیکون'}">
              <span class="ntv-row-current-icon">${defaultIconSvg}</span>
            </button>
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][icon]" class="ntv-item-icon-input" value="${defaultIcon}">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][icon_type]" class="ntv-item-icon-type-input" value="lucide">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][custom_svg]" class="ntv-item-custom-svg-input" value="">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][id]" value="bitem_${Date.now()}">
          </div>
          <div class="ntv-row-fields">
            <div class="ntv-row-main-inputs">
              <input type="text" name="ntv_settings[more_menu_items][${newIndex}][title]" class="regular-text ntv-item-title-input" value="لینک جدید" placeholder="عنوان لینک" required>
              <input type="url" name="ntv_settings[more_menu_items][${newIndex}][url]" class="regular-text ntv-item-url-input" value="/" placeholder="https://..." required>
            </div>
            <div class="ntv-row-options-toggle">
              <button type="button" class="ntv-toggle-options-btn"><span class="dashicons dashicons-arrow-down-alt2"></span> گزینه‌های دسترسی</button>
            </div>
            <div class="ntv-row-extra-options" style="display:none;">
              <div class="ntv-field-group">
                <label>سطح دسترسی نمایش:</label>
                <select name="ntv_settings[more_menu_items][${newIndex}][visibility]" class="ntv-item-visibility-select">
                  <option value="all">نمایش برای همه کاربران</option>
                  <option value="logged_in">فقط کاربران وارد شده</option>
                  <option value="guests">فقط کاربران مهمان</option>
                </select>
              </div>
            </div>
          </div>
          <div class="ntv-row-actions">
            <button type="button" class="ntv-delete-item-btn" title="حذف آیتم"><span class="dashicons dashicons-trash"></span></button>
          </div>
        </div>
      `;

      list.append(rowHtml);
      reindexBubbleRepeater();
      updateLivePreview();
    });
  }

  /**
   * Reindex Bubble Menu repeater array names
   */
  function reindexBubbleRepeater() {
    $('#ntv-bubble-menu-items-list .ntv-bubble-item-row').each(function (index) {
      $(this).attr('data-index', index);
      $(this).find('input, select, textarea').each(function () {
        const name = $(this).attr('name');
        if (name) {
          const updatedName = name.replace(/\[more_menu_items\]\[\d+\]/, `[more_menu_items][${index}]`);
          $(this).attr('name', updatedName);
        }
      });
    });
  }

  /**
   * Navbar Width Control Switcher
   */
  function initNavbarWidthControl() {
    $('input[name="ntv_settings[navbar_width_mode]"]').on('change', function () {
      if ($(this).val() === 'custom') {
        $('#ntv-custom-width-wrap').slideDown(200);
      } else {
        $('#ntv-custom-width-wrap').slideUp(200);
      }
      updateLivePreview();
    });

    $('#ntv-navbar-custom-width').on('input change', function () {
      updateLivePreview();
    });
  }

  /**
   * Lucide Visual Icon Picker Modal
   */
  function initIconPicker() {
    const modal = $('#ntv-icon-picker-modal');
    const grid = $('#ntv-modal-icons-grid');
    const search = $('#ntv-icon-search-input');

    // Populate catalog safely with rendered SVGs
    let iconsHtml = '';
    const catalog = data.iconSvgs || data.icons || {};

    Object.keys(catalog).forEach(function (iconName) {
      const svgMarkup = getIconSvg(iconName);
      const iconLabel = getIconLabel(iconName);

      iconsHtml += `
        <div class="ntv-modal-icon-card" data-icon="${iconName}">
          <div class="ntv-modal-icon-svg">${svgMarkup}</div>
          <span class="ntv-modal-icon-name">${iconLabel}</span>
          <small class="ntv-modal-icon-slug">${iconName}</small>
        </div>
      `;
    });
    grid.html(iconsHtml);

    // Open Modal from Main Menu repeater row
    $('#ntv-menu-items-list').on('click', '.ntv-row-icon-trigger', function () {
      currentTargetRow = $(this).closest('.ntv-menu-item-row');
      search.val('').trigger('input');
      modal.fadeIn(150);
    });

    // Open Modal from Bubble Menu repeater row
    $('#ntv-bubble-menu-items-list').on('click', '.ntv-row-icon-trigger', function () {
      currentTargetRow = $(this).closest('.ntv-menu-item-row');
      search.val('').trigger('input');
      modal.fadeIn(150);
    });

    // Open Modal from More Menu trigger icon picker
    $('#ntv-more-menu-icon-trigger').on('click', function () {
      currentTargetRow = $(this);
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
        const iconName = ($(this).data('icon') || '').toString().toLowerCase();
        const iconLabel = $(this).find('.ntv-modal-icon-name').text().toLowerCase();
        if (!q || iconName.includes(q) || iconLabel.includes(q)) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    });

    // Select icon
    grid.on('click', '.ntv-modal-icon-card', function () {
      const selectedIcon = $(this).data('icon');
      const selectedSvg = getIconSvg(selectedIcon);

      if (currentTargetRow) {
        if (currentTargetRow.is('#ntv-more-menu-icon-trigger') || currentTargetRow.attr('id') === 'ntv-more-menu-icon-trigger') {
          $('#ntv-more-menu-icon-input').val(selectedIcon);
          $('#ntv-more-menu-icon-preview').html(selectedSvg);
        } else {
          currentTargetRow.find('.ntv-item-icon-input').val(selectedIcon);
          currentTargetRow.find('.ntv-item-icon-type-input').val('lucide');
          currentTargetRow.find('.ntv-row-current-icon').html(selectedSvg);
        }
        modal.fadeOut(150);
        updateLivePreview();
      }
    });
  }

  /**
   * WP Media Uploader for 512x512 PWA Icon
   */
  function initMediaUploader() {
    let fileFrame;
    $('#ntv-upload-icon-btn').on('click', function (e) {
      e.preventDefault();

      if (fileFrame) {
        fileFrame.open();
        return;
      }

      fileFrame = wp.media({
        title: 'انتخاب آیکون PWA (حداقل 512x512 پیکسل)',
        button: { text: 'انتخاب آیکون' },
        multiple: false
      });

      fileFrame.on('select', function () {
        const attachment = fileFrame.state().get('selection').first().toJSON();
        $('#ntv-pwa-icon-id').val(attachment.id);
        $('#ntv-pwa-icon-512').val(attachment.url);
        $('#ntv-icon-512-preview').html(`<img src="${attachment.url}" alt="Icon Preview">`);
      });

      fileFrame.open();
    });
  }

  /**
   * AJAX: Purge PWA Cache and bust client Service Workers
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
          const v = res.data && res.data.version ? res.data.version : Date.now();
          $('#ntv-cache-version-badge').text('ntv-v' + v);
          $('#ntv-tools-cache-version').text('ntv-v' + v);
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
    $('select[name="ntv_settings[header_hiding_preset]"]').on('change', function () {
      if ($(this).val() === 'custom') {
        $('input[name="ntv_settings[custom_header_selector]"]').closest('.ntv-field-group').slideDown(200);
      }
    });
  }

  /**
   * Diagnostics Persian Tooltips & Explanations Accordion Toggle
   */
  function initDiagnosticsHelp() {
    $('.ntv-help-trigger').on('click', function (e) {
      e.preventDefault();
      $(this).closest('.ntv-check-content').find('.ntv-help-box').slideToggle(200);
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
    const activePillColor = $('#ntv-active-pill-bg, #ntv-color-active-pill').val() || '#0047FF';
    const activeTextColor = $('#ntv-active-text-color, #ntv-color-active-text').val() || '#FFFFFF';
    const inactiveIconColor = $('#ntv-inactive-icon-color, #ntv-color-inactive-icon').val() || 'rgba(255, 255, 255, 0.7)';
    const glassBg = $('#ntv-glass-bg-color, #ntv-color-glass-bg').val() || 'rgba(18, 18, 24, 0.82)';
    const glassBorder = $('#ntv-glass-border-color').val() || 'rgba(255, 255, 255, 0.12)';
    const blur = $('#ntv-glass-blur, #ntv-blur-slider').val() || 20;
    const radius = $('#ntv-border-radius, #ntv-radius-slider').val() || 999;
    const offset = $('#ntv-mobile-bottom-offset, #ntv-offset-input').val() || 16;
    const widthMode = $('input[name="ntv_settings[navbar_width_mode]"]:checked').val() || 'auto';
    const customWidth = $('#ntv-navbar-custom-width').val() || '90%';

    // Apply CSS Variables directly to Mockup container & elements
    const mockupNav = $('#ntv-preview-nav-root');
    const glassNav = mockupNav.find('.ntv-glass-nav');

    mockupNav.css('bottom', offset + 'px');

    // Apply Width Mode to Mockup
    mockupNav.removeClass('ntv-width-auto ntv-width-full ntv-width-custom');
    if (widthMode === 'full') {
      mockupNav.addClass('ntv-width-full');
      mockupNav.css('width', 'calc(100% - 24px)');
      mockupNav.css('max-width', 'none');
    } else if (widthMode === 'custom') {
      mockupNav.addClass('ntv-width-custom');
      mockupNav.css('width', customWidth);
      mockupNav.css('max-width', customWidth);
    } else {
      mockupNav.addClass('ntv-width-auto');
      mockupNav.css('width', 'auto');
      mockupNav.css('max-width', 'none');
    }

    glassNav.css({
      'background-color': glassBg,
      'border-color': glassBorder,
      'backdrop-filter': `blur(${blur}px)`,
      '-webkit-backdrop-filter': `blur(${blur}px)`,
      'border-radius': radius + 'px'
    });

    // Mockup scoped CSS Variables
    mockupNav[0].style.setProperty('--ntv-active-bg', activePillColor);
    mockupNav[0].style.setProperty('--ntv-active-text', activeTextColor);
    mockupNav[0].style.setProperty('--ntv-inactive-color', inactiveIconColor);
    mockupNav[0].style.setProperty('--ntv-glass-bg', glassBg);
    mockupNav[0].style.setProperty('--ntv-glass-border', glassBorder);
    mockupNav[0].style.setProperty('--ntv-glass-blur', blur + 'px');
    mockupNav[0].style.setProperty('--ntv-radius', radius + 'px');

    // Build Preview Items from Repeater
    let itemsHtml = '';
    const activeIndex = previewList.find('.ntv-nav-item.is-active').data('index') || 0;

    $('#ntv-menu-items-list .ntv-menu-item-row').each(function (idx) {
      const title = $(this).find('.ntv-item-title-input').val() || 'تب';
      const iconKey = $(this).find('.ntv-item-icon-input').val() || 'home';
      const badgeType = $(this).find('.ntv-item-badge-type-select').val();
      const badgeText = $(this).find('.ntv-item-badge-text-input').val();
      const iconSvg = getIconSvg(iconKey);

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

    // Check if Glass Bubble More menu is enabled
    const enableMoreMenu = $('#ntv-enable-more-menu').is(':checked');
    if (enableMoreMenu) {
      const moreTitle = $('#ntv-more-menu-title').val() || 'بیشتر';
      const moreIcon = $('#ntv-more-menu-icon-input').val() || 'grid';
      const moreSvg = getIconSvg(moreIcon);

      itemsHtml += `
        <a href="javascript:void(0)" class="ntv-nav-item ntv-more-trigger-item" id="ntv-mockup-more-trigger" title="${moreTitle}">
          <span class="ntv-icon-wrap" aria-hidden="true">
            ${moreSvg}
          </span>
          <span class="ntv-label">${moreTitle}</span>
        </a>
      `;

      // Build Bubble Submenu Popover Content
      let bubbleItemsHtml = '';
      $('#ntv-bubble-menu-items-list .ntv-bubble-item-row').each(function () {
        const bTitle = $(this).find('.ntv-item-title-input').val() || 'لینک';
        const bIcon = $(this).find('.ntv-item-icon-input').val() || 'compass';
        const bSvg = getIconSvg(bIcon);

        bubbleItemsHtml += `
          <a href="javascript:void(0)" class="ntv-bubble-item">
            <span class="ntv-bubble-item-icon">${bSvg}</span>
            <span class="ntv-bubble-item-title">${bTitle}</span>
          </a>
        `;
      });

      if (!bubbleItemsHtml) {
        bubbleItemsHtml = '<p style="color:#94a3b8; font-size:11px; margin:8px auto; text-align:center; grid-column:span 3;">آیتمی در منوی حبابی وجود ندارد.</p>';
      }
      $('#ntv-mockup-bubble-grid').html(bubbleItemsHtml);
    } else {
      $('#ntv-mockup-bubble').hide();
    }

    previewList.html(itemsHtml);

    // Interactive Preview: Click tab to expand pill
    previewList.find('.ntv-nav-item:not(.ntv-more-trigger-item)').off('click').on('click', function () {
      previewList.find('.ntv-nav-item').removeClass('is-active');
      $(this).addClass('is-active');
      $('#ntv-mockup-bubble').fadeOut(150);
    });

    // Interactive Preview: Click More trigger to toggle bubble
    $('#ntv-mockup-more-trigger').off('click').on('click', function (e) {
      e.stopPropagation();
      $('#ntv-mockup-bubble').fadeToggle(150);
    });
  }

})(jQuery);
