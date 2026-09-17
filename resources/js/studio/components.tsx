import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { ArrowUp, Bell, Bookmark, Home, Layers, LoaderCircle, LogOut, MessageCircle, Moon, Plus, Settings, Sun, Users, X } from 'lucide-react';
import { Spotlight } from './Spotlight';
import { createAvatarFromName } from '../../../scribble-generator/scribble-avatar';
import type { Opening, Person, Shared, Work } from './types';

export const useShared = () => usePage<Shared>().props;
export const path = (value: string | null | undefined) => value ? (/^(https?:|data:|\/)/.test(value) ? value : `/${value}`) : '';
export const date = (value: string) => new Intl.DateTimeFormat('en', {day: 'numeric', month: 'short'}).format(new Date(value));

export function Avatar({person, large = false}: {person: Person; large?: boolean}) {
    const [src, setSrc] = useState(path(person.avatar));
    useEffect(() => { setSrc(person.avatar && !person.avatar.includes('avatar-default') ? path(person.avatar) : createAvatarFromName(person.name).dataUrl); }, [person.avatar, person.name]);
    return <img className={`avatar${large ? ' avatar-large' : ''}`} src={src || '/images/avatar-default.svg'} alt="" onError={() => setSrc(createAvatarFromName(person.name).dataUrl)} />;
}

export function ProfileBanner({person}: {person: Person}) {
    const generated = useMemo(() => {
        const svg = createAvatarFromName(person.name, {svgPointBudget: 1600}).svg
            .replace(/<\?xml[^>]*\?>/i, '').replace(/<rect[^>]*\/>/i, '');
        return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    }, [person.name]);
    const [src, setSrc] = useState(person.banner_url ? path(person.banner_url) : generated);
    useEffect(() => {setSrc(person.banner_url ? path(person.banner_url) : generated);}, [person.banner_url, generated]);
    return <img className={person.banner_url ? '' : 'profile-banner-generated'} src={src} alt="" onError={() => setSrc(generated)}/>;
}

