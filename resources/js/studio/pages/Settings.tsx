import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Bell, Download, KeyRound, LockKeyhole, LogOut, Shield, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Avatar, Errors, Form, SelectMenu, VerifiedName, useShared } from '../components';
import { ProfileMediaEditor } from '../ProfileMediaEditor';
import type { Person, ProfileLinks } from '../types';

type SettingsPerson = Person & {
    featured_post_id: number | null;
    email: string;
    email_verified_at: string | null;
    privacy_allow_mentions: boolean;
    notify_comments: boolean;
    notify_reviews: boolean;
    notify_follows: boolean;
    connections_allow_follow: boolean;
    connections_show_follow_counts: boolean;
    security_login_alerts: boolean;
    headline: string | null;
    profile_readme: string | null;
    profile_highlights: string | null;
    profile_links: ProfileLinks | null;
    github_readme_repository: string | null;
    wall_mode: 'everyone' | 'owner';
};
type ProjectOption = {id: number; title: string; is_project: boolean};
type TwoFactorState = {enabled: boolean; pending: boolean; secret: string | null; qr: string | null; uri: string | null; recoveryCodes: string[]};

function Toggle({name, checked, title, detail}: {name: string; checked: boolean; title: string; detail: string}) {
    return <label className="settings-toggle"><span><strong>{title}</strong><small>{detail}</small></span><input type="hidden" name={name} value="0"/><input type="checkbox" name={name} value="1" defaultChecked={checked}/></label>;
}

const SectionHead = ({title, text}: {title: string; text: string}) => <header className="settings-section-head"><h2>{title}</h2><p>{text}</p></header>;

