import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Image, Layers, Users } from 'lucide-react';
import { useShared } from '../components';

export default function Create() {
    const {copy: t} = useShared();
    return <section className="create-page"><Head title={t.create}/><h1>{t.choose}</h1><p className="lead">{t.create_hint}</p><div className="create-options">
        {[
            {url: '/publish?kind=work', title: t.new_work, hint: t.new_work_hint, mode: t.work_mode, kind: 'work', icon: Image},
            {url: '/publish?kind=project', title: t.new_project, hint: t.new_project_hint, mode: t.project_mode, kind: 'project', icon: Layers},
            {url: '/collaboration/create', title: t.new_help, hint: t.new_help_hint, mode: t.help_mode_short, kind: 'help', icon: Users},
        ].map(({url, title, hint, mode, kind, icon: Icon}) => <Link key={url} href={url} data-kind={kind}><Icon size={25}/><div><span className="create-mode">{mode}</span><h2>{title}</h2><p>{hint}</p></div><ArrowRight size={20}/></Link>)}
    </div></section>;
}
