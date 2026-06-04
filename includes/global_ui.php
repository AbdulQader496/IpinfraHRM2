<?php
/**
 * Global UI Enhancements
 * ----------------------
 * Provides premium-quality, reusable UI primitives for the entire HRM system.
 *
 * Include once per page, after <head> opens (or just before </body>):
 *   <?php require_once '../includes/global_ui.php'; ?>
 *
 * CSS utilities exposed:
 *   .avatar-auto          — coloured avatar circle (needs data-name="...")
 *   .count-up             — animated number counter (needs data-target="123")
 *   .btn                  — premium button base class
 *   Skeleton / shimmer    — add class "skeleton" to any placeholder element
 *
 * JS globals exposed:
 *   window.getAvatarColor(name)          → { bg, text }  (Tailwind class strings)
 *   window.animateCounter(el, target, duration=1500)
 *   window.showToast(message, type)      — re-exported from toast.php
 */
?>

<!-- ═══════════════════════════════════════════════════════════════════
     GLOBAL UI — Style block
     ═══════════════════════════════════════════════════════════════════ -->
<style>
/* ── 1. Page fade-in on load ──────────────────────────────────────── */
/*
   NOTE: We animate opacity ONLY — never transform on body.
   Adding transform to body makes it the containing block for
   position:fixed children (sidebar, header, modals) which breaks layout.
*/
@keyframes fadeInPage {
  from { opacity: 0; }
  to   { opacity: 1; }
}

body {
  animation: fadeInPage 0.3s ease both;
}

/* Page exit — JS adds this class before navigating away */
body.page-exit {
  animation: none !important;
  opacity: 0;
  transition: opacity 0.2s ease;
  pointer-events: none;
}

/* ── 2. Skeleton / shimmer loading state ──────────────────────────── */
@keyframes shimmer {
  0%   { background-position: -468px 0; }
  100% { background-position:  468px 0; }
}

.skeleton {
  background: linear-gradient(
    90deg,
    #f0f0f0 25%,
    #e0e0e0 50%,
    #f0f0f0 75%
  );
  background-size: 936px 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: 0.5rem;
  color: transparent !important;
  pointer-events: none;
  user-select: none;
}

.skeleton * {
  visibility: hidden;
}

/* ── 3. Smooth custom scrollbar (WebKit) ──────────────────────────── */
::-webkit-scrollbar {
  width: 7px;
  height: 7px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.35);
  border-radius: 99px;
  transition: background 0.2s;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(100, 116, 139, 0.65);
}

::-webkit-scrollbar-corner {
  background: transparent;
}

/* Firefox */
* {
  scrollbar-width: thin;
  scrollbar-color: rgba(100, 116, 139, 0.35) transparent;
}

/* ── 4. Premium button hover effects ──────────────────────────────── */
/*
   Apply class "btn" alongside Tailwind classes for the lift effect.
   Works on <button>, <a>, or any element.
*/
.btn,
button:not([class*="no-lift"]),
input[type="submit"],
input[type="button"] {
  transition:
    transform    0.18s cubic-bezier(0.34, 1.56, 0.64, 1),
    box-shadow   0.18s cubic-bezier(0.34, 1.56, 0.64, 1),
    filter       0.15s ease,
    opacity      0.15s ease !important;
  will-change: transform, box-shadow;
}

.btn:hover,
button:not([class*="no-lift"]):hover,
input[type="submit"]:hover,
input[type="button"]:hover {
  transform: translateY(-2px);
  box-shadow:
    0 4px 12px -2px rgba(0, 0, 0, 0.14),
    0 2px  6px -1px rgba(0, 0, 0, 0.10);
}

.btn:active,
button:not([class*="no-lift"]):active,
input[type="submit"]:active,
input[type="button"]:active {
  transform: translateY(0px) scale(0.98);
  box-shadow: none;
  transition-duration: 0.08s !important;
}

/* Disabled state — suppress lift */
.btn:disabled,
button:disabled {
  transform: none !important;
  box-shadow: none !important;
  cursor: not-allowed;
  opacity: 0.55;
}