export default function Settings({person, projects, showcaseProjectIds, twoFactor}: {person: SettingsPerson; projects: ProjectOption[]; showcaseProjectIds: number[]; twoFactor: TwoFactorState}) {
    const {copy: t} = useShared();
    const [active, setActive] = useState(() => {const hash = window.location.hash.slice(1); return ['privacy', 'notifications', 'security', 'data'].includes(hash) ? hash : 'profile';});
    const sections = [
        {id: 'profile', label: t.profile_settings, icon: UserRound},
        {id: 'privacy', label: t.privacy_connections, icon: Shield},
        {id: 'notifications', label: t.notification_settings, icon: Bell},
        {id: 'security', label: t.security, icon: LockKeyhole},
        {id: 'data', label: t.data_account, icon: Download},
    ];
    useEffect(() => {
        let frame = 0;
        const update = () => {cancelAnimationFrame(frame); frame = requestAnimationFrame(() => {const current = [...sections].reverse().find(({id}) => (document.getElementById(id)?.getBoundingClientRect().top ?? Infinity) <= 118)?.id || sections[0].id; setActive(previous => previous === current ? previous : current);});};
        update(); window.addEventListener('scroll', update, {passive: true});
        return () => {cancelAnimationFrame(frame); window.removeEventListener('scroll', update);};
    }, []);
    const projectOptions = projects.map(project => ({value: String(project.id), label: `${project.is_project ? t.project : t.work} · ${project.title}`}));
    return <section className="settings-page"><Head title={t.settings}/><Link className="back-link" href={`/profile/${person.slug}`}><ArrowLeft size={16}/>{t.profile}</Link><header className="settings-heading"><h1>{t.settings}</h1><p>{t.settings_hint}</p></header><Errors/>
        <div className="settings-layout">
            <nav className="settings-nav" aria-label={t.settings_sections}>
                <div className="settings-person"><Avatar person={person}/><span><strong><VerifiedName person={person}/></strong><small>@{person.slug}</small></span></div>
                {sections.map(({id, label, icon: Icon}) => <a key={id} href={`#${id}`} className={active === id ? 'active' : ''} aria-current={active === id ? 'location' : undefined} onClick={() => setActive(id)}><Icon size={17}/>{label}</a>)}
            </nav>

            <div className="settings-sections">
                <section className="settings-section" id="profile"><SectionHead title={t.profile_settings} text={t.profile_settings_hint}/>
                    <div className="profile-media-settings"><ProfileMediaEditor kind="banner" person={person}/><ProfileMediaEditor kind="avatar" person={person}/></div>
                    <Form action="/profile/settings" className="settings-form"><input type="hidden" name="section" value="profile"/>
                        <div className="settings-identity-grid"><label><span className="field-label">{t.display_name}</span><input name="name" required maxLength={255} defaultValue={person.name}/><span className="meta">{t.display_name_hint}</span></label><label><span className="field-label">{t.handle}</span><span className="handle-input"><span>@</span><input name="handle" required minLength={3} maxLength={30} pattern="[a-z0-9](?:(?:[a-z0-9_]|-)*[a-z0-9])?" defaultValue={person.slug}/></span><span className="meta">{t.handle_hint}</span></label></div>
                        <label>{t.professional_headline}<input name="headline" maxLength={160} defaultValue={person.headline || ''} placeholder={t.headline_placeholder}/></label>
                        <label>{t.bio}<textarea name="bio" rows={5} maxLength={1000} defaultValue={person.bio || ''} placeholder={t.bio_placeholder}/></label>
                        <label>{t.skills}<input name="skills" maxLength={400} defaultValue={person.skills || ''} placeholder={t.skills_placeholder}/></label>
                        <div className="settings-subsection"><h3>{t.professional_links}</h3><label>{t.portfolio}<input type="url" name="portfolio_url" maxLength={500} defaultValue={person.portfolio_url || ''} placeholder="https://"/></label><div className="profile-link-settings"><label>{t.contact_link}<input type="url" name="profile_links[contact]" maxLength={500} defaultValue={person.profile_links?.contact || ''} placeholder="https://"/></label><label>{t.github}<input type="url" name="profile_links[github]" maxLength={500} defaultValue={person.profile_links?.github || ''} placeholder="https://github.com/"/></label><label>{t.behance}<input type="url" name="profile_links[behance]" maxLength={500} defaultValue={person.profile_links?.behance || ''} placeholder="https://behance.net/"/></label><label>{t.linkedin}<input type="url" name="profile_links[linkedin]" maxLength={500} defaultValue={person.profile_links?.linkedin || ''} placeholder="https://linkedin.com/in/"/></label></div><label>{t.showreel}<input type="url" name="profile_links[showreel]" maxLength={500} defaultValue={person.profile_links?.showreel || ''} placeholder="https://youtube.com/ or https://vimeo.com/"/><span className="meta">{t.showreel_hint}</span></label></div>
                        <label>{t.profile_highlights}<textarea name="profile_highlights" rows={4} maxLength={1000} defaultValue={person.profile_highlights || ''} placeholder={t.profile_highlights_hint}/><span className="meta">{t.profile_highlights_note}</span></label>
                        <SelectMenu name="featured_post_id" label={t.featured} options={[{value: '', label: t.no_featured}, ...projectOptions]} defaultValue={String(person.featured_post_id || '')}/>
                        <div id="showcase" className="settings-subsection"><h3>{t.showcase}</h3><input type="hidden" name="showcase_project_ids_present" value="1"/><SelectMenu name="showcase_project_ids[]" label={t.showcase_projects} options={projectOptions} defaultValue={showcaseProjectIds.map(String)} multiple max={6} placeholder={t.showcase_empty}/><label>{t.github_readme}<input name="github_readme_repository" maxLength={255} defaultValue={person.github_readme_repository || ''} placeholder="owner/repository"/><span className="meta">{t.github_readme_hint}</span></label><label>{t.profile_readme}<textarea name="profile_readme" rows={6} maxLength={5000} defaultValue={person.profile_readme || ''} placeholder={t.profile_readme_hint}/></label></div>
                        <input type="hidden" name="open_to_help" value="0"/><label className="check"><input type="checkbox" name="open_to_help" value="1" defaultChecked={person.open_to_help}/>{t.available}</label>
                        <button className="button primary">{t.save_profile}</button>
                    </Form>
                </section>

                <section className="settings-section" id="privacy"><SectionHead title={t.privacy_connections} text={t.privacy_connections_hint}/>
                    <Form action="/profile/settings" className="settings-form"><input type="hidden" name="section" value="privacy"/><input type="hidden" name="name" value={person.name}/>
                        <Toggle name="privacy_allow_mentions" checked={person.privacy_allow_mentions} title={t.allow_mentions} detail={t.allow_mentions_hint}/>
                        <Toggle name="connections_allow_follow" checked={person.connections_allow_follow} title={t.allow_followers} detail={t.allow_followers_hint}/>
                        <Toggle name="connections_show_follow_counts" checked={person.connections_show_follow_counts} title={t.show_follow_counts} detail={t.show_follow_counts_hint}/>
                        <SelectMenu name="wall_mode" label={t.wall_access} options={[{value: 'everyone', label: t.wall_everyone}, {value: 'owner', label: t.wall_owner}]} defaultValue={person.wall_mode}/>
                        <button className="button primary">{t.save_privacy}</button>
                    </Form>
                </section>

                <section className="settings-section" id="notifications"><SectionHead title={t.notification_settings} text={t.notification_settings_hint}/>
                    <Form action="/profile/settings" className="settings-form"><input type="hidden" name="section" value="notifications"/><input type="hidden" name="name" value={person.name}/>
                        <Toggle name="notify_comments" checked={person.notify_comments} title={t.notify_comments} detail={t.notify_comments_hint}/>
                        <Toggle name="notify_reviews" checked={person.notify_reviews} title={t.notify_reviews} detail={t.notify_reviews_hint}/>
                        <Toggle name="notify_follows" checked={person.notify_follows} title={t.notify_follows} detail={t.notify_follows_hint}/>
                        <button className="button primary">{t.save_notifications}</button>
                    </Form>
                </section>

                <section className="settings-section" id="security"><SectionHead title={t.security} text={t.security_hint}/>
                    <dl className="account-facts"><div><dt>{t.email}</dt><dd>{person.email}</dd></div><div><dt>{t.email_status}</dt><dd>{person.email_verified_at ? t.verified : t.not_verified}</dd></div></dl>
                    <Form action="/profile/settings" className="settings-form"><input type="hidden" name="section" value="security"/><input type="hidden" name="name" value={person.name}/><Toggle name="security_login_alerts" checked={person.security_login_alerts} title={t.login_alerts} detail={t.login_alerts_hint}/><button className="button primary">{t.save_security}</button></Form>
                    <div className="two-factor"><div className="two-factor-head"><div><strong>{t.two_factor}</strong><p>{twoFactor.enabled ? t.two_factor_enabled_hint : t.two_factor_hint}</p></div><span className={twoFactor.enabled ? 'state-label enabled' : 'state-label'}>{twoFactor.enabled ? t.enabled : t.disabled}</span></div>
                        {twoFactor.pending && twoFactor.qr && twoFactor.secret ? <div className="two-factor-setup"><img src={twoFactor.qr} alt={t.two_factor_qr}/><div><p>{t.two_factor_scan_hint}</p><code>{twoFactor.secret}</code>{twoFactor.uri && <a className="text-link" href={twoFactor.uri}>{t.open_authenticator}</a>}<Form action="/account/two-factor/confirm" className="settings-form"><label>{t.authenticator_code}<input name="code" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required/></label><button className="button primary"><KeyRound size={16}/>{t.confirm_two_factor}</button></Form></div></div> : twoFactor.enabled ? <><Form action="/account/two-factor/recovery-codes" className="settings-form"><label>{t.current_password}<input type="password" name="current_password" required autoComplete="current-password"/></label><button className="button primary">{t.new_recovery_codes}</button></Form><Form action="/account/two-factor" method="delete" className="settings-form" confirmMessage={t.confirm_disable_two_factor}><label>{t.current_password}<input type="password" name="current_password" required autoComplete="current-password"/></label><button className="text-button danger">{t.disable_two_factor}</button></Form></> : <Form action="/account/two-factor" className="settings-form"><label>{t.current_password}<input type="password" name="current_password" required autoComplete="current-password"/></label><button className="button primary"><KeyRound size={16}/>{t.enable_two_factor}</button></Form>}
                        {twoFactor.recoveryCodes.length > 0 && <div className="recovery-codes"><strong>{t.recovery_codes}</strong><p>{t.recovery_codes_hint}</p><div>{twoFactor.recoveryCodes.map(code => <code key={code}>{code}</code>)}</div></div>}
                    </div>
                    <Form action="/account/password" method="patch" className="settings-form password-form"><h3>{t.change_password}</h3><label>{t.current_password}<input type="password" name="current_password" required autoComplete="current-password"/></label><label>{t.new_password}<input type="password" name="password" required minLength={10} autoComplete="new-password"/></label><label>{t.password_confirmation}<input type="password" name="password_confirmation" required minLength={10} autoComplete="new-password"/></label><button className="button primary">{t.change_password}</button></Form>
                </section>

                <section className="settings-section" id="data"><SectionHead title={t.data_account} text={t.data_account_hint}/>
                    <div className="data-action"><div><strong>{t.export_data}</strong><p>{t.export_data_hint}</p></div><a className="button" href="/account/export"><Download size={16}/>{t.download_export}</a></div>
                    <div className="data-action"><div><strong>{t.sign_out}</strong><p>{t.sign_out_hint}</p></div><Form action="/logout"><button className="button"><LogOut size={16}/>{t.logout}</button></Form></div>
                    <div className="danger-zone"><strong>{t.delete_account}</strong><p>{t.delete_account_hint}</p><Form action="/account" method="delete" className="settings-form" confirmMessage={t.confirm_account_delete}><label>{t.current_password}<input type="password" name="password" required autoComplete="current-password"/></label><button className="button danger">{t.delete_account}</button></Form></div>
                </section>
            </div>
        </div>
    </section>;
}
