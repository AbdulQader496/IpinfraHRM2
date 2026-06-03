<?php
// Confirmation Modal Component
// Usage: include this file once in your layout (e.g., footer or before </body>)
// JS API:
//   confirmAction(title, message, onConfirm)
//     - onConfirm: function callback OR URL string to navigate to on confirm
//   interceptConfirmLinks()
//     - Auto-converts <a href="..." data-confirm="Your message?"> links to use this modal
?>

<!-- Premium Confirmation Modal -->
<div
  id="confirmModal"
  role="dialog"
  aria-modal="true"
  aria-labelledby="confirmModalTitle"
  aria-describedby="confirmModalMessage"
  class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
  style="display: none !important;"
>
  <!-- Backdrop -->
  <div
    id="confirmModalBackdrop"
    class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0"
    onclick="window._confirmModalCancel()"
  ></div>

  <!-- Card -->
  <div
    id="confirmModalCard"
    class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden
           transform transition-all duration-300 scale-90 opacity-0"
  >
    <!-- Top accent bar -->
    <div class="h-1.5 w-full bg-gradient-to-r from-red-500 via-orange-400 to-red-600"></div>

    <div class="px-8 pt-8 pb-6">
      <!-- Icon -->
      <div class="flex justify-center mb-5">
        <div class="relative flex items-center justify-center w-20 h-20 rounded-full
                    bg-gradient-to-br from-red-50 to-orange-50
                    ring-8 ring-red-50 shadow-inner">
          <!-- Outer pulsing ring (CSS animation via inline style) -->
          <span id="confirmModalIconPulse"
                class="absolute inset-0 rounded-full bg-red-400 opacity-0"
                style="animation: confirmModalPulse 2s ease-out infinite;"></span>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-9 h-9 relative z-10 drop-shadow-sm"
            viewBox="0 0 24 24"
            fill="none"
            stroke="url(#confirmGrad)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id="confirmGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%"   stop-color="#ef4444" />
                <stop offset="100%" stop-color="#f97316" />
              </linearGradient>
            </defs>
            <!-- Warning triangle -->
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            <line x1="12" y1="9"  x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
      </div>

      <!-- Title -->
      <h2
        id="confirmModalTitle"
        class="text-center text-xl font-bold text-gray-800 mb-2 leading-snug tracking-tight"
      >
        Are you sure?
      </h2>

      <!-- Message -->
      <p
        id="confirmModalMessage"
        class="text-center text-sm text-gray-500 leading-relaxed mb-7"
      >
        This action cannot be undone.
      </p>

      <!-- Divider -->
      <div class="border-t border-gray-100 mb-6"></div>

      <!-- Buttons -->
      <div class="flex items-center gap-3">
        <!-- Cancel -->
        <button
          id="confirmModalCancelBtn"
          type="button"
          onclick="window._confirmModalCancel()"
          class="flex-1 px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600
                 bg-gray-100 hover:bg-gray-200 active:bg-gray-300
                 border border-gray-200 hover:border-gray-300
                 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-gray-300
                 select-none cursor-pointer"
        >
          Cancel
        </button>

        <!-- Confirm -->
        <button
          id="confirmModalConfirmBtn"
          type="button"
          onclick="window._confirmModalProceed()"
          class="flex-1 px-5 py-2.5 rounded-xl text-sm font-semibold text-white
                 bg-gradient-to-r from-red-500 to-red-600
                 hover:from-red-600 hover:to-red-700 active:from-red-700 active:to-red-800
                 shadow-md hover:shadow-lg hover:shadow-red-200
                 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-red-400
                 select-none cursor-pointer"
        >
          Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  @keyframes confirmModalPulse {
    0%   { transform: scale(1);   opacity: 0.35; }
    60%  { transform: scale(1.6); opacity: 0;    }
    100% { transform: scale(1.6); opacity: 0;    }
  }

  #confirmModal[data-open="true"] {
    display: flex !important;
  }

  /* Scale-in card animation class toggled via JS */
  #confirmModalCard.modal-visible {
    transform: scale(1);
    opacity: 1;
  }

  #confirmModalBackdrop.modal-visible {
    opacity: 1;
  }
</style>

