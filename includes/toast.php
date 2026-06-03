<?php
// PHP function is in toast_fn.php — included at top of PHP files before header() calls.
// This file only outputs the HTML/CSS/JS toast renderer (safe to include inside <body>).
require_once __DIR__ . '/toast_fn.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     TOAST NOTIFICATION SYSTEM — Premium UI
     ═══════════════════════════════════════════════════════════ -->

<style>
  /* Toast container */
  #toast-container {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;
    gap: 0.65rem;
    pointer-events: none;
  }

  /* Individual toast */
  .toast-item {
    pointer-events: all;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 300px;
    max-width: 420px;
    padding: 1rem 1.1rem;
    border-radius: 0.75rem;
    border-left-width: 4px;
    border-left-style: solid;
    background: #ffffff;
    box-shadow:
      0 4px 6px -1px rgba(0,0,0,0.07),
      0 10px 25px -5px rgba(0,0,0,0.12),
      0 0 0 1px rgba(0,0,0,0.04);
    transform: translateX(calc(100% + 2rem));
    opacity: 0;
    transition:
      transform 0.38s cubic-bezier(0.34, 1.3, 0.64, 1),
      opacity   0.32s ease;
    will-change: transform, opacity;
    position: relative;
    overflow: hidden;
  }

  /* Slide-in state */
  .toast-item.toast-visible {
    transform: translateX(0);
    opacity: 1;
  }

  /* Slide-out state */
  .toast-item.toast-hide {
    transform: translateX(calc(100% + 2rem));
    opacity: 0;
    transition:
      transform 0.3s cubic-bezier(0.4, 0, 0.6, 1),
      opacity   0.28s ease;
  }

  /* Progress bar at bottom */
  .toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    border-radius: 0 0 0.75rem 0;
    animation: toast-countdown linear forwards;
    transform-origin: left;
  }

  @keyframes toast-countdown {
    from { width: 100%; }
    to   { width: 0%; }
  }

  /* Icon circle */
  .toast-icon {
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
    margin-top: 0.05rem;
  }

  /* Close button */
  .toast-close {
    flex-shrink: 0;
    margin-left: auto;
    width: 1.4rem;
    height: 1.4rem;
    border-radius: 50%;
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: #9ca3af;
    transition: background 0.15s, color 0.15s;
    padding: 0;
    line-height: 1;
  }
  .toast-close:hover {
    background: #f3f4f6;
    color: #374151;
  }

  /* Toast type colour tokens */
  /* Success */
  .toast-success { border-left-color: #22c55e; }
  .toast-success .toast-icon  { background: #dcfce7; color: #16a34a; }
  .toast-success .toast-title { color: #15803d; }
  .toast-success .toast-progress { background: #22c55e; }

  /* Error */
  .toast-error { border-left-color: #ef4444; }
  .toast-error .toast-icon  { background: #fee2e2; color: #dc2626; }
  .toast-error .toast-title { color: #b91c1c; }
  .toast-error .toast-progress { background: #ef4444; }

  /* Warning */
  .toast-warning { border-left-color: #f97316; }
  .toast-warning .toast-icon  { background: #ffedd5; color: #ea580c; }
  .toast-warning .toast-title { color: #c2410c; }
  .toast-warning .toast-progress { background: #f97316; }

  /* Info */
  .toast-info { border-left-color: #3b82f6; }
  .toast-info .toast-icon  { background: #dbeafe; color: #2563eb; }
  .toast-info .toast-title { color: #1d4ed8; }
  .toast-info .toast-progress { background: #3b82f6; }

  /* Text */
  .toast-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.15rem;
  }
  .toast-message {
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.45;
    word-break: break-word;
  }

  /* Dark-mode aware */
  @media (prefers-color-scheme: dark) {
    .toast-item {
      background: #1f2937;
      box-shadow:
        0 4px 6px -1px rgba(0,0,0,0.3),
        0 10px 25px -5px rgba(0,0,0,0.4),
        0 0 0 1px rgba(255,255,255,0.05);
    }
    .toast-message { color: #d1d5db; }
    .toast-close   { color: #6b7280; }
    .toast-close:hover { background: #374151; color: #e5e7eb; }
    .toast-success .toast-icon  { background: #14532d; color: #4ade80; }
    .toast-error   .toast-icon  { background: #7f1d1d; color: #f87171; }
    .toast-warning .toast-icon  { background: #431407; color: #fb923c; }
    .toast-info    .toast-icon  { background: #1e3a8a; color: #60a5fa; }
  }
</style>

<!-- Toast container injected once -->
<div id="toast-container" aria-live="polite" aria-atomic="false"></div>

<script>
(function () {
  'use strict';

  /* ── Config ─────────────────────────────────────────────── */
  var AUTO_DISMISS_MS = 4000;

  /* ── Lookup tables ──────────────────────────────────────── */
  var TYPE_META = {
    success: { label: 'Success', icon: '✓' },
    error:   { label: 'Error',   icon: '✗' },
    warning: { label: 'Warning', icon: '⚠' },
    info:    { label: 'Info',    icon: 'ℹ' },
  };

  /* ── Core renderer ──────────────────────────────────────── */
  function showToast(message, type) {
    type = TYPE_META[type] ? type : 'success';
    var meta = TYPE_META[type];

    var container = document.getElementById('toast-container');
    if (!container) return;

    /* Build DOM */
    var toast = document.createElement('div');
    toast.className = 'toast-item toast-' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    /* Icon */
    var icon = document.createElement('div');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = meta.icon;

    /* Body */
    var body = document.createElement('div');
    body.style.flex = '1';
    body.style.minWidth = '0';

    var title = document.createElement('div');
    title.className = 'toast-title';
    title.textContent = meta.label;

    var msg = document.createElement('div');
    msg.className = 'toast-message';
    msg.textContent = message;   /* textContent — safe; no XSS */

    body.appendChild(title);
    body.appendChild(msg);

    /* Close button */
    var closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Dismiss notification');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', function () { dismiss(toast, progressEl); });

    /* Progress bar */
    var progressEl = document.createElement('div');
    progressEl.className = 'toast-progress';
    progressEl.style.animationDuration = AUTO_DISMISS_MS + 'ms';

    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(closeBtn);
    toast.appendChild(progressEl);
    container.appendChild(toast);

    /* Trigger slide-in on next frame */
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        toast.classList.add('toast-visible');
      });
    });

    /* Pause progress on hover */
    toast.addEventListener('mouseenter', function () {
      progressEl.style.animationPlayState = 'paused';
    });
    toast.addEventListener('mouseleave', function () {
      progressEl.style.animationPlayState = 'running';
    });

    /* Auto-dismiss */
    var timer = setTimeout(function () { dismiss(toast, progressEl); }, AUTO_DISMISS_MS);

    /* Stop timer when user interacts */
    toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
    toast.addEventListener('mouseleave', function () {
      /* Recalculate remaining time from progress bar width */
      var computedWidth = progressEl.getBoundingClientRect().width;
      var totalWidth    = toast.getBoundingClientRect().width;
      var ratio = totalWidth > 0 ? computedWidth / totalWidth : 0;
      timer = setTimeout(function () { dismiss(toast, progressEl); }, ratio * AUTO_DISMISS_MS);
    });
  }

  /* ── Dismiss helper ─────────────────────────────────────── */
  function dismiss(toast, progressEl) {
    if (!toast.parentNode) return;            /* already removed */
    progressEl.style.animationPlayState = 'paused';
    toast.classList.remove('toast-visible');
    toast.classList.add('toast-hide');
    toast.addEventListener('transitionend', function handler() {
      toast.removeEventListener('transitionend', handler);
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    });
  }

  /* ── Expose globally ────────────────────────────────────── */
  window.showToast = showToast;

})();
</script>

<?php
/* ── Server-side flash ── output any pending session toast ── */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['toast'])) {
    $t = $_SESSION['toast'];
    unset($_SESSION['toast']);

    // Sanitise for JS string literal output
    $jsMessage = addslashes($t['message']);
    $jsType    = addslashes($t['type']);
    echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.showToast('{$jsMessage}', '{$jsType}'); });</script>\n";
}
?>
