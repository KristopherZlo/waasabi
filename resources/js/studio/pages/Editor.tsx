import { Head, Link, router } from '@inertiajs/react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { ArrowLeft, ChevronDown, Eye, PenLine } from 'lucide-react';
import { Errors, Form, useShared } from '../components';
const RichEditor = lazy(() => import('../RichEditor'));

type Editable = {id: number; slug: string; title: string; subtitle: string | null; body_markdown: string; type: string; is_project: boolean; feedback_mode: string; category: string; tags: string[]; status: string; visibility: string; external_url: string | null; repository_url: string | null; license: string; media_type: string; updated_at: string; nsfw: boolean};
type EditorProps = {post: Editable | null; kind: string; categories: Record<string, string>; mediaTypes: Record<string, string>; licenses: Record<string, string>; journal: {project: {id: number; slug: string; title: string}; update: {id: number; title: string; body: string; updated_at: string} | null} | null};
type Draft = {fields: Record<string, string>; time: number};

export default function EditorPage(props: EditorProps) {return <Editor key={`${props.kind}-${props.post?.id || props.journal?.project.id || 'new'}-${props.journal?.update?.id || ''}`} {...props}/>;}

function Editor({post, kind, categories, mediaTypes, licenses, journal}: EditorProps) {
    const {copy: t, auth, old} = useShared();
    const draftId = journal ? `journal-${journal.project.id}-${journal.update?.id ?? 'new'}` : String(post?.id ?? kind);
    const draftKey = `waasabi:compose:${auth.user?.id}:${draftId}`;
    const [recovered, setRecovered] = useState(false); const [storageError, setStorageError] = useState(false); const saved = useRef(false);
    const defaults: Record<string, string> = {
        title: journal?.update?.title ?? post?.title ?? '', body: journal?.update?.body ?? post?.body_markdown ?? '',
        subtitle: post?.subtitle ?? '', feedback_mode: post?.feedback_mode ?? 'sharing', category: post?.category ?? 'other', tags: post?.tags?.join(', ') ?? '',
        status: post?.status ?? 'in_progress', visibility: post?.visibility === 'unlisted' ? 'unlisted' : 'public', external_url: post?.external_url ?? '', repository_url: post?.repository_url ?? '',
        license: post?.license ?? 'all-rights-reserved', media_type: post?.media_type ?? 'mixed',
    };
    const [fields, setFields] = useState(defaults); const [ready, setReady] = useState(false);
    const fieldsRef = useRef(fields); fieldsRef.current = fields;
    const [preview, setPreview] = useState(false); const [stage, setStage] = useState<'write' | 'details'>('write'); const [html, setHtml] = useState(''); const [uploading, setUploading] = useState(false);
    const [missing, setMissing] = useState<string[]>([]);
    useEffect(() => {
        try {
            const stored = localStorage.getItem(draftKey); const draft = stored ? JSON.parse(stored) as Draft : null;
            const serverTime = new Date(journal?.update?.updated_at ?? post?.updated_at ?? 0).getTime();
            if (old?.title !== undefined) setFields(current => ({...current, ...Object.fromEntries(Object.keys(current).filter(key => old[key] !== undefined).map(key => [key, old[key]]))}));
            else if (draft?.fields && draft.time >= serverTime && Object.values(draft.fields).every(v => typeof v === 'string')) {setFields(current => ({...current, ...draft.fields})); setRecovered(true);}
        } catch {setStorageError(true);} finally {setReady(true);}
    }, [draftKey]);
    const flush = () => {if (!ready || saved.current) return; try {localStorage.setItem(draftKey, JSON.stringify({fields: fieldsRef.current, time: Date.now()}));} catch {setStorageError(true);}};
    useEffect(() => {if (!ready) return; const timer = setTimeout(flush, 600); return () => clearTimeout(timer);}, [fields, ready]);
    useEffect(() => {if (!ready) return; const off = router.on('before', flush); window.addEventListener('pagehide', flush); return () => {off(); window.removeEventListener('pagehide', flush);};}, [ready]);
    const set = (key: string, value: string) => setFields(current => ({...current, [key]: value}));
    const clear = () => {saved.current = true; try {localStorage.removeItem(draftKey);} catch { /* No local copy. */ }};
    const destination = journal ? `/projects/${journal.project.slug}/updates${journal.update ? `/${journal.update.id}` : ''}` : '/publish';
    const back = journal ? `/projects/${journal.project.slug}?tab=updates` : post ? (post.type === 'question' ? `/questions/${post.slug}` : `/projects/${post.slug}`) : '/create';
    const field = (name: string, label: string, type = 'text') => <label>{label}<input name={name} type={type} value={fields[name] ?? ''} onChange={event => set(name, event.target.value)}/></label>;
    const validatePublish = (event: React.MouseEvent<HTMLButtonElement>) => {
        const next = [!fields.title.trim() ? 'title' : '', !fields.body.trim() ? 'body' : ''].filter(Boolean);
        setMissing(next);
        if (!next.length) return;
        event.preventDefault(); setPreview(false); setStage('write');
        window.requestAnimationFrame(() => document.querySelector<HTMLElement>(next[0] === 'title' ? '.title-input' : '.writing-surface')?.focus());
    };
    const ViewIcon = preview ? PenLine : Eye;
    return <><Head title={kind === 'update' ? t.write_update : post ? t.edit : kind === 'project' ? t.new_project : t.new_work}/>
        <div className="editor-page"><Errors/>
        <Form action={destination} method={journal?.update ? 'put' : 'post'} onSuccess={clear} className="compose-form">
            {!journal && <><input type="hidden" name="post_id" value={post?.id ?? ''}/><input type="hidden" name="publish_type" value={post?.type ?? 'post'}/><input type="hidden" name="is_project" value={kind === 'project' ? '1' : '0'}/></>}
            <div className="editor-top"><Link className="back-link" href={back}><ArrowLeft size={16}/>{journal?.project.title ?? t.back}</Link><div className="editor-top-actions"><span className="meta editor-status" role="status">{storageError ? t.draft_unavailable : recovered ? t.draft_recovered : t.saved_local}</span>{stage === 'write' && <button className="text-button editor-view" type="button" onClick={() => setPreview(value => !value)}><ViewIcon size={16}/>{preview ? t.write : t.preview}</button>}{journal ? <button className="button primary" name="publish_action" value="publish" disabled={uploading} onClick={validatePublish}>{journal.update ? t.save_changes : t.publish}</button> : <button className="button primary" type="button" onClick={() => setStage(stage === 'write' ? 'details' : 'write')} disabled={!ready}>{stage === 'write' ? t.continue : t.back_to_editor}</button>}</div></div>
            {!journal && <nav className="editor-tabs" aria-label={t.publish}><button type="button" className={stage === 'write' ? 'active' : ''} onClick={() => setStage('write')}>{t.write}</button><button type="button" className={stage === 'details' ? 'active' : ''} onClick={() => setStage('details')}>{t.details}</button></nav>}
            <section className="compose-paper" hidden={stage !== 'write'}>
                <label className={`title-label${missing.includes('title') ? ' field-missing' : ''}`}><span className="sr-only">{t.title}</span><input className="title-input" name="title" required maxLength={journal ? 120 : 255} placeholder={t.title_placeholder} value={fields.title} onChange={e => {set('title', e.target.value); setMissing(value => value.filter(item => item !== 'title'));}}/></label>
                {!journal && <label className="subtitle-label"><span className="sr-only">Subtitle</span><input name="subtitle" placeholder="A short introduction, if you need one" maxLength={255} value={fields.subtitle} onChange={e => set('subtitle', e.target.value)}/></label>}
                {ready && <><div className={missing.includes('body') ? 'field-missing' : ''} hidden={preview}><Suspense fallback={<p>{t.saving}</p>}><RichEditor initial={fields.body} placeholder={journal ? t.update_placeholder : t.body_placeholder} onBusy={setUploading} onChange={(body, html) => {set('body', body); setHtml(html); if (body.trim()) setMissing(value => value.filter(item => item !== 'body'));}}/></Suspense></div>{preview && <div className="prose writing-preview">{html ? <div dangerouslySetInnerHTML={{__html: html}}/> : <p className="preserve-lines">{fields.body}</p>}</div>}</>}
                <textarea name={post?.type === 'question' ? 'question_body' : 'body'} value={fields.body} readOnly hidden/>
            </section>
            {!journal && <section className="compose-details-page" hidden={stage !== 'details'}><div className="compose-details-head"><h1>{t.details}</h1><p>{t.details_hint}</p></div><div className="compose-settings"><label>{t.response}<select name="feedback_mode" value={fields.feedback_mode} onChange={e => set('feedback_mode', e.target.value)}><option value="sharing">{t.sharing}</option><option value="feedback">{t.feedback}</option><option value="help">{t.help_mode}</option></select></label>
                    <label>{t.cover}<input type="file" name="cover_images[]" accept="image/jpeg,image/png,image/webp" multiple/><span className="meta">{t.cover_hint}</span></label>
                    <details open><summary><span>{t.category} & {t.tags.toLowerCase()}</span><ChevronDown size={17}/></summary><label>{t.category}<select name="category" value={fields.category} onChange={e => set('category', e.target.value)}>{Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label>{t.media_type}<select name="media_type" value={fields.media_type} onChange={e => set('media_type', e.target.value)}>{Object.entries(mediaTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label>{t.license}<select name="license" value={fields.license} onChange={e => set('license', e.target.value)}>{Object.entries(licenses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>{field('tags', t.tags)}</details>
                    <details open><summary><span>{t.links_files}</span><ChevronDown size={17}/></summary>{field('external_url', t.external_url, 'url')}{field('repository_url', t.repository_url, 'url')}<label>{t.attachments}<input type="file" name="attachments[]" multiple accept=".mp3,.wav,.ogg,.flac,.m4a,.mp4,.webm,.mov,.pdf,.zip,.txt,.md"/></label></details>
                    {kind === 'project' && <label>{t.status}<select name="status" value={fields.status} onChange={e => set('status', e.target.value)}>{['in_progress', 'done', 'paused'].map(s => <option key={s} value={s}>{t[s]}</option>)}</select></label>}
                    <label>{t.visibility}<select name="visibility" value={fields.visibility} onChange={e => set('visibility', e.target.value)}><option value="public">{t.public}</option><option value="unlisted">{t.unlisted}</option></select></label>
                    <label className="check"><input type="checkbox" name="nsfw" value="1" defaultChecked={post?.nsfw}/> Sensitive content</label>
                <div className="publish-actions"><button className="button primary" name="publish_action" value="publish" disabled={uploading} onClick={validatePublish}>{post ? t.save_changes : t.publish}</button><button className="button" name="publish_action" value="draft" formNoValidate disabled={uploading || !ready}>{t.save_draft}</button></div>
            </div></section>}
        </Form></div>
    </>;
}
