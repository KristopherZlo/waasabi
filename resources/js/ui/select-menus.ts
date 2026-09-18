import './select-menus.css';

const closeSelectMenus = (except?: HTMLElement) => {
    document.querySelectorAll<HTMLElement>('.site-select.is-open').forEach(menu => {
        if (menu !== except) menu.classList.remove('is-open');
    });
};

export const setupSelectMenus = () => {
    document.querySelectorAll<HTMLSelectElement>('select:not([multiple]):not([data-native-select])').forEach(select => {
        if (select.dataset.siteSelect === '1') return;
        select.dataset.siteSelect = '1';
        const root = document.createElement('div'); root.className = 'site-select';
        const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = ['site-select__trigger', ...select.classList].join(' '); trigger.setAttribute('aria-haspopup', 'listbox');
        const menu = document.createElement('div'); menu.className = 'site-select__menu'; menu.setAttribute('role', 'listbox');
        const options = document.createElement('div'); options.className = 'site-select__options';
        const search = document.createElement('input'); search.type = 'search'; search.className = 'site-select__search'; search.placeholder = 'Search'; search.setAttribute('aria-label', 'Search options');
        const render = () => {
            const query = search.value.trim().toLocaleLowerCase(); options.replaceChildren();
            Array.from(select.options).filter(option => option.text.toLocaleLowerCase().includes(query)).forEach(option => {
                const button = document.createElement('button'); button.type = 'button'; button.textContent = option.text; button.disabled = option.disabled; button.setAttribute('role', 'option'); button.setAttribute('aria-selected', String(option.selected));
                button.addEventListener('click', () => {select.value = option.value; select.dispatchEvent(new Event('change', {bubbles: true})); update(); root.classList.remove('is-open'); trigger.focus();});
                options.append(button);
            });
        };
        const update = () => {trigger.textContent = select.selectedOptions[0]?.text || ''; render();};
        trigger.addEventListener('click', () => {const opening = !root.classList.contains('is-open'); closeSelectMenus(opening ? root : undefined); root.classList.toggle('is-open', opening); trigger.setAttribute('aria-expanded', String(opening)); if (opening && select.options.length > 5) search.focus();});
        search.addEventListener('input', render); search.addEventListener('keydown', event => {if (event.key === 'Enter') event.preventDefault();}); select.addEventListener('change', update); select.addEventListener('focus', () => trigger.focus()); select.form?.addEventListener('reset', () => setTimeout(update));
        select.parentNode?.insertBefore(root, select); root.append(select, trigger, menu); if (select.options.length > 5) menu.append(search); menu.append(options); update();
    });
    if (document.documentElement.dataset.siteSelectReady === '1') return;
    document.documentElement.dataset.siteSelectReady = '1';
    document.addEventListener('pointerdown', event => {if (!(event.target instanceof Element) || !event.target.closest('.site-select')) closeSelectMenus();});
    document.addEventListener('keydown', event => {if (event.key === 'Escape') closeSelectMenus();});
};
