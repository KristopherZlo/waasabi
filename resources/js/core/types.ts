export type ReadingState = {
    percent: number;
    anchor: string | null;
    scroll: number;
    updatedAt: number;
};

export type PublishDraft = {
    fields: Record<string, string>;
    contentHtml: string;
    updatedAt: number;
};

export type PageSettings = {
    theme: 'dark' | 'light' | 'system';
    feedView: 'classic' | 'compact';
    publications: string[];
};

export type DomRoot = Document | DocumentFragment | Element;

export type SearchItem = {
    type: 'post' | 'question' | 'user' | 'tag';
    title: string;
    subtitle?: string | null;
    url: string;
    slug?: string | null;
    author?: string | null;
    keywords?: string | null;
};
