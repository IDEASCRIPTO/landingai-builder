(function () {
  'use strict';

  // Per-form state: color + size selection
  var formState = {};

  function getState(formId) {
    if (!formState[formId]) formState[formId] = { colorId: 0, sizeId: 0 };
    return formState[formId];
  }

  // Initialize color/size state from first selected
  function initSelectors(wrap, formId) {
    var firstColor = wrap.querySelector('.df-color-swatch');
    var firstSize  = wrap.querySelector('.df-size-btn');
    var st = getState(formId);
    if (firstColor) st.colorId = parseInt(firstColor.getAttribute('data-color-id')) || 0;
    if (firstSize)  st.sizeId  = parseInt(firstSize.getAttribute('data-size-id'))   || 0;
    updatePackVariations(wrap, formId);
  }

  // Update pack radio values based on selected color+size
  function updatePackVariations(wrap, formId) {
    var st = getState(formId);
    var key = st.colorId + '-' + st.sizeId;
    wrap.querySelectorAll('.df-pack-option input[type="radio"]').forEach(function(radio) {
      var vars = {};
      try { vars = JSON.parse(radio.getAttribute('data-pack-vars') || '{}'); } catch(e) {}
      if (Object.keys(vars).length === 0) return; // fallback WC mode, no change
      var varId = vars[key];
      if (!varId) {
        // Try without size dimension
        varId = vars[st.colorId + '-0'] || vars[Object.keys(vars)[0]] || '0';
      }
      radio.value = varId;
    });
  }

  // Color swatch click
  document.addEventListener('click', function(e) {
    var swatch = e.target.closest('.df-color-swatch');
    if (!swatch) return;
    var wrap   = swatch.closest('.dropi-wrap');
    if (!wrap) return;
    var formId = wrap.id;
    wrap.querySelectorAll('.df-color-swatch').forEach(function(s) { s.classList.remove('selected'); });
    swatch.classList.add('selected');
    getState(formId).colorId = parseInt(swatch.getAttribute('data-color-id')) || 0;
    updatePackVariations(wrap, formId);
  });

  // Size button click
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.df-size-btn');
    if (!btn) return;
    var wrap   = btn.closest('.dropi-wrap');
    if (!wrap) return;
    var formId = wrap.id;
    wrap.querySelectorAll('.df-size-btn').forEach(function(b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    getState(formId).sizeId = parseInt(btn.getAttribute('data-size-id')) || 0;
    updatePackVariations(wrap, formId);
  });

  // Cascading provincia → ciudad
  document.addEventListener('change', function(e) {
    if (e.target.name !== 'provincia') return;
    var wrap = e.target.closest('.dropi-wrap');
    var sel  = wrap ? wrap.querySelector('select[name="ciudad"]') : null;
    if (!sel) return;
    var ciudades = (typeof dropiConfig !== 'undefined') ? dropiConfig.ciudades : {};
    var cod = e.target.value;
    sel.innerHTML = '';
    if (!cod || !ciudades[cod]) {
      sel.innerHTML = '<option value="">Primero selecciona una provincia</option>';
      sel.disabled = true;
      return;
    }
    sel.innerHTML = '<option value="">Selecciona tu ciudad</option>';
    ciudades[cod].forEach(function(c) {
      var o = document.createElement('option');
      o.value = o.textContent = c;
      sel.appendChild(o);
    });
    sel.disabled = false;
  });

  // Update total price on pack change
  document.addEventListener('change', function(e) {
    if (e.target.type !== 'radio' || !e.target.name || e.target.name.indexOf('df_var_') !== 0) return;
    var wrap = e.target.closest('.dropi-wrap');
    var el   = wrap ? wrap.querySelector('.df-total-price') : null;
    if (el && e.target.getAttribute('data-price-html')) el.textContent = e.target.getAttribute('data-price-html');
  });

  // Form submit
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form.classList.contains('df-form')) return;
    e.preventDefault();

    var wrap = form.closest('.dropi-wrap');
    if (!wrap) return;

    var productId = wrap.getAttribute('data-product-id');
    var btn   = form.querySelector('.df-btn-submit');
    var msgOk = form.querySelector('.df-ok');
    var msgEr = form.querySelector('.df-err');

    // Validate required fields
    var ok = true;
    form.querySelectorAll('[required]').forEach(function(f) {
      if (!f.value.trim()) { f.style.borderColor = '#ef4444'; ok = false; }
      else f.style.borderColor = '';
    });
    if (!ok) return;

    var varEl      = wrap.querySelector('input[name^="df_var_"]:checked');
    var varId      = varEl ? varEl.value : '0';
    var orderTotal = varEl ? varEl.getAttribute('data-price') : '0';
    var packLabel  = varEl ? (varEl.getAttribute('data-pack-label') || '') : '';
    var origTxt    = btn.innerHTML;

    // Capture field values before clearing (for WA message)
    var fieldValues = {};
    ['nombre','apellido','telefono','direccion','ciudad'].forEach(function(n) {
      var f = form.querySelector('[name="' + n + '"]');
      fieldValues[n] = f ? f.value.trim() : '';
    });

    btn.disabled  = true;
    btn.innerHTML = '<span class="df-spinner"></span>Procesando pedido...';
    if (msgOk) msgOk.style.display = 'none';
    if (msgEr) msgEr.style.display = 'none';

    fireInitiateCheckout(wrap);

    var fd = new FormData();
    fd.append('action',       'dropi_pedido');
    fd.append('nonce',        dropiConfig.nonce);
    fd.append('product_id',   productId);
    fd.append('variation_id', varId);

    ['nombre','apellido','telefono','provincia','ciudad','direccion','calle2','barrio','numero','cedula','email','notas'].forEach(function(n) {
      var f = form.querySelector('[name="' + n + '"]');
      if (f) fd.append(n, f.value.trim());
    });
    form.querySelectorAll('[name^="custom_"]').forEach(function(f) {
      if (f.value.trim()) {
        fd.append(f.name, f.value.trim());
        fd.append('_label_' + f.name, f.getAttribute('data-label') || f.name);
      }
    });

    fetch(dropiConfig.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          if (msgOk) { msgOk.textContent = data.data.message || msgOk.textContent; msgOk.style.display = 'block'; }
          btn.innerHTML = '✅ Pedido Realizado';
          form.querySelectorAll('input:not([type="radio"]), textarea').forEach(function(f) { f.value = ''; });
          form.querySelectorAll('select').forEach(function(f) { f.selectedIndex = 0; if (f.name === 'ciudad') f.disabled = true; });

          var orderData = {
            order_id:     data.data.order_id,
            total:        parseFloat(data.data.total || orderTotal || 0),
            currency:     data.data.currency || 'USD',
            product_id:   String(data.data.product_id || productId),
            product_name: data.data.product_name || '',
          };

          firePixels(orderData);
          redirectWhatsApp(orderData, fieldValues, packLabel);
        } else {
          if (msgEr) { if (data.data && data.data.message) msgEr.textContent = data.data.message; msgEr.style.display = 'block'; }
          btn.disabled = false; btn.innerHTML = origTxt;
        }
      })
      .catch(function() {
        if (msgEr) msgEr.style.display = 'block';
        btn.disabled = false; btn.innerHTML = origTxt;
      });
  });

  // WhatsApp redirect after successful order
  function redirectWhatsApp(orderData, fields, packLabel) {
    var config = (typeof dropiConfig !== 'undefined' && dropiConfig.config) ? dropiConfig.config : null;
    if (!config || !config.ui || !config.ui.waNum) return;
    var phone = config.ui.waNum.replace(/\D/g, '');
    if (!phone) return;
    var rawMsg = (config.ui.waMsg || 'Hola {nombre}, tu pedido de {producto} fue recibido. Valor: ${precio}')
      .replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n').replace(/\\r/g, '\n');
    var msg = rawMsg
      .replace(/{nombre}/g,    fields.nombre   || '')
      .replace(/{producto}/g,  config.ui.productName || orderData.product_name || '')
      .replace(/{direccion}/g, fields.direccion || '')
      .replace(/{telefono}/g,  fields.telefono  || '')
      .replace(/{cantidad}/g,  packLabel        || '1')
      .replace(/{precio}/g,    orderData.total  || '');
    setTimeout(function() {
      window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
    }, 800);
  }

  // ── Pixel helpers ──────────────────────────────────────────────────────────
  function getPixelCfg() {
    var config = (typeof dropiConfig !== 'undefined' && dropiConfig.config) ? dropiConfig.config : null;
    return {
      ui: config ? (config.ui || {}) : {},
      px: (typeof dropiConfig !== 'undefined' && dropiConfig.pixels) ? dropiConfig.pixels : {},
    };
  }

  function getMetaPixelId() {
    var c = getPixelCfg();
    return (c.px.meta || c.ui.pixelMeta || '').toString().trim();
  }

  function fireMetaEvent(eventName, params, pixelId) {
    if (typeof fbq !== 'function') return;
    try {
      // Use fbq('track') when fbevents.js may not yet be loaded (queued calls).
      // trackSingle is only safe once fbq.callMethod is set (fbevents.js loaded).
      if (pixelId && fbq.callMethod) {
        fbq('trackSingle', pixelId, eventName, params);
      } else {
        fbq('track', eventName, params);
      }
    } catch(e) {}
  }

  function fireTikTokEvent(eventName, params) {
    if (typeof ttq === 'undefined' || typeof ttq.track !== 'function') return;
    try { ttq.track(eventName, params); } catch(e) {}
  }

  function fireGoogleEvent(eventName, params) {
    if (typeof gtag === 'function') {
      try { gtag('event', eventName, params); } catch(e) {}
    }
    if (typeof dataLayer !== 'undefined') {
      try { dataLayer.push(Object.assign({ event: eventName }, params)); } catch(e) {}
    }
  }

  // 1) ViewContent — cuando el formulario es visible en pantalla
  function fireViewContent(wrap) {
    var prodId   = wrap.getAttribute('data-product-id') || '';
    var prodName = wrap.querySelector('.df-title') ? wrap.querySelector('.df-title').textContent.trim() : '';
    var checked  = wrap.querySelector('input[name^="df_var_"]:checked');
    var price    = checked ? parseFloat(checked.getAttribute('data-price') || 0) : 0;

    fireMetaEvent('ViewContent', {
      content_ids: [prodId], content_type: 'product',
      content_name: prodName, value: price, currency: 'USD',
    }, getMetaPixelId());

    fireTikTokEvent('ViewContent', {
      content_id: prodId, content_type: 'product',
      content_name: prodName, value: price, currency: 'USD',
    });

    fireGoogleEvent('view_item', {
      currency: 'USD', value: price,
      items: [{ item_id: prodId, item_name: prodName, quantity: 1, price: price }],
    });
  }

  // 2) InitiateCheckout — cuando el usuario hace clic en "Realizar Pedido"
  function fireInitiateCheckout(wrap) {
    var prodId  = wrap.getAttribute('data-product-id') || '';
    var checked = wrap.querySelector('input[name^="df_var_"]:checked');
    var price   = checked ? parseFloat(checked.getAttribute('data-price') || 0) : 0;
    var label   = checked ? (checked.getAttribute('data-pack-label') || '') : '';

    fireMetaEvent('InitiateCheckout', {
      content_ids: [prodId], content_type: 'product',
      value: price, currency: 'USD', num_items: 1,
    }, getMetaPixelId());

    fireTikTokEvent('InitiateCheckout', {
      content_id: prodId, content_type: 'product',
      description: label, value: price, currency: 'USD',
    });

    fireGoogleEvent('begin_checkout', {
      currency: 'USD', value: price,
      items: [{ item_id: prodId, quantity: 1, price: price }],
    });
  }

  // 3) Purchase — después del pedido exitoso
  function firePixels(o) {
    fireMetaEvent('Purchase', {
      value: o.total, currency: o.currency,
      content_ids: [o.product_id], content_type: 'product',
      order_id: String(o.order_id),
    }, getMetaPixelId());

    fireTikTokEvent('CompletePayment', {
      value: o.total, currency: o.currency,
      content_id: o.product_id, content_type: 'product',
      order_id: String(o.order_id),
    });

    fireGoogleEvent('purchase', {
      transaction_id: String(o.order_id),
      value: o.total, currency: o.currency,
      items: [{ item_id: o.product_id, item_name: o.product_name, quantity: 1, price: o.total }],
    });
  }

  // Init all forms on page load
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dropi-wrap').forEach(function(wrap) {
      initSelectors(wrap, wrap.id);
      fireViewContent(wrap);
    });
  });

})();
