import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Plus, SlidersHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { Empty, InfinitePage, OpeningCard, PersonLine, WorkCard, date, useShared } from '../components';
import type { Opening, Pagination, Person, WallPost, Work } from '../types';

type TagSelection = {included: string[]; excluded: string[]};
const splitTags = (value: string) => value.split(',').map(tag => tag.trim()).filter(Boolean);

export default function Feed({works, openings, people, wallPosts, filter, sort, stream, q, tags, exclude, suggestedTags}: {works: Pagination<Work>; openings: Opening[]; people: Person[]; wallPosts: WallPost[]; filter: string; sort: string; stream: string; q: string; tags: string; exclude: string; suggestedTags: string[]}) {
    const {copy: t} = useShared();
    const [filtersOpen, setFiltersOpen] = useState(Boolean(q || tags || exclude));
    const [selectedTags, setSelectedTags] = useState<TagSelection>({included: splitTags(tags), excluded: splitTags(exclude)});
    const discovery = useRef<HTMLElement>(null);
    const drag = useRef({pointerId: -1, x: 0, scrollLeft: 0, moved: false});
    const suppressClick = useRef(false);
    const searchTimer = useRef<number | undefined>(undefined);
    const [dragging, setDragging] = useState(false);
    const [hasMoreFilters, setHasMoreFilters] = useState(false);

    useEffect(() => setSelectedTags({included: splitTags(tags), excluded: splitTags(exclude)}), [tags, exclude]);
    useEffect(() => {
        const element = discovery.current;
        if (!element) return;
        const update = () => {
            element.style.setProperty('--discovery-gap', '7px');
            const widths = Array.from(element.children, child => child.getBoundingClientRect().width);
            if (element.scrollWidth > element.clientWidth + 1) {
                for (let partial = 1; partial < widths.length; partial++) {
                    const gap = (element.clientWidth - widths.slice(0, partial).reduce((sum, width) => sum + width, 0) - widths[partial] / 2) / partial;
                    if (gap >= 7 && gap <= 18) {
                        element.style.setProperty('--discovery-gap', `${gap}px`);
                        break;
                    }
                }
            }
            setHasMoreFilters(element.scrollLeft + element.clientWidth < element.scrollWidth - 1);
        };
        update();
        const observer = new ResizeObserver(update);
        observer.observe(element);
        return () => observer.disconnect();
    }, [suggestedTags]);

    const visit = (changes: Record<string, string>) => {
        const values = {filter, sort, stream, q, tags: selectedTags.included.join(','), exclude: selectedTags.excluded.join(','), ...changes};
        router.get('/', Object.fromEntries(Object.entries(values).filter(([, value]) => value)), {preserveState: true, preserveScroll: true, replace: true});
    };
    const href = (next: string) => `/?${new URLSearchParams(Object.fromEntries(Object.entries({filter: next, sort, stream, q, tags, exclude}).filter(([, value]) => value)))}`;
    const cycleTag = (tag: string) => {
        const included = selectedTags.included.filter(value => value !== tag);
        const excluded = selectedTags.excluded.filter(value => value !== tag);
        const next = selectedTags.included.includes(tag)
            ? {included, excluded: [...excluded, tag]}
            : selectedTags.excluded.includes(tag) ? {included, excluded} : {included: [...included, tag], excluded};
        setSelectedTags(next);
        visit({tags: next.included.join(','), exclude: next.excluded.join(',')});
    };
    const tagState = (tag: string) => selectedTags.included.includes(tag) ? 'included' : selectedTags.excluded.includes(tag) ? 'excluded' : 'off';
    const startDrag = (event: PointerEvent<HTMLElement>) => {
        if (event.button !== 0) return;
        suppressClick.current = false;
        drag.current = {pointerId: event.pointerId, x: event.clientX, scrollLeft: event.currentTarget.scrollLeft, moved: false};
    };
    const moveDrag = (event: PointerEvent<HTMLElement>) => {
        if (drag.current.pointerId !== event.pointerId) return;
        const distance = event.clientX - drag.current.x;
        if (Math.abs(distance) > 6 && !drag.current.moved) {
            drag.current.moved = true;
            event.currentTarget.setPointerCapture(event.pointerId);
            setDragging(true);
        }
        if (drag.current.moved) event.currentTarget.scrollLeft = drag.current.scrollLeft - distance;
    };
    const stopDrag = (event: PointerEvent<HTMLElement>) => {
        if (drag.current.pointerId !== event.pointerId) return;
        suppressClick.current = drag.current.moved;
        drag.current.pointerId = -1;
        setDragging(false);
        if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    };

    return <><Head title={q ? `${t.search_short}: ${q}` : stream === 'projects' ? t.projects : t.feed}/>
        <div className="feed-layout"><section className="feed-column">
            <div className="page-heading"><h1>{q ? `“${q}”` : stream === 'projects' ? t.projects : t.feed}</h1><Link className="button primary small" href="/create"><Plus size={17}/>{t.share}</Link></div>
            <div className="feed-controls"><nav className="feed-tabs" aria-label="Feed filter">{[['', t.all], ['following', t.following], ['quiet', t.quiet]].map(([key, label]) => <Link key={key} className={filter === key ? 'active' : ''} href={href(key)} preserveScroll>{label}</Link>)}</nav><button className={`filter-icon${filtersOpen ? ' active' : ''}`} type="button" aria-label={t.filters} aria-expanded={filtersOpen} onClick={() => setFiltersOpen(value => !value)}><SlidersHorizontal size={17}/></button></div>
            <div className={`filter-discovery-wrap${hasMoreFilters ? ' has-more' : ''}`}><nav ref={discovery} className={`filter-discovery${dragging ? ' is-dragging' : ''}`} aria-label={t.filters} onScroll={event => setHasMoreFilters(event.currentTarget.scrollLeft + event.currentTarget.clientWidth < event.currentTarget.scrollWidth - 1)} onPointerDown={startDrag} onPointerMove={moveDrag} onPointerUp={stopDrag} onPointerCancel={stopDrag} onClickCapture={event => {if (suppressClick.current) {event.preventDefault(); event.stopPropagation(); suppressClick.current = false;}}}>
                <span>{t.explore_by}</span>{[['', t.all], ['projects', t.projects], ['questions', t.questions]].map(([value, label]) => <button key={value} type="button" className={stream === value ? 'active' : ''} aria-pressed={stream === value} onClick={() => visit({stream: value})}>{label}</button>)}{suggestedTags.slice(0, 12).map(tag => <button key={tag} type="button" data-state={tagState(tag)} aria-pressed={tagState(tag) === 'excluded' ? 'mixed' : tagState(tag) === 'included'} onClick={() => cycleTag(tag)}>#{tag}</button>)}
            </nav></div>
            <div className="feed-sort-row"><div className="feed-sort" role="group" aria-label={t.sort}>{[['hot', t.hot], ['new', t.newest]].map(([value, label]) => <button key={value} type="button" className={sort === value ? 'active' : ''} aria-pressed={sort === value} onClick={() => visit({sort: value})}>{label}</button>)}</div></div>
            {filtersOpen && <form key={`${q}|${stream}|${tags}|${exclude}`} className="feed-filter-panel" onSubmit={event => {event.preventDefault(); router.get('/', Object.fromEntries(new FormData(event.currentTarget)), {preserveState: true, preserveScroll: true, replace: true});}}><input type="hidden" name="filter" value={filter}/><input type="hidden" name="sort" value={sort}/><label>{t.search}<input name="q" defaultValue={q} placeholder={t.search} onChange={event => {const form = event.currentTarget.form; if (!form) return; window.clearTimeout(searchTimer.current); searchTimer.current = window.setTimeout(() => router.get('/', Object.fromEntries(new FormData(form)), {preserveState: true, preserveScroll: true, replace: true}), 260);}}/></label><label>{t.content_type}<select name="stream" defaultValue={stream}><option value="">{t.all}</option><option value="projects">{t.projects}</option><option value="questions">{t.questions}</option></select></label><label>{t.include_tags}<input name="tags" defaultValue={tags} placeholder="3d, music"/></label><label>{t.exclude_tags}<input name="exclude" defaultValue={exclude} placeholder="ai, nsfw"/></label><div className="button-row"><button className="button primary" type="submit">{t.apply_filters}</button><Link className="button" href="/" preserveScroll>{t.clear}</Link></div></form>}
            {wallPosts.map(post => <article className="wall-feed-card" key={post.id}><PersonLine person={post.author} detail={date(post.date)}/><p>{post.body}</p>{post.profile && <Link className="text-link" href={`/profile/${post.profile.slug}?view=wall#wall-post-${post.id}`}>{t.wall_post_on} {post.profile.name} →</Link>}</article>)}
            <InfinitePage data="works" className="feed-list">{works.data.map(work => <WorkCard key={work.id} work={work}/>)}
                {!works.data.length && <Empty action={<Link className="button primary" href={filter === 'following' ? '/' : '/create'}>{filter === 'following' ? t.browse : t.share}</Link>}>{q ? t.empty_search : filter === 'following' ? t.empty_following : t.empty_feed}</Empty>}
            </InfinitePage>
        </section><aside className="feed-sidebar">
            <section><div className="section-heading"><h2>{t.find_help}</h2><Link href="/collaboration/create" aria-label={t.create}><Plus size={18}/></Link></div>{openings.map(opening => <OpeningCard key={opening.id} opening={opening} compact/>)}<Link className="button primary small sidebar-more" href="/collaboration">{t.view_all}<ArrowRight size={15}/></Link></section>
            <section><h2>{t.people}</h2>{people.map(person => <PersonLine key={person.id} person={person} detail={person.skills || undefined}/>)}<Link className="text-link" href="/people">{t.people} →</Link></section>
            <div className="sidebar-footer"><a href="/support">Help & rules</a><span>waasabi</span></div>
        </aside></div>
    </>;
}
