/* ============================================================
   Luxora Hotel Suite — shared JS
   Toasts, chips filter, confirm modal, table search, live totals
   ============================================================ */
(function () {
  window.showToast = function (msg, ok) {
    var t = document.getElementById('toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = ok === false ? '#e5484d' : '#2f9e63';
    t.classList.add('show');
    clearTimeout(window.__toastT);
    window.__toastT = setTimeout(function () { t.classList.remove('show'); }, 3200);
  };

  document.addEventListener('click', function (e) {
    var chip = e.target.closest('.chip');
    if (!chip || !chip.parentElement) return;
    chip.parentElement.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
    chip.classList.add('active');
    var f = chip.dataset.filter || 'all';
    document.querySelectorAll('table[data-chip-filter] tbody tr').forEach(function (row) {
      row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
    });
  });

  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-table-search]')) return;
    var q = e.target.value.toLowerCase();
    var tbl = document.querySelector(e.target.dataset.tableSearch);
    if (!tbl) return;
    tbl.querySelectorAll('tbody tr').forEach(function (row) {
      row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    });
  });

  window.askConfirm = function (title, body, onYes) {
    var bd = document.getElementById('confirmModal');
    if (!bd) {
      bd = document.createElement('div');
      bd.id = 'confirmModal';
      bd.className = 'modal-backdrop';
      bd.innerHTML = '<div class="modal-box"><div class="modal-head" id="cmTitle"></div>' +
        '<div class="modal-body" id="cmBody"></div>' +
        '<div class="modal-foot"><button class="btn btn-ghost" id="cmNo">Cancel</button>' +
        '<button class="btn btn-danger" id="cmYes">Confirm</button></div></div>';
      document.body.appendChild(bd);
      bd.addEventListener('click', function (ev) {
        if (ev.target === bd || ev.target.id === 'cmNo') bd.classList.remove('open');
      });
    }
    document.getElementById('cmTitle').textContent = title || 'Confirm';
    document.getElementById('cmBody').textContent = body || 'Are you sure?';
    bd.classList.add('open');
    document.getElementById('cmYes').onclick = function () {
      bd.classList.remove('open');
      if (typeof onYes === 'function') onYes();
    };
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert[data-autohide]').forEach(function (a) {
      setTimeout(function () { a.style.display = 'none'; }, 4000);
    });
  });

  document.addEventListener('input', function (e) {
    if (!e.target.closest('[data-live-total]')) return;
    var box = e.target.closest('[data-live-total]');
    var sum = 0;
    box.querySelectorAll('input[data-price]').forEach(function (inp) {
      var row = inp.closest('.svc-row') || inp.parentElement;
      var p = parseFloat(inp.dataset.price || 0);
      var qEl = row.querySelector('[data-qty]');
      var q = parseFloat(qEl ? qEl.value : 1 || 1);
      sum += p * q;
    });
    var out = box.querySelector('[data-total-out]');
    if (out) out.textContent = sum.toFixed(2);
  });
})();
