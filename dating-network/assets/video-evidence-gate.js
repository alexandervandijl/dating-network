(() => {
    const reportButton = document.getElementById('dn-video-report');
    const submitButton = document.getElementById('dn-video-report-submit');
    if (!reportButton || !submitButton) return;

    reportButton.addEventListener('click', () => {
        submitButton.disabled = true;
        const state = document.querySelector('.dn-video-evidence-state');
        if (!state) { submitButton.disabled = false; return; }

        const release = () => {
            submitButton.disabled = false;
            observer.disconnect();
        };
        const observer = new MutationObserver(() => {
            if ((state.textContent || '').trim() !== '') release();
        });
        observer.observe(state, {childList:true, subtree:true, characterData:true});
        window.setTimeout(release, 4000);
    }, {capture:true});
})();
