import { Head, Link } from '@inertiajs/react';
import { Errors, Form, InfinitePage, PersonLine, Prose, useShared } from '../components';
import type { Pagination, Work } from '../types';

type ModerationWork = Work & {html: string; moderation_status: string};
export default function Moderation({works, all}: {works: Pagination<ModerationWork>; all: boolean}) {
    const {copy: t, auth} = useShared();
    return <section className="moderation-page"><Head title={t.moderation}/><div className="page-heading"><h1>{t.moderation}</h1><a className="button small" href="/admin/tools">{t.admin_tools}</a></div><nav className="feed-tabs"><Link className={!all ? 'active' : ''} href="/admin">{t.queue}</Link><Link className={all ? 'active' : ''} href="/admin?filter=all">{t.history}</Link></nav><Errors/>
        <InfinitePage data="works" className="moderation-list">{works.data.map(work => { const hiding = work.moderation_status === 'approved'; return <article key={work.id}><div className="moderation-head"><PersonLine person={work.author}/><span className="state-label">{work.moderation_status}</span></div><Link href={work.url}><h2>{work.title}</h2></Link><Prose html={work.html}/><Form json reloadData="works" action={`/admin/moderation/posts/${work.id}/${hiding ? 'hide' : 'restore'}`} className="stack"><label>{t.reason}<input name="reason" maxLength={500} required={hiding} defaultValue={hiding ? 'Violates community guidelines' : ''}/></label><div className="button-row"><button className={`button small ${hiding ? 'danger' : 'primary'}`}>{hiding ? t.hide : t.restore}</button></div></Form></article>; })}</InfinitePage>
        {!works.data.length && <p className="empty">{t.no_queue}</p>}{auth.user?.moderator && <p className="meta">{t.check_next}</p>}
    </section>;
}
