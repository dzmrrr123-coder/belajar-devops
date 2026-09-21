(function() {
  var reduced = (window.LTMotion && window.LTMotion.reduced()) || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var saveData = navigator.connection && navigator.connection.saveData;
  var lowMem = navigator.deviceMemory && navigator.deviceMemory <= 2;
  var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
  // Lite: langsung tampil tanpa gerak (hemat seluler + hormat reduced)
  if (reduced || saveData || lowMem) {
    document.querySelectorAll('[data-reveal]').forEach(function(el) { el.classList.add('in'); });
    return;
  }
  // Reveal stagger via IO (sekali saja)
  try {
    var io = new IntersectionObserver(function(entries) {
      entries.forEach(function(en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.15 });
    document.querySelectorAll('[data-reveal]').forEach(function(el) { io.observe(el); });
  } catch (e) {
    document.querySelectorAll('[data-reveal]').forEach(function(el) { el.classList.add('in'); });
  }
  // Parallax brutal 0.25 max — rAF throttle, transform only
  var px = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function() {
      var y = window.scrollY || 0;
      // Hanya geser saat hero terlihat (hemat compositor HP)
      if (y < window.innerHeight * 1.2) {
        px.forEach(function(el) {
          var s = parseFloat(el.getAttribute('data-parallax')) || 0.15;
          s = Math.max(-0.3, Math.min(0.3, s));
          el.style.transform = 'translateY(' + (y * s).toFixed(1) + 'px)';
        });
      }
      ticking = false;
    });
  }
  if (px.length) { window.addEventListener('scroll', onScroll, { passive: true }); onScroll(); }
  // Tilt 8deg + magnetic 6px — desktop pointer halus saja
  if (!fine) return;
  document.querySelectorAll('[data-tilt]').forEach(function(card) {
    var raf = 0;
    card.addEventListener('mousemove', function(e) {
      if (raf) return;
      raf = requestAnimationFrame(function() {
        var r = card.getBoundingClientRect();
        var rx = ((e.clientY - r.top) / r.height - 0.5) * -16;
        var ry = ((e.clientX - r.left) / r.width - 0.5) * 16;
        rx = Math.max(-8, Math.min(8, rx)); ry = Math.max(-8, Math.min(8, ry));
        card.style.transform = 'perspective(700px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg)';
        raf = 0;
      });
    });
    card.addEventListener('mouseleave', function() {
      if (raf) cancelAnimationFrame(raf); raf = 0;
      card.style.transform = '';
    });
  });
  document.querySelectorAll('[data-magnetic]').forEach(function(btn) {
    var raf = 0;
    btn.addEventListener('mousemove', function(e) {
      if (raf) return;
      raf = requestAnimationFrame(function() {
        var r = btn.getBoundingClientRect();
        var dx = (e.clientX - (r.left + r.width / 2)) * 0.12;
        var dy = (e.clientY - (r.top + r.height / 2)) * 0.18;
        dx = Math.max(-6, Math.min(6, dx)); dy = Math.max(-6, Math.min(6, dy));
        btn.style.transform = 'translate(' + dx.toFixed(1) + 'px,' + dy.toFixed(1) + 'px)';
        raf = 0;
      });
    });
    btn.addEventListener('mouseleave', function() {
      if (raf) cancelAnimationFrame(raf); raf = 0;
      btn.style.transform = '';
    });
  });
})();
