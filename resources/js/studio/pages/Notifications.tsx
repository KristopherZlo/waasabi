import { Head, Link } from '@inertiajs/react';
import { Empty, Form, InfinitePage, useShared } from '../components';
import type { Pagination } from '../types';

type Note = {id: number; type: string; text: string; link: string; read_at: string | null; created_at: string};
export default function Notifications({notifications}: {notifications: Pagination<Note>}) {
    const {copy: t} = useShared();
    const unread = notifications.data.some(note => !note.read_at);
    return <section className="notifications-page"><Head title={t.notifications}/><div className="page-heading"><h1>{t.notifications}</h1>{unread && <Form json reloadData="notifications" action="/notifications/read-all"><button className="button small">{t.mark_all_read}</button></Form>}</div><InfinitePage data="notifications" className="notification-list">{notifications.data.map(note => <article key={note.id} className={note.read_at ? '' : 'unread'}><Link href={note.link || '/notifications'}><span className="meta">{note.type}</span><p>{note.text}</p><time>{new Date(note.created_at).toLocaleString()}</time></Link>{!note.read_at && <Form json reloadData="notifications" action={`/notifications/${note.id}/read`}><button className="text-button">{t.mark_read}</button></Form>}</article>)}</InfinitePage>{!notifications.data.length && <Empty>{t.empty}</Empty>}</section>;
}
