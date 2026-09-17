import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type CSSProperties, type ReactNode, type SyntheticEvent } from 'react';
import { Check, ExternalLink, GitFork, Pencil, Plus, Search, Send, Settings, Trash2, X } from 'lucide-react';
import { Avatar, Empty, Form, InfinitePage, OpeningCard, PersonLine, ProfileBanner, Prose, WorkCard, date, path, useShared } from '../components';
import type { Badge, Opening, Pagination, Person, WallPost, Work } from '../types';

type ProfilePerson = Person & {featured_post_id: number | null; is_banned: boolean; allow_follow: boolean; following: boolean; wall_mode: 'everyone' | 'owner'};
type InlineFieldProps = {field: 'name' | 'bio' | 'skills' | 'portfolio_url' | 'open_to_help'; value: string; label: string; placeholder: string; personName: string; kind?: 'input' | 'textarea' | 'url' | 'checkbox'; className?: string; icon?: ReactNode};

function InlineField({field, value, label, placeholder, personName, kind = 'input', className = '', icon}: InlineFieldProps) {
    const {copy: t} = useShared();
    const [editing, setEditing] = useState(false);
    if (!editing) return <button type="button" className={`inline-profile-value ${className}`} onClick={() => setEditing(true)} aria-label={`${t.edit} ${label}`}>{icon}<span>{kind === 'checkbox' ? placeholder : value || placeholder}</span><Pencil className="inline-edit-icon" size={14}/></button>;
    return <Form action="/profile/settings" className="inline-profile-form" onSuccess={() => setEditing(false)}><input type="hidden" name="section" value="profile"/><input type="hidden" name="return_to_profile" value="1"/>{field !== 'name' && <input type="hidden" name="name" value={personName}/>} {kind === 'textarea' ? <textarea name={field} rows={3} maxLength={1000} defaultValue={value} aria-label={label} autoFocus/> : kind === 'checkbox' ? <><input type="hidden" name={field} value="0"/><label className="check"><input type="checkbox" name={field} value="1" defaultChecked={value === '1'}/>{label}</label></> : <input name={field} type={kind === 'url' ? 'url' : 'text'} required={field === 'name'} maxLength={field === 'skills' ? 400 : field === 'portfolio_url' ? 500 : 255} defaultValue={value} placeholder={kind === 'url' ? 'https://' : ''} aria-label={label} autoFocus/>}<div className="inline-profile-actions"><button className="icon-button" aria-label={t.save}><Check size={17}/></button><button type="button" className="icon-button" onClick={() => setEditing(false)} aria-label={t.cancel}><X size={17}/></button></div></Form>;
}

function setBadgeGlow(event: SyntheticEvent<HTMLImageElement>) {
    const image = event.currentTarget;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 24;
    const context = canvas.getContext('2d', {willReadFrequently: true});
    if (!context) return;

    try {
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
        let red = 0; let green = 0; let blue = 0; let weight = 0;
        for (let index = 0; index < pixels.length; index += 4) {
            const alpha = pixels[index + 3] / 255;
            if (alpha < .1) continue;
            red += pixels[index] * alpha;
            green += pixels[index + 1] * alpha;
            blue += pixels[index + 2] * alpha;
            weight += alpha;
        }
        if (weight > 0) image.closest<HTMLElement>('.profile-badge')?.style.setProperty('--badge-glow', `rgb(${Math.round(red / weight)} ${Math.round(green / weight)} ${Math.round(blue / weight)})`);
    } catch {
        // The accent fallback remains if an icon is served from another origin.
    }
}

function Badges({badges}: {badges: Badge[]}) {
    const {copy: t} = useShared(); const [selected, setSelected] = useState<Badge | null>(null); const [glow, setGlow] = useState('rgb(214 139 119)');
    if (!badges.length) return null;
    return <><div className="profile-badges" aria-label={t.badges}>{badges.map(badge => <button type="button" className="profile-badge" key={badge.id} data-tooltip={[badge.label, badge.reason || badge.description].filter(Boolean).join(' — ')} aria-label={badge.label} onClick={event => {setGlow(event.currentTarget.style.getPropertyValue('--badge-glow') || 'rgb(214 139 119)'); setSelected(badge);}}>{badge.icon && <img src={badge.icon} alt="" onLoad={setBadgeGlow}/>}</button>)}</div><BadgeView badge={selected} glow={glow} close={() => setSelected(null)} issuedLabel={t.badge_issued}/></>;
}

