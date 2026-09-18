import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowDown, ArrowLeft, ArrowUp, ChevronLeft, ChevronRight, Flag, Layers, Pencil, Plus, Send, Trash2 } from 'lucide-react';
import { Avatar, Dialog, Empty, Errors, Form, InfinitePage, OpeningCard, PersonLine, Prose, SelectMenu, WorkActions, date, jsonAction, path, useShared } from '../components';
import type { Comment, Opening, Pagination, Person, Work as WorkType } from '../types';

type Detail = WorkType & {html: string; gallery: string[]; following: boolean; external_url: string | null; repository_url: string | null; license: string; is_hidden: boolean; moderation_status: string; attachments: {id: number; original_name: string; kind: string; path: string}[]};
type Update = {id: number; title: string; user_id: number; author: Person; html: string; date: string; is_hidden: boolean};
type Member = {id: number; can_edit: boolean; role: string; user: Person};

function threadComments(comments: Comment[]) {
    const used = new Set<number>();
    const children = new Map<number, Comment[]>();
    comments.filter(comment => comment.parent_id).forEach(comment => {
        const target = comment.reply_to_id || comment.parent_id!;
        children.set(target, [...(children.get(target) || []), comment]);
    });
    const ordered: Comment[] = [];
    const append = (comment: Comment) => {
        if (used.has(comment.id)) return;
        used.add(comment.id); ordered.push(comment);
        (children.get(comment.id) || []).forEach(append);
    };
    comments.filter(comment => !comment.parent_id).forEach(append);
    comments.filter(comment => !used.has(comment.id)).forEach(append);
    return ordered;
}

function AnswerVote({comment}: {comment: Comment}) {
    const {auth, copy: t} = useShared();
    const [score, setScore] = useState(comment.score);
    const [vote, setVote] = useState(comment.vote);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const submit = async (value: -1 | 1) => {
        if (!auth.user) {router.visit('/login'); return;}
        if (busy) return;
        setBusy(true); setError('');
        const previous = {score, vote};
        const nextVote = vote === value ? 0 : value;
        setVote(nextVote); setScore(score + nextVote - vote);
        try {
            const result = await jsonAction(`/comments/${comment.id}/vote`, {value}, 'PUT');
            setScore(Number(result.score)); setVote(Number(result.vote) as -1 | 0 | 1);
        } catch (exception) {setScore(previous.score); setVote(previous.vote); setError((exception as Error).message);} finally {setBusy(false);}
    };
    return <div className="answer-vote"><button type="button" aria-label={t.good_answer} aria-pressed={vote === 1} aria-busy={busy} onClick={() => void submit(1)}><ArrowUp size={20}/></button><strong>{score}</strong><button type="button" aria-label={t.bad_answer} aria-pressed={vote === -1} aria-busy={busy} onClick={() => void submit(-1)}><ArrowDown size={20}/></button>{error && <span className="sr-only" role="alert">{error}</span>}</div>;
}

function CommentComposer({work, parent, onDone}: {work: Detail; parent?: Comment; onDone: () => void}) {
    const {auth, copy: t} = useShared();
    if (!auth.user) return null;
    const inputId = parent ? `comment-body-${parent.id}` : 'comment-body-new';
    return <div className={`comment-composer${parent ? ' inline-reply' : ''}`}><Avatar person={auth.user}/><Form json reloadData="comments" scrollToResult={parent ? `comment-${parent.id}` : true} action={work.type === 'question' ? `/questions/${work.slug}/comments` : `/projects/${work.slug}/comments`} reset onSuccess={onDone} className="comment-form">{parent && <div className="replying-chip">{t.replying}: {parent.author.name}<button className="text-button" type="button" onClick={onDone}>{t.cancel}</button></div>}<input type="hidden" name="parent_id" value={parent?.id ?? ''}/><label className="sr-only" htmlFor={inputId}>{t.comment}</label><textarea id={inputId} name="body" required maxLength={2000} rows={parent ? 2 : 3} placeholder={t.comment_placeholder}/><div className="comment-form-actions"><span>{t.comment_hint}</span><button className="comment-send" aria-label={t.send} title={t.send}><Send size={18}/></button></div></Form></div>;
}