<script>
(function () {
  'use strict';

  /* ---------------------------------------------------------------
   * Internal state
   * ------------------------------------------------------------- */
  var _onConfirmCallback = null;

  /* ---------------------------------------------------------------
   * Helpers
   * ------------------------------------------------------------- */
  function getEl(id) {
    return document.getElementById(id);
  }

  function trapFocus(modal) {
    var focusable = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;
    var first = focusable[0];
    var last  = focusable[focusable.length - 1];

    modal.addEventListener('keydown', function onKey(e) {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
      }
    });
  }

  /* ---------------------------------------------------------------
   * Open / Close
   * ------------------------------------------------------------- */
  function openModal(title, message, onConfirm) {
    _onConfirmCallback = onConfirm || null;

    // Set text
    getEl('confirmModalTitle').textContent   = title   || 'Are you sure?';
    getEl('confirmModalMessage').textContent = message || 'This action cannot be undone.';

    // Show modal shell
    var modal    = getEl('confirmModal');
    var backdrop = getEl('confirmModalBackdrop');
    var card     = getEl('confirmModalCard');

    modal.setAttribute('data-open', 'true');
    modal.removeAttribute('style'); // clear the display:none inline style

    // Prevent body scroll
    document.body.style.overflow = 'hidden';

    // Animate in on next frame so the transition fires
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        backdrop.classList.add('modal-visible');
        card.classList.add('modal-visible');
      });
    });

    // Focus the cancel button for accessibility
    setTimeout(function () {
      var cancelBtn = getEl('confirmModalCancelBtn');
      if (cancelBtn) cancelBtn.focus();
    }, 50);

    trapFocus(modal);
  }

  function closeModal(callback) {
    var modal    = getEl('confirmModal');
    var backdrop = getEl('confirmModalBackdrop');
    var card     = getEl('confirmModalCard');

    backdrop.classList.remove('modal-visible');
    card.classList.remove('modal-visible');

    // Wait for CSS transition to finish before hiding
    setTimeout(function () {
      modal.setAttribute('data-open', 'false');
      modal.style.display = 'none';
      document.body.style.overflow = '';
      _onConfirmCallback = null;
      if (typeof callback === 'function') callback();
    }, 300);
  }

  /* ---------------------------------------------------------------
   * Public API
   * ------------------------------------------------------------- */

  /**
   * confirmAction(title, message, onConfirm)
   *
   * @param {string}            title     - Modal heading text
   * @param {string}            message   - Modal body text
   * @param {function|string}   onConfirm - Callback function OR URL string to navigate to
   */
  window.confirmAction = function (title, message, onConfirm) {
    openModal(title, message, onConfirm);
  };

  /** Internal: called when user clicks Cancel or backdrop */
  window._confirmModalCancel = function () {
    closeModal();
  };

  /** Internal: called when user clicks Confirm */
  window._confirmModalProceed = function () {
    var cb = _onConfirmCallback;

    // Disable button to prevent double-click
    var confirmBtn = getEl('confirmModalConfirmBtn');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Please wait...';
    }

    closeModal(function () {
      if (typeof cb === 'function') {
        cb();
      } else if (typeof cb === 'string' && cb.length > 0) {
        window.location.href = cb;
      }
    });
  };

  /**
   * interceptConfirmLinks()
   *
   * Scans the DOM for <a> elements with a data-confirm attribute and
   * replaces the default navigation with the premium modal.
   *
   * Supported data attributes on the <a> tag:
   *   data-confirm="Your message here"   - body text (required to activate)
   *   data-confirm-title="Delete Record" - optional custom title (default: "Are you sure?")
   *
   * Call this after DOM is ready, and again after dynamic content is injected.
   *
   * @param {Element} [root=document] - Scope the scan to a subtree
   */
  window.interceptConfirmLinks = function (root) {
    var scope = root || document;
    var links = scope.querySelectorAll('a[data-confirm]');

    links.forEach(function (link) {
      // Avoid double-binding
      if (link.dataset.confirmBound) return;
      link.dataset.confirmBound = '1';

      link.addEventListener('click', function (e) {
        e.preventDefault();

        var message = link.getAttribute('data-confirm')       || 'This action cannot be undone.';
        var title   = link.getAttribute('data-confirm-title') || 'Are you sure?';
        var href    = link.href;

        confirmAction(title, message, href);
      });
    });
  };

  /* ---------------------------------------------------------------
   * Keyboard: close on Escape
   * ------------------------------------------------------------- */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var modal = getEl('confirmModal');
      if (modal && modal.getAttribute('data-open') === 'true') {
        window._confirmModalCancel();
      }
    }
  });

  /* ---------------------------------------------------------------
   * Auto-intercept on DOMContentLoaded
   * ------------------------------------------------------------- */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      window.interceptConfirmLinks();
    });
  } else {
    window.interceptConfirmLinks();
  }

}());
</script>