function BadgeView({badge, glow, close, issuedLabel}: {badge: Badge | null; glow: string; close: () => void; issuedLabel: string}) {
    const ref = useRef<HTMLDialogElement>(null);
    useEffect(() => {if (badge) ref.current?.showModal(); else ref.current?.close();}, [badge]);
    return <dialog ref={ref} className="badge-view-dialog" onCancel={close} onClick={event => {if (event.target === ref.current) close();}} aria-label={badge?.label || ''}>{badge && <div className="badge-view-card is-glow-ready" style={{'--badge-view-glow': glow} as CSSProperties}><button type="button" className="icon-button badge-view-card__close" onClick={close} aria-label="Close"><X size={20}/></button><div className="badge-view-card__media"><div className="badge-view-card__glow" aria-hidden/><div className="badge-view-card__burst" key={badge.id} aria-hidden>{Array.from({length: 16}, (_, index) => {const angle = index * Math.PI / 8; const distance = 54 + index % 4 * 14; return <img key={index} className="badge-view-card__star" src="/images/star.svg" alt="" style={{width: 8 + index % 5 * 2, height: 8 + index % 5 * 2, '--burst-x': `${Math.cos(angle) * distance}px`, '--burst-y': `${Math.sin(angle) * distance}px`, '--burst-rotate': `${index % 2 ? 140 : -140}deg`, '--burst-delay': `${index % 4 * 28}ms`} as CSSProperties}/>;})}</div>{badge.icon && <img className="badge-view-card__icon" src={badge.icon} alt=""/>}</div><div className="badge-view-card__body"><div className="badge-view-card__title">{badge.label}</div><p className="badge-view-card__desc">{badge.reason || badge.description}</p>{badge.issued_at && <div className="badge-view-card__meta"><span>{issuedLabel}</span><strong>{badge.issued_at}</strong></div>}</div></div>}</dialog>;
}

function WallComposer({person, returnView}: {person: ProfilePerson; returnView: 'work' | 'wall'}) {
    const {auth, copy: t} = useShared();
    if (!auth.user) return null;
    const placeholder = returnView === 'work' ? t.post_placeholder : t.wall_placeholder;
    return <div className="profile-post-composer"><Avatar person={auth.user}/><Form action={`/profile/${person.slug}/wall`} reset><input type="hidden" name="return_view" value={returnView}/><label className="sr-only" htmlFor={`wall-post-${returnView}`}>{placeholder}</label><textarea id={`wall-post-${returnView}`} name="body" required maxLength={2000} rows={1} placeholder={placeholder}/><div className="profile-post-actions"><button className="comment-send" aria-label={t.send} title={t.send}><Send size={18}/></button></div></Form></div>;
}

function WallEntry({post, anchored = false}: {post: WallPost; anchored?: boolean}) {
    const {copy: t} = useShared(); const [editing, setEditing] = useState(false);
    return <article id={anchored ? `wall-post-${post.id}` : undefined} className="profile-feed-post"><PersonLine person={post.author} detail={date(post.date)}/>{editing ? <Form action={`/profile/wall/${post.id}`} method="patch" onSuccess={() => setEditing(false)} className="wall-edit-form"><textarea name="body" required maxLength={2000} rows={3} defaultValue={post.body} autoFocus/><div className="button-row"><button className="button small">{t.save}</button><button type="button" className="text-button" onClick={() => setEditing(false)}>{t.cancel}</button></div></Form> : <p>{post.body}</p>}<div className="wall-entry-actions">{post.can_edit && <button type="button" className="text-button" onClick={() => setEditing(true)}><Pencil size={14}/>{t.edit}</button>}{post.can_delete && <Form action={`/profile/wall/${post.id}`} method="delete" confirmMessage={t.confirm_delete}><button className="text-button danger" aria-label={t.delete}><Trash2 size={15}/>{t.delete}</button></Form>}</div></article>;
}

