import './bootstrap';
import '../css/waasabi.css';
import { hydratePage } from './features/hydration';
import { registerSpaDependencies, setupSpaNavigation } from './features/spa';
import { resetActionMenus } from './ui/action-menus';

registerSpaDependencies({ hydratePage, resetActionMenus });

hydratePage();
setupSpaNavigation();
