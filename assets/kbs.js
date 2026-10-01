(function () {
  var API = '__KBS_API__';

  function pad(n){ return String(n).padStart(2, '0'); }
  function iso(d){ return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parseISO(s){ var p = (s || '').split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; }
  function sameDay(a, b){ return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
  function addDays(d, n){ var x = new Date(d); x.setDate(x.getDate() + n); return x; }

  // Adults / children stepper -> single hidden "guests" total.
  function wireGuests(form){
    var box = form.querySelector('.kbs-guests');
    var hidden = form.querySelector('[data-kbs-guests]');
    if (!box || !hidden) return;

    var labelEl = box.querySelector('[data-kbs-guests-label]');
    var max = parseInt(hidden.getAttribute('data-kbs-max') || '16', 10) || 16;
    var valEl = {
      adults: box.querySelector('[data-kbs-val="adults"]'),
      children: box.querySelector('[data-kbs-val="children"]')
    };
    var vals = {
      adults: Math.max(1, parseInt(valEl.adults.textContent, 10) || 1),
      children: Math.max(0, parseInt(valEl.children.textContent, 10) || 0)
    };

    function render(){
      var total = vals.adults + vals.children;
      valEl.adults.textContent = vals.adults;
      valEl.children.textContent = vals.children;
      hidden.value = total;
      if (labelEl) labelEl.textContent = total + (total === 1 ? ' guest' : ' guests');
      box.querySelectorAll('[data-kbs-step]').forEach(function (btn) {
        var t = btn.getAttribute('data-kbs-target');
        var down = parseInt(btn.getAttribute('data-kbs-step'), 10) < 0;
        btn.disabled = down ? vals[t] <= (t === 'adults' ? 1 : 0) : total >= max;
      });
    }

    box.querySelectorAll('[data-kbs-step]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var t = btn.getAttribute('data-kbs-target');
        var dir = parseInt(btn.getAttribute('data-kbs-step'), 10);
        var floor = t === 'adults' ? 1 : 0;
        if (dir < 0 && vals[t] <= floor) return;
        if (dir > 0 && (vals.adults + vals.children) >= max) return;
        vals[t] += dir;
        render();
      });
    });
    document.addEventListener('click', function (e) {
      if (box.open && !box.contains(e.target)) box.open = false;
    });
    render();
  }

  function wire(form){
    if (form.dataset.kbsReady) return;
    form.dataset.kbsReady = '1';

    wireGuests(form);

    var checkin    = form.querySelector('[data-kbs-checkin]');
    var checkout   = form.querySelector('[data-kbs-checkout]');
    var arrival    = form.querySelector('[data-kbs-arrival]');
    var departure  = form.querySelector('[data-kbs-departure]');
    var showPrices = form.getAttribute('data-kbs-prices') !== 'off';
    // Each widget reads prices from the booking site it submits to (set from `base`).
    var api = form.getAttribute('data-kbs-api') || API;

    form.addEventListener('submit', function (e) {
      // Need both dates, and at least one night (check-out after check-in).
      if (!arrival.value || !departure.value || departure.value <= arrival.value) {
        e.preventDefault();
        checkin.classList.add('kbs-invalid');
        return;
      }
      if (form.action.indexOf('#') === -1) form.action = form.action + '#rooms';
    });

    // Plain native date fields if flatpickr / rangePlugin can't be reached.
    function fallback(){
      var today = iso(new Date());
      [checkin, checkout].forEach(function (i) { i.type = 'date'; i.readOnly = false; i.placeholder = ''; });
      checkin.min = today;
      checkout.min = iso(addDays(new Date(), 1));
      checkin.value = arrival.value; checkout.value = departure.value;
      function s(){ arrival.value = checkin.value; departure.value = checkout.value; }
      checkin.addEventListener('change', function () {
        if (checkin.value) {
          var floor = iso(addDays(parseISO(checkin.value), 1));
          checkout.min = floor;
          if (!checkout.value || checkout.value < floor) checkout.value = floor;
        }
        s();
      });
      checkout.addEventListener('change', s);
    }

    if (!window.flatpickr || !window.rangePlugin) { fallback(); return; }

    if (showPrices && api) {
      fetch(api).then(function (r) { return r.ok ? r.json() : {}; })
        .catch(function () { return {}; })
        .then(function (cal) { initPicker(cal || {}); });
    } else {
      initPicker({});
    }

    function initPicker(cal){
      var priceDays  = cal.days || {};
      var priceCur   = cal.currency || 'PHP';
      var keys       = Object.keys(priceDays).sort();
      var lastPriced = keys.length ? keys[keys.length - 1] : null;
      var havePrices = showPrices && !!lastPriced;
      var sym = priceCur === 'PHP' ? '₱' : priceCur + ' ';
      function fmtPrice(n){ return n >= 1000 ? sym + (Math.round(n / 100) / 10) + 'K' : sym + Math.round(n); }

      function nightOpen(date){ if (!lastPriced) return true; var k = iso(date); return k > lastPriced || !!priceDays[k]; }
      function reachableCheckout(ci, date){
        for (var d = new Date(ci); d < date; d.setDate(d.getDate() + 1)) { if (!nightOpen(d)) return false; }
        return true;
      }
      function blockUnavailable(date){ return !nightOpen(date); }

      var setting = false;
      function syncDisable(fp, dates){
        if (setting) return;
        var ci = dates[0];
        var rule = ci
          ? function (date) {
              return date <= ci ? blockUnavailable(date) : !reachableCheckout(ci, date);
            }
          : blockUnavailable;
        setting = true; fp.set('disable', [rule]); setting = false;
      }
      function sync(dates){
        arrival.value = dates[0] ? iso(dates[0]) : '';
        departure.value = dates[1] ? iso(dates[1]) : '';
      }

      var pre = [parseISO(arrival.value), parseISO(departure.value)].filter(Boolean);

      window.flatpickr(checkin, {
        mode: 'range',
        showMonths: 1,
        minDate: 'today',
        dateFormat: 'D, M j',
        defaultDate: pre.length === 2 ? pre : null,
        plugins: [ new window.rangePlugin({ input: checkout }) ],
        disable: [ blockUnavailable ],
        onChange: function (dates, str, fp) {
          // Reject a same-day range: drop back to just the check-in so the
          // guest has to pick a later check-out (>= 1 night).
          if (dates.length === 2 && sameDay(dates[0], dates[1])) {
            fp.setDate([dates[0]], false); // false = don't re-fire onChange
            dates = [dates[0]];
          }
          sync(dates); syncDisable(fp, dates);
          checkin.classList.remove('kbs-invalid');
        },
        onReady: function (dates, str, fp) {
          sync(dates); syncDisable(fp, dates);
          fp.calendarContainer.classList.add('kbs-fp');
          if (havePrices) {
            fp.calendarContainer.classList.add('has-prices');
            var note = document.createElement('div');
            note.className = 'kbs-fp-note';
            note.textContent = 'Nightly prices in ' + priceCur + ' · lowest available room';
            fp.calendarContainer.appendChild(note);
          }
        },
        onDayCreate: function (dates, str, fp, dayElem) {
          if (!havePrices) return;
          if (dayElem.classList.contains('flatpickr-disabled')) return;
          var price = priceDays[iso(dayElem.dateObj)];
          if (!price) return;
          var tag = document.createElement('span');
          tag.className = 'kbs-fp-price';
          tag.textContent = fmtPrice(price);
          dayElem.appendChild(tag);
        }
      });
    }
  }

  function init(){ document.querySelectorAll('form[data-kbs]').forEach(wire); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
