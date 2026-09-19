import { router } from '@inertiajs/react';
import { Crop, ImagePlus, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { Avatar, Dialog, Form, ProfileBanner, useShared } from './components';
import type { Person } from './types';

type MediaKind = 'avatar' | 'banner';
type Source = {image: HTMLImageElement; url: string};
type CropBox = {x: number; y: number; width: number; height: number};
type Drag = {id: number; clientX: number; clientY: number; crop: CropBox; mode: 'move' | 'resize'};

const configs = {
    avatar: {width: 512, height: 512, maxBytes: 2 * 1024 * 1024},
    banner: {width: 1600, height: 400, maxBytes: 5 * 1024 * 1024},
};
const clamp = (value: number, minimum = 0, maximum = 1) => Math.min(maximum, Math.max(minimum, value));

function centeredCrop(image: HTMLImageElement, aspect: number): CropBox {
    const imageAspect = image.naturalWidth / image.naturalHeight;
    const width = imageAspect > aspect ? aspect / imageAspect : 1;
    const height = imageAspect > aspect ? 1 : imageAspect / aspect;
    return {x: (1 - width) / 2, y: (1 - height) / 2, width, height};
}

export function ProfileMediaEditor({kind, person}: {kind: MediaKind; person: Person}) {
    const {copy: t, csrf} = useShared(); const config = configs[kind]; const aspect = config.width / config.height;
    const input = useRef<HTMLInputElement>(null); const stage = useRef<HTMLDivElement>(null); const drag = useRef<Drag | null>(null);
    const [open, setOpen] = useState(false); const [source, setSource] = useState<Source | null>(null);
    const [crop, setCrop] = useState<CropBox>({x: 0, y: 0, width: 1, height: 1});
    const [busy, setBusy] = useState(false); const [error, setError] = useState('');
    const hasCustom = kind === 'banner' ? Boolean(person.banner_url) : Boolean(person.avatar && !person.avatar.includes('avatar-default'));
    const field = kind === 'banner' ? 'banner_file' : 'avatar_file';
    const title = kind === 'banner' ? t.banner : t.avatar;

    useEffect(() => () => {if (source) URL.revokeObjectURL(source.url);}, [source]);

    const clear = () => {setSource(null); setCrop({x: 0, y: 0, width: 1, height: 1}); setError(''); if (input.current) input.current.value = '';};
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
            setSource({image, url}); setCrop(centeredCrop(image, aspect)); setError('');
        } catch {URL.revokeObjectURL(url); setError(t.invalid_image);}
    };
    const start = (event: PointerEvent<HTMLElement>, mode: Drag['mode']) => {
        drag.current = {id: event.pointerId, clientX: event.clientX, clientY: event.clientY, crop, mode};
        event.currentTarget.setPointerCapture(event.pointerId);
    };
    const move = (event: PointerEvent<HTMLElement>) => {
        const active = drag.current; const bounds = stage.current?.getBoundingClientRect();
        if (!active || active.id !== event.pointerId || !bounds || !source) return;
        const dx = (event.clientX - active.clientX) / bounds.width;
        const dy = (event.clientY - active.clientY) / bounds.height;
        if (active.mode === 'move') {
            setCrop({...active.crop, x: clamp(active.crop.x + dx, 0, 1 - active.crop.width), y: clamp(active.crop.y + dy, 0, 1 - active.crop.height)});
            return;
        }
        const ratio = aspect / (source.image.naturalWidth / source.image.naturalHeight);
        const maxWidth = Math.min(1 - active.crop.x, (1 - active.crop.y) * ratio);
        const width = clamp(active.crop.width + dx, Math.min(.12, maxWidth), maxWidth);
        setCrop({...active.crop, width, height: width / ratio});
    };
    const stop = (event: PointerEvent<HTMLElement>) => {
        if (drag.current?.id !== event.pointerId) return;
        drag.current = null; if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    };
    const keyboardCrop = (event: KeyboardEvent<HTMLDivElement>) => {
        if (!source || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const direction = ['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : -1;
        if (event.shiftKey) {
            const ratio = aspect / (source.image.naturalWidth / source.image.naturalHeight);
            const maxWidth = Math.min(1 - crop.x, (1 - crop.y) * ratio);
            const width = clamp(crop.width + direction * .02, Math.min(.12, maxWidth), maxWidth);
            setCrop({...crop, width, height: width / ratio});
            return;
        }
        setCrop({...crop,
            x: clamp(crop.x + (event.key === 'ArrowLeft' ? -.01 : event.key === 'ArrowRight' ? .01 : 0), 0, 1 - crop.width),
            y: clamp(crop.y + (event.key === 'ArrowUp' ? -.01 : event.key === 'ArrowDown' ? .01 : 0), 0, 1 - crop.height),
        });
    };
    const save = async () => {
        if (!source) {pick(); return;}
        setBusy(true); setError('');
        const output = document.createElement('canvas'); output.width = config.width; output.height = config.height;
        const context = output.getContext('2d');
        context?.drawImage(source.image, crop.x * source.image.naturalWidth, crop.y * source.image.naturalHeight, crop.width * source.image.naturalWidth, crop.height * source.image.naturalHeight, 0, 0, output.width, output.height);
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
            {source ? <><div className="crop-workspace"><div ref={stage} className="crop-image-stage"><img src={source.url} alt="" draggable={false}/><div className="crop-selection" style={{left: `${crop.x * 100}%`, top: `${crop.y * 100}%`, width: `${crop.width * 100}%`, height: `${crop.height * 100}%`}} role="application" tabIndex={0} aria-label={t.crop_area} onKeyDown={keyboardCrop} onPointerDown={event => start(event, 'move')} onPointerMove={move} onPointerUp={stop} onPointerCancel={stop}><span className="crop-grid" aria-hidden/><button type="button" className="crop-handle" aria-label={t.resize_crop} onPointerDown={event => {event.stopPropagation(); start(event, 'resize');}} onPointerMove={move} onPointerUp={stop} onPointerCancel={stop}/></div></div></div><div className="crop-tools"><span><Crop size={16}/>{t.crop_area_hint}</span><button type="button" className="text-button" onClick={() => setCrop(centeredCrop(source.image, aspect))}><RotateCcw size={14}/>{t.reset_crop}</button></div></> : <button type="button" className="media-file-drop" onClick={pick}><ImagePlus size={24}/><span>{t.choose_or_drop_image}</span></button>}
            {error && <p className="field-error" role="alert">{error}</p>}
            <div className="button-row media-editor-actions"><button type="button" className="button" onClick={pick}>{source ? t.choose_another : t.choose_image}</button><button type="button" className="button" onClick={close}>{t.cancel}</button><button type="button" className="button primary" disabled={busy || !source} onClick={() => void save()}>{busy ? t.uploading : t.save}</button></div>
        </div></Dialog>
    </div>;
}
