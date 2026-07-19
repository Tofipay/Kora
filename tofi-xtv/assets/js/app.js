/* ============================================================
   ToFi X Tv — Landing 2026
   Vanilla JS: header, mobile nav, reveal, hero slideshow,
   rail nav, copy activation code, scroll progress, dock.
   ============================================================ */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Header scroll state ---------- */
  var header = document.getElementById("header");
  var progress = document.getElementById("progress");
  var dock = document.getElementById("dock");
  var ticking = false;

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var y = window.scrollY;
      header.classList.toggle("is-scrolled", y > 24);
      if (dock) dock.classList.toggle("is-visible", y > 500);
      var max = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.width = (max > 0 ? (y / max) * 100 : 0) + "%";
      ticking = false;
    });
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile nav ---------- */
  var menuBtn = document.getElementById("menuBtn");
  if (menuBtn) {
    menuBtn.addEventListener("click", function () {
      var open = header.classList.toggle("is-open");
      menuBtn.setAttribute("aria-expanded", open ? "true" : "false");
      menuBtn.setAttribute("aria-label", open ? "إغلاق القائمة" : "فتح القائمة");
    });
    document.querySelectorAll(".mobile-nav a").forEach(function (a) {
      a.addEventListener("click", function () {
        header.classList.remove("is-open");
        menuBtn.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Scroll reveal ---------- */
  var revealEls = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-in");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px" });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* ---------- Hero slideshow ---------- */
  var shots = document.querySelectorAll("#heroPhone .phone__shot");
  if (shots.length > 1 && !reduceMotion) {
    var current = 0;
    setInterval(function () {
      if (document.hidden) return;
      shots[current].classList.remove("is-active");
      current = (current + 1) % shots.length;
      shots[current].classList.add("is-active");
    }, 3800);
  }

  /* ---------- Screenshots rail ---------- */
  var rail = document.getElementById("screensRail");
  document.querySelectorAll(".rail-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      if (!rail) return;
      var dir = btn.getAttribute("data-dir") === "next" ? 1 : -1;
      // RTL: scrollBy موجب يتحرك لليسار، نعكس حسب اتجاه الصفحة
      var rtl = document.documentElement.dir === "rtl";
      rail.scrollBy({
        left: (rtl ? -dir : dir) * Math.round(rail.clientWidth * 0.7),
        behavior: reduceMotion ? "auto" : "smooth"
      });
    });
  });

  /* ---------- Copy activation code ---------- */
  var copyBtn = document.getElementById("copyCode");
  var codeEl = document.getElementById("activationCode");
  var toast = document.getElementById("toast");
  var toastTimer = null;

  function showToast() {
    if (!toast) return;
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove("is-visible");
    }, 2400);
  }

  function fallbackCopy(text) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.setAttribute("readonly", "");
    ta.style.position = "fixed";
    ta.style.opacity = "0";
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand("copy"); } catch (e) { /* تجاهل */ }
    document.body.removeChild(ta);
  }

  if (copyBtn && codeEl) {
    copyBtn.addEventListener("click", function () {
      var code = codeEl.textContent.trim();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(showToast, function () {
          fallbackCopy(code);
          showToast();
        });
      } else {
        fallbackCopy(code);
        showToast();
      }
    });
  }

  /* ---------- عدّاد الزيارات والتحميلات ---------- */
  var counterApi = "counter.php";
  var counterBox = document.getElementById("counterBox");
  var statDl = document.getElementById("statDownloads");
  var statVisits = document.getElementById("statVisits");

  function renderCounts(d) {
    if (!d || typeof d.downloads === "undefined") return;
    if (statDl) statDl.textContent = Number(d.downloads || 0).toLocaleString("en-US");
    if (statVisits) statVisits.textContent = Number(d.visits || 0).toLocaleString("en-US");
    if (counterBox) counterBox.hidden = false;
  }

  var visitAction = "visit";
  try {
    if (sessionStorage.getItem("txv-visited")) visitAction = "get";
  } catch (e) { /* تجاهل */ }

  fetch(counterApi + "?action=" + visitAction, { cache: "no-store" })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      try { sessionStorage.setItem("txv-visited", "1"); } catch (e) { /* تجاهل */ }
      renderCounts(d);
    })
    .catch(function () { /* استضافة بدون PHP: يبقى العدّاد مخفيًا */ });

  document.querySelectorAll("a[data-dl]").forEach(function (a) {
    a.addEventListener("click", function () {
      try {
        if (navigator.sendBeacon) navigator.sendBeacon(counterApi + "?action=download");
        else fetch(counterApi + "?action=download", { keepalive: true }).catch(function () {});
      } catch (e) { /* تجاهل */ }
      if (statDl && counterBox && !counterBox.hidden) {
        var n = parseInt(statDl.textContent.replace(/,/g, ""), 10) || 0;
        statDl.textContent = (n + 1).toLocaleString("en-US");
      }
    });
  });

  /* ---------- Tilt on showcase mockups ---------- */
  var finePointer = window.matchMedia("(pointer: fine)").matches;
  if (finePointer && !reduceMotion) {
    document.querySelectorAll("[data-tilt]").forEach(function (el) {
      el.addEventListener("mousemove", function (e) {
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width - 0.5;
        var y = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform =
          "perspective(900px) rotateY(" + (x * 8) + "deg) rotateX(" + (-y * 8) + "deg) translateY(-6px)";
      });
      el.addEventListener("mouseleave", function () {
        el.style.transform = "";
      });
    });
  }
})();