/* ── 5. Link click ripple effect ──────────────────────────────────── */
/*
   The JS block injects a <span class="ripple-wave"> into clicked elements.
   The container needs position:relative — applied via JS automatically.
*/
.ripple-host {
  position: relative;
  overflow: hidden;
}

@keyframes rippleExpand {
  0% {
    transform: scale(0);
    opacity: 0.45;
  }
  60% {
    opacity: 0.2;
  }
  100% {
    transform: scale(4);
    opacity: 0;
  }
}

.ripple-wave {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.55);
  pointer-events: none;
  animation: rippleExpand 0.55s cubic-bezier(0.4, 0, 0.2, 1) forwards;
  transform: scale(0);
  z-index: 9999;
}

/* On darker surfaces use a lighter ripple automatically */
.ripple-wave.ripple-dark {
  background: rgba(0, 0, 0, 0.12);
}

/* ── 6. Avatar auto-colour circles ────────────────────────────────── */
/*
   Base shape — colours are set via inline style by JS (not Tailwind JIT).
   JS reads data-name and picks a deterministic palette entry.
*/
.avatar-auto {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  border-radius: 50%;
  user-select: none;
  flex-shrink: 0;
  letter-spacing: -0.01em;
}

/* ── 7. Active nav link indicator ─────────────────────────────────── */
/*
   JS adds .nav-active to the matching <a> inside the sidebar.
   Pair with existing Tailwind classes; this adds the accent.
*/
nav a.nav-active,
.bottom-nav a.nav-active {
  background: rgba(255, 255, 255, 0.15) !important;
  color: #ffffff !important;
  font-weight: 600;
}

nav a.nav-active i,
.bottom-nav a.nav-active i {
  opacity: 1 !important;
}

/* Bottom mobile nav active dot */
.bottom-nav a.nav-active::after {
  content: '';
  display: block;
  width: 4px;
  height: 4px;
  background: currentColor;
  border-radius: 50%;
  margin: 2px auto 0;
}

/* ── 8. Smooth focus rings (accessibility + aesthetics) ───────────── */
:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 2px;
  border-radius: 6px;
}

/* ── 9. Smooth image loading ──────────────────────────────────────── */
img {
  transition: opacity 0.3s ease;
}

img[loading="lazy"] {
  opacity: 0;
}

img[loading="lazy"].img-loaded {
  opacity: 1;
}

/* ── 10. Card hover micro-interaction ─────────────────────────────── */
.card-hover {
  transition:
    transform  0.22s cubic-bezier(0.34, 1.3, 0.64, 1),
    box-shadow 0.22s ease;
}

.card-hover:hover {
  transform: translateY(-3px);
  box-shadow:
    0 12px 28px -6px rgba(0, 0, 0, 0.12),
    0  4px 10px -3px rgba(0, 0, 0, 0.08);
}

/* ── 11. Form loading / submitting state ──────────────────────────── */
.form-submitting {
  opacity: 0.8;
  pointer-events: none;
}
</style>

<!-- ═══════════════════════════════════════════════════════════════════
     GLOBAL UI — Script block
     ═══════════════════════════════════════════════════════════════════ -->
