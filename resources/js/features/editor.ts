import { Editor, Mark, mergeAttributes } from '@tiptap/core';
import CharacterCount from '@tiptap/extension-character-count';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import TurndownService from 'turndown';
import { gfm } from 'turndown-plugin-gfm';
import { marked } from 'marked';
import { appUrl, csrfToken } from '../core/config';
import { t, tFormat } from '../core/i18n';
import { getPublishDraft, updatePublishDraft } from '../core/storage';
import { toast } from '../core/toast';

const Spoiler = Mark.create({
    name: 'spoiler',
    addOptions() {
        return {
            HTMLAttributes: {
                class: 'spoiler',
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-spoiler]' }, { tag: 'span.spoiler' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, { 'data-spoiler': 'true' }), 0];
    },
    addCommands() {
        return {
            toggleSpoiler:
                () =>
                ({ commands }) =>
                    commands.toggleMark(this.name),
        };
    },
});

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...(this.parent?.() ?? {}),
            width: {
                default: null,
                parseHTML: (element) =>
                    element.getAttribute('data-width') || (element as HTMLElement).style.width || null,
                renderHTML: (attributes) => {
                    if (!attributes.width) {
                        return {};
                    }
                    return {
                        'data-width': attributes.width,
                        style: `width: ${attributes.width}; height: auto;`,
                    };
                },
            },
        };
    },
});

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        spoiler: {
            toggleSpoiler: () => ReturnType;
        };
    }
}

