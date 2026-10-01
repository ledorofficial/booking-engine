(function () {
  var DEFAULT_BASE = '__KBS_API__';
  var I18N = window.KBS_I18N || {};
  var LANG = I18N.lang || 'en';
  var MONTH_NAMES = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
  var WEEKDAYS = ['MO','TU','WE','TH','FR','SA','SU'];
  if (LANG !== 'en') {
    // Month and weekday names from the browser's own locale data.
    try {
      var mf = new Intl.DateTimeFormat(LANG, { month: 'long' }), wf = new Intl.DateTimeFormat(LANG, { weekday: 'short' });
      MONTH_NAMES = []; WEEKDAYS = [];
      for (var i = 0; i < 12; i++) MONTH_NAMES.push(mf.format(new Date(2024, i, 1)).toUpperCase());
      for (var j = 1; j <= 7; j++) WEEKDAYS.push(wf.format(new Date(2024, 0, j)).toUpperCase()); // 2024-01-01 is a Monday
    } catch (e) { /* keep English */ }
  }

  function pad(n){ return String(n).padStart(2, '0'); }
  function iso(d){ return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function addMonths(d, n){ return new Date(d.getFullYear(), d.getMonth() + n, 1); }
  function daysInMonth(d){ return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate(); }
  function mondayIndex(d){ return (d.getDay() + 6) % 7; }

  function buildMonth(container, monthDate, unavailable, today, showPrev, showNext, onPrev, onNext){
    container.innerHTML = '';

    var head = document.createElement('div');
    head.className = 'kbs-room-cal-month-head';
    var y = document.createElement('div'); y.className = 'y'; y.textContent = String(monthDate.getFullYear());
    var m = document.createElement('div'); m.className = 'm'; m.textContent = MONTH_NAMES[monthDate.getMonth()];
    head.appendChild(y); head.appendChild(m);

    if (showPrev) {
      var prevBtn = document.createElement('button');
      prevBtn.type = 'button'; prevBtn.className = 'kbs-room-cal-nav prev';
      prevBtn.setAttribute('aria-label', I18N.earlier || 'Earlier months');
      prevBtn.innerHTML = '&lsaquo;';
      prevBtn.addEventListener('click', onPrev);
      head.appendChild(prevBtn);
    }
    if (showNext) {
      var nextBtn = document.createElement('button');
      nextBtn.type = 'button'; nextBtn.className = 'kbs-room-cal-nav next';
      nextBtn.setAttribute('aria-label', I18N.later || 'Later months');
      nextBtn.innerHTML = '&rsaquo;';
      nextBtn.addEventListener('click', onNext);
      head.appendChild(nextBtn);
    }
    container.appendChild(head);

    var week = document.createElement('div');
    week.className = 'kbs-room-cal-week';
    WEEKDAYS.forEach(function (w) {
      var s = document.createElement('span');
      s.textContent = w;
      week.appendChild(s);
    });
    container.appendChild(week);

    var days = document.createElement('div');
    days.className = 'kbs-room-cal-days';

    var lead = mondayIndex(monthDate);
    for (var i = 0; i < lead; i++) {
      var blank = document.createElement('div');
      blank.className = 'd blank';
      days.appendChild(blank);
    }

    var total = daysInMonth(monthDate);
    for (var day = 1; day <= total; day++) {
      var date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      var key = iso(date);

      var cell = document.createElement('div');
      cell.className = 'd';
      cell.textContent = pad(day);

      var isOff = key < today || unavailable.has(key);
      if (isOff) cell.classList.add('off');

      var prevDate = new Date(date);
      prevDate.setDate(prevDate.getDate() - 1);
      var prevKey = iso(prevDate);
      var prevOff = prevKey < today || unavailable.has(prevKey);
      if (!isOff && prevOff) cell.classList.add('turnover');

      if (key === today) cell.classList.add('today');

      days.appendChild(cell);
    }
    container.appendChild(days);
  }

  function initWidget(root){
    if (root.dataset.kbsRoomReady) return;
    root.dataset.kbsRoomReady = '1';

    var roomId = parseInt(root.getAttribute('data-room-id'), 10);
    var totalMonths = Math.max(2, parseInt(root.getAttribute('data-months'), 10) || 6);
    var base = root.getAttribute('data-base') || DEFAULT_BASE;
    if (!roomId) { root.style.display = 'none'; return; }

    var monthsEl = root.querySelector('.kbs-room-cal-months');
    var monthA = document.createElement('div'); monthA.className = 'kbs-room-cal-month';
    var monthB = document.createElement('div'); monthB.className = 'kbs-room-cal-month';
    monthsEl.appendChild(monthA);
    monthsEl.appendChild(monthB);

    var apiBase = base.replace(/\/?$/, '/');
    fetch(apiBase + 'api/calendar/room/' + roomId + '?months=' + totalMonths)
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.start) { root.style.display = 'none'; return; }

        var today = data.start;
        var unavailable = new Set(data.unavailable || []);
        var baseMonth = new Date();
        baseMonth.setDate(1);
        var offset = 0;
        var maxOffset = Math.max(0, totalMonths - 2);

        function render(){
          var m1 = addMonths(baseMonth, offset);
          var m2 = addMonths(baseMonth, offset + 1);
          buildMonth(monthA, m1, unavailable, today, offset > 0, false, prev, null);
          buildMonth(monthB, m2, unavailable, today, false, offset < maxOffset, null, next);
        }
        function prev(){ if (offset > 0) { offset--; render(); } }
        function next(){ if (offset < maxOffset) { offset++; render(); } }

        render();
      })
      .catch(function () { root.style.display = 'none'; });
  }

  function init(){ document.querySelectorAll('[data-kbs-room]').forEach(initWidget); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
