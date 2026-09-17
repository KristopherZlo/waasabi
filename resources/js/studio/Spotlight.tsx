import { router, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { Shared } from './types';

type Result = {type: 'post' | 'question' | 'user' | 'tag'; title: string; subtitle?: string; author?: string; url: string};

export function Spotlight() {
    const {copy: t} = usePage<Shared>().props;
    const dialog = useRef<HTMLDialogElement>(null);
    const input = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<Result[]>([]);
    const [selected, setSelected] = useState(0);
    const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle');
    const [shortcut, setShortcut] = useState('Ctrl K');
    const term = query.trim();
    const labels = {post: t.work, question: t.questions, user: t.search_people, tag: t.tags};

    useEffect(() => {
        setShortcut(/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘ K' : 'Ctrl K');
        const keydown = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen(value => !value);
            }
        };
        document.addEventListener('keydown', keydown);
        return () => document.removeEventListener('keydown', keydown);
    }, []);

    useEffect(() => {
        if (!open) {dialog.current?.close(); return;}
        const previous = document.activeElement as HTMLElement | null;
        const overflow = document.body.style.overflow;
        const paddingRight = document.body.style.paddingRight;
        const scrollbar = window.innerWidth - document.documentElement.clientWidth;
        setQuery(''); setItems([]); setSelected(0); setStatus('idle');
        dialog.current?.showModal();
        input.current?.focus();
        if (scrollbar > 0) document.body.style.paddingRight = `${(parseFloat(getComputedStyle(document.body).paddingRight) || 0) + scrollbar}px`;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = overflow;
            document.body.style.paddingRight = paddingRight;
            dialog.current?.close();
            previous?.focus();
        };
    }, [open]);

    useEffect(() => {
        if (!open || term.length < 2) {setItems([]); setStatus('idle'); return;}
        const controller = new AbortController();
        setStatus('loading'); setItems([]); setSelected(0);
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(term)}`, {headers: {Accept: 'application/json'}, signal: controller.signal});
                if (!response.ok) throw new Error('Search failed');
                const data = await response.json() as {items: Result[]};
                if (!controller.signal.aborted) {setItems(data.items); setStatus('ready');}
            } catch {
                if (!controller.signal.aborted) setStatus('error');
            }
        }, 180);
        return () => {window.clearTimeout(timer); controller.abort();};
    }, [open, term]);

    useEffect(() => {document.getElementById(`spotlight-result-${selected}`)?.scrollIntoView({block: 'nearest'});}, [selected, items]);
    const visit = (item: Result) => {setOpen(false); router.visit(item.url);};

    return <>
        <button className="top-search" aria-label={t.search} aria-haspopup="dialog" aria-controls="spotlight" onClick={() => setOpen(true)}>
            <Search size={18}/><span>{t.search}</span><kbd>{shortcut}</kbd>
        </button>
        <dialog ref={dialog} id="spotlight" className="spotlight" aria-label={t.search_short} onCancel={event => {event.preventDefault(); setOpen(false);}} onClick={event => {if (event.target === event.currentTarget) {const rect = event.currentTarget.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) setOpen(false);}}}>
            <div className="spotlight-input">
                <Search size={21}/>
                <input ref={input} value={query} maxLength={80} placeholder={t.search} aria-label={t.search} role="combobox" aria-autocomplete="list" aria-expanded={items.length > 0} aria-controls="spotlight-results" aria-activedescendant={items[selected] ? `spotlight-result-${selected}` : undefined} onChange={event => {setQuery(event.target.value); setItems([]); setSelected(0); setStatus(event.target.value.trim().length >= 2 ? 'loading' : 'idle');}} onKeyDown={event => {
                    if (event.nativeEvent.isComposing || !items.length) return;
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {event.preventDefault(); setSelected(value => (value + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);}
                    if (event.key === 'Enter' && items[selected]) {event.preventDefault(); visit(items[selected]);}
                }}/>
                <button className="icon-button" aria-label={t.search_close} onClick={() => setOpen(false)}><X size={19}/></button>
            </div>
            <div className="spotlight-results" id="spotlight-results" role="listbox" aria-label={t.search_results} aria-busy={status === 'loading'}>
                {items.map((item, index) => <div key={`${item.type}:${item.url}`} id={`spotlight-result-${index}`} role="option" aria-selected={index === selected} className="spotlight-result" onMouseMove={() => setSelected(index)} onClick={() => visit(item)}>
                    <div><strong>{item.title}</strong>{item.subtitle && <p>{item.subtitle}</p>}{item.author && <span className="meta">{item.author}</span>}</div><span className="meta">{labels[item.type]}</span>
                </div>)}
            </div>
            {!items.length && <p className="spotlight-message" role="status">{status === 'loading' ? t.search_loading : status === 'error' ? t.search_error : status === 'ready' ? t.empty_search : t.search_hint}</p>}
            <footer className="spotlight-footer"><span>↑ ↓ {t.search_navigate}</span><span>↵ {t.search_open}</span><span>Esc {t.search_close}</span></footer>
        </dialog>
    </>;
}
