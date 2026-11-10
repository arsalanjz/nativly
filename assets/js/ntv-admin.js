/**
 * Nativly - Admin Dashboard Controller, Live Mockup Preview & Icon Picker
 * Author: Arsalan Jabarzare
 */

(function ($) {
  'use strict';

  const data = window.ntvAdminData || {};
  let currentTargetRow = null;
  let lastActiveColorPicker = null;

  /**
   * Helper: Hex to RGBA
   */
  function hexToRgba(hex, opacityPercent) {
    if (!hex) return 'rgba(255, 255, 255, 1)';
    let c = hex.replace('#', '');
    if (c.length === 3) {
      c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
    }
    const num = parseInt(c, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    const a = (parseFloat(opacityPercent) / 100).toFixed(2);
    const cleanAlpha = parseFloat(a);
    return `rgba(${r}, ${g}, ${b}, ${cleanAlpha})`;
  }

  /**
   * Helper: RGBA to Hex and Opacity
   */
  function rgbaToHexAndOpacity(rgbaStr, defaultHex, defaultOpacity) {
    if (!rgbaStr) return { hex: defaultHex || '#FFFFFF', opacity: defaultOpacity || 100 };
    const match = rgbaStr.match(/rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-9.]+)\s*\)/i);
    if (match) {
      const r = parseInt(match[1], 10);
      const g = parseInt(match[2], 10);
      const b = parseInt(match[3], 10);
      const a = parseFloat(match[4]);
      const hex = '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
      const opacity = Math.round(a * 100);
      return { hex: hex, opacity: Math.max(0, Math.min(100, opacity)) };
    }
    if (/^#([a-f0-9]{3}|[a-f0-9]{6})$/i.test(rgbaStr)) {
      return { hex: rgbaStr.toUpperCase(), opacity: 100 };
    }
    return { hex: defaultHex || '#FFFFFF', opacity: defaultOpacity || 100 };
  }

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
    initPwaCustomStyles();

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
   * Tab Navigation Switcher with persistent active tab
   */
  function initTabs() {
    // Check URL param first, then localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const urlTab = urlParams.get('tab');
    let initialTab = urlTab;
    if (!initialTab) {
      try {
        initialTab = localStorage.getItem('ntv_active_tab');
      } catch (e) {}
    }

    if (initialTab && $('#ntv-tab-' + initialTab).length) {
      $('.ntv-tab-btn').removeClass('is-active');
      $(`.ntv-tab-btn[data-tab="${initialTab}"]`).addClass('is-active');
      $('.ntv-tab-content').removeClass('is-active');
      $('#ntv-tab-' + initialTab).addClass('is-active');
      $('#ntv-active-tab-input').val(initialTab);
    }

    $('.ntv-tab-btn').on('click', function () {
      const tabId = $(this).data('tab');

      $('.ntv-tab-btn').removeClass('is-active');
      $(this).addClass('is-active');

      $('.ntv-tab-content').removeClass('is-active');
      $('#ntv-tab-' + tabId).addClass('is-active');

      $('#ntv-active-tab-input').val(tabId);
      try {
        localStorage.setItem('ntv_active_tab', tabId);
      } catch (e) {}
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
   * WordPress Color Pickers, Opacity Sliders & Palette Swatches
   */
  function initColorPickers() {
    if ($.fn.wpColorPicker) {
      // Normal color pickers
      $('.ntv-color-picker:not(.ntv-rgba-base-color)').wpColorPicker({
        change: function (event, ui) {
          if (ui && ui.color) {
            $(this).val(ui.color.toString());
          }
          updateLivePreview();
          setTimeout(updateLivePreview, 50);
        },
        clear: function () {
          updateLivePreview();
          setTimeout(updateLivePreview, 50);
        }
      });

      // Track last focused picker for swatches
      $(document).on('focus click', '.wp-color-result, .wp-picker-container', function () {
        lastActiveColorPicker = $(this).closest('.wp-picker-container').find('.ntv-color-picker');
      });

      // RGBA Color Pickers with Opacity Sliders
      $('.ntv-color-with-opacity').each(function () {
        const wrap = $(this);
        const baseColorInput = wrap.find('.ntv-rgba-base-color');
        const slider = wrap.find('.ntv-opacity-slider');
        const opacityVal = wrap.find('.ntv-opacity-val');
        const hiddenTarget = wrap.find('input[type="hidden"]');

        baseColorInput.wpColorPicker({
          change: function (event, ui) {
            const hex = ui.color.toString();
            const op = slider.val();
            const rgba = hexToRgba(hex, op);
            hiddenTarget.val(rgba);
            setTimeout(updateLivePreview, 50);
          },
          clear: function () {
            const hex = baseColorInput.data('default-color') || '#FFFFFF';
            const op = slider.val();
            const rgba = hexToRgba(hex, op);
            hiddenTarget.val(rgba);
            setTimeout(updateLivePreview, 50);
          }
        });

        slider.on('input change', function () {
          const op = $(this).val();
          opacityVal.text(op + '%');
          let hex = baseColorInput.val();
          if (!hex || !hex.startsWith('#')) {
            hex = baseColorInput.data('default-color') || '#FFFFFF';
          }
          const rgba = hexToRgba(hex, op);
          hiddenTarget.val(rgba);
          updateLivePreview();
        });
      });
    }

    // Palette Swatch Click Handler (Global & Inline)
    $(document).on('click', '.ntv-inline-swatch', function (e) {
      e.preventDefault();
      const color = $(this).data('color');
      if (!color) return;

      const row = $(this).closest('.ntv-color-row-inline');
      const targetPicker = row.find('.ntv-color-picker');

      if (targetPicker && targetPicker.length) {
        if (targetPicker.hasClass('ntv-rgba-base-color')) {
          const slider = row.find('.ntv-opacity-slider');
          const hiddenTarget = row.find('input[type="hidden"]');
          targetPicker.val(color);
          if (targetPicker.wpColorPicker) {
            targetPicker.wpColorPicker('color', color);
          }
          const op = slider.length ? slider.val() : 100;
          const rgba = hexToRgba(color, op);
          hiddenTarget.val(rgba);
        } else {
          targetPicker.val(color);
          if (targetPicker.wpColorPicker) {
            targetPicker.wpColorPicker('color', color);
          }
        }
        updateLivePreview();
        setTimeout(updateLivePreview, 50);
      }
    });

    $('.ntv-palette-swatch').on('click', function (e) {
      e.preventDefault();
      const color = $(this).data('color');
      if (!color) return;

      let targetPicker = lastActiveColorPicker;
      if (!targetPicker || !targetPicker.length || !targetPicker.is(':visible')) {
        targetPicker = $('#ntv-active-pill-bg');
      }

      if (targetPicker && targetPicker.length) {
        if (targetPicker.hasClass('ntv-rgba-base-color')) {
          const wrap = targetPicker.closest('.ntv-color-with-opacity');
          const slider = wrap.find('.ntv-opacity-slider');
          const hiddenTarget = wrap.find('input[type="hidden"]');
          targetPicker.val(color);
          if (targetPicker.wpColorPicker) {
            targetPicker.wpColorPicker('color', color);
          }
          const rgba = hexToRgba(color, slider.val());
          hiddenTarget.val(rgba);
        } else {
          targetPicker.val(color);
          if (targetPicker.wpColorPicker) {
            targetPicker.wpColorPicker('color', color);
          }
        }
        updateLivePreview();
      }
    });

    $('#ntv-active-pill-bg, #ntv-active-text-color, #ntv-inactive-icon-color, #ntv-glass-bg-color, #ntv-glass-border-color').on('input change', function () {
      updateLivePreview();
    });
  }

  /**
   * Sliders (Blur & Border Radius & Offset) with Percentage Display
   */
  function initSliders() {
    $('#ntv-glass-blur').on('input change', function () {
      const val = $(this).val();
      $('#ntv-blur-val').text(val + '%');
      updateLivePreview();
    });

    $('#ntv-border-radius').on('input change', function () {
      const val = $(this).val();
      $('#ntv-radius-val').text(val + '%');
      updateLivePreview();
    });

    $('#ntv-mobile-bottom-offset').on('input change', function () {
      const val = $(this).val();
      $('#ntv-offset-val').text(val + '%');
      updateLivePreview();
    });
  }

  /**
   * PWA Custom Styling Controls (Tab 4)
   */
  function initPwaCustomStyles() {
    $('#ntv-pwa-custom-style-enable').on('change', function () {
      if ($(this).is(':checked')) {
        $('#ntv-pwa-custom-style-box').slideDown(200);
      } else {
        $('#ntv-pwa-custom-style-box').slideUp(200);
      }
    });

    $('#ntv-pwa-blur').on('input change', function () {
      const val = $(this).val();
      $('#ntv-pwa-blur-val').text(val + '%');
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
        blur: 50,
        radius: 100
      },
      light_glass: {
        pill_bg: '#2563EB',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(15, 23, 42, 0.65)',
        glass_bg: 'rgba(255, 255, 255, 0.85)',
        glass_border: 'rgba(0, 0, 0, 0.08)',
        blur: 50,
        radius: 100
      },
      cyber_emerald: {
        pill_bg: '#10B981',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(6, 30, 20, 0.84)',
        glass_border: 'rgba(16, 185, 129, 0.25)',
        blur: 50,
        radius: 100
      },
      sunset_violet: {
        pill_bg: '#8B5CF6',
        text_color: '#FFFFFF',
        inactive_color: 'rgba(255, 255, 255, 0.7)',
        glass_bg: 'rgba(28, 16, 45, 0.85)',
        glass_border: 'rgba(139, 92, 246, 0.25)',
        blur: 55,
        radius: 100
      }
    };

    function applyRgbaField(fieldId, rgbaValue, defaultHex, defaultOpacity) {
      const field = $('#' + fieldId);
      field.val(rgbaValue);
      const wrap = field.closest('.ntv-color-with-opacity');
      if (wrap.length) {
        const parsed = rgbaToHexAndOpacity(rgbaValue, defaultHex, defaultOpacity);
        const baseColorInput = wrap.find('.ntv-rgba-base-color');
        const slider = wrap.find('.ntv-opacity-slider');
        const valText = wrap.find('.ntv-opacity-val');

        baseColorInput.val(parsed.hex);
        if (baseColorInput.wpColorPicker) {
          baseColorInput.wpColorPicker('color', parsed.hex);
        }
        slider.val(parsed.opacity);
        valText.text(parsed.opacity + '%');
      }
    }

    $('.ntv-preset-card').on('click', function () {
      const presetKey = $(this).data('preset');
      const p = presets[presetKey];
      if (!p) return;

      $('.ntv-preset-card').removeClass('is-selected');
      $(this).addClass('is-selected');
      $('#ntv-active-preset-input').val(presetKey);

      // Apply values to inputs
      applyRgbaField('ntv-active-pill-bg', p.pill_bg, '#0047FF', 100);
      applyRgbaField('ntv-active-text-color', p.text_color, '#FFFFFF', 100);

      applyRgbaField('ntv-inactive-icon-color', p.inactive_color, '#FFFFFF', 70);
      applyRgbaField('ntv-glass-bg-color', p.glass_bg, '#121218', 82);
      applyRgbaField('ntv-glass-border-color', p.glass_border || 'rgba(255, 255, 255, 0.12)', '#FFFFFF', 12);

      $('#ntv-glass-blur').val(p.blur);
      $('#ntv-blur-val').text(p.blur + '%');

      $('#ntv-border-radius').val(p.radius);
      $('#ntv-radius-val').text(p.radius + '%');

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
    list.on('input change', '.ntv-item-title-input, .ntv-item-url-input, .ntv-item-custom-svg-textarea, .ntv-item-badge-type-select, .ntv-item-badge-text-input', function () {
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
            <button type="button" class="ntv-row-icon-trigger" title="${data.i18n && data.i18n.selectIcon ? data.i18n.selectIcon : 'انتخاب آیکون'}">
              <span class="ntv-row-current-icon">${defaultIconSvg}</span>
            </button>
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon]" class="ntv-item-icon-input" value="${defaultIcon}">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][icon_type]" class="ntv-item-icon-type-input" value="lucide">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][custom_svg]" class="ntv-item-custom-svg-input" value="">
            <input type="hidden" name="ntv_settings[menu_items][${newIndex}][id]" value="item_${Date.now()}">
          </div>
          <div class="ntv-row-fields">
            <div class="ntv-row-main-inputs">
              <input type="text" name="ntv_settings[menu_items][${newIndex}][title]" class="ntv-item-title-input regular-text" value="" placeholder="عنوان آیتم (مثلاً: فروشگاه)" required>
              <input type="url" name="ntv_settings[menu_items][${newIndex}][url]" class="ntv-item-url-input regular-text" value="" placeholder="لینک مقصد (مثلاً: /shop)" required>
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
                <label>کد SVG سفارشی یا آدرس آیکون تصویر:</label>
                <div style="display:flex; gap:8px; align-items:flex-start;">
                  <textarea name="ntv_settings[menu_items][${newIndex}][custom_svg]" class="large-text ntv-item-custom-svg-textarea" rows="2" placeholder="<svg viewBox='0 0 24 24'>...</svg> یا https://.../icon.png"></textarea>
                  <button type="button" class="button button-secondary ntv-upload-custom-icon-btn" title="آپلود / انتخاب آیکون از رسانه" style="white-space:nowrap; height:42px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-media"></span>
                    انتخاب رسانه
                  </button>
                </div>
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
    list.on('input change', '.ntv-item-title-input, .ntv-item-url-input, .ntv-item-custom-svg-textarea', function () {
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
            <button type="button" class="ntv-row-icon-trigger" title="${data.i18n && data.i18n.selectIcon ? data.i18n.selectIcon : 'انتخاب آیکون'}">
              <span class="ntv-row-current-icon">${defaultIconSvg}</span>
            </button>
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][icon]" class="ntv-item-icon-input" value="${defaultIcon}">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][icon_type]" class="ntv-item-icon-type-input" value="lucide">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][custom_svg]" class="ntv-item-custom-svg-input" value="">
            <input type="hidden" name="ntv_settings[more_menu_items][${newIndex}][id]" value="bitem_${Date.now()}">
          </div>
          <div class="ntv-row-fields">
            <div class="ntv-row-main-inputs">
              <input type="text" name="ntv_settings[more_menu_items][${newIndex}][title]" class="regular-text ntv-item-title-input" value="" placeholder="عنوان آیتم (مثلاً: درباره ما)" required>
              <input type="url" name="ntv_settings[more_menu_items][${newIndex}][url]" class="regular-text ntv-item-url-input" value="" placeholder="لینک مقصد (مثلاً: /about)" required>
            </div>
            <div class="ntv-row-options-toggle">
              <button type="button" class="ntv-toggle-options-btn"><span class="dashicons dashicons-arrow-down-alt2"></span> گزینه‌های پیشرفته (آیکون سفارشی و دسترسی)</button>
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
              <div class="ntv-field-group ntv-mt-2">
                <label>کد SVG سفارشی یا آدرس آیکون تصویر:</label>
                <div style="display:flex; gap:8px; align-items:flex-start;">
                  <textarea name="ntv_settings[more_menu_items][${newIndex}][custom_svg]" class="large-text ntv-item-custom-svg-textarea" rows="2" placeholder="<svg viewBox='0 0 24 24'>...</svg> یا https://.../icon.png"></textarea>
                  <button type="button" class="button button-secondary ntv-upload-custom-icon-btn" title="آپلود / انتخاب آیکون از رسانه" style="white-space:nowrap; height:42px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-media"></span>
                    انتخاب رسانه
                  </button>
                </div>
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
   * WP Media Uploader for 512x512 PWA Icon & Custom Icons
   */
  function initMediaUploader() {
    let pwaFileFrame;
    let customIconFrame;

    // 512x512 PWA Icon
    $('#ntv-upload-icon-btn').on('click', function (e) {
      e.preventDefault();

      if (pwaFileFrame) {
        pwaFileFrame.open();
        return;
      }

      pwaFileFrame = wp.media({
        title: 'انتخاب آیکون PWA (حداقل 512x512 پیکسل)',
        button: { text: 'انتخاب آیکون' },
        multiple: false
      });

      pwaFileFrame.on('select', function () {
        const attachment = pwaFileFrame.state().get('selection').first().toJSON();
        $('#ntv-pwa-icon-id').val(attachment.id);
        $('#ntv-pwa-icon-512').val(attachment.url);
        $('#ntv-icon-512-preview').html(`<img src="${attachment.url}" alt="Icon Preview">`);
      });

      pwaFileFrame.open();
    });

    // Custom Icon / Media upload for repeater rows
    $(document).on('click', '.ntv-upload-custom-icon-btn', function (e) {
      e.preventDefault();
      const row = $(this).closest('.ntv-menu-item-row');

      customIconFrame = wp.media({
        title: 'انتخاب یا آپلود آیکون سفارشی / تصویر',
        button: { text: 'انتخاب این آیکون' },
        multiple: false
      });

      customIconFrame.on('select', function () {
        const attachment = customIconFrame.state().get('selection').first().toJSON();
        row.find('.ntv-item-custom-svg-input').val(attachment.url);
        row.find('.ntv-item-custom-svg-textarea').val(attachment.url);
        row.find('.ntv-item-icon-type-input').val('custom');
        row.find('.ntv-row-current-icon').html(`<img src="${attachment.url}" class="ntv-custom-icon" style="width:22px;height:22px;object-fit:contain;">`);
        updateLivePreview();
      });

      customIconFrame.open();
    });

    // Custom Icon upload from within Lucide modal
    $('#ntv-modal-upload-icon-btn').on('click', function (e) {
      e.preventDefault();
      customIconFrame = wp.media({
        title: 'انتخاب یا آپلود آیکون سفارشی / تصویر',
        button: { text: 'انتخاب این آیکون' },
        multiple: false
      });

      customIconFrame.on('select', function () {
        const attachment = customIconFrame.state().get('selection').first().toJSON();
        if (currentTargetRow) {
          if (currentTargetRow.is('#ntv-more-menu-icon-trigger') || currentTargetRow.attr('id') === 'ntv-more-menu-icon-trigger') {
            $('#ntv-more-menu-icon-input').val(attachment.url);
            $('#ntv-more-menu-icon-preview').html(`<img src="${attachment.url}" class="ntv-custom-icon" style="width:22px;height:22px;object-fit:contain;">`);
          } else {
            currentTargetRow.find('.ntv-item-custom-svg-input').val(attachment.url);
            currentTargetRow.find('.ntv-item-custom-svg-textarea').val(attachment.url);
            currentTargetRow.find('.ntv-item-icon-type-input').val('custom');
            currentTargetRow.find('.ntv-row-current-icon').html(`<img src="${attachment.url}" class="ntv-custom-icon" style="width:22px;height:22px;object-fit:contain;">`);
          }
          $('#ntv-icon-picker-modal').fadeOut(150);
          updateLivePreview();
        }
      });

      customIconFrame.open();
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
          showAjaxNotice(res.data.message || (data.i18n && data.i18n.purgeSuccess ? data.i18n.purgeSuccess : 'کش با موفقیت پاک‌سازی شد.'), 'updated');
        } else {
          showAjaxNotice((res.data && res.data.message) || 'خطا در پاک‌سازی کش', 'error');
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
      const confirmMsg = (data.i18n && data.i18n.resetConfirm) ? data.i18n.resetConfirm : 'آیا مطمئن هستید که می‌خواهید همه تنظیمات را بازنشانی کنید؟';
      if (!confirm(confirmMsg)) {
        return;
      }

      $.post(data.ajaxUrl, {
        action: 'ntv_reset_settings',
        nonce: data.nonce
      }, function (res) {
        if (res.success) {
          window.location.reload();
        } else {
          showAjaxNotice((res.data && res.data.message) || 'خطا در بازنشانی تنظیمات', 'error');
        }
      }).fail(function () {
        showAjaxNotice('خطای ارتباط با سرور', 'error');
      });
    });
  }

  /**
   * Theme Header Hiding Preset Switcher
   */
  function initThemeHeaderHidingToggle() {
    const presetSelect = $('select[name="ntv_settings[header_hiding_preset]"]');
    const customWrap = $('#ntv-custom-selectors-wrap');

    presetSelect.on('change', function () {
      if ($(this).val() === 'custom') {
        customWrap.slideDown(200);
      } else {
        customWrap.slideUp(200);
      }
    });
  }

  /**
   * Diagnostics Copy System Info Helper
   */
  function initDiagnosticsHelp() {
    $('#ntv-copy-diagnostics-btn').on('click', function (e) {
      e.preventDefault();
      const codeBlock = $('#ntv-diagnostics-report').text();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(codeBlock).then(function () {
          alert('گزارش عیب‌یابی با موفقیت در کلیپ‌بورد کپی شد.');
        });
      } else {
        const temp = $('<textarea>');
        $('body').append(temp);
        temp.val(codeBlock).select();
        document.execCommand('copy');
        temp.remove();
        alert('گزارش عیب‌یابی با موفقیت در کلیپ‌بورد کپی شد.');
      }
    });
  }

  /**
   * Helper: Show transient admin notice for AJAX responses
   */
  function showAjaxNotice(message, type) {
    const notice = $('#ntv-ajax-notice');
    notice.removeClass('notice-success notice-error notice-warning updated error')
      .addClass(type === 'updated' ? 'notice-success updated' : 'notice-error error')
      .find('p').text(message);

    notice.slideDown(200);
    setTimeout(function () {
      notice.slideUp(200);
    }, 4000);
  }

  /**
   * Helper: Render icon in preview (handles Lucide, custom SVG, and image URLs)
   */
  function renderItemIcon(iconType, iconKey, customSvg) {
    if (iconType === 'custom' && customSvg) {
      const trimmed = customSvg.trim();
      if (/^(https?:\/\/|\/)/i.test(trimmed)) {
        return `<img src="${trimmed}" class="ntv-custom-icon" style="width:22px;height:22px;object-fit:contain;display:block;">`;
      }
      if (trimmed.includes('<svg')) {
        return trimmed;
      }
    }
    return getIconSvg(iconKey || 'compass');
  }

  /**
   * Core Live Interactive Mobile Preview Engine
   */
  function updateLivePreview() {
    const mockupNav = $('#ntv-preview-nav-root');
    const previewList = $('#ntv-preview-nav-list');
    if (!mockupNav.length || !previewList.length) return;

    // Get current values
    const pillBg = $('#ntv-active-pill-bg').val() || '#0047FF';
    const textColor = $('#ntv-active-text-color').val() || '#FFFFFF';
    const inactiveColor = $('#ntv-inactive-icon-color').val() || 'rgba(255, 255, 255, 0.7)';
    const glassBg = $('#ntv-glass-bg-color').val() || 'rgba(18, 18, 24, 0.82)';
    const glassBorder = $('#ntv-glass-border-color').val() || 'rgba(255, 255, 255, 0.12)';
    const blurPercent = parseInt($('#ntv-glass-blur').val(), 10) || 50;
    const radiusPercent = parseInt($('#ntv-border-radius').val(), 10) || 100;
    const offsetPercent = parseInt($('#ntv-mobile-bottom-offset').val(), 10) || 25;

    const blurPx = Math.round((blurPercent / 100) * 40);
    const radiusPx = radiusPercent >= 100 ? 999 : Math.round((radiusPercent / 100) * 48);
    const offsetPx = Math.round((offsetPercent / 100) * 64);
    const widthMode = $('input[name="ntv_settings[navbar_width_mode]"]:checked').val() || 'auto';
    const customWidth = $('#ntv-navbar-custom-width').val() || 340;

    // Width classes
    mockupNav.removeClass('ntv-width-auto ntv-width-full ntv-width-custom');
    if (widthMode === 'full') {
      mockupNav.addClass('ntv-width-full');
      mockupNav[0].style.removeProperty('--ntv-custom-width');
    } else if (widthMode === 'custom') {
      mockupNav.addClass('ntv-width-custom');
      mockupNav[0].style.setProperty('--ntv-custom-width', customWidth + 'px');
    } else {
      mockupNav.addClass('ntv-width-auto');
      mockupNav[0].style.removeProperty('--ntv-custom-width');
    }

    mockupNav.css('bottom', offsetPx + 'px');

    // Apply CSS Variables directly to container and glass nav
    const glassNav = mockupNav.find('.ntv-glass-nav');
    [mockupNav[0], glassNav[0]].forEach(function (el) {
      if (!el) return;
      el.style.setProperty('--ntv-active-bg', pillBg);
      el.style.setProperty('--ntv-active-pill-bg', pillBg);
      el.style.setProperty('--ntv-pill-bg', pillBg);
      el.style.setProperty('--ntv-active-text', textColor);
      el.style.setProperty('--ntv-active-text-color', textColor);
      el.style.setProperty('--ntv-inactive-color', inactiveColor);
      el.style.setProperty('--ntv-inactive-icon', inactiveColor);
      el.style.setProperty('--ntv-glass-bg', glassBg);
      el.style.setProperty('--ntv-glass-border', glassBorder);
      el.style.setProperty('--ntv-blur', blurPx + 'px');
      el.style.setProperty('--ntv-glass-blur', blurPx + 'px');
      el.style.setProperty('--ntv-border-radius', radiusPx + 'px');
      el.style.setProperty('--ntv-radius', radiusPx + 'px');
      el.style.setProperty('--ntv-bottom-offset', offsetPx + 'px');
    });

    glassNav.css({
      'background': glassBg,
      'border-color': glassBorder,
      'border-radius': radiusPx + 'px',
      'backdrop-filter': 'blur(' + blurPx + 'px) saturate(180%)',
      '-webkit-backdrop-filter': 'blur(' + blurPx + 'px) saturate(180%)'
    });

    // Build Preview Items from Repeater
    let itemsHtml = '';
    let currentActiveIndex = previewList.find('.ntv-nav-item.is-active').data('index');
    if (typeof currentActiveIndex === 'undefined' || currentActiveIndex === null) {
      currentActiveIndex = 0;
    }

    $('#ntv-menu-items-list .ntv-menu-item-row').each(function (idx) {
      const title = $(this).find('.ntv-item-title-input').val() || 'تب';
      const iconKey = $(this).find('.ntv-item-icon-input').val() || 'home';
      const iconType = $(this).find('.ntv-item-icon-type-input').val() || 'lucide';
      const customSvg = $(this).find('.ntv-item-custom-svg-input').val() || $(this).find('.ntv-item-custom-svg-textarea').val() || '';
      const badgeType = $(this).find('.ntv-item-badge-type-select').val();
      const badgeText = $(this).find('.ntv-item-badge-text-input').val();
      const iconSvg = renderItemIcon(iconType, iconKey, customSvg);

      const isActive = (idx === parseInt(currentActiveIndex, 10));
      const activeClass = isActive ? 'is-active' : '';

      let badgeHtml = '';
      if (badgeType === 'wc_cart_count') {
        badgeHtml = `<span class="ntv-badge ntv-cart-badge" data-count="3">3</span>`;
      } else if (badgeType === 'custom' && badgeText) {
        badgeHtml = `<span class="ntv-badge ntv-custom-badge">${badgeText}</span>`;
      }

      itemsHtml += `
        <a href="javascript:void(0)" class="ntv-nav-item ${activeClass}" data-index="${idx}">
          <span class="ntv-icon-wrap ntv-nav-icon" aria-hidden="true">
            ${iconSvg}
            ${badgeHtml}
          </span>
          <span class="ntv-label ntv-nav-label">${title}</span>
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
        const bIconType = $(this).find('.ntv-item-icon-type-input').val() || 'lucide';
        const bCustomSvg = $(this).find('.ntv-item-custom-svg-input').val() || $(this).find('.ntv-item-custom-svg-textarea').val() || '';
        const bSvg = renderItemIcon(bIconType, bIcon, bCustomSvg);

        bubbleItemsHtml += `
          <a href="javascript:void(0)" class="ntv-bubble-item">
            <span class="ntv-bubble-item-icon">${bSvg}</span>
            <span class="ntv-bubble-item-title">${bTitle}</span>
          </a>
        `;
      });

      if (!bubbleItemsHtml) {
        bubbleItemsHtml = '<p style="color:#94a3b8; font-size:11px; margin:8px auto; text-align:center; width:100%;">آیتمی در منوی حبابی وجود ندارد.</p>';
      }
      $('#ntv-mockup-bubble-grid').html(bubbleItemsHtml);
      const mockupPopover = $('#ntv-mockup-bubble-popover, #ntv-mockup-bubble');
      mockupPopover.find('.ntv-bubble-glass-card').css({
        'background': glassBg,
        'border-color': glassBorder,
        'border-radius': '18px',
        'backdrop-filter': 'blur(' + blurPx + 'px)',
        '-webkit-backdrop-filter': 'blur(' + blurPx + 'px)'
      });
    } else {
      $('#ntv-mockup-bubble-popover, #ntv-mockup-bubble').removeClass('is-open is-visible').attr('aria-hidden', 'true').hide();
    }

    previewList.html(itemsHtml);

    // Directly apply dynamic colors to active and inactive items
    previewList.find('.ntv-nav-item.is-active').css({
      'background': pillBg,
      'color': textColor,
      'box-shadow': 'none'
    });
    previewList.find('.ntv-nav-item.is-active .ntv-label').css({
      'color': textColor
    });
    previewList.find('.ntv-nav-item:not(.is-active)').css({
      'background': 'transparent',
      'color': inactiveColor
    });
    previewList.find('.ntv-nav-item:not(.is-active) .ntv-label').css({
      'color': inactiveColor
    });

    // Interactive Preview: Click tab to expand pill
    previewList.find('.ntv-nav-item:not(.ntv-more-trigger-item)').off('click').on('click', function () {
      previewList.find('.ntv-nav-item').removeClass('is-active').css({
        'background': 'transparent',
        'color': inactiveColor
      });
      previewList.find('.ntv-nav-item .ntv-label').css({ 'color': inactiveColor });
      previewList.find('.ntv-nav-item svg').css({ 'stroke': inactiveColor, 'color': inactiveColor });

      $(this).addClass('is-active').css({
        'background': pillBg,
        'color': textColor,
        'box-shadow': 'none'
      });
      $(this).find('.ntv-label').css({ 'color': textColor });
      $(this).find('svg').css({ 'stroke': textColor, 'color': textColor });
      $('#ntv-mockup-bubble-popover, #ntv-mockup-bubble').removeClass('is-open is-visible').attr('aria-hidden', 'true').fadeOut(150);
      $('#ntv-mockup-more-trigger').removeClass('is-open').css({
        'background': 'transparent',
        'color': inactiveColor
      });
      $('#ntv-mockup-more-trigger').find('svg').css({ 'stroke': inactiveColor, 'color': inactiveColor });
    });

    // Interactive Preview: Click More trigger to toggle bubble
    $('#ntv-mockup-more-trigger').off('click').on('click', function (e) {
      e.stopPropagation();
      const popover = $('#ntv-mockup-bubble-popover, #ntv-mockup-bubble');
      const isVisible = popover.is(':visible') || popover.hasClass('is-open');
      if (isVisible) {
        popover.removeClass('is-open is-visible').attr('aria-hidden', 'true').fadeOut(150);
        $(this).removeClass('is-open').css({
          'background': 'transparent',
          'color': inactiveColor,
          'border-radius': '999px'
        });
        $(this).find('svg').css({ 'stroke': inactiveColor, 'color': inactiveColor });
      } else {
        popover.addClass('is-open is-visible').attr('aria-hidden', 'false').fadeIn(150);
        $(this).addClass('is-open').css({
          'background': pillBg,
          'color': textColor,
          'border-radius': '999px',
          'box-shadow': 'none'
        });
        $(this).find('svg').css({ 'stroke': textColor, 'color': textColor });
      }
    });

    // Close mockup bubble on outside click in phone screen
    $('.ntv-phone-screen').off('click.ntvMockup').on('click.ntvMockup', function (e) {
      if (!$(e.target).closest('#ntv-mockup-bubble-popover, #ntv-mockup-bubble, #ntv-mockup-more-trigger').length) {
        $('#ntv-mockup-bubble-popover, #ntv-mockup-bubble').removeClass('is-open is-visible').attr('aria-hidden', 'true').fadeOut(150);
        $('#ntv-mockup-more-trigger').removeClass('is-open').css({
          'background': 'transparent',
          'color': inactiveColor
        });
        $('#ntv-mockup-more-trigger').find('svg').css({ 'stroke': inactiveColor, 'color': inactiveColor });
      }
    });
  }

})(jQuery);
