import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { Bold, Check, Heading2, ImagePlus, Italic, Link as LinkIcon, List, Quote, Redo, Undo, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { marked } from 'marked';
import TurndownService from 'turndown';
import { gfm } from 'turndown-plugin-gfm';
import { useShared } from './components';

const markdown = new TurndownService({headingStyle: 'atx', codeBlockStyle: 'fenced'});
markdown.use(gfm);

export default function RichEditor({initial, onChange, onBusy, placeholder}: {initial: string; onChange: (markdown: string, html: string) => void; onBusy: (busy: boolean) => void; placeholder: string}) {
    const {copy: t, csrf} = useShared();
    const file = useRef<HTMLInputElement>(null);
    const [error, setError] = useState(''); const [busy, setBusy] = useState(false); const [linkOpen, setLinkOpen] = useState(false); const [linkValue, setLinkValue] = useState('');
    const uploadRef = useRef<(files: File[]) => Promise<void>>(async () => {});
    const editor = useEditor({
        extensions: [StarterKit.configure({link: {openOnClick: false, protocols: ['http', 'https'], autolink: true}, underline: false}), Image.configure({allowBase64: false}), Placeholder.configure({placeholder})],
        content: marked.parse(initial, {async: false}),
        immediatelyRender: false,
        editorProps: {attributes: {class: 'writing-surface', role: 'textbox', 'aria-label': placeholder, 'aria-multiline': 'true'},
            handlePaste: (_, event) => {const files = Array.from(event.clipboardData?.files ?? []).filter(f => f.type.startsWith('image/')); if(!files.length) return false; void uploadRef.current(files); return true;},
            handleDrop: (_, event) => {const files = Array.from(event.dataTransfer?.files ?? []).filter(f => f.type.startsWith('image/')); if(!files.length) return false; event.preventDefault(); void uploadRef.current(files); return true;},
        },
        onUpdate: ({editor}) => onChange(markdown.turndown(editor.getHTML()), editor.getHTML()),
    });
    const state = useEditorState({editor, selector: ({editor}) => ({bold: editor?.isActive('bold'), italic: editor?.isActive('italic'), heading: editor?.isActive('heading'), list: editor?.isActive('bulletList'), quote: editor?.isActive('blockquote')})});
    const upload = async (files: File[]) => {
        if (!editor || busy) return;
        setBusy(true); onBusy(true); setError('');
        try {
            for (const image of files) {
                const data = new FormData(); data.append('image', image);
                const response = await fetch('/uploads/images', {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'}, body: data});
                if (!response.ok) throw new Error(t.image_error);
                const result = await response.json() as {url: string};
                if (!editor.isDestroyed) editor.chain().focus().setImage({src: result.url, alt: image.name.replace(/\.[^.]+$/, '')}).run();
            }
        } catch (error) {setError((error as Error).message);} finally {setBusy(false); onBusy(false); if(file.current) file.current.value = '';}
    };
    uploadRef.current = upload;
    const tools = [
        {label: t.bold, icon: Bold, active: state?.bold, run: () => editor?.chain().focus().toggleBold().run()},
        {label: t.italic, icon: Italic, active: state?.italic, run: () => editor?.chain().focus().toggleItalic().run()},
        {label: t.heading, icon: Heading2, active: state?.heading, run: () => editor?.chain().focus().toggleHeading({level: 2}).run()},
        {label: t.list, icon: List, active: state?.list, run: () => editor?.chain().focus().toggleBulletList().run()},
        {label: t.quote, icon: Quote, active: state?.quote, run: () => editor?.chain().focus().toggleBlockquote().run()},
        {label: t.link, icon: LinkIcon, active: editor?.isActive('link'), run: () => {setLinkValue(editor?.getAttributes('link').href || 'https://'); setLinkOpen(value => !value);}},
        {label: t.image, icon: ImagePlus, active: false, run: () => file.current?.click()},
        {label: t.undo, icon: Undo, active: false, run: () => editor?.chain().focus().undo().run()},
        {label: t.redo, icon: Redo, active: false, run: () => editor?.chain().focus().redo().run()},
    ];
    const applyLink = () => {
        const value = linkValue.trim();
        if (!/^https?:\/\/[^\s]+$/i.test(value)) {setError(t.link_error); return;}
        editor?.chain().focus().setLink({href: value}).run(); setLinkOpen(false); setError('');
    };
    return <div className="rich-editor"><div className="writing-toolbar" role="toolbar" aria-label={t.write}>{tools.map(({label, icon: Icon, active, run}) => <button type="button" key={label} title={label} aria-label={label} aria-pressed={Boolean(active)} onClick={run} disabled={!editor || busy}><Icon size={18}/></button>)}{busy && <span role="status">{t.saving}</span>}</div>
        {linkOpen && <form className="link-popover" onSubmit={event => {event.preventDefault(); applyLink();}}><label className="sr-only" htmlFor="editor-link">{t.link}</label><input id="editor-link" type="url" value={linkValue} onChange={event => setLinkValue(event.target.value)} placeholder={t.link_prompt} autoFocus/><button className="icon-button" aria-label={t.apply_link}><Check size={17}/></button><button className="icon-button" type="button" aria-label={t.cancel} onClick={() => setLinkOpen(false)}><X size={17}/></button></form>}
        <input ref={file} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={event => void upload(Array.from(event.target.files ?? []))}/>
        {error && <p className="field-error" role="alert">{error}</p>}<EditorContent editor={editor}/>
    </div>;
}