const looksLikeMarkdown = (text: string) => {
    if (!text) {
        return false;
    }
    const trimmed = text.trim();
    if (!trimmed) {
        return false;
    }
    return (
        /(^|\n)#{1,6}\s/.test(trimmed) ||
        /(^|\n)\s*([-*+]|\d+\.)\s+/.test(trimmed) ||
        /```/.test(trimmed) ||
        /(^|\n)>\!?/.test(trimmed) ||
        /\|.+\|/.test(trimmed) ||
        /\*\*.+\*\*/.test(trimmed) ||
        /__.+__/.test(trimmed)
    );
};

const getEditorTooltip = () => {
    let tooltip = document.querySelector<HTMLElement>('[data-editor-tooltip]');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.className = 'editor-tooltip';
        tooltip.dataset.editorTooltip = 'true';
        document.body.appendChild(tooltip);
    }
    return tooltip;
};

export const setupArticleEditor = () => {
    const surfaces = Array.from(document.querySelectorAll<HTMLElement>('[data-editor]'));
    if (!surfaces.length) {
        return;
    }

    surfaces.forEach((surface) => {
        if (surface.dataset.editorBound === '1') {
            return;
        }
        surface.dataset.editorBound = '1';
        const root = surface.closest<HTMLElement>('.editor-panel--editor') ?? surface.parentElement;
        const output = root?.querySelector<HTMLTextAreaElement>('[data-editor-output]');
        const count = root?.querySelector<HTMLElement>('[data-editor-count]');
        const toolbar = root?.querySelector<HTMLElement>('[data-editor-toolbar]');
        const imageInput = root?.querySelector<HTMLInputElement>('[data-editor-image-input]');
        const imagePanel = root?.querySelector<HTMLElement>('[data-editor-image-panel]');
        const imageUrlInput = root?.querySelector<HTMLInputElement>('[data-editor-image-url]');
        const imageInsertButton = root?.querySelector<HTMLButtonElement>('[data-editor-image-insert]');
        const imageUploadButton = root?.querySelector<HTMLButtonElement>('[data-editor-image-upload]');
        const imageCloseButton = root?.querySelector<HTMLButtonElement>('[data-editor-image-close]');
        const imageToggleButton = toolbar?.querySelector<HTMLButtonElement>('[data-editor-action="image"]');
        const tablePanel = root?.querySelector<HTMLElement>('[data-editor-table-panel]');
        const placeholder = surface.dataset.editorPlaceholder ?? t('editor_placeholder', 'Start writing...');
        const initialValue = output?.value ?? '';
        const form = surface.closest<HTMLFormElement>('[data-publish-form]');
        const isEditing = form?.dataset.editing === '1';
        const draft = isEditing ? null : getPublishDraft();
        const initialContent = draft?.contentHtml ? draft.contentHtml : initialValue;

        const turndown = new TurndownService({
            codeBlockStyle: 'fenced',
            headingStyle: 'atx',
        });
        turndown.use(gfm);
        turndown.addRule('spoiler', {
            filter: (node) =>
                node.nodeName === 'SPAN' && (node as HTMLElement).getAttribute('data-spoiler') === 'true',
            replacement: (content) => (content ? `>!${content}!<` : ''),
        });

        let draftTimer: number | undefined;
        let rawMarkdownSnapshot: string | null = null;
        let rawMarkdownDocSignature: string | null = null;
        const getDocSignature = (instance: Editor) => JSON.stringify(instance.getJSON());

        const scheduleDraftSave = (instance: Editor) => {
            window.clearTimeout(draftTimer);
            draftTimer = window.setTimeout(() => {
                updatePublishDraft({ contentHtml: instance.getHTML() });
            }, 800);
        };

        const tooltip = getEditorTooltip();
        const showTooltip = (element: HTMLElement) => {
            const label = element.dataset.tooltip ?? element.getAttribute('aria-label') ?? '';
            if (!label) {
                return;
            }
            tooltip.textContent = label;
            const rect = element.getBoundingClientRect();
            const offset = 10;
            tooltip.style.left = `${rect.left + rect.width / 2}px`;
            tooltip.style.top = `${rect.bottom + offset}px`;
            tooltip.classList.add('is-visible');
        };
        const hideTooltip = () => {
            tooltip.classList.remove('is-visible');
        };
        const attachTooltips = (container: HTMLElement | null, selector: string) => {
            if (!container) {
                return;
            }
            const buttons = container.querySelectorAll<HTMLElement>(selector);
            buttons.forEach((button) => {
                const label = button.getAttribute('aria-label') ?? button.textContent?.trim();
                if (label) {
                    button.dataset.tooltip = label;
                    button.setAttribute('title', label);
                }
            });
            container.addEventListener('pointerover', (event) => {
                const target = event.target as HTMLElement | null;
                const button = target?.closest<HTMLElement>(selector);
                if (button) {
                    showTooltip(button);
                }
            });
            container.addEventListener('pointerout', () => {
                hideTooltip();
            });
            container.addEventListener('focusin', (event) => {
                const target = event.target as HTMLElement | null;
                const button = target?.closest<HTMLElement>(selector);
                if (button) {
                    showTooltip(button);
                }
            });
            container.addEventListener('focusout', () => hideTooltip());
        };

        attachTooltips(toolbar, '[data-editor-action]');
        attachTooltips(tablePanel, '[data-table-action]');
        window.addEventListener('scroll', hideTooltip, { passive: true });
        window.addEventListener('resize', hideTooltip);

        const syncOutput = (instance: Editor) => {
            const html = instance.getHTML();
            let markdown = '';
            if (rawMarkdownSnapshot && rawMarkdownDocSignature) {
                const currentSignature = getDocSignature(instance);
                if (currentSignature === rawMarkdownDocSignature) {
                    markdown = rawMarkdownSnapshot;
                } else {
                    rawMarkdownSnapshot = null;
                    rawMarkdownDocSignature = null;
                }
            }
            if (!markdown) {
                markdown = turndown.turndown(html);
            }
            if (output) {
                output.value = markdown;
                output.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (count) {
                count.textContent = tFormat('words', ':count words', { count: instance.storage.characterCount.words() });
            }
            scheduleDraftSave(instance);
        };

        let editor: Editor;

        const handlePaste = (_view: unknown, event: ClipboardEvent) => {
            const text = event.clipboardData?.getData('text/plain') ?? '';
            if (!looksLikeMarkdown(text)) {
                return false;
            }
            event.preventDefault();
            const selection = editor.state.selection;
            const wasEmpty = editor.isEmpty;
            const isFullSelection =
                selection.from <= 1 && selection.to >= editor.state.doc.content.size;
            const prepared = text.replace(/>!([\s\S]+?)!</g, '<span data-spoiler="true">$1</span>');
            const htmlFromMarkdown = marked.parse(prepared, { gfm: true, breaks: true }) as string;
            const shouldSnapshot = wasEmpty || isFullSelection;
            if (shouldSnapshot) {
                editor.commands.setContent(htmlFromMarkdown, false, { preserveWhitespace: 'full' });
            } else {
                editor.commands.insertContent(htmlFromMarkdown);
            }
            if (shouldSnapshot) {
                rawMarkdownSnapshot = text;
                rawMarkdownDocSignature = getDocSignature(editor);
                syncOutput(editor);
            } else {
                rawMarkdownSnapshot = null;
                rawMarkdownDocSignature = null;
            }
            return true;
        };

        editor = new Editor({
            element: surface,
            extensions: [
                StarterKit,
                Table.configure({ resizable: true }),
                TableRow,
                TableHeader,
                TableCell,
                Spoiler,
                Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
                ResizableImage.configure({ allowBase64: true }),
                Placeholder.configure({ placeholder }),
                CharacterCount.configure(),
            ],
            content: initialContent,
            editorProps: {
                attributes: {
                    class: 'tiptap',
                },
                handlePaste,
            },
            onUpdate: ({ editor }) => {
                syncOutput(editor);
            },
        });

        const closeImagePanel = () => {
            if (!imagePanel || imagePanel.hidden) {
                return;
            }
            imagePanel.hidden = true;
        };

        const toggleImagePanel = (force?: boolean) => {
            if (!imagePanel) {
                return;
            }
            const shouldShow = typeof force === 'boolean' ? force : imagePanel.hidden;
            if (!shouldShow) {
                closeImagePanel();
                return;
            }
            imagePanel.hidden = false;
            imageUrlInput?.focus();
        };

        const uploadEditorImage = async (file: File) => {
            if (!csrfToken) {
                return null;
            }
            const formData = new FormData();
            formData.append('image', file);
            try {
                const response = await fetch(`${appUrl || ''}/uploads/images`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                });
                if (!response.ok) {
                    return null;
                }
                const payload = (await response.json()) as { url?: string };
                return typeof payload.url === 'string' ? payload.url : null;
            } catch {
                return null;
            }
        };

        const insertImageFromFile = async (file: File) => {
            const uploadedUrl = await uploadEditorImage(file);
            if (uploadedUrl) {
                editor.chain().focus().setImage({ src: uploadedUrl }).run();
                toggleImagePanel(false);
                return;
            }
            toast.show(t('upload_failed', 'Unable to upload image.'));
        };

        const insertImagesFromList = async (files: FileList | File[]) => {
            for (const file of Array.from(files)) {
                if (file.type.startsWith('image/')) {
                    await insertImageFromFile(file);
                }
            }
        };

        const insertImageFromUrl = () => {
            if (!imageUrlInput) {
                return;
            }
            const url = imageUrlInput.value.trim();
            if (!url) {
                return;
            }
            editor.chain().focus().setImage({ src: url }).run();
            imageUrlInput.value = '';
            toggleImagePanel(false);
        };

        const openImagePicker = () => {
            if (!imageInput) {
                return;
            }
            imageInput.value = '';
            imageInput.click();
        };

        if (imageInput) {
            imageInput.multiple = true;
            imageInput.addEventListener('change', () => {
                const files = imageInput.files;
                if (files && files.length) {
                    void insertImagesFromList(files);
                }
            });
        }

        imageInsertButton?.addEventListener('click', insertImageFromUrl);
        imageUploadButton?.addEventListener('click', () => openImagePicker());
        imageCloseButton?.addEventListener('click', () => closeImagePanel());
        imageUrlInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                insertImageFromUrl();
            }
        });

        const setImageWidth = (value: string | null) => {
            if (!editor.isActive('image')) {
                return;
            }
            const width = value && value !== 'auto' ? value : null;
            editor.chain().focus().updateAttributes('image', { width }).run();
        };

        const imageResizer = document.createElement('button');
        imageResizer.type = 'button';
        imageResizer.className = 'editor-image-handle editor-image-handle--se';
        imageResizer.hidden = true;
        document.body.appendChild(imageResizer);

        let resizing = false;
        let resizeStartX = 0;
        let resizeStartWidth = 0;

        const getSelectedImage = () => editor.view.dom.querySelector<HTMLImageElement>('img.ProseMirror-selectednode');

        const updateImageResizer = () => {
            const image = getSelectedImage();
            if (!image) {
                imageResizer.hidden = true;
                return;
            }
            const rect = image.getBoundingClientRect();
            imageResizer.style.left = `${rect.right - 6}px`;
            imageResizer.style.top = `${rect.bottom - 6}px`;
            imageResizer.hidden = false;
        };

        imageResizer.addEventListener('pointerdown', (event) => {
            const image = getSelectedImage();
            if (!image) {
                return;
            }
            resizing = true;
            resizeStartX = event.clientX;
            resizeStartWidth = image.getBoundingClientRect().width;
            imageResizer.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        window.addEventListener('pointermove', (event) => {
            if (!resizing) {
                return;
            }
            const containerRect = surface.getBoundingClientRect();
            const delta = event.clientX - resizeStartX;
            const newWidth = resizeStartWidth + delta;
            const percent = Math.min(100, Math.max(20, Math.round((newWidth / containerRect.width) * 100)));
            setImageWidth(`${percent}%`);
            updateImageResizer();
        });

        window.addEventListener('pointerup', (event) => {
            if (!resizing) {
                return;
            }
            resizing = false;
            imageResizer.releasePointerCapture(event.pointerId);
        });

        window.addEventListener('scroll', updateImageResizer, { passive: true });
        window.addEventListener('resize', updateImageResizer);

        const canRunTableAction = (action: string) => {
            const checker = editor.can().chain().focus();
            if (action === 'add-row-before') return checker.addRowBefore().run();
            if (action === 'add-row-after') return checker.addRowAfter().run();
            if (action === 'delete-row') return checker.deleteRow().run();
            if (action === 'add-column-before') return checker.addColumnBefore().run();
            if (action === 'add-column-after') return checker.addColumnAfter().run();
            if (action === 'delete-column') return checker.deleteColumn().run();
            if (action === 'toggle-header-row') return checker.toggleHeaderRow().run();
            if (action === 'toggle-header-column') return checker.toggleHeaderColumn().run();
            if (action === 'toggle-header-cell') return checker.toggleHeaderCell().run();
            if (action === 'merge-cells') return checker.mergeCells().run();
            if (action === 'split-cell') return checker.splitCell().run();
            if (action === 'delete-table') return checker.deleteTable().run();
            return false;
        };

        const runTableAction = (action: string) => {
            const canRun = canRunTableAction(action);
            if (!canRun) {
                if (action === 'merge-cells') {
                    toast.show(t('table_merge_hint', 'Select multiple cells to merge.'));
                } else if (action === 'split-cell') {
                    toast.show(t('table_split_hint', 'Nothing to split. Merge cells first.'));
                } else {
                    toast.show(t('table_unavailable', 'Action is not available in this table.'));
                }
                return;
            }
            const chain = editor.chain().focus();
            if (action === 'add-row-before') chain.addRowBefore().run();
            if (action === 'add-row-after') chain.addRowAfter().run();
            if (action === 'delete-row') chain.deleteRow().run();
            if (action === 'add-column-before') chain.addColumnBefore().run();
            if (action === 'add-column-after') chain.addColumnAfter().run();
            if (action === 'delete-column') chain.deleteColumn().run();
            if (action === 'toggle-header-row') chain.toggleHeaderRow().run();
            if (action === 'toggle-header-column') chain.toggleHeaderColumn().run();
            if (action === 'toggle-header-cell') chain.toggleHeaderCell().run();
            if (action === 'merge-cells') chain.mergeCells().run();
            if (action === 'split-cell') chain.splitCell().run();
            if (action === 'delete-table') chain.deleteTable().run();
            updateToolbar();
        };

        tablePanel?.addEventListener('click', (event) => {
            const target = event.target as HTMLElement | null;
            const button = target?.closest<HTMLButtonElement>('[data-table-action]');
            if (!button) {
                return;
            }
            runTableAction(button.dataset.tableAction ?? '');
        });

        surface.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        surface.addEventListener('drop', (event) => {
            event.preventDefault();
            const files = event.dataTransfer?.files;
            if (files && files.length) {
                void insertImagesFromList(files);
            }
        });

        const handleImageAction = () => {
            if (imagePanel) {
                toggleImagePanel();
            } else {
                openImagePicker();
            }
        };

        imageToggleButton?.addEventListener('click', (event) => {
            event.preventDefault();
            handleImageAction();
        });

        toolbar?.addEventListener('click', (event) => {
            const target = event.target as HTMLElement | null;
            const button = target?.closest<HTMLButtonElement>('[data-editor-action]');
            if (!button) {
                return;
            }
            const action = button.dataset.editorAction ?? '';
            editor.chain().focus();

            if (action === 'bold') editor.chain().focus().toggleBold().run();
            if (action === 'italic') editor.chain().focus().toggleItalic().run();
            if (action === 'h2') editor.chain().focus().toggleHeading({ level: 2 }).run();
            if (action === 'h3') editor.chain().focus().toggleHeading({ level: 3 }).run();
            if (action === 'bullet') editor.chain().focus().toggleBulletList().run();
            if (action === 'ordered') editor.chain().focus().toggleOrderedList().run();
            if (action === 'table') {
                editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
            }
            if (action === 'quote') editor.chain().focus().toggleBlockquote().run();
            if (action === 'code') editor.chain().focus().toggleCodeBlock().run();
            if (action === 'spoiler') editor.chain().focus().toggleSpoiler().run();
            if (action === 'undo') editor.chain().focus().undo().run();
            if (action === 'redo') editor.chain().focus().redo().run();
            if (action === 'link') {
                if (editor.isActive('link')) {
                    editor.chain().focus().unsetLink().run();
                } else {
                    const url = window.prompt(t('link_prompt', 'Link'));
                    if (url) {
                        editor.chain().focus().setLink({ href: url }).run();
                    }
                }
            }
            if (action === 'image') {
                handleImageAction();
            }

            updateToolbar();
        });

        const updateToolbar = () => {
            if (!toolbar) {
                return;
            }
            const buttons = toolbar.querySelectorAll<HTMLButtonElement>('[data-editor-action]');
            buttons.forEach((button) => {
                const action = button.dataset.editorAction ?? '';
                let active = false;
                if (action === 'bold') active = editor.isActive('bold');
                if (action === 'italic') active = editor.isActive('italic');
                if (action === 'h2') active = editor.isActive('heading', { level: 2 });
                if (action === 'h3') active = editor.isActive('heading', { level: 3 });
                if (action === 'bullet') active = editor.isActive('bulletList');
                if (action === 'ordered') active = editor.isActive('orderedList');
                if (action === 'table') active = editor.isActive('table');
                if (action === 'quote') active = editor.isActive('blockquote');
                if (action === 'code') active = editor.isActive('codeBlock');
                if (action === 'spoiler') active = editor.isActive('spoiler');
                if (action === 'link') active = editor.isActive('link');
                button.classList.toggle('is-active', active);
            });
            if (tablePanel) {
                tablePanel.hidden = !editor.isActive('table');
                const tableButtons = tablePanel.querySelectorAll<HTMLButtonElement>('[data-table-action]');
                tableButtons.forEach((button) => {
                    const action = button.dataset.tableAction ?? '';
                    button.disabled = !canRunTableAction(action);
                });
            }
            updateImageResizer();
        };

        editor.on('selectionUpdate', updateToolbar);
        editor.on('transaction', updateToolbar);
        updateToolbar();
        syncOutput(editor);
    });
};
