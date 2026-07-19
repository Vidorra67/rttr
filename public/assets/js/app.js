(function () {
  'use strict';

  function initPinPad() {
    var hidden = document.getElementById('pin');
    var display = document.getElementById('pin_display');
    if (!hidden || !display) {
      return;
    }

    document.querySelectorAll('[data-pin-digit]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (hidden.value.length >= 6) {
          return;
        }
        hidden.value += button.getAttribute('data-pin-digit');
        display.value = hidden.value;
      });
    });

    var clear = document.querySelector('[data-pin-clear]');
    if (clear) {
      clear.addEventListener('click', function () {
        hidden.value = hidden.value.slice(0, -1);
        display.value = hidden.value;
      });
    }

    var reset = document.querySelector('[data-pin-reset]');
    if (reset) {
      reset.addEventListener('click', function () {
        hidden.value = '';
        display.value = '';
      });
    }
  }


  function initSteppers() {
    document.querySelectorAll('[data-stepper]').forEach(function (button) {
      button.addEventListener('click', function () {
        var wrapper = button.closest('.stepper');
        if (!wrapper) {
          return;
        }
        var input = wrapper.querySelector('input[type="number"]');
        if (!input) {
          return;
        }
        var delta = parseInt(button.getAttribute('data-stepper') || '0', 10);
        var value = parseInt(input.value || '0', 10);
        var min = input.min !== '' ? parseInt(input.min, 10) : -9999;
        var max = input.max !== '' ? parseInt(input.max, 10) : 9999;
        var next = Math.max(min, Math.min(max, value + delta));
        input.value = String(next);
      });
    });
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      return;
    }
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {
        // Service-Worker-Fehler sind nicht fachkritisch. Details bleiben im Browser-Kontext.
      });
    });
  }

  initPinPad();
  initSteppers();
  registerServiceWorker();
})();
