import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import { Errors, Form, SelectMenu, useShared } from '../components';

type OpeningDraft = {id: number; title: string; role: string; summary: string; availability: string; format: string; skills: string[]};

export default function HelpEditor({roles, availability, formats, projects, projectId, opening}: {roles: Record<string, string>; availability: Record<string, string>; formats: Record<string, string>; projects: {id: number; title: string}[]; projectId: string; opening?: OpeningDraft | null}) {
    const {copy: t} = useShared();
    return <section className="form-page narrow"><Head title={t.new_help}/><Link className="back-link" href="/collaboration"><ArrowLeft size={16}/>{t.help}</Link><h1>{t.new_help}</h1><p className="lead">{t.new_help_hint}</p><Errors/>
        <Form action={opening ? `/collaboration/${opening.id}` : '/collaboration'} method={opening ? 'patch' : 'post'} className="stack"><input type="text" name="website" tabIndex={-1} autoComplete="off" className="honeypot"/>
            <SelectMenu name="role" label={t.role} defaultValue={opening?.role || '3d-artist'} options={Object.entries(roles).map(([value, label]) => ({value, label}))}/>
            <label>{t.task}<textarea name="summary" required minLength={2} maxLength={2000} rows={6} placeholder={t.task_placeholder} defaultValue={opening?.summary || ''}/></label>
            <div className="two-fields"><SelectMenu name="availability" label={t.commitment} defaultValue={opening?.availability || 'one-time'} options={Object.entries(availability).map(([value, label]) => ({value, label}))}/><SelectMenu name="format" label={t.format} defaultValue={opening?.format || 'remote'} options={Object.entries(formats).map(([value, label]) => ({value, label}))}/></div>
            <SelectMenu name="post_id" label={t.optional_project} defaultValue={projectId} options={[{value: '', label: t.no_project}, ...projects.map(project => ({value: String(project.id), label: project.title}))]}/>
            <details open={Boolean(opening)}><summary><span>{t.optional_details}</span><ChevronDown size={17}/></summary><label>Title<input name="title" maxLength={120} defaultValue={opening?.title || ''}/></label><label>{t.skills}<input name="skills" maxLength={400} defaultValue={opening?.skills?.join(', ') || ''}/></label><input type="hidden" name="expires_in_days" value="30"/></details>
            <button className="button primary">{opening ? t.save_changes : t.publish}</button>
        </Form>
    </section>;
}