export function Shell({children}: {children: ReactNode}) {
    const {auth, copy: t, flash, csrf, quick} = useShared();
    const {url} = usePage();
    const [theme, setTheme] = useState('dark');
    const [menu, setMenu] = useState<'notifications' | 'saved' | 'profile' | null>(null);
    const [toast, setToast] = useState(flash.message ?? '');
    const actionsRef = useRef<HTMLDivElement>(null);
    const authPage = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email'].some(path => url.startsWith(path));
    useEffect(() => {setTheme(document.documentElement.dataset.theme || 'dark');}, []);
    useEffect(() => {const meta = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]'); if (meta) meta.content = csrf;}, [csrf]);
    useEffect(() => {if (flash.message) setToast(flash.message);}, [flash.message, url]);
    useEffect(() => {if (!toast) return; const timer = window.setTimeout(() => setToast(''), 4200); return () => window.clearTimeout(timer);}, [toast]);
    useEffect(() => {const show = (event: Event) => setToast((event as CustomEvent<string>).detail); window.addEventListener('waasabi:toast', show); return () => window.removeEventListener('waasabi:toast', show);}, []);
    useEffect(() => {
        const outside = (event: PointerEvent) => {if (!actionsRef.current?.contains(event.target as Node)) setMenu(null);};
        const escape = (event: KeyboardEvent) => {if (event.key === 'Escape') setMenu(null);};
        document.addEventListener('pointerdown', outside); document.addEventListener('keydown', escape);
        return () => {document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape);};
    }, []);
    const changeTheme = () => {const next = theme === 'dark' ? 'light' : 'dark'; setTheme(next); document.documentElement.dataset.theme = next; try {localStorage.setItem('waasabi:theme', next);} catch { /* The theme still works for this visit. */ }};
    const profile = auth.user ? `/profile/${auth.user.slug}` : '/login';
    const nav = [
        {href: '/', label: t.feed, icon: Home, active: url === '/' || url.startsWith('/?')},
        {href: '/?stream=projects', label: t.projects, icon: Layers, active: url.includes('stream=projects')},
        {href: '/collaboration', label: t.help, icon: Users, active: url.startsWith('/collaboration') || url.startsWith('/people')},
        {href: '/create', label: t.create, icon: Plus, active: url.startsWith('/create') || url.startsWith('/publish')},
        {href: '/read-later', label: t.saved, icon: Bookmark, active: url.startsWith('/read-later')},
    ];
    return <>
        <a className="skip" href="#content">Skip to content</a>
        <header className="topbar"><div className="topbar-inner">
            <Link className="brand" href="/"><img src="/images/logo-black.svg" alt="" /><span>waasabi</span></Link>
            <Spotlight/>
            <div className="top-actions" ref={actionsRef}>
                <button className="icon-button" onClick={changeTheme} aria-label={t.theme}>{theme === 'dark' ? <Sun size={19}/> : <Moon size={19}/>}</button>
                {auth.user ? <><div className="top-menu-wrap"><button className="icon-button" aria-label={t.notifications} aria-expanded={menu === 'notifications'} onClick={() => setMenu(menu === 'notifications' ? null : 'notifications')}><Bell size={19}/>{quick.unread > 0 && <span className="icon-count">{Math.min(quick.unread, 99)}</span>}</button>{menu === 'notifications' && <div className="top-menu" role="menu"><div className="top-menu-head"><strong>{t.notifications}</strong><Link href="/notifications">{t.view_all}</Link></div>{quick.notifications.length ? quick.notifications.map(note => <Link className={note.unread ? 'quick-item unread' : 'quick-item'} href={note.link || '/notifications'} key={note.id}><span>{note.text}</span><small>{note.type}</small></Link>) : <p className="quick-empty">{t.no_notifications}</p>}</div>}</div><div className="top-menu-wrap"><button className="icon-button" aria-label={t.saved} aria-expanded={menu === 'saved'} onClick={() => setMenu(menu === 'saved' ? null : 'saved')}><Bookmark size={19}/></button>{menu === 'saved' && <div className="top-menu" role="menu"><div className="top-menu-head"><strong>{t.saved}</strong><Link href="/read-later">{t.view_all}</Link></div>{quick.saved.length ? quick.saved.map(item => <Link className="quick-item" href={item.url} key={item.id}><span>{item.title}</span></Link>) : <p className="quick-empty">{t.no_saved}</p>}</div>}</div><div className="top-menu-wrap"><button className="avatar-button" aria-label={t.profile} aria-expanded={menu === 'profile'} onClick={() => setMenu(menu === 'profile' ? null : 'profile')}><Avatar person={auth.user}/></button>{menu === 'profile' && <div className="top-menu profile-menu" role="menu"><div className="profile-menu-person"><Avatar person={auth.user}/><div><strong>{auth.user.name}</strong><span className="meta">@{auth.user.slug}</span></div></div><Link href={profile}><Users size={16}/>{t.profile}</Link><Link href="/profile/settings"><Settings size={16}/>{t.settings}</Link><Form action="/logout" className="top-menu-form"><button className="text-button"><LogOut size={16}/>{t.logout}</button></Form></div>}</div></> : !authPage && <Link className="button small" href="/login">{t.login}</Link>}
            </div>
        </div></header>
        <main className="page" id="content" tabIndex={-1}>
            {auth.user?.banned && <p className="notice error" role="alert">{t.not_ready}</p>}
            {children}
        </main>
        {toast && <div className="toast" role="status"><span>{toast}</span><button type="button" aria-label={t.close} onClick={() => setToast('')}><X size={16}/></button></div>}
        <nav className="bottom-nav" aria-label="Main navigation">
            {nav.map(({href, label, icon: Icon, active}) => <Link key={href} href={href} aria-label={label} className={active && (href !== '/' || !url.includes('stream=projects')) ? 'active' : ''} aria-current={active ? 'page' : undefined}><Icon size={21}/><span className="nav-tooltip" role="tooltip">{label}</span></Link>)}
            <Link href={profile} aria-label={t.profile} className={url.startsWith('/profile') ? 'active' : ''}>{auth.user ? <Avatar person={auth.user}/> : <Users size={21}/>}<span className="nav-tooltip" role="tooltip">{t.profile}</span></Link>
        </nav>
    </>;
}

