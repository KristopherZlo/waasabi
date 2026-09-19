import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { ArrowUp, BadgeCheck, Bell, Bookmark, Check, ChevronDown, ExternalLink, EyeOff, Home, Layers, LoaderCircle, LogOut, MessageCircle, Moon, Plus, RotateCcw, Search, Settings, Shield, Sun, Users, X } from 'lucide-react';
import { Spotlight } from './Spotlight';
import { createAvatarFromName } from '../../../scribble-generator/scribble-avatar';
import type { Opening, Person, Shared, Work } from './types';

export const useShared = () => usePage<Shared>().props;
export const path = (value: string | null | undefined) => value ? (/^(https?:|data:|\/)/.test(value) ? value : `/${value}`) : '';
export const date = (value: string) => new Intl.DateTimeFormat('en', {day: 'numeric', month: 'short'}).format(new Date(value));
export type ModerationTarget = {id: number; title: string; type: 'post' | 'comment' | 'review'; is_hidden: boolean; moderation_status: string; nsfw?: boolean; reloadData: string};
export const openModeration = (target: ModerationTarget) => window.dispatchEvent(new CustomEvent('waasabi:moderate', {detail: target}));
type ToastMessage = {message: string; actionLabel?: string; onAction?: () => void; duration?: number};
let cancelPendingUndo: (() => void) | null = null;

export function Avatar({person, large = false}: {person: Person; large?: boolean}) {
    const [src, setSrc] = useState(path(person.avatar));
    useEffect(() => { setSrc(person.avatar && !person.avatar.includes('avatar-default') ? path(person.avatar) : createAvatarFromName(person.name).dataUrl); }, [person.avatar, person.name]);
    return <img className={`avatar${large ? ' avatar-large' : ''}`} src={src || '/images/avatar-default.svg'} alt="" onError={() => setSrc(createAvatarFromName(person.name).dataUrl)} />;
}