export default function Profile({person, badges, isOwner, works, openings, contributions, workFilter, view, stats, showcase, profileReadmeHtml, profileReadmeSource, wallPosts}: {person: ProfilePerson; badges: Badge[]; isOwner: boolean; works: Pagination<Work>; openings: Opening[]; contributions: Work[]; workFilter: {kind: string; q: string}; view: string; stats: {followers: number; upvotes: number; collaborations: number}; showcase: Work[]; profileReadmeHtml: string; profileReadmeSource: {repository: string; url: string} | null; wallPosts: WallPost[]}) {
    const {copy: t, auth} = useShared(); const searchTimer = useRef<number | undefined>(undefined);
    const mediaEdit = (anchor: string, label: string) => isOwner && <Link className="edit-affordance" href={`/profile/settings#${anchor}`} aria-label={`${t.edit} ${label}`}><Pencil size={18}/></Link>;
    const href = (nextView: string, extra: Record<string, string> = {}) => `/profile/${person.slug}?${new URLSearchParams({view: nextView, ...extra})}`;
    const filterHref = (kind: string) => href('work', {kind, ...(workFilter.q ? {q: workFilter.q} : {})});
    const liveSearch = (value: string) => {window.clearTimeout(searchTimer.current); searchTimer.current = window.setTimeout(() => router.get(`/profile/${person.slug}`, {view: 'work', kind: workFilter.kind, q: value}, {preserveState: true, preserveScroll: true, replace: true}), 260);};
    const canWriteWall = Boolean(auth.user && (isOwner || person.wall_mode === 'everyone'));
    const ownPosts = wallPosts.filter(post => post.author.id === person.id && (!workFilter.q || post.body.toLocaleLowerCase().includes(workFilter.q.toLocaleLowerCase())));
    return <section className="profile-page"><Head title={person.name}/><div className="profile-banner editable-block"><ProfileBanner person={person}/>{mediaEdit('banner', t.banner)}</div>
        <header className="profile-head"><div className="profile-avatar editable-block"><Avatar person={person} large/>{mediaEdit('avatar', t.avatar)}</div><div className="profile-copy"><div className="profile-name-row"><div className="profile-name">{isOwner ? <InlineField field="name" value={person.name} label={t.name} placeholder={t.name} personName={person.name} className="inline-profile-name"/> : <h1>{person.name}</h1>}</div><Badges badges={badges}/></div><div className="profile-bio">{isOwner ? <InlineField field="bio" value={person.bio || ''} label={t.bio} placeholder={t.add_bio} personName={person.name} kind="textarea"/> : person.bio && <p>{person.bio}</p>}</div><div className="profile-skills">{isOwner ? <InlineField field="skills" value={person.skills || ''} label={t.skills} placeholder={t.add_skills} personName={person.name} className="skills"/> : person.skills && <p className="skills">{person.skills}</p>}</div><dl className="profile-stats"><div><dt>{t.followers}</dt><dd>{stats.followers}</dd></div><div><dt>{t.total_upvotes}</dt><dd>{stats.upvotes}</dd></div><div><dt>{t.collaborations_count}</dt><dd>{stats.collaborations}</dd></div></dl></div><aside className="profile-actions">{isOwner ? <InlineField field="portfolio_url" value={person.portfolio_url || ''} label={t.portfolio} placeholder={t.add_portfolio} personName={person.name} kind="url" icon={<ExternalLink size={15}/>}/> : person.portfolio_url && <a className="text-link profile-action-link" href={person.portfolio_url} target="_blank" rel="noreferrer"><ExternalLink size={15}/>{t.portfolio}</a>}{(isOwner || person.open_to_help) && (isOwner ? <InlineField field="open_to_help" value={person.open_to_help ? '1' : '0'} label={t.available} placeholder={person.open_to_help ? t.available : t.not_available} personName={person.name} kind="checkbox"/> : <span className="state-label">{t.available}</span>)}{isOwner ? <Link className="button small" href="/profile/settings"><Settings size={16}/>{t.settings}</Link> : auth.user && person.allow_follow && <Form json reloadData={['person', 'stats']} action={`/profile/${person.slug}/follow`}><button className="button small">{person.following ? t.unfollow_author : t.follow_author}</button></Form>}</aside></header>
        {person.is_banned ? <p className="notice error">{t.profile_unavailable}</p> : <div className="profile-content">{(isOwner || showcase.length > 0 || profileReadmeHtml) && <section className="profile-showcase"><div className="section-heading"><h2>{t.showcase}</h2>{isOwner && <Link className="text-link" href="/profile/settings#showcase">{t.edit}</Link>}</div>{showcase.length > 0 && <div className="showcase-projects">{showcase.map(project => <Link key={project.id} href={project.url}>{project.cover && <img src={path(project.cover)} alt=""/>}<span><strong>{project.title}</strong>{project.subtitle && <small>{project.subtitle}</small>}</span></Link>)}</div>}{profileReadmeHtml && <div className="showcase-readme"><header><strong>README.md</strong>{profileReadmeSource && <a href={profileReadmeSource.url} target="_blank" rel="noreferrer"><GitFork size={15}/>{profileReadmeSource.repository}<ExternalLink size={13}/></a>}</header><Prose html={profileReadmeHtml}/></div>} {!showcase.length && !profileReadmeHtml && <p className="meta">{t.showcase_empty}</p>}</section>}
            <nav className="profile-tabs" aria-label={t.profile_sections}><Link className={view === 'work' ? 'active' : ''} href={href('work', {kind: workFilter.kind})}>{t.my_work}</Link><Link className={view === 'collaborations' ? 'active' : ''} href={href('collaborations')}>{t.help_requests} · {openings.length}</Link><Link className={view === 'wall' ? 'active' : ''} href={href('wall')}>{t.wall}</Link></nav>
            {view === 'work' && <main><div className="profile-section">{isOwner && <WallComposer person={person} returnView="work"/>}<div className="profile-work-tools"><nav className="filter-discovery profile-work-filters" aria-label={t.content_type}>{[['all', t.all_work], ['projects', t.projects], ['works', t.work], ['questions', t.questions], ['posts', t.posts]].map(([kind, label]) => <Link key={kind} href={filterHref(kind)} className={workFilter.kind === kind ? 'active' : ''}>{label}</Link>)}</nav><div className="profile-work-actions"><form onSubmit={event => event.preventDefault()}><input type="hidden" name="kind" value={workFilter.kind}/><Search size={16}/><input name="q" defaultValue={workFilter.q} placeholder={t.search_my_work} aria-label={t.search_my_work} onChange={event => liveSearch(event.currentTarget.value)}/></form>{isOwner && <Link className="button primary small" href="/create"><Plus size={16}/>{t.create}</Link>}</div></div>{['all', 'posts'].includes(workFilter.kind) && ownPosts.map(post => <WallEntry key={post.id} post={post}/>)}{workFilter.kind !== 'posts' && <InfinitePage data="works" className="feed-list">{works.data.map(work => <WorkCard key={work.id} work={work}/>)}</InfinitePage>}{!works.data.length && !(['all', 'posts'].includes(workFilter.kind) && ownPosts.length) && <Empty action={isOwner ? <Link className="button" href="/create">{t.share}</Link> : undefined}>{t.empty}</Empty>}</div></main>}
            {view === 'collaborations' && <section className="profile-section profile-collaborations">{isOwner && <div className="profile-panel-actions"><Link className="button primary small" href="/collaboration/create"><Plus size={16}/>{t.new_help}</Link></div>}{openings.length > 0 ? <div className="profile-openings">{openings.map(opening => <OpeningCard key={opening.id} opening={opening} compact/>)}</div> : <Empty>{t.empty}</Empty>}{contributions.length > 0 && <div className="profile-contributions"><h2>{t.contributions}</h2>{contributions.map(work => <Link key={work.id} href={work.url}><span>{work.title}</span><span className="meta">{work.author.name}</span></Link>)}</div>}</section>}
            {view === 'wall' && <section className="profile-section profile-wall"><p className="profile-wall-mode meta">{person.wall_mode === 'everyone' ? t.wall_everyone : t.wall_owner}</p>{canWriteWall && <WallComposer person={person} returnView="wall"/>}<div className="wall-list">{wallPosts.map(post => <WallEntry key={post.id} post={post} anchored/>)}</div>{!wallPosts.length && <Empty>{t.wall_empty}</Empty>}</section>}
        </div>}
    </section>;
}