export function Errors() {
    const {errors, copy: t} = useShared();
    if (!Object.keys(errors).length) return null;
    return <div className="notice error" role="alert"><strong>{t.errors}</strong><ul>{Object.entries(errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div>;
}

export function Form({action, method = 'post', children, className = '', reset = false, json = false, reloadData, scrollToResult = false, confirmMessage, preventEnterSubmit = false, onSuccess}: {action: string; method?: 'post' | 'put' | 'patch' | 'delete'; children: ReactNode; className?: string; reset?: boolean; json?: boolean; reloadData?: string | string[]; scrollToResult?: boolean | string; confirmMessage?: string; preventEnterSubmit?: boolean; onSuccess?: () => void}) {
    const {csrf, copy: t} = useShared();
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState('');
    const [pending, setPending] = useState<{form: HTMLFormElement; submitter: HTMLButtonElement | null} | null>(null);
    const complete = () => {onSuccess?.(); if (method === 'delete') window.dispatchEvent(new CustomEvent('waasabi:toast', {detail: t.deleted_successfully}));};
    const performSubmit = (form: HTMLFormElement, submitter: HTMLButtonElement | null) => {
        if (busy) return;
        setPending(null);
        const data = new FormData(form);
        if (submitter?.name) data.set(submitter.name, submitter.value);
        setBusy(true); setFailure('');
        if (json) {
            void jsonAction(action, Object.fromEntries(data)).then(result => {
                if (reset) form.reset();
                const only = typeof reloadData === 'string' ? [reloadData] : reloadData;
                router.reload({...only ? {only, reset: only} : {}, onSuccess: () => {
                    complete();
                    const target = typeof scrollToResult === 'string' ? scrollToResult : scrollToResult && result.id ? `comment-${result.id}` : '';
                    if (target) requestAnimationFrame(() => document.getElementById(target)?.scrollIntoView({block: 'center', behavior: 'smooth'}));
                }, onFinish: () => setBusy(false)});
            }).catch(error => {setFailure((error as Error).message); setBusy(false);});
            return;
        }
        router.post(action, data, {preserveScroll: true, onSuccess: () => {if (reset) form.reset(); complete();}, onFinish: () => setBusy(false)});
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault(); if (busy) return;
        const form = event.currentTarget;
        const submitter = (event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null;
        if (confirmMessage) {setPending({form, submitter}); return;}
        performSubmit(form, submitter);
    };
    return <><form action={action} method="post" encType="multipart/form-data" onSubmit={submit} onKeyDown={event => {const input = event.target instanceof HTMLInputElement ? event.target : null; if (preventEnterSubmit && event.key === 'Enter' && !event.defaultPrevented && input && !['button', 'checkbox', 'file', 'radio', 'submit'].includes(input.type)) event.preventDefault();}} className={className} aria-busy={busy}>
        <input type="hidden" name="_token" value={csrf}/>{method !== 'post' && <input type="hidden" name="_method" value={method.toUpperCase()}/>}
        <fieldset disabled={busy}>{children}</fieldset>{failure && <p className="field-error" role="alert">{failure}</p>}
    </form>{confirmMessage && <Dialog title={t.delete} open={Boolean(pending)} close={() => setPending(null)}><div className="confirm-dialog"><p>{confirmMessage}</p><div className="button-row"><button type="button" className="button" onClick={() => setPending(null)}>{t.cancel}</button><button type="button" className="button danger" onClick={() => pending && performSubmit(pending.form, pending.submitter)}>{t.delete}</button></div></div></Dialog>}</>;
}

export function InfinitePage({data, children, className = ''}: {data: string; children: ReactNode; className?: string}) {
    const {copy: t} = useShared();
    return <InfiniteScroll data={data} buffer={500} onlyNext className={className} loading={<div className="infinite-loading" role="status"><LoaderCircle size={17}/>{t.more}</div>}>{children}</InfiniteScroll>;
}

export function Empty({children, action}: {children: ReactNode; action?: ReactNode}) {return <div className="empty"><p>{children}</p>{action}</div>;}
export function Prose({html}: {html: string}) {return <div className="prose" dangerouslySetInnerHTML={{__html: html}}/>;}
export function PersonLine({person, detail}: {person: Person; detail?: string}) {return <div className="person-line"><Link href={`/profile/${person.slug}`}><Avatar person={person}/></Link><div><Link href={`/profile/${person.slug}`}>{person.name}</Link>{detail && <span className="meta">{detail}</span>}</div></div>;}

export async function jsonAction(url: string, data: Record<string, unknown> = {}, method = 'POST') {
    const response = await fetch(url, {method, headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? ''}, body: JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Could not save. Please try again.');
    return result as Record<string, unknown>;
}

export function WorkActions({work}: {work: Work}) {
    const {auth, copy: t} = useShared();
    const [liked, setLiked] = useState(work.liked); const [saved, setSaved] = useState(work.saved); const [score, setScore] = useState(work.score);
    const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [celebrating, setCelebrating] = useState(false);
    const [burst, setBurst] = useState<{id: number; x: number; y: number} | null>(null);
    const toggle = async (action: 'save' | 'upvote', source?: HTMLButtonElement) => {
        if (!auth.user) {router.visit('/login'); return;}
        if (busy) return; setBusy(true); setError('');
        const previous = {liked, saved, score};
        if (action === 'save') setSaved(!saved);
        else {
            setLiked(!liked); setScore(score + (liked ? -1 : 1));
            if (!liked && source) {const box = source.getBoundingClientRect(); setCelebrating(true); setBurst({id: Date.now(), x: box.left + box.width / 2, y: box.top + box.height / 2});}
        }
        try {const result = await jsonAction(`/posts/${work.slug}/${action}`); if (action === 'save') setSaved(Boolean(result.saved)); else {setLiked(Boolean(result.upvoted)); setScore(Number(result.count));}} catch (error) {setLiked(previous.liked); setSaved(previous.saved); setScore(previous.score); setError((error as Error).message);} finally {setBusy(false);}
    };
    return <><div className="work-actions"><button className={celebrating ? 'upvote-action is-celebrating' : 'upvote-action'} aria-label="Appreciate this work" aria-pressed={liked} aria-busy={busy} onClick={event => void toggle('upvote', event.currentTarget)} onAnimationEnd={event => {if (event.target === event.currentTarget) setCelebrating(false);}}><ArrowUp size={18}/><span className="upvote-score">{score}</span></button><Link href={work.type === 'question' ? `${work.url}#answers` : `${work.url}?tab=discussion`}><MessageCircle size={17}/>{work.comments}</Link><button className="save-action" aria-label={t.saved} aria-pressed={saved} aria-busy={busy} onClick={() => void toggle('save')}><Bookmark size={17}/></button></div>{burst && createPortal(<span key={burst.id} className="upvote-burst" style={{left: burst.x, top: burst.y}} aria-hidden onAnimationEnd={event => {if (event.target === event.currentTarget) setBurst(null);}}>{Array.from({length: 16}, (_, index) => <i key={index}/>)}</span>, document.body)}{error && <p className="field-error" role="alert">{error}</p>}</>;
}

export function WorkCard({work}: {work: Work}) {
    const {copy: t} = useShared();
    const [reveal, setReveal] = useState(false);
    return <article className="work-card" data-category={work.category}>
        <div className="work-card-head"><PersonLine person={work.author} detail={date(work.date)}/><span className="work-kind">{work.type === 'question' ? t.questions : work.is_project ? t.project : t.work}</span></div>
        <Link href={work.url}><h2>{work.title}</h2></Link>
        <p className="work-intro">{work.subtitle || work.preview}</p>
        {work.cover && <div className="work-image-wrap">{work.nsfw && !reveal ? <button className="sensitive" onClick={() => setReveal(true)}>Sensitive content · show</button> : <Link href={work.url}><img className="work-image" src={path(work.cover)} alt={work.title} loading="lazy"/></Link>}</div>}
        <div className="work-card-foot"><div className="work-tags">{(work.tags ?? []).slice(0, 4).map(tag => <Link key={tag} href={`/?tags=${encodeURIComponent(tag)}`}>#{tag}</Link>)}</div>{work.visibility === 'draft' && <span className="state-label">{t.draft}</span>}</div>
        <WorkActions work={work}/>
    </article>;
}

export function OpeningCard({opening, compact = false}: {opening: Opening; compact?: boolean}) {
    const {copy: t} = useShared();
    return <article className={compact ? 'opening-compact' : 'opening-card'} data-role={opening.role}>
        {!compact && <PersonLine person={opening.author} detail={date(opening.date)}/>}
        <Link href={`/collaboration/${opening.id}`}><h3>{opening.title}</h3></Link>
        <p>{opening.summary}</p><div className="meta opening-facts"><span>{opening.role.replace(/-/g, ' ')}</span><span>{opening.availability.replace(/-/g, ' ')}</span><span>{opening.open ? t.open : t.closed}</span></div>
    </article>;
}

export function Dialog({title, children, open, close}: {title: string; children: ReactNode; open: boolean; close: () => void}) {
    const ref = useRef<HTMLDialogElement>(null);
    useEffect(() => {if (open) ref.current?.showModal(); else ref.current?.close();}, [open]);
    return <dialog ref={ref} onCancel={close} onClick={event => {if(event.target === ref.current) close();}} aria-label={title}><div className="dialog-head"><h2>{title}</h2><button className="icon-button" type="button" onClick={close} aria-label="Close"><X size={20}/></button></div>{children}</dialog>;
}
