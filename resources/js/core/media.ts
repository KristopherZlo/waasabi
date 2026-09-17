import {
    AlertCircle,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Award,
    Ban,
    Bell,
    Bold,
    Bookmark,
    BookmarkX,
    BookOpen,
    CalendarDays,
    ChartNoAxesCombined,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    CircleDot,
    CircleSlash2,
    ClipboardCheck,
    Clock3,
    Code,
    Code2,
    CornerUpLeft,
    createIcons,
    Edit3,
    ExternalLink,
    Eye,
    EyeOff,
    Files,
    Flag,
    Gauge,
    Handshake,
    Heading,
    History,
    Home,
    Image as ImageIcon,
    Italic,
    Layers,
    LayoutDashboard,
    Link as LinkIcon,
    List as ListIcon,
    ListOrdered,
    LogIn,
    LogOut,
    MapPin,
    Megaphone,
    MessageCircle,
    MessagesSquare,
    Minus,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Plus,
    PlusCircle,
    Quote,
    Redo2,
    RotateCcw,
    Search,
    Send,
    ServerCog,
    Settings,
    Share2,
    ShieldCheck,
    SlidersHorizontal,
    Table,
    TableCellsMerge,
    TableCellsSplit,
    Trash2,
    TriangleAlert,
    Undo2,
    User,
    UserMinus,
    UserPlus,
    Users,
    X,
    XCircle,
    Zap,
} from 'lucide';
import { createAvatarFromName } from '../../../scribble-generator/scribble-avatar';
import type { DomRoot } from './types';

const icons = {
    AlertCircle,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Award,
    Ban,
    Bell,
    Bold,
    Bookmark,
    BookmarkX,
    BookOpen,
    CalendarDays,
    ChartNoAxesCombined,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    CircleDot,
    CircleSlash2,
    ClipboardCheck,
    Clock3,
    Code,
    Code2,
    CornerUpLeft,
    Edit3,
    ExternalLink,
    Eye,
    EyeOff,
    Files,
    Flag,
    Gauge,
    Handshake,
    Heading,
    History,
    Home,
    Image: ImageIcon,
    Italic,
    Layers,
    LayoutDashboard,
    Link: LinkIcon,
    List: ListIcon,
    ListOrdered,
    LogIn,
    LogOut,
    MapPin,
    Megaphone,
    MessageCircle,
    MessagesSquare,
    Minus,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Plus,
    PlusCircle,
    Quote,
    Redo2,
    RotateCcw,
    Search,
    Send,
    ServerCog,
    Settings,
    Share2,
    ShieldCheck,
    SlidersHorizontal,
    Table,
    TableCellsMerge,
    TableCellsSplit,
    Trash2,
    TriangleAlert,
    Undo2,
    User,
    UserMinus,
    UserPlus,
    Users,
    X,
    XCircle,
    Zap,
};

export const setupIcons = (root: DomRoot = document) => {
    createIcons({ icons, root });
};

export const setupImageFallbacks = (root: DomRoot = document) => {
    const fallbackDefault = document.body.dataset.placeholder ?? '';
    const images = root.querySelectorAll<HTMLImageElement>('img');
    if (!images.length) {
        return;
    }
    images.forEach((image) => {
        const fallback = image.dataset.fallback ?? fallbackDefault;
        if (!fallback) {
            return;
        }
        if (image.dataset.fallbackBound === '1') {
            return;
        }
        image.dataset.fallbackBound = '1';
        const applyFallback = () => {
            if (image.dataset.fallbackApplied) {
                return;
            }
            image.dataset.fallbackApplied = 'true';
            image.src = fallback;
        };
        image.addEventListener('error', applyFallback);
        if (!image.src) {
            applyFallback();
        }
    });
};

export const setupScribbleAvatars = (root: DomRoot = document) => {
    const avatars = Array.from(root.querySelectorAll<HTMLImageElement>('img.avatar'));
    if (!avatars.length) {
        return;
    }
    avatars.forEach((avatar) => {
        const name = avatar.dataset.avatarName ?? avatar.getAttribute('alt') ?? '';
        applyScribbleAvatar(avatar, name);
    });
};

export const applyScribbleAvatar = (avatar: HTMLImageElement, name: string) => {
    if (avatar.dataset.avatarGenerated === '1') {
        return;
    }
    const shouldAuto =
        avatar.dataset.avatarAuto === '1' || (avatar.getAttribute('src') ?? '').includes('avatar-default.svg');
    if (!shouldAuto) {
        return;
    }
    if (!name.trim()) {
        return;
    }
    try {
        const result = createAvatarFromName(name);
        avatar.src = result.dataUrl;
        avatar.dataset.avatarGenerated = '1';
    } catch {
        // ignore
    }
};
