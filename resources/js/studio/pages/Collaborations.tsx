import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Check, ChevronDown, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Empty, InfinitePage, PersonLine, date, useShared } from '../components';
import type { Opening, Pagination } from '../types';

function MultiFilter({label, anyLabel, options, selected, onChange}: {label: string; anyLabel: string; options: Record<string, string>; selected: string[]; onChange: (values: string[]) => void}) {
    const {copy: t} = useShared(); const [query, setQuery] = useState(''); const root = useRef<HTMLDetailsElement>(null);
    useEffect(() => {
        const outside = (event: PointerEvent) => {if (!root.current?.contains(event.target as Node)) root.current?.removeAttribute('open');};
        const escape = (event: KeyboardEvent) => {if (event.key === 'Escape') root.current?.removeAttribute('open');};
        document.addEventListener('pointerdown', outside); document.addEventListener('keydown', escape);
        return () => {document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape);};
    }, []);
    const visible = Object.entries(options).filter(([, option]) => option.toLocaleLowerCase().includes(query.toLocaleLowerCase()));
    const selectedLabels = selected.map(value => options[value]).filter(Boolean);
    const summary = selectedLabels.length > 1 ? `${selectedLabels[0]} +${selectedLabels.length - 1}` : selectedLabels[0] || anyLabel;
    const toggle = (value: string) => onChange(selected.includes(value) ? selected.filter(item => item !== value) : [...selected, value]);
    return <details ref={root} className="multi-filter"><summary><span>{label}</span><strong>{summary}</strong><ChevronDown size={16}/></summary><div className="multi-filter-menu"><label className="multi-filter-search"><Search size={15}/><input value={query} onChange={event => setQuery(event.target.value)} placeholder={t.search_short} aria-label={`${t.search_short}: ${label}`}/></label><div className="multi-filter-options" role="listbox" aria-multiselectable="true">{visible.map(([value, option]) => <button type="button" role="option" aria-selected={selected.includes(value)} key={value} onClick={() => toggle(value)}><Check size={15}/><span>{option}</span></button>)}{!visible.length && <span className="multi-filter-empty">{t.empty}</span>}</div>{selected.length > 0 && <button type="button" className="multi-filter-clear" onClick={() => onChange([])}>{t.clear}</button>}</div></details>;
}

const selectedValues = (value?: string) => (value || '').split(',').map(item => item.trim()).filter(Boolean);

export default function Collaborations({openings, roles, availability, formats, filters}: {openings: Pagination<Opening>; roles: Record<string, string>; availability: Record<string, string>; formats: Record<string, string>; filters: Record<string, string>}) {
    const {copy: t, auth} = useShared();
    const collaboratorWords = t.available_collaborators.trim().split(/\s+/); const collaboratorTail = collaboratorWords.pop();
    const searchTimer = useRef<number | undefined>(undefined);
    const liveFilters = useRef({...filters}); const [query, setQuery] = useState(filters.q || '');
    const [choices, setChoices] = useState({role: selectedValues(filters.role), availability: selectedValues(filters.availability), format: selectedValues(filters.format)});
    useEffect(() => {liveFilters.current = {...filters}; setQuery(filters.q || ''); setChoices({role: selectedValues(filters.role), availability: selectedValues(filters.availability), format: selectedValues(filters.format)});}, [filters.q, filters.role, filters.availability, filters.format, filters.scope]);
    const apply = (change: Record<string, string>, delay = 0) => {const next = {...liveFilters.current, ...change}; Object.keys(next).forEach(key => {if (!next[key]) delete next[key];}); liveFilters.current = next; window.clearTimeout(searchTimer.current); searchTimer.current = window.setTimeout(() => router.get('/collaboration', next, {preserveState: true, preserveScroll: true, replace: true}), delay);};
    const choose = (field: keyof typeof choices, values: string[]) => {setChoices(current => ({...current, [field]: values})); apply({[field]: values.join(',')});};
    const clear = () => {setQuery(''); setChoices({role: [], availability: [], format: []}); apply({q: '', role: '', availability: '', format: ''});};
    const hasFilters = Boolean(query || choices.role.length || choices.availability.length || choices.format.length);
    return <section className="collaboration-page"><Head title={t.help}/><header className="collaboration-header"><div className="page-heading"><div><h1>{t.help}</h1><p>{t.new_help_hint}</p></div><Link className="button primary" href="/collaboration/create"><Plus size={17}/>{t.new_help}</Link></div>
        <div className="collaboration-intro"><p><strong>{t.want_to_help}</strong> {t.want_to_help_hint}</p><Link className="text-link" href="/people">{collaboratorWords.join(' ')} <span>{collaboratorTail} →</span></Link></div>
        {auth.user && <nav className="collaboration-scopes" aria-label={t.collaboration_scope}>{[['', t.all_requests], ['mine', t.my_requests], ['applied', t.my_replies]].map(([value, label]) => <Link key={value} className={(filters.scope || '') === value ? 'active' : ''} href={`/collaboration?${new URLSearchParams({...filters, scope: value})}`}>{label}</Link>)}</nav>}</header>
        <div className="collaboration-toolbar"><div className="collaboration-search-row"><label className="collaboration-search"><Search size={17}/><input value={query} placeholder={t.collaboration_search_placeholder} onChange={event => {setQuery(event.currentTarget.value); apply({q: event.currentTarget.value}, 260);}}/></label>{hasFilters && <button type="button" className="text-button" onClick={clear}>{t.clear}</button>}</div><div className="collaboration-filters"><MultiFilter label={t.role} anyLabel={t.any_role} options={roles} selected={choices.role} onChange={values => choose('role', values)}/><MultiFilter label={t.commitment} anyLabel={t.any_availability} options={availability} selected={choices.availability} onChange={values => choose('availability', values)}/><MultiFilter label={t.format} anyLabel={t.any_format} options={formats} selected={choices.format} onChange={values => choose('format', values)}/></div></div>
        <InfinitePage data="openings" className="collaboration-list">{openings.data.map(opening => <article className="job-opening" key={opening.id}><div className="job-opening-main"><div className="job-opening-kicker"><span>{roles[opening.role] || opening.role.replace(/-/g, ' ')}</span><span className="meta">{opening.open ? t.open : t.closed} · {date(opening.date)}</span></div><Link href={`/collaboration/${opening.id}`}><h2>{opening.title}</h2></Link><p>{opening.summary}</p>{opening.skills.length > 0 && <div className="job-skills"><small>{t.help_needed}</small>{opening.skills.slice(0, 6).map(skill => <span key={skill}>{skill}</span>)}</div>}<div className="job-opening-facts"><span>{availability[opening.availability] || opening.availability.replace(/-/g, ' ')}</span><span>{formats[opening.format] || opening.format}</span><span>{opening.applications} {t.replies}</span>{opening.project && <span>{opening.project.title}</span>}</div></div><footer><PersonLine person={opening.author}/><Link className="button small" href={`/collaboration/${opening.id}`}>{opening.author.id === auth.user?.id ? t.view_request : t.offer_help}<ArrowUpRight size={15}/></Link></footer></article>)}</InfinitePage>{!openings.data.length && <Empty action={<Link className="button" href="/collaboration/create">{t.new_help}</Link>}>{t.empty}</Empty>}
    </section>;
}
