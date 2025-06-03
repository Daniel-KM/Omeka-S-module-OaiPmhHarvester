(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sets-textarea details').forEach(e => {
            e.addEventListener('toggle', onToggle, { once: true });
        });
    });

    async function onToggle (ev) {
        const sourceId = ev.target.closest('[data-source-id]')?.getAttribute('data-source-id');
        if (!sourceId) {
            return;
        }

        const setsList = ev.target.querySelector('.sets-textarea__sets-list');

        const loadingMessage = ev.target.querySelector('.sets-textarea__loading-message');
        const textarea = ev.target.closest('.sets-textarea').querySelector('textarea');

        textarea.addEventListener('input', ev => {
            const setSpecs = textarea.value.split(/\n/).map(s => s.trim());

            setsList.querySelectorAll('tbody tr').forEach(tr => {
                const setSpec = tr.cells[0].textContent.trim();
                if (setSpecs.includes(setSpec)) {
                    tr.setAttribute('hidden', true);
                } else {
                    tr.removeAttribute('hidden');
                }
            });
        });

        let resumptionToken = null;
        do {
            loadingMessage.textContent = 'Fetching OAI sets...';

            try {
                const url = new URL(`/admin/oai-pmh-harvester/source/${sourceId}/list-sets`, location.origin);
                if (resumptionToken) {
                    url.searchParams.set('resumptionToken', resumptionToken);
                }
                const response = await fetch(url);
                const data = await response.json();
                resumptionToken = data.resumptionToken;

                for (const set of data.sets) {
                    const addLink = document.createElement('a');
                    addLink.setAttribute('href', '#');
                    addLink.classList.add('sets-textarea__add-link');
                    addLink.textContent = Omeka.jsTranslate('add set');
                    addLink.addEventListener('click', ev => {
                        const text = textarea.value === '' ? `${set.setSpec}` : `\n${set.setSpec}`;
                        textarea.focus();
                        textarea.setRangeText(text, textarea.textLength, textarea.textLength, 'select');
                        textarea.dispatchEvent(new InputEvent('input'));
                    });
                    const tr = document.createElement('tr');
                    const specTd = document.createElement('td');
                    specTd.textContent = set.setSpec;
                    const nameTd = document.createElement('td');
                    nameTd.textContent = set.setName;
                    const linkTd = document.createElement('td');
                    linkTd.append(addLink);
                    tr.append(specTd, nameTd, linkTd);
                    setsList.querySelector('table').tBodies[0].append(tr);
                }

                textarea.dispatchEvent(new InputEvent('input'));

                if (!resumptionToken) {
                    loadingMessage.textContent = '';
                }
            } catch (err) {
                console.error(err);
                loadingMessage.textContent = err.message;
                break;
            }
        } while (resumptionToken);
    }
})();