export function VerifiedName({person, children}: {person: Person; children?: ReactNode}) {
    const {copy: t} = useShared();
    return <span className="verified-name">{children ?? person.name}{person.verified && <BadgeCheck className="verified-mark" size={20} strokeWidth={2.5} aria-label={t.profile_verified}/>}</span>;
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
    const [moderationOpen, setModerationOpen] = useState(false);
    const [toast, setToast] = useState<ToastMessage | null>(flash.message ? {message: flash.message} : null);
    const actionsRef = useRef<HTMLDivElement>(null);
    const authPage = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/two-factor-challenge'].some(path => url.startsWith(path));
    useEffect(() => {setTheme(document.documentElement.dataset.theme || 'dark');}, []);
    useEffect(() => {const meta = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]'); if (meta) meta.content = csrf;}, [csrf]);
    useEffect(() => {if (flash.message) setToast({message: flash.message});}, [flash.message, url]);
    useEffect(() => {if (!toast) return; const timer = window.setTimeout(() => setToast(null), toast.duration ?? 4200); return () => window.clearTimeout(timer);}, [toast]);
    useEffect(() => {const show = (event: Event) => {const detail = (event as CustomEvent<string | ToastMessage>).detail; setToast(typeof detail === 'string' ? {message: detail} : detail);}; window.addEventListener('waasabi:toast', show); return () => window.removeEventListener('waasabi:toast', show);}, []);
    useEffect(() => {const open = () => setModerationOpen(true); window.addEventListener('waasabi:moderate', open); return () => window.removeEventListener('waasabi:moderate', open);}, []);
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
                {auth.user ? <><div className="top-menu-wrap"><button className="icon-button" aria-label={t.notifications} aria-expanded={menu === 'notifications'} onClick={() => setMenu(menu === 'notifications' ? null : 'notifications')}><Bell size={19}/>{quick.unread > 0 && <span className="icon-count">{Math.min(quick.unread, 99)}</span>}</button>{menu === 'notifications' && <div className="top-menu" role="menu"><div className="top-menu-head"><strong>{t.notifications}</strong><Link href="/notifications">{t.view_all}</Link></div>{quick.notifications.length ? quick.notifications.map(note => <Link className={note.unread ? 'quick-item unread' : 'quick-item'} href={note.link || '/notifications'} key={note.id}><span>{note.text}</span><small>{note.type}</small></Link>) : <p className="quick-empty">{t.no_notifications}</p>}</div>}</div><div className="top-menu-wrap"><button className="icon-button" aria-label={t.saved} aria-expanded={menu === 'saved'} onClick={() => setMenu(menu === 'saved' ? null : 'saved')}><Bookmark size={19}/></button>{menu === 'saved' && <div className="top-menu" role="menu"><div className="top-menu-head"><strong>{t.saved}</strong><Link href="/read-later">{t.view_all}</Link></div>{quick.saved.length ? quick.saved.map(item => <Link className="quick-item" href={item.url} key={item.id}><span>{item.title}</span></Link>) : <p className="quick-empty">{t.no_saved}</p>}</div>}</div><div className="top-menu-wrap"><button className="avatar-button" aria-label={t.profile} aria-expanded={menu === 'profile'} onClick={() => setMenu(menu === 'profile' ? null : 'profile')}><Avatar person={auth.user}/></button>{menu === 'profile' && <div className="top-menu profile-menu" role="menu"><div className="profile-menu-person"><Avatar person={auth.user}/><div><strong><VerifiedName person={auth.user}/></strong><span className="meta">@{auth.user.slug}</span></div></div><Link href={profile}><Users size={16}/>{t.profile}</Link><Link href="/profile/settings"><Settings size={16}/>{t.settings}</Link><Form action="/logout" className="top-menu-form"><button className="text-button"><LogOut size={16}/>{t.logout}</button></Form></div>}</div></> : !authPage && <Link className="button small" href="/login">{t.login}</Link>}
            </div>
        </div></header>
        <main className="page" id="content" tabIndex={-1}>
            {auth.user?.banned && <p className="notice error" role="alert">{t.not_ready}</p>}
            {children}
        </main>
        {auth.user?.moderator && <ModeratorDrawer open={moderationOpen} close={() => setModerationOpen(false)} toggle={() => setModerationOpen(value => !value)}/>}
        {toast && <div className="toast" role="status"><span>{toast.message}</span><div className="toast-actions">{toast.actionLabel && toast.onAction && <button className="toast-action" type="button" onClick={() => {toast.onAction?.(); setToast(null);}}>{toast.actionLabel}</button>}<button type="button" aria-label={t.close} onClick={() => setToast(null)}><X size={16}/></button></div></div>}
        <nav className="bottom-nav" aria-label="Main navigation">
            {nav.map(({href, label, icon: Icon, active}) => <Link key={href} href={href} aria-label={label} className={active && (href !== '/' || !url.includes('stream=projects')) ? 'active' : ''} aria-current={active ? 'page' : undefined}><Icon size={21}/><span className="nav-tooltip" role="tooltip">{label}</span></Link>)}
            <Link href={profile} aria-label={t.profile} className={url.startsWith('/profile') ? 'active' : ''}>{auth.user ? <Avatar person={auth.user}/> : <Users size={21}/>}<span className="nav-tooltip" role="tooltip">{t.profile}</span></Link>
        </nav>
    </>;
}

function ModeratorDrawer({open, close, toggle}: {open: boolean; close: () => void; toggle: () => void}) {
    const {auth, copy: t, moderation} = useShared();
    const page = usePage<Shared & {work?: {id: number; title: string; type: string; is_hidden: boolean; moderation_status: string; nsfw: boolean}}>();
    const work = page.props.work?.id ? page.props.work : null;
    const current: ModerationTarget | null = work ? {id: work.id, title: work.title, type: 'post', is_hidden: work.is_hidden, moderation_status: work.moderation_status, nsfw: work.nsfw, reloadData: 'work'} : null;
    const [selected, setSelected] = useState<ModerationTarget | null>(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteError, setDeleteError] = useState('');
    const [deleting, setDeleting] = useState(false);
    const target = selected || current;
    const hidden = Boolean(target?.is_hidden || (target && target.moderation_status !== 'approved'));
    const resource = target?.type === 'post' ? 'posts' : `${target?.type}s`;
    const closeRef = useRef<HTMLButtonElement>(null);
    const dismiss = () => {setSelected(null); close();};
    useEffect(() => {const select = (event: Event) => setSelected((event as CustomEvent<ModerationTarget>).detail); window.addEventListener('waasabi:moderate', select); return () => window.removeEventListener('waasabi:moderate', select);}, []);
    useEffect(() => {setSelected(null); setDeleteReason(''); setDeleteError('');}, [page.url]);
    useEffect(() => {
        if (!open) return;
        closeRef.current?.focus();
        const escape = (event: KeyboardEvent) => {if (event.key === 'Escape') dismiss();};
        document.addEventListener('keydown', escape);
        return () => document.removeEventListener('keydown', escape);
    }, [open, close]);
    const remove = async () => {
        if (!target || !deleteReason.trim() || !window.confirm(t.confirm_delete)) return;
        setDeleting(true); setDeleteError('');
        try {
            await jsonAction(`/admin/${resource}/${target.id}`, {reason: deleteReason}, 'DELETE');
            dismiss();
            if (target.reloadData === 'work') router.visit('/');
            else router.reload({only: [target.reloadData, 'moderation'], reset: [target.reloadData]});
        } catch (error) {setDeleteError((error as Error).message);} finally {setDeleting(false);}
    };
    return <>
        <button className="moderation-trigger" type="button" onClick={toggle} aria-label={t.open_moderation} aria-expanded={open}><Shield size={20}/>{Boolean(moderation?.queue) && <span>{moderation?.queue}</span>}</button>
        {open && <button className="moderation-backdrop" type="button" onClick={dismiss} aria-label={t.close}/>}
        {open && <aside className="moderation-drawer" aria-label={t.moderation_panel}>
            <div className="moderation-drawer-head"><div><strong>{t.moderation_panel}</strong><span>{moderation?.queue || 0} {t.queue_items}</span></div><button ref={closeRef} className="icon-button" type="button" onClick={dismiss} aria-label={t.close}><X size={19}/></button></div>
            <div className="moderation-drawer-links"><Link href="/admin" onClick={dismiss}><Shield size={17}/><span>{t.queue}</span><strong>{moderation?.queue || 0}</strong></Link>{auth.user?.admin && <a href="/admin/tools"><Settings size={17}/><span>{t.admin_tools}</span><ExternalLink size={14}/></a>}</div>
            <div className="moderation-rule"><span>{t.report_threshold}</span><strong>{moderation?.threshold ?? 16}</strong><p>{t.reports_minimum}: {moderation?.minimum_reports ?? 3}</p></div>
            {target ? <div className="moderation-context"><span className="moderation-label">{t.current_content}</span><strong>{target.title}</strong><span className="state-label">{target.moderation_status}</span>
                {!auth.user?.admin && !hidden && <Form json undoable confirmMessage={t.confirm_queue_content} confirmLabel={t.send_to_moderation} reloadData={[target.reloadData, 'moderation']} action={`/admin/moderation/${resource}/${target.id}/queue`} className="stack" onSuccess={dismiss}><label>{t.reason}<input name="reason" maxLength={500} required placeholder={t.hide_reason}/></label><button className="button danger"><EyeOff size={16}/>{t.send_to_moderation}</button></Form>}
                {!auth.user?.admin && hidden && <p className="notice">{t.awaiting_review}</p>}
                {auth.user?.admin && hidden && <div className="button-row"><Form json undoable confirmMessage={t.confirm_restore_content} confirmLabel={t.restore} reloadData={[target.reloadData, 'moderation']} action={`/admin/moderation/${resource}/${target.id}/restore`} onSuccess={dismiss}><button className="button primary"><RotateCcw size={16}/>{t.restore}</button></Form>{target.moderation_status === 'pending' && <Form json undoable confirmMessage={t.confirm_hide_content} confirmLabel={t.confirm_hide} reloadData={[target.reloadData, 'moderation']} action={`/admin/moderation/${resource}/${target.id}/hide`} onSuccess={dismiss}><input type="hidden" name="reason" value="Confirmed after review"/><button className="button danger"><EyeOff size={16}/>{t.confirm_hide}</button></Form>}</div>}
                {auth.user?.admin && !hidden && <Form json undoable confirmMessage={t.confirm_hide_content} confirmLabel={t.hide} reloadData={[target.reloadData, 'moderation']} action={`/admin/moderation/${resource}/${target.id}/hide`} className="stack" onSuccess={dismiss}><label>{t.reason}<input name="reason" maxLength={500} required placeholder={t.hide_reason}/></label><button className="button danger"><EyeOff size={16}/>{t.hide}</button></Form>}
                {auth.user?.admin && target.type === 'post' && !target.nsfw && <Form json undoable confirmMessage={t.confirm_nsfw_content} confirmLabel={t.mark_nsfw} reloadData={[target.reloadData, 'moderation']} action={`/admin/moderation/posts/${target.id}/nsfw`} onSuccess={dismiss}><input type="hidden" name="nsfw" value="1"/><button className="button"><EyeOff size={16}/>{t.mark_nsfw}</button></Form>}
                {auth.user?.admin && <div className="moderation-delete"><label>{t.reason}<input value={deleteReason} onChange={event => setDeleteReason(event.target.value)} maxLength={1000} required placeholder={t.delete_reason}/></label><button className="button danger" type="button" disabled={deleting || !deleteReason.trim()} onClick={() => void remove()}>{t.delete}</button>{deleteError && <p className="field-error" role="alert">{deleteError}</p>}</div>}
            </div> : <p className="moderation-empty">{t.no_context}</p>}
        </aside>}
    </>;
}

export function Errors() {
    const {errors, copy: t} = useShared();
    if (!Object.keys(errors).length) return null;
    return <div className="toast error-toast" role="alert"><div><strong>{t.errors}</strong><ul>{Object.entries(errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div></div>;
}

export function Form({action, method = 'post', children, className = '', reset = false, json = false, reloadData, scrollToResult = false, confirmMessage, confirmLabel, successMessage, preventEnterSubmit = false, undoable = false, onSuccess}: {action: string; method?: 'post' | 'put' | 'patch' | 'delete'; children: ReactNode; className?: string; reset?: boolean; json?: boolean; reloadData?: string | string[]; scrollToResult?: boolean | string; confirmMessage?: string; confirmLabel?: string; successMessage?: string; preventEnterSubmit?: boolean; undoable?: boolean; onSuccess?: () => void}) {
    const {csrf, copy: t} = useShared();
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState('');
    const [pending, setPending] = useState<{form: HTMLFormElement; submitter: HTMLButtonElement | null} | null>(null);
    const complete = () => {onSuccess?.(); if (method === 'delete') window.dispatchEvent(new CustomEvent('waasabi:toast', {detail: successMessage || t.deleted_successfully}));};
    const submitNow = (form: HTMLFormElement, submitter: HTMLButtonElement | null) => {
        setPending(null);
        const data = new FormData(form);
        if (submitter?.name) data.set(submitter.name, submitter.value);
        setBusy(true); setFailure('');
        if (json) {
            void jsonAction(action, Object.fromEntries(data), method.toUpperCase()).then(result => {
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
    const performSubmit = (form: HTMLFormElement, submitter: HTMLButtonElement | null) => {
        if (busy) return;
        if (!undoable) {submitNow(form, submitter); return;}
        cancelPendingUndo?.();
        setPending(null); setBusy(true); setFailure('');
        let timer = 0;
        const cancel = () => {window.clearTimeout(timer); if (cancelPendingUndo === cancel) cancelPendingUndo = null; setBusy(false);};
        cancelPendingUndo = cancel;
        timer = window.setTimeout(() => {if (cancelPendingUndo === cancel) cancelPendingUndo = null; submitNow(form, submitter);}, 5000);
        window.dispatchEvent(new CustomEvent('waasabi:toast', {detail: {message: t.action_pending, actionLabel: t.undo, onAction: cancel, duration: 5200}}));
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
    </form>{confirmMessage && <Dialog title={confirmLabel || t.delete} open={Boolean(pending)} close={() => setPending(null)}><div className="confirm-dialog"><p>{confirmMessage}</p><div className="button-row"><button type="button" className="button" onClick={() => setPending(null)}>{t.cancel}</button><button type="button" className="button danger" onClick={() => pending && performSubmit(pending.form, pending.submitter)}>{confirmLabel || t.delete}</button></div></div></Dialog>}</>;
}

export type SelectOption = {value: string; label: string};

export function SelectMenu({name, label, options, value, defaultValue = '', multiple = false, max, placeholder, onChange}: {name: string; label: string; options: SelectOption[]; value?: string | string[]; defaultValue?: string | string[]; multiple?: boolean; max?: number; placeholder?: string; onChange?: (value: string | string[]) => void}) {
    const {copy: t} = useShared();
    const root = useRef<HTMLDivElement>(null); const [open, setOpen] = useState(false); const [query, setQuery] = useState('');
    const [internal, setInternal] = useState<string[]>(() => (Array.isArray(defaultValue) ? defaultValue : defaultValue ? [defaultValue] : []));
    const selected = value === undefined ? internal : Array.isArray(value) ? value : value ? [value] : [];
    useEffect(() => {
        const outside = (event: PointerEvent) => {if (!root.current?.contains(event.target as Node)) setOpen(false);};
        const escape = (event: KeyboardEvent) => {if (event.key === 'Escape') setOpen(false);};
        document.addEventListener('pointerdown', outside); document.addEventListener('keydown', escape);
        return () => {document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape);};
    }, []);
    const choose = (nextValue: string) => {
        const next = multiple
            ? selected.includes(nextValue) ? selected.filter(item => item !== nextValue) : max && selected.length >= max ? selected : [...selected, nextValue]
            : [nextValue];
        if (value === undefined) setInternal(next);
        onChange?.(multiple ? next : next[0] || '');
        if (!multiple) setOpen(false);
    };
    const visible = options.filter(option => option.label.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()));
    const labels = selected.map(item => options.find(option => option.value === item)?.label).filter(Boolean);
    const summary = labels.length > 1 ? `${labels[0]} +${labels.length - 1}` : labels[0] || placeholder || t.choose;
    return <div className="select-field" ref={root}>
        <span className="select-label">{label}</span>
        {multiple ? selected.map(item => <input key={item} type="hidden" name={name} value={item}/>) : <input type="hidden" name={name} value={selected[0] || ''}/>}
        <button className="select-trigger" type="button" aria-haspopup="listbox" aria-expanded={open} onClick={() => setOpen(current => !current)}><span>{summary}</span>{multiple && max && <small>{selected.length}/{max}</small>}<ChevronDown size={16}/></button>
        {open && <div className="select-popover"><label className="select-search"><Search size={15}/><input value={query} onChange={event => setQuery(event.target.value)} onKeyDown={event => {if (event.key === 'Enter') event.preventDefault();}} placeholder={t.search_short} aria-label={`${t.search_short}: ${label}`} autoFocus/></label><div className="select-options" role="listbox" aria-multiselectable={multiple || undefined}>{visible.map(option => {const active = selected.includes(option.value); const disabled = Boolean(multiple && max && selected.length >= max && !active); return <button type="button" role="option" aria-selected={active} disabled={disabled} key={option.value} onClick={() => choose(option.value)}><Check size={15}/><span>{option.label}</span></button>;})}{!visible.length && <span className="select-empty">{t.empty}</span>}</div></div>}
    </div>;
}

export function InfinitePage({data, children, className = ''}: {data: string; children: ReactNode; className?: string}) {
    const {copy: t} = useShared();
    return <InfiniteScroll data={data} buffer={500} onlyNext className={className} loading={<div className="infinite-loading" role="status"><LoaderCircle size={17}/>{t.more}</div>}>{children}</InfiniteScroll>;
}

export function Empty({children, action}: {children: ReactNode; action?: ReactNode}) {return <div className="empty"><p>{children}</p>{action}</div>;}
export function Prose({html}: {html: string}) {return <div className="prose" dangerouslySetInnerHTML={{__html: html}}/>;}
export function PersonLine({person, detail}: {person: Person; detail?: string}) {return <div className="person-line"><Link href={`/profile/${person.slug}`}><Avatar person={person}/></Link><div><Link href={`/profile/${person.slug}`}><VerifiedName person={person}/></Link>{detail && <span className="meta">{detail}</span>}</div></div>;}

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
    useEffect(() => {
        if (!celebrating) return;
        const timer = window.setTimeout(() => setCelebrating(false), 1250);
        return () => window.clearTimeout(timer);
    }, [celebrating]);
    const toggle = async (action: 'save' | 'upvote') => {
        if (!auth.user) {router.visit('/login'); return;}
        if (busy) return; setBusy(true); setError('');
        const previous = {liked, saved, score};
        if (action === 'save') setSaved(!saved);
        else {
            setLiked(!liked); setScore(score + (liked ? -1 : 1));
            if (!liked) setCelebrating(true);
        }
        try {const result = await jsonAction(`/posts/${work.slug}/${action}`); if (action === 'save') setSaved(Boolean(result.saved)); else {setLiked(Boolean(result.upvoted)); setScore(Number(result.count));}} catch (error) {setLiked(previous.liked); setSaved(previous.saved); setScore(previous.score); setError((error as Error).message);} finally {setBusy(false);}
    };
    return <><div className="work-actions"><button className={celebrating ? 'upvote-action is-celebrating' : 'upvote-action'} aria-label="Appreciate this work" aria-pressed={liked} aria-busy={busy} onClick={() => void toggle('upvote')}><span className="upvote-sparks" aria-hidden>{Array.from({length: 10}, (_, index) => <i key={index}/>)}</span><ArrowUp className="upvote-ghost" size={18} aria-hidden/><ArrowUp className="upvote-arrow" size={18}/><span className="upvote-score">{score}</span></button><Link href={work.type === 'question' ? `${work.url}#answers` : `${work.url}?tab=discussion`}><MessageCircle size={17}/>{work.comments}</Link><button className="save-action" aria-label={t.saved} aria-pressed={saved} aria-busy={busy} onClick={() => void toggle('save')}><Bookmark size={17}/></button></div>{error && <p className="field-error" role="alert">{error}</p>}</>;
}

export function WorkCard({work}: {work: Work}) {
    const {auth, copy: t} = useShared();
    const [reveal, setReveal] = useState(false);
    return <article className="work-card" data-category={work.category}>
        <div className="work-card-head"><PersonLine person={work.author} detail={date(work.date)}/><div className="work-card-tools"><span className="work-kind">{work.type === 'question' ? t.questions : work.is_project ? t.project : t.work}</span>{auth.user?.moderator && <button className="icon-button" type="button" onClick={() => openModeration({id: work.id, title: work.title, type: 'post', is_hidden: work.is_hidden, moderation_status: work.moderation_status, nsfw: work.nsfw, reloadData: 'works'})} aria-label={`${t.open_moderation}: ${work.title}`}><Shield size={17}/></button>}</div></div>
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
