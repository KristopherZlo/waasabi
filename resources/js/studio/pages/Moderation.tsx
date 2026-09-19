import { Head, Link } from '@inertiajs/react';
import { ChevronDown, EyeOff, Flag, RotateCcw, Settings } from 'lucide-react';
import { date, Errors, Form, InfinitePage, PersonLine, useShared } from '../components';
import type { Pagination, Person } from '../types';

type Reason = {reason: string; count: number; details: string | null};
type ModerationItem = {
    key: string; id: number | null; type: string; title: string; excerpt: string; url: string | null; author: Person | null;
    status: string; content_status: string | null; hidden: boolean; reports_count: number; reporters_count: number;
    weight_total: number; weight_threshold: number; last_report_at: string | null; reasons: Reason[];
    hide_url: string | null; restore_url: string | null; dismiss_url: string | null;
};

const formatWeight = (value: number) => new Intl.NumberFormat('en', {maximumFractionDigits: 1}).format(value);

export default function Moderation({items, all}: {items: Pagination<ModerationItem>; all: boolean}) {
    const {auth, copy: t} = useShared();
    const typeLabel = (type: string) => t[`moderation_type_${type}`] || type.replace(/_/g, ' ');
    const reasonLabel = (reason: string) => t[`reason_${reason}`] || reason.replace(/_/g, ' ');
    const statusLabel = (status: string) => t[`moderation_status_${status}`] || status.replace(/_/g, ' ');

    return <section className="moderation-page">
        <Head title={t.moderation}/>
        <header className="moderation-page-heading">
            <div>
                <h1>{t.moderation}</h1>
                <p>{items.total} {all ? t.reported_items : t.queue_items}</p>
            </div>
            <a className="text-button moderation-tools-link" href="/admin/tools"><Settings size={16}/>{t.admin_tools}</a>
        </header>
        <nav className="feed-tabs moderation-tabs" aria-label={t.moderation}>
            <Link className={!all ? 'active' : ''} href="/admin">{t.queue}</Link>
            <Link className={all ? 'active' : ''} href="/admin?filter=all">{t.history}</Link>
        </nav>
        <Errors/>
        <InfinitePage data="items" className="moderation-list">
            {items.data.map(item => {
                const awaitingVerdict = item.status === 'pending' || item.status === 'auto_hidden' || item.content_status === 'pending';
                const canRestore = Boolean(auth.user?.admin && item.restore_url && item.hidden);
                const canConfirmHide = Boolean(auth.user?.admin && item.hide_url && item.hidden && awaitingVerdict);
                const canHide = Boolean(auth.user?.admin && item.hide_url && !item.hidden);
                const canAllow = Boolean(auth.user?.admin && item.dismiss_url && !item.hidden && awaitingVerdict);
                const thresholdReached = item.weight_total >= item.weight_threshold;
                const hasActions = (!auth.user?.admin && awaitingVerdict) || canRestore || canConfirmHide || canHide || canAllow || Boolean(auth.user?.admin && !item.hide_url && !item.dismiss_url);

                return <article className="moderation-item" key={item.key}>
                    <header className="moderation-item__head">
                        <div className="moderation-kind"><Flag size={15}/><span>{typeLabel(item.type)}</span>{item.last_report_at && <time>{date(item.last_report_at)}</time>}</div>
                        <span className={`state-label state-${item.status}`}>{statusLabel(item.status)}</span>
                    </header>
                    <div className="moderation-item__content">
                        {item.url ? <a className="moderation-item__title" href={item.url} target="_blank" rel="noreferrer">{item.title}</a> : <h2 className="moderation-item__title">{item.title}</h2>}
                        {item.author && <PersonLine person={item.author}/>}
                        {item.excerpt && <p className="moderation-excerpt">{item.excerpt}</p>}
                    </div>
                    <div className={`moderation-signal${thresholdReached ? ' is-threshold' : ''}`}>
                        <div><span>{t.weight}</span><strong>{formatWeight(item.weight_total)} / {formatWeight(item.weight_threshold)}</strong></div>
                        <meter className="moderation-meter" min="0" max={Math.max(item.weight_threshold, item.weight_total)} value={item.weight_total}>{item.weight_total}</meter>
                        <span>{item.reporters_count} {item.reporters_count === 1 ? t.reporter : t.reporters} · {item.reports_count} {item.reports_count === 1 ? t.one_report : t.reports}</span>
                    </div>
                    {item.reasons.length > 0 && <details className="moderation-reasons">
                        <summary><span>{t.report_details}</span><ChevronDown size={15}/></summary>
                        <div className="moderation-reason-list">{item.reasons.map(reason => <div key={reason.reason}>
                            <strong>{reasonLabel(reason.reason)} <span>× {reason.count}</span></strong>
                            {reason.details && <p>{reason.details}</p>}
                        </div>)}</div>
                    </details>}
                    {hasActions && <footer className="moderation-actions">
                        {!auth.user?.admin && awaitingVerdict && <span className="meta">{t.awaiting_review}</span>}
                        {canRestore && <Form json undoable confirmMessage={awaitingVerdict ? t.confirm_allow_content : t.confirm_restore_content} confirmLabel={awaitingVerdict ? t.dismiss_report : t.restore} reloadData={['items', 'moderation']} action={item.restore_url!}>
                            <button className="button primary"><RotateCcw size={16}/>{awaitingVerdict ? t.dismiss_report : t.restore}</button>
                        </Form>}
                        {canAllow && <Form json undoable confirmMessage={t.confirm_allow_content} confirmLabel={t.dismiss_report} reloadData={['items', 'moderation']} action={item.dismiss_url!}>
                            <button className="button primary">{t.dismiss_report}</button>
                        </Form>}
                        {canConfirmHide && <Form json undoable confirmMessage={t.confirm_hide_content} confirmLabel={t.confirm_hide} reloadData={['items', 'moderation']} action={item.hide_url!}>
                            <input type="hidden" name="reason" value={t.confirmed_after_review}/>
                            <button className="button danger"><EyeOff size={16}/>{t.confirm_hide}</button>
                        </Form>}
                        {canHide && <details className="moderation-hide-form">
                            <summary className="button danger"><EyeOff size={16}/>{t.hide}</summary>
                            <Form json undoable confirmMessage={t.confirm_hide_content} confirmLabel={t.hide} reloadData={['items', 'moderation']} action={item.hide_url!}>
                                <label>{t.reason}<input name="reason" maxLength={500} required placeholder={t.hide_reason}/></label>
                                <div className="button-row"><button className="button danger">{t.confirm_hide}</button></div>
                            </Form>
                        </details>}
                        {auth.user?.admin && !item.hide_url && !item.dismiss_url && <a className="button" href="/admin/tools?tab=moderation">{t.admin_tools}</a>}
                    </footer>}
                </article>;
            })}
        </InfinitePage>
        {!items.data.length && <p className="empty">{t.no_queue}</p>}
    </section>;
}