<script>
(function (global) {
  'use strict';

  /* ================================================================
     1. AVATAR COLOR GENERATOR
     Returns deterministic { bg, text } CSS colour values for a name.
     The same name always maps to the same palette slot.
     ================================================================ */

  var AVATAR_PALETTE = [
    { bg: '#4f46e5', text: '#ffffff' }, /* Indigo         */
    { bg: '#0ea5e9', text: '#ffffff' }, /* Sky            */
    { bg: '#10b981', text: '#ffffff' }, /* Emerald        */
    { bg: '#f59e0b', text: '#ffffff' }, /* Amber          */
    { bg: '#ef4444', text: '#ffffff' }, /* Red            */
    { bg: '#8b5cf6', text: '#ffffff' }, /* Violet         */
    { bg: '#ec4899', text: '#ffffff' }, /* Pink           */
    { bg: '#14b8a6', text: '#ffffff' }, /* Teal           */
    { bg: '#f97316', text: '#ffffff' }, /* Orange         */
    { bg: '#6366f1', text: '#ffffff' }, /* Indigo-lighter */
    { bg: '#22c55e', text: '#ffffff' }, /* Green          */
    { bg: '#a855f7', text: '#ffffff' }, /* Purple         */
  ];

  /**
   * Hash a string to a stable integer using djb2.
   * @param  {string} str
   * @returns {number} non-negative integer
   */
  function hashString(str) {
    var hash = 5381;
    for (var i = 0; i < str.length; i++) {
      hash = ((hash << 5) + hash) + str.charCodeAt(i);
      hash = hash & hash; /* keep 32-bit */
    }
    return Math.abs(hash);
  }

  /**
   * Get avatar colour entry for a given name.
   * @param  {string} name — any non-empty string
   * @returns {{ bg: string, text: string }}
   *          bg   = CSS background-color value
   *          text = CSS color value
   */
  function getAvatarColor(name) {
    if (!name || typeof name !== 'string') {
      return AVATAR_PALETTE[0];
    }
    var idx = hashString(name.trim().toLowerCase()) % AVATAR_PALETTE.length;
    return AVATAR_PALETTE[idx];
  }

  global.getAvatarColor = getAvatarColor;

  /* ================================================================
     2. APPLY AVATAR COLORS
     Finds all .avatar-auto elements, reads data-name, injects
     background colour and derives initials as inner text.
     ================================================================ */

  function applyAvatarColors() {
    var avatars = document.querySelectorAll('.avatar-auto');
    avatars.forEach(function (el) {
      var name = el.getAttribute('data-name') || el.textContent.trim() || '?';
      var colors = getAvatarColor(name);

      el.style.backgroundColor = colors.bg;
      el.style.color           = colors.text;

      /* If the element is currently empty or only whitespace, inject initials */
      if (!el.getAttribute('data-no-initials') && el.textContent.trim() === '') {
        el.textContent = getInitials(name);
      }
    });
  }

  function getInitials(name) {
    var parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
      return parts[0].charAt(0).toUpperCase();
    }
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  /* ================================================================
     3. ANIMATED NUMBER COUNTER
     Counts an element's inner number from 0 → target with easing.
     ================================================================ */

  /**
   * Animate an element's text content from 0 to target.
   * @param {HTMLElement} el
   * @param {number}      target    — final value (integer or float)
   * @param {number}      [duration=1500] — ms
   */
  function animateCounter(el, target, duration) {
    if (!el) return;
    duration = duration !== undefined ? duration : 1500;

    var startTime  = null;
    var startValue = 0;
    var isFloat    = String(target).indexOf('.') !== -1;
    var decimals   = isFloat ? (String(target).split('.')[1] || '').length : 0;
    var prefix     = el.getAttribute('data-prefix') || '';
    var suffix     = el.getAttribute('data-suffix') || '';

    function easeOutExpo(t) {
      return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
    }

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var elapsed  = timestamp - startTime;
      var progress = Math.min(elapsed / duration, 1);
      var eased    = easeOutExpo(progress);
      var current  = startValue + (target - startValue) * eased;

      el.textContent = prefix + (isFloat ? current.toFixed(decimals) : Math.round(current).toLocaleString()) + suffix;

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = prefix + (isFloat ? target.toFixed(decimals) : target.toLocaleString()) + suffix;
      }
    }

    requestAnimationFrame(step);
  }

  global.animateCounter = animateCounter;

  /* Apply counters to all .count-up elements via IntersectionObserver */
  function applyCounters() {
    var targets = document.querySelectorAll('.count-up');
    if (!targets.length) return;

    /* Use IntersectionObserver so counting starts when element enters viewport */
    var observer;
    var supportsIO = typeof IntersectionObserver !== 'undefined';

    function triggerCounter(el) {
      var raw      = el.getAttribute('data-target');
      var target   = parseFloat(raw);
      var duration = parseInt(el.getAttribute('data-duration'), 10) || 1500;
      if (!isNaN(target)) {
        animateCounter(el, target, duration);
      }
    }

    if (supportsIO) {
      observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting && !entry.target.getAttribute('data-counted')) {
            entry.target.setAttribute('data-counted', '1');
            triggerCounter(entry.target);
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.3 });

      targets.forEach(function (el) {
        /* Show a placeholder zero while waiting */
        if (el.textContent.trim() === '') el.textContent = '0';
        observer.observe(el);
      });
    } else {
      /* Fallback — run immediately */
      targets.forEach(function (el) {
        triggerCounter(el);
      });
    }
  }

  /* ================================================================
     4. PAGE EXIT TRANSITION + INTERNAL LINK INTERCEPTION
     Intercepts clicks on same-origin links, fades body out, navigates.
     External links, download links, and anchor-only links are skipped.
     ================================================================ */

  function setupPageTransitions() {
    var currentOrigin = location.origin || (location.protocol + '//' + location.host);
    var navigating    = false;

    function isInternalLink(anchor) {
      /* Must have href */
      if (!anchor.href) return false;
      /* Same origin */
      try {
        var url = new URL(anchor.href);
        if (url.origin !== currentOrigin) return false;
        /* Skip pure anchor scrolls */
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
        /* Skip download attribute */
        if (anchor.hasAttribute('download')) return false;
        /* Skip new tab / window */
        if (anchor.target === '_blank' || anchor.target === '_top') return false;
        /* Skip mailto / tel / javascript */
        if (/^(mailto:|tel:|javascript:)/.test(anchor.href)) return false;
        return true;
      } catch (e) {
        return false;
      }
    }

    function navigateTo(href) {
      if (navigating) return;
      navigating = true;

      document.body.classList.add('page-exit');
      /* Wait for the CSS transition (220 ms) then navigate */
      setTimeout(function () {
        window.location.href = href;
      }, 220);
    }

    document.addEventListener('click', function (e) {
      /* Walk up the DOM to find the nearest <a> */
      var el = e.target;
      while (el && el.tagName !== 'A') {
        el = el.parentElement;
      }

      if (!el) return;
      if (e.defaultPrevented) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (!isInternalLink(el)) return;

      e.preventDefault();
      addRipple(el, e);
      navigateTo(el.href);
    }, true /* capture phase — fires before other handlers */);

    /* Browser back/forward — remove exit class so the fade-in plays */
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        document.body.classList.remove('page-exit');
        navigating = false;
      }
    });
  }

  /* ================================================================
     5. LINK CLICK RIPPLE
     ================================================================ */

  function addRipple(el, e) {
    /* Ensure the element clips the ripple */
    var style = window.getComputedStyle(el);
    if (style.position === 'static') {
      el.style.position = 'relative';
    }
    el.style.overflow = 'hidden';

    var rect   = el.getBoundingClientRect();
    var size   = Math.max(rect.width, rect.height) * 1.6;
    var x      = (e.clientX - rect.left) - size / 2;
    var y      = (e.clientY - rect.top)  - size / 2;

    /* Detect if the surface is dark so we can invert ripple colour */
    var bgColor = style.backgroundColor;
    var isDark  = isColorDark(bgColor);

    var ripple = document.createElement('span');
    ripple.className = 'ripple-wave' + (isDark ? '' : ' ripple-dark');
    ripple.style.cssText =
      'width:'  + size + 'px;' +
      'height:' + size + 'px;' +
      'left:'   + x    + 'px;' +
      'top:'    + y    + 'px;';

    el.appendChild(ripple);

    ripple.addEventListener('animationend', function () {
      if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
    });
  }

  /* Rough luminance check — returns true if the colour is perceived as dark */
  function isColorDark(cssColor) {
    var m = cssColor.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (!m) return true; /* default assume dark */
    var r = parseInt(m[1], 10);
    var g = parseInt(m[2], 10);
    var b = parseInt(m[3], 10);
    /* Relative luminance (simplified) */
    var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance < 0.5;
  }

  /* ================================================================
     6. ACTIVE NAV LINK DETECTION
     Compares current pathname to each <nav> anchor's pathname.
     Adds .nav-active to the best match (longest matching segment).
     ================================================================ */

  function highlightActiveNav() {
    var currentPath = location.pathname;
    var currentFile = currentPath.split('/').pop() || 'index.php';

    /* Collect all nav links — sidebar + mobile bottom nav */
    var selectors = [
      '#sidebar nav a',
      'nav a',
      '.bottom-nav a',
    ];

    var allLinks = [];
    selectors.forEach(function (sel) {
      try {
        var found = document.querySelectorAll(sel);
        found.forEach(function (a) {
          if (allLinks.indexOf(a) === -1) allLinks.push(a);
        });
      } catch (e) { /* ignore invalid selectors */ }
    });

    if (!allLinks.length) return;

    /* Score each link */
    var best      = null;
    var bestScore = -1;

    allLinks.forEach(function (a) {
      /* Skip logout, hash, external */
      if (!a.href) return;
      try {
        var url = new URL(a.href);
        if (url.origin !== (location.origin || (location.protocol + '//' + location.host))) return;
        if (/logout/i.test(url.pathname)) return;

        var linkFile = url.pathname.split('/').pop() || 'index.php';
        var score    = -1;

        if (url.pathname === currentPath) {
          /* Exact pathname match — highest priority */
          score = 1000;
        } else if (linkFile && linkFile !== 'index.php' && linkFile === currentFile) {
          /* Same filename */
          score = 500;
        }

        if (score > bestScore) {
          bestScore = score;
          best      = a;
        }
      } catch (e) { /* ignore */ }
    });

    if (best && bestScore >= 0) {
      best.classList.add('nav-active');

      /* Also handle mobile bottom nav — same filename match */
      allLinks.forEach(function (a) {
        if (a === best) return;
        try {
          var url      = new URL(a.href);
          var linkFile = url.pathname.split('/').pop() || 'index.php';
          if (linkFile === currentFile && !/logout/i.test(url.pathname)) {
            a.classList.add('nav-active');
          }
        } catch (e) { /* ignore */ }
      });
    }
  }

  /* ================================================================
     7. FORM LOADING STATES
     On submit, disables the submit button and shows a spinner to
     prevent double-submission. Skip forms with data-no-loading.
     ================================================================ */

  function setupFormLoadingStates() {
    var forms = document.querySelectorAll('form[method="POST"], form[method="post"]');
    forms.forEach(function (form) {
      if (form.hasAttribute('data-no-loading')) return;

      form.addEventListener('submit', function () {
        /* Find the primary submit button */
        var btn = form.querySelector('button[type="submit"], input[type="submit"], button[name]');
        if (!btn) {
          /* Fallback: any button without an explicit type (defaults to submit) */
          btn = form.querySelector('button');
        }

        if (btn) {
          /* Preserve original label so it can be restored on back-nav */
          if (!btn.getAttribute('data-original-text')) {
            btn.setAttribute('data-original-text', btn.innerHTML);
          }
          btn.innerHTML =
            '<svg class="inline-block animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
            '</svg>Processing...';
          btn.disabled = true;
        }

        form.classList.add('form-submitting');
      });
    });
  }

  /* ================================================================
     8. LAZY IMAGE FADE-IN (section renumbered — see 9. BOOTSTRAP)
     ================================================================ */

  function setupLazyImages() {
    var imgs = document.querySelectorAll('img[loading="lazy"]');
    imgs.forEach(function (img) {
      if (img.complete) {
        img.classList.add('img-loaded');
      } else {
        img.addEventListener('load', function () {
          img.classList.add('img-loaded');
        });
      }
    });
  }

  /* ================================================================
     9. BOOTSTRAP — run everything on DOMContentLoaded
     ================================================================ */

  function init() {
    applyAvatarColors();
    applyCounters();
    highlightActiveNav();
    setupPageTransitions();
    setupLazyImages();
    setupFormLoadingStates();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    /* DOM already parsed (e.g. script in footer) */
    init();
  }

}(window));
</script>
