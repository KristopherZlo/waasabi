import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useRef } from 'react';
import { Avatar, Empty, InfinitePage, useShared } from '../components';
import type { Pagination, Person } from '../types';

export default function People({people, q}: {people: Pagination<Person>; q: string}) {
    const {copy: t, auth} = useShared();
    const searchTimer = useRef<number | undefined>(undefined);
    return <section className="directory-page"><Head title={t.people}/>
        <div className="page-heading"><div><h1>{t.people}</h1><p>{t.people_detail}</p></div><Link className="button primary" href="/collaboration/create">{t.post_request}</Link></div>
        <p className="directory-guidance">{t.people_how_it_works} <Link className="text-link" href={auth.user ? '/profile/settings' : '/register'}>{t.people_join}</Link></p>
        <form className="directory-search" onSubmit={event => event.preventDefault()}><Search size={18}/><input name="q" defaultValue={q} placeholder={t.search} onChange={event => {const value = event.currentTarget.value; window.clearTimeout(searchTimer.current); searchTimer.current = window.setTimeout(() => router.get('/people', {q: value}, {preserveState: true, preserveScroll: true, replace: true}), 260);}}/></form>
        <InfinitePage data="people" className="people-list">{people.data.map(person => <article key={person.id}><Avatar person={person} large/><div><Link href={`/profile/${person.slug}`}><h2>{person.name}</h2></Link>{person.skills && <p className="skills">{person.skills}</p>}<p>{person.bio}</p></div><div className="people-actions">{person.portfolio_url && <a className="text-link" href={person.portfolio_url} target="_blank" rel="noreferrer">{t.contact} ↗</a>}<Link className="button small" href={`/profile/${person.slug}`}>{t.view_profile}</Link></div></article>)}</InfinitePage>
        {!people.data.length && <Empty>{t.empty_search}</Empty>}
    </section>;
}
