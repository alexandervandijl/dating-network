(function () {
    'use strict';

    function initFunnel(form) {
        var steps = Array.prototype.slice.call(form.querySelectorAll('.dn-funnel-step'));
        if (!steps.length) return;

        var shell = form.closest('.dn-signup-card');
        var progress = shell ? shell.querySelector('.dn-progress-bar span') : null;
        var number = shell ? shell.querySelector('[data-dn-step-number]') : null;
        var title = shell ? shell.querySelector('[data-dn-step-title]') : null;
        var current = 0;

        function setStep(index) {
            current = Math.max(0, Math.min(index, steps.length - 1));
            steps.forEach(function (step, i) {
                step.classList.toggle('is-active', i === current);
            });
            if (progress) progress.style.width = (((current + 1) / steps.length) * 100) + '%';
            if (number) number.textContent = String(current + 1);
            if (title) title.textContent = steps[current].getAttribute('data-title') || '';
            var first = steps[current].querySelector('input:not([type="hidden"]),select,button');
            if (first) window.setTimeout(function () { first.focus({preventScroll: true}); }, 30);
            if (shell) shell.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        function validateStep(step) {
            var fields = Array.prototype.slice.call(step.querySelectorAll('input,select,textarea'));
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    fields[i].focus();
                    return false;
                }
            }
            return true;
        }

        form.addEventListener('click', function (event) {
            var next = event.target.closest('.dn-funnel-next');
            var back = event.target.closest('.dn-funnel-back');
            if (next) {
                if (validateStep(steps[current])) setStep(current + 1);
            }
            if (back) setStep(current - 1);
        });

        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && current < steps.length - 1 && event.target.tagName !== 'TEXTAREA') {
                event.preventDefault();
                if (validateStep(steps[current])) setStep(current + 1);
            }
        });

        setStep(0);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dn-funnel]').forEach(initFunnel);
    });
}());
