import { router } from '@inertiajs/react';
import { ImagePlus, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState, type PointerEvent } from 'react';
import { Avatar, Dialog, Form, ProfileBanner, useShared } from './components';
import type { Person } from './types';

type MediaKind = 'avatar' | 'banner';
type Source = {image: HTMLImageElement; url: string};

const configs = {
    avatar: {width: 512, height: 512, maxBytes: 2 * 1024 * 1024},
    banner: {width: 1600, height: 400, maxBytes: 5 * 1024 * 1024},
};
const clamp = (value: number) => Math.min(1, Math.max(0, value));

function sourceRect(image: HTMLImageElement, zoom: number, x: number, y: number, aspect: number) {
    const imageAspect = image.naturalWidth / image.naturalHeight;
    const baseWidth = imageAspect > aspect ? image.naturalHeight * aspect : image.naturalWidth;
    const baseHeight = imageAspect > aspect ? image.naturalHeight : image.naturalWidth / aspect;
    const width = baseWidth / zoom; const height = baseHeight / zoom;
    return {x: (image.naturalWidth - width) * x, y: (image.naturalHeight - height) * y, width, height};
}

export function ProfileMediaEditor({kind, person}: {kind: MediaKind; person: Person}) {
    const {copy: t, csrf} = useShared(); const config = configs[kind];
    const input = useRef<HTMLInputElement>(null); const canvas = useRef<HTMLCanvasElement>(null);
    const drag = useRef<{id: number; clientX: number; clientY: number; x: number; y: number} | null>(null);
    const [open, setOpen] = useState(false); const [source, setSource] = useState<Source | null>(null);
    const [zoom, setZoom] = useState(1); const [x, setX] = useState(.5); const [y, setY] = useState(.5);
    const [busy, setBusy] = useState(false); const [error, setError] = useState('');
    const hasCustom = Boolean(kind === 'banner' ? person.banner_url : person.avatar);
    const field = kind === 'banner' ? 'banner_file' : 'avatar_file';
    const title = kind === 'banner' ? t.banner : t.avatar;

    useEffect(() => () => {if (source) URL.revokeObjectURL(source.url);}, [source]);
    useEffect(() => {
        if (!source || !canvas.current) return;
        const target = canvas.current; const context = target.getContext('2d');
        if (!context) return;
        const rect = sourceRect(source.image, zoom, x, y, config.width / config.height);
        context.clearRect(0, 0, target.width, target.height);
        context.drawImage(source.image, rect.x, rect.y, rect.width, rect.height, 0, 0, target.width, target.height);
    }, [source, zoom, x, y, config.width, config.height]);

    const clear = () => {setSource(null); setZoom(1); setX(.5); setY(.5); setError(''); if (input.current) input.current.value = '';};
    const pick = () => {if (input.current) {input.current.value = ''; input.current.click();}};
    const openEditor = () => {setOpen(true); pick();};
    const close = () => {if (busy) return; setOpen(false); clear();};
    const choose = async (file?: File) => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > config.maxBytes) {
            setError(file.size > config.maxBytes ? t.image_too_large : t.invalid_image); return;
        }
        const url = URL.createObjectURL(file); const image = new Image(); image.decoding = 'async'; image.src = url;
        try {
            await image.decode();
            setSource({image, url});
            setZoom(1); setX(.5); setY(.5); setError('');
        } catch {URL.revokeObjectURL(url); setError(t.invalid_image);}
    };
    const move = (event: PointerEvent<HTMLCanvasElement>) => {
        if (!drag.current || drag.current.id !== event.pointerId) return;
        const bounds = event.currentTarget.getBoundingClientRect();
        setX(clamp(drag.current.x - (event.clientX - drag.current.clientX) / bounds.width));
        setY(clamp(drag.current.y - (event.clientY - drag.current.clientY) / bounds.height));
    };
    const stop = (event: PointerEvent<HTMLCanvasElement>) => {
        if (drag.current?.id !== event.pointerId) return;
        drag.current = null; if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    };
    const save = async () => {
        if (!source) {pick(); return;}
        setBusy(true); setError('');
        const output = document.createElement('canvas'); output.width = config.width; output.height = config.height;
        const context = output.getContext('2d'); const rect = sourceRect(source.image, zoom, x, y, config.width / config.height);
        context?.drawImage(source.image, rect.x, rect.y, rect.width, rect.height, 0, 0, output.width, output.height);
        const blob = context && await new Promise<Blob | null>(resolve => output.toBlob(resolve, 'image/webp', .9));
        if (!blob) {setError(t.image_crop_failed); setBusy(false); return;}
        const data = new FormData(); data.append(field, new File([blob], `${kind}.webp`, {type: 'image/webp'}));
        try {
            const response = await fetch(`/profile/${encodeURIComponent(person.slug)}/${kind}`, {method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf}, body: data});
            const result = await response.json() as {message?: string};
            if (!response.ok) throw new Error(result.message || t.upload_failed);
            window.dispatchEvent(new CustomEvent('waasabi:toast', {detail: kind === 'banner' ? t.banner_updated : t.avatar_updated}));
            setOpen(false); clear(); router.reload({only: ['person'], reset: ['person']});
        } catch (exception) {setError((exception as Error).message);}
        finally {setBusy(false);}
    };

    return <div className="profile-media-control" data-kind={kind} id={kind}>
        <div className="profile-media-preview">{kind === 'banner' ? <ProfileBanner person={person}/> : <Avatar person={person} large/>}</div>
        <div className="profile-media-copy"><strong>{title}</strong><span>{t.saved_dimensions.replace(':width', String(config.width)).replace(':height', String(config.height))}</span><div className="button-row"><button type="button" className="button small" onClick={openEditor}>{hasCustom ? t.change_image : t.choose_image}</button>{hasCustom && <Form json reloadData="person" action={`/profile/${person.slug}/${kind}`} method="delete" confirmMessage={kind === 'banner' ? t.confirm_reset_banner : t.confirm_reset_avatar} confirmLabel={kind === 'banner' ? t.reset_banner : t.reset_avatar} successMessage={kind === 'banner' ? t.banner_reset : t.avatar_reset}><button className="text-button danger"><RotateCcw size={14}/>{kind === 'banner' ? t.reset_banner : t.reset_avatar}</button></Form>}</div></div>
        <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={event => void choose(event.target.files?.[0])}/>
        <Dialog title={`${t.edit} ${title}`} open={open} close={close}><div className="studio-media-editor" data-kind={kind} onDragOver={event => event.preventDefault()} onDrop={event => {event.preventDefault(); void choose(event.dataTransfer.files[0]);}}>
            <p>{t.crop_image_hint}</p>
            {source ? <><canvas ref={canvas} width={kind === 'banner' ? 800 : 400} height={kind === 'banner' ? 200 : 400} onPointerDown={event => {drag.current = {id: event.pointerId, clientX: event.clientX, clientY: event.clientY, x, y}; event.currentTarget.setPointerCapture(event.pointerId);}} onPointerMove={move} onPointerUp={stop} onPointerCancel={stop}/><label className="media-zoom"><span>{t.zoom}</span><input type="range" min="1" max="3" step="0.01" value={zoom} onChange={event => setZoom(Number(event.target.value))}/><output>{Math.round(zoom * 100)}%</output></label></> : <button type="button" className="media-file-drop" onClick={pick}><ImagePlus size={24}/><span>{t.choose_or_drop_image}</span></button>}
            {error && <p className="field-error" role="alert">{error}</p>}
            <div className="button-row media-editor-actions"><button type="button" className="button" onClick={pick}>{source ? t.choose_another : t.choose_image}</button><button type="button" className="button" onClick={close}>{t.cancel}</button><button type="button" className="button primary" disabled={busy || !source} onClick={() => void save()}>{busy ? t.uploading : t.save}</button></div>
        </div></Dialog>
    </div>;
}