function CommentEditor({comment, onDone}: {comment: Comment; onDone: () => void}) {
    const {copy: t} = useShared();
    return <Form json reloadData="comments" action={`/comments/${comment.id}`} method="patch" onSuccess={onDone} className="comment-edit-form"><textarea name="body" required maxLength={2000} rows={3} defaultValue={comment.body} autoFocus/><div className="button-row"><button className="button small">{t.save}</button><button type="button" className="text-button" onClick={onDone}>{t.cancel}</button></div></Form>;
}

function ImageViewer({images, index, setIndex, open, close, title}: {images: string[]; index: number; setIndex: (index: number) => void; open: boolean; close: () => void; title: string}) {
    const {copy: t} = useShared();
    useEffect(() => {
        if (!open) return;
        const keydown = (event: KeyboardEvent) => {
            if (event.key === 'ArrowLeft') setIndex(Math.max(0, index - 1));
            if (event.key === 'ArrowRight') setIndex(Math.min(images.length - 1, index + 1));
        };
        document.addEventListener('keydown', keydown);
        return () => document.removeEventListener('keydown', keydown);
    }, [open, index, images.length]);
    return <Dialog title={`${title} · ${index + 1}/${images.length}`} open={open} close={close}><div className="media-viewer"><div className="media-viewer-frame"><img src={images[index]} alt={`${title} — ${index + 1}`}/>{images.length > 1 && <><button className="media-control previous" type="button" disabled={index === 0} onClick={() => setIndex(Math.max(0, index - 1))} aria-label={t.previous}><ChevronLeft/></button><button className="media-control next" type="button" disabled={index === images.length - 1} onClick={() => setIndex(Math.min(images.length - 1, index + 1))} aria-label={t.next}><ChevronRight/></button></>}</div>{images.length > 1 && <div className="media-thumbs">{images.map((image, imageIndex) => <button type="button" key={`${image}-${imageIndex}`} className={index === imageIndex ? 'active' : ''} onClick={() => setIndex(imageIndex)} aria-label={`${t.image} ${imageIndex + 1}`}><img src={image} alt=""/></button>)}</div>}</div></Dialog>;
}

function MediaGallery({images, title}: {images: string[]; title: string}) {
    const {copy: t} = useShared();
    const [index, setIndex] = useState(0); const [viewer, setViewer] = useState(false);
    if (!images.length) return null;
    const sources = images.map(path);
    return <><div className="media-gallery"><button type="button" className="media-gallery-frame" onClick={() => setViewer(true)} aria-label={t.open_image}><img src={sources[index]} alt={`${title} — ${index + 1}`}/></button>{sources.length > 1 && <><button className="media-control previous" type="button" disabled={index === 0} onClick={() => setIndex(Math.max(0, index - 1))} aria-label={t.previous}><ChevronLeft/></button><button className="media-control next" type="button" disabled={index === sources.length - 1} onClick={() => setIndex(Math.min(sources.length - 1, index + 1))} aria-label={t.next}><ChevronRight/></button><div className="media-gallery-count">{index + 1} / {sources.length}</div><div className="media-dots">{sources.map((_, imageIndex) => <button type="button" key={imageIndex} className={index === imageIndex ? 'active' : ''} onClick={() => setIndex(imageIndex)} aria-label={`${t.image} ${imageIndex + 1}`}/>)}</div></>}</div><ImageViewer images={sources} index={index} setIndex={setIndex} open={viewer} close={() => setViewer(false)} title={title}/></>;
}

