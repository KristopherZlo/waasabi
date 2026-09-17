import { Head, Link } from '@inertiajs/react';
import { Empty, InfinitePage, WorkCard, useShared } from '../components';
import type { Pagination, Work } from '../types';

export default function Saved({works}: {works: Pagination<Work>}) {
    const {copy: t} = useShared();
    return <section className="single-feed"><Head title={t.saved}/><h1>{t.saved}</h1><InfinitePage data="works" className="feed-list">{works.data.map(work => <WorkCard key={work.id} work={work}/>)}</InfinitePage>{!works.data.length && <Empty action={<Link className="button" href="/">{t.browse}</Link>}>{t.empty}</Empty>}</section>;
}
