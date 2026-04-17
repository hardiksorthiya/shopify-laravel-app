(function () {
  function formatMoney(symbol, n) {
    var num = Number(n);
    if (Number.isNaN(num)) {
      return symbol + ' 0.00';
    }
    return symbol + ' ' + num.toFixed(2);
  }

  function formatRate(symbol, rate, unit) {
    var num = Number(rate);
    if (Number.isNaN(num) || num <= 0) {
      return '-';
    }
    return formatMoney(symbol, num) + '/' + unit;
  }

  function formatTaxLabelPercent(taxPct) {
    var n = Number(taxPct);
    if (Number.isNaN(n)) {
      return '0';
    }
    if (n % 1 === 0) {
      return String(n);
    }
    return n.toFixed(2);
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function getVariantId(defaultId) {
    var params = new URLSearchParams(window.location.search);
    var fromUrl = params.get('variant');
    if (fromUrl) {
      return fromUrl;
    }
    var form = document.querySelector('form[action*="/cart/add"]');
    var input = form
      ? form.querySelector('select[name="id"], input[name="id"]')
      : document.querySelector('select[name="id"], input[name="id"]');
    if (input && input.value) {
      return String(input.value);
    }
    return defaultId ? String(defaultId) : '';
  }

  function renderBreakup(root, data) {
    var sym = data.currency_symbol || '';
    var m = data.metal || {};
    var d = data.diamond || {};
    var taxPct = data.tax_percent != null ? Number(data.tax_percent) : 0;

    var metalRateCell =
      m.rate != null && Number(m.rate) > 0 ? formatRate(sym, m.rate, 'g') : '-';
    var diamondRateNum = d.rate != null ? Number(d.rate) : 0;
    var diamondRateCell =
      diamondRateNum > 0 ? formatRate(sym, d.rate, 'ct') : '-';

    root.innerHTML =
      '<div class="price-breakup-box">' +
      '<div class="breakup-card">' +
      '<table class="breakup-table">' +
      '<thead><tr>' +
      '<th>Component</th><th>Rate</th><th>Weight</th><th width="150px">Final Value</th>' +
      '</tr></thead>' +
      '<tbody>' +
      '<tr class="breakup-subhead"><td colspan="4">Metal</td></tr>' +
      '<tr>' +
      '<td>' +
      escapeHtml(m.title || 'Metal') +
      '</td>' +
      '<td>' +
      escapeHtml(metalRateCell) +
      '</td>' +
      '<td>' +
      escapeHtml(String(m.weight != null ? m.weight : '0')) +
      ' ' +
      escapeHtml(m.weight_unit || 'g') +
      '</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, m.value) +
      '</td>' +
      '</tr>' +
      '<tr class="breakup-row-total">' +
      '<td>Total Metal Value</td><td>-</td><td>-</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, m.value) +
      '</td>' +
      '</tr>' +
      '</tbody></table></div>' +
      '<div class="breakup-card">' +
      '<table class="breakup-table">' +
      '<thead><tr>' +
      '<th>Component</th><th>Rate</th><th>Weight</th><th width="150px">Final Value</th>' +
      '</tr></thead>' +
      '<tbody>' +
      '<tr class="breakup-subhead"><td colspan="4">Diamond</td></tr>' +
      '<tr>' +
      '<td>' +
      escapeHtml(d.title || 'Diamond') +
      '</td>' +
      '<td>' +
      escapeHtml(diamondRateCell) +
      '</td>' +
      '<td>' +
      escapeHtml(String(d.weight != null ? d.weight : '0')) +
      ' ' +
      escapeHtml(d.weight_unit || 'ct') +
      '</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, d.value) +
      '</td>' +
      '</tr>' +
      '<tr class="breakup-row-total">' +
      '<td>Total Diamond Value</td><td>-</td><td>-</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, d.value) +
      '</td>' +
      '</tr>' +
      '</tbody></table></div>' +
      '<div class="breakup-card breakup-card--compact">' +
      '<table class="breakup-table"><tbody>' +
      '<tr class="breakup-row-total">' +
      '<td>Making Charges</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, data.making_charge) +
      '</td></tr></tbody></table></div>' +
      '<div class="breakup-card breakup-card--compact">' +
      '<table class="breakup-table"><tbody>' +
      '<tr><td>Sub Total</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, data.subtotal) +
      '</td></tr></tbody></table></div>' +
      '<div class="breakup-card breakup-card--compact">' +
      '<table class="breakup-table"><tbody>' +
      '<tr><td>Tax (' +
      escapeHtml(formatTaxLabelPercent(taxPct)) +
      '%)</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, data.tax_value) +
      '</td></tr></tbody></table></div>' +
      '<div class="breakup-card breakup-card--compact breakup-card--grand">' +
      '<table class="breakup-table"><tbody>' +
      '<tr><td>Grand Total</td>' +
      '<td class="breakup-table__amount" width="150px">' +
      formatMoney(sym, data.grand_total) +
      '</td></tr></tbody></table></div>' +
      '</div>';
  }

  function bindVariantWatchers(load) {
    document.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'id') {
        load();
      }
    });
    document.addEventListener('variant:change', function () {
      load();
    });
    var form = document.querySelector('form[action*="/cart/add"]');
    var idInput = form ? form.querySelector('input[name="id"][type="hidden"]') : null;
    if (idInput) {
      var mo = new MutationObserver(function () {
        load();
      });
      mo.observe(idInput, { attributes: true, attributeFilter: ['value'] });
    }
    window.addEventListener('popstate', function () {
      load();
    });
  }

  function initRoot(root) {
    var base = (root.getAttribute('data-app-url') || '').trim().replace(/\/$/, '');
    var shop = (root.getAttribute('data-shop') || '').trim();
    var defaultVariantId = root.getAttribute('data-default-variant-id') || '';

    if (!base || !shop) {
      return;
    }

    function load() {
      var vid = getVariantId(defaultVariantId);
      if (!vid) {
        root.innerHTML = '';
        return;
      }

      var hadContent = !!root.querySelector('.price-breakup-box .breakup-card');

      var url =
        base +
        '/api/storefront/variant-breakup?variant_id=' +
        encodeURIComponent(vid) +
        '&shop=' +
        encodeURIComponent(shop);

      var headers = {};
      if (/ngrok/i.test(base)) {
        headers['ngrok-skip-browser-warning'] = 'true';
      }

      fetch(url, { credentials: 'omit', headers: headers })
        .then(function (r) {
          var currentVid = getVariantId(defaultVariantId);
          if (String(currentVid) !== String(vid)) {
            return null;
          }
          if (r.status === 404) {
            root.innerHTML =
              '<div class="price-breakup-box price-breakup-box--empty"><p>Price breakup is not available for this option.</p></div>';
            return null;
          }
          if (!r.ok) {
            throw new Error('Breakup request failed');
          }
          return r.json();
        })
        .then(function (data) {
          if (!data) {
            return;
          }
          var currentVid = getVariantId(defaultVariantId);
          if (String(currentVid) !== String(vid)) {
            return;
          }
          renderBreakup(root, data);
        })
        .catch(function () {
          var currentVid = getVariantId(defaultVariantId);
          if (String(currentVid) !== String(vid)) {
            return;
          }
          if (hadContent) {
            return;
          }
          root.innerHTML =
            '<div class="price-breakup-box price-breakup-box--empty"><p>Could not load price breakup.</p></div>';
        });
    }

    bindVariantWatchers(load);
    load();
  }

  document.querySelectorAll('[data-app-price-breakup]').forEach(initRoot);
})();