export default function Work({work, comments, updates, canEdit, isOwner, members, openings, partners, related}: {work: Detail; comments: Pagination<Comment>; updates: Pagination<Update>; canEdit: boolean; isOwner: boolean; members: Member[]; openings: Opening[]; partners: WorkType[]; related: WorkType[]}) {
    const {copy: t, auth} = useShared();
    const {url} = usePage();
    const [tab, setTab] = useState(work.type === 'question' ? 'about' : new URL(url, 'http://local').searchParams.get('tab') || 'about');
    const [reply, setReply] = useState<Comment | null>(null); const [editingComment, setEditingComment] = useState<number | null>(null); const [report, setReport] = useState(false); const [reportStatus, setReportStatus] = useState(''); const [revealed, setRevealed] = useState(!work.nsfw);
    const [storyImages, setStoryImages] = useState<string[]>([]); const [storyImage, setStoryImage] = useState(0); const [storyViewer, setStoryViewer] = useState(false);
    useEffect(() => {setTab(work.type === 'question' ? 'about' : window.location.hash.startsWith('#update') ? 'updates' : new URL(url, 'http://local').searchParams.get('tab') || 'about');}, [url, work.type]);
    useEffect(() => {if(window.location.hash.startsWith('#update')) document.getElementById(window.location.hash.slice(1))?.scrollIntoView();}, [tab]);
    useEffect(() => {if (reply) document.getElementById(`comment-body-${reply.id}`)?.focus();}, [reply]);
    const reportWork = async (event: React.FormEvent<HTMLFormElement>) => {event.preventDefault(); const data = new FormData(event.currentTarget); try {await jsonAction('/reports', {content_type: work.type, content_id: String(work.id), reason: data.get('reason'), details: data.get('details')}); setReport(false); setReportStatus('Report sent.');} catch(error) {setReportStatus((error as Error).message);}};
    const discussion = <section id={work.type === 'question' ? 'answers' : undefined} className={`discussion${work.type === 'question' ? ' question-answers' : ''}`}><h2>{work.type === 'question' ? `${t.answers} · ${comments.total}` : t.discussion}</h2>{work.type !== 'question' && auth.user && !reply ? <CommentComposer work={work} onDone={() => setReply(null)}/> : null}
        {!comments.data.length && <Empty>{t.no_comments}</Empty>}<InfinitePage data="comments">{threadComments(comments.data).map(comment => <article id={`comment-${comment.id}`} className={`comment${comment.parent_id ? ' is-reply' : ''}${work.type === 'question' && !comment.parent_id ? ' answer' : ''}`} key={comment.id}>{work.type === 'question' && !comment.parent_id && <AnswerVote comment={comment}/>}<div className="comment-content"><PersonLine person={comment.author} detail={date(comment.date)}/>{comment.reply_to && <span className="reply-context">{t.replying}: <Link href={`/profile/${comment.reply_to.slug}`}>{comment.reply_to.name}</Link></span>}{editingComment === comment.id ? <CommentEditor comment={comment} onDone={() => setEditingComment(null)}/> : <p>{comment.body}</p>}<div className="comment-actions">{auth.user && <button className="text-button" onClick={() => setReply(comment)}>{t.reply}</button>}{comment.can_edit && <button className="text-button" onClick={() => setEditingComment(comment.id)}><Pencil size={14}/>{t.edit}</button>}{comment.can_delete && <Form json reloadData="comments" action={`/comments/${comment.id}`} method="delete" confirmMessage={t.confirm_delete}><button className="text-button danger"><Trash2 size={14}/>{t.delete}</button></Form>}</div>{reply?.id === comment.id && <CommentComposer work={work} parent={comment} onDone={() => setReply(null)}/>}</div></article>)}</InfinitePage>
        {work.type === 'question' && auth.user && !reply && <CommentComposer work={work} onDone={() => setReply(null)}/>}
    </section>;
    return <><Head title={work.title}/><div className={`work-page${work.is_project ? ' project-page' : ''}`}>
        <Link className="back-link" href="/"><ArrowLeft size={16}/>{t.feed}</Link>
        <header className={`work-header${work.is_project ? ' project-header' : ''}`} data-category={work.category}>{work.is_project && <div className="project-mark">{work.gallery[0] ? <img src={path(work.gallery[0])} alt=""/> : <Layers size={42}/>}</div>}<div className="project-header-copy"><PersonLine person={work.author} detail={`${work.is_project ? t.project : t.work} · ${date(work.date)}`}/><h1>{work.title}</h1>{work.subtitle && <p className="lead">{work.subtitle}</p>}
            <div className="work-toolbar"><span className="feedback-label">{t[work.feedback_mode === 'feedback' ? 'feedback' : work.feedback_mode === 'help' ? 'help_mode' : 'sharing']}</span>
                <div className="button-row">{canEdit && <Link className="button small" href={`/posts/${work.slug}/edit`}>{t.edit}</Link>}
                    {work.is_project && auth.user && <Form action={`/projects/${work.id}/follow`} method="put"><input type="hidden" name="following" value={work.following ? '0' : '1'}/><button className="button small">{work.following ? t.unfollow : t.follow}</button></Form>}
                    {!isOwner && auth.user && <button className="icon-button" onClick={() => setReport(true)} aria-label={t.report}><Flag size={17}/></button>}
                </div>
            </div>{(work.visibility === 'draft' || work.is_hidden) && <p className="notice">{work.visibility === 'draft' ? t.draft : t.hidden}</p>}</div>
        </header>
        {isOwner && <Form action={`/posts/${work.id}`} method="delete" className="work-delete" confirmMessage={t.confirm_delete}><button className="text-button danger"><Trash2 size={15}/>{t.delete}</button></Form>}
        {work.type !== 'question' && <nav className="section-tabs" aria-label="Project sections">{[['about', t.about], ...(work.is_project ? [['updates', `${t.journal} · ${updates.total}`]] : []), ['discussion', `${t.discussion} · ${comments.total}`], ...(work.is_project ? [['team', `${t.team} · ${members.length + 1}`]] : [])].map(([key, label]) => <Link key={key} href={`${work.url}?tab=${key}`} className={tab === key ? 'active' : ''} preserveScroll>{label}</Link>)}</nav>}
        <Errors/>{reportStatus && <p role="status">{reportStatus}</p>}<div className={work.is_project ? 'project-layout' : ''}><main className="project-main">
        {work.nsfw && !revealed ? <button className="sensitive" onClick={() => setRevealed(true)}>Sensitive content · show</button> : <>
        {tab === 'about' && <section className="work-story" onClick={event => {const image = (event.target as HTMLElement).closest<HTMLImageElement>('.prose img'); if (!image) return; const images = Array.from(event.currentTarget.querySelectorAll<HTMLImageElement>('.prose img')); event.preventDefault(); setStoryImages(images.map(item => item.currentSrc || item.src)); setStoryImage(Math.max(0, images.indexOf(image))); setStoryViewer(true);}}><MediaGallery images={work.gallery} title={work.title}/>
            <Prose html={work.html}/>{storyImages.length > 0 && <ImageViewer images={storyImages} index={storyImage} setIndex={setStoryImage} open={storyViewer} close={() => setStoryViewer(false)} title={work.title}/>} 
            {work.attachments.length > 0 && <div className="attachments">{work.attachments.map(a => <div key={a.id}><a href={path(a.path)} target="_blank" rel="noreferrer">{a.original_name}</a>{a.kind === 'audio' && <audio controls preload="metadata" src={path(a.path)}/>} {a.kind === 'video' && <video controls preload="metadata" src={path(a.path)}/>}</div>)}</div>}
            <div className="button-row">{work.external_url && <a className="button" href={work.external_url} target="_blank" rel="noreferrer">{t.external_url} ↗</a>}{work.repository_url && <a className="button" href={work.repository_url} target="_blank" rel="noreferrer">{t.repository_url} ↗</a>}</div>
            <p className="meta">{t[`${work.feedback_mode === 'help' ? 'help' : work.feedback_mode === 'feedback' ? 'feedback' : 'sharing'}_hint`]}</p><WorkActions work={work}/>
            {work.type === 'question' && discussion}
            {isOwner && !work.is_project && work.type === 'post' && <div className="continue-project"><h2>{t.start_project}</h2><p>{t.start_project_hint}</p><Form action={`/projects/${work.id}/start`}><button className="button">{t.start_project}</button></Form></div>}
            {!!related.length && <div className="related-list"><h2>{t.related}</h2><div className="related-cards">{related.map(item => <Link key={item.id} href={item.url} data-category={item.category}>{item.cover && <img src={path(item.cover)} alt=""/>}<span><strong>{item.title}</strong><small>{item.author.name}</small></span></Link>)}</div></div>}
        </section>}
        {tab === 'updates' && <section className="journal"><div className="section-heading"><h2>{t.journal}</h2>{canEdit && <Link className="button primary" href={`/projects/${work.slug}/updates/create`}><Plus size={17}/>{t.write_update}</Link>}</div>
            <InfinitePage data="updates">{updates.data.map(update => <article className="journal-entry" id={`update-${update.id}`} key={update.id}><PersonLine person={update.author} detail={date(update.date)}/><h2>{update.title}</h2>{update.is_hidden && <p className="notice">{t.hidden}</p>}<Prose html={update.html}/>{canEdit && (isOwner || auth.user?.id === update.user_id) && <div className="button-row"><Link className="button small" href={`/projects/${work.slug}/updates/${update.id}/edit`}>{t.edit}</Link><Form action={`/projects/${work.slug}/updates/${update.id}`} method="delete" confirmMessage={t.confirm_delete}><button className="text-button danger">{t.delete}</button></Form></div>}</article>)}</InfinitePage>
            {!updates.data.length && <Empty>{t.no_updates}</Empty>}
        </section>}
        {work.type !== 'question' && tab === 'discussion' && discussion}
        {tab === 'team' && <section><div className="section-heading"><h2>{t.team}</h2>{isOwner && <Link className="button primary" href={`/collaboration/create?project=${work.id}`}>{t.find_help}</Link>}</div><p className="meta">{t.team_hint}</p><div className="member"><PersonLine person={work.author}/><span>{t.owner}</span></div>{members.map(member => <div className="member" key={member.id}><PersonLine person={member.user} detail={member.role}/><span className="meta">{member.can_edit ? t.can_edit : t.readonly}</span>{isOwner && <Form action={`/projects/${work.id}/members/${member.id}`} method="patch"><input type="hidden" name="can_edit" value={member.can_edit ? '0' : '1'}/><button className="button small">{member.can_edit ? t.revoke_edit : t.grant_edit}</button></Form>}{(isOwner || member.user.id === auth.user?.id) && <Form action={`/projects/${work.id}/members/${member.id}`} method="delete" confirmMessage={t.confirm_delete}><button className="text-button danger">{t.leave}</button></Form>}</div>)}{!!partners.length && <div className="partner-list"><h3>{t.partner_projects}</h3>{partners.map(partner => <Link key={partner.id} href={partner.url}><span>{partner.title}</span><span className="meta">{partner.author.name}</span></Link>)}</div>}{openings.map(opening => <OpeningCard key={opening.id} opening={opening}/>)}</section>}
        </>}</main>{work.is_project && <aside className="project-sidebar"><section><h2>{t.project_details}</h2><dl><div><dt>{t.status}</dt><dd>{t[work.status] || work.status}</dd></div><div><dt>{t.category}</dt><dd>{work.category}</dd></div><div><dt>{t.license}</dt><dd>{work.license}</dd></div><div><dt>{t.team}</dt><dd>{members.length + 1}</dd></div></dl></section><section><h2>{t.owner}</h2><PersonLine person={work.author}/></section>{work.tags.length > 0 && <section><h2>{t.tags}</h2><div className="project-tags">{work.tags.map(tag => <Link key={tag} href={`/?tags=${encodeURIComponent(tag)}`}>#{tag}</Link>)}</div></section>}{(work.external_url || work.repository_url) && <section><h2>{t.links_files}</h2>{work.external_url && <a className="project-link" href={work.external_url} target="_blank" rel="noreferrer">{t.external_url} ↗</a>}{work.repository_url && <a className="project-link" href={work.repository_url} target="_blank" rel="noreferrer">{t.repository_url} ↗</a>}</section>}{openings.length > 0 && <section><h2>{t.help_requests}</h2>{openings.slice(0, 3).map(opening => <OpeningCard key={opening.id} opening={opening} compact/>)}</section>}</aside>}</div>
    </div><Dialog title={t.report} open={report} close={() => setReport(false)}><form onSubmit={reportWork} className="stack"><p>{t.report_hint}</p><SelectMenu name="reason" label={t.reason} defaultValue="spam" options={[{value: 'spam', label: 'Spam'}, {value: 'abuse', label: 'Abuse'}, {value: 'offtopic', label: 'Off topic'}, {value: 'other', label: 'Other'}]}/><textarea name="details" maxLength={1000} rows={4} aria-label={t.details}/><button className="button primary">{t.send}</button></form></Dialog></>;
}
