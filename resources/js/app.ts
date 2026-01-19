import './bootstrap';
import { hydratePage } from './features/hydration';
import { registerSpaDependencies, setupSpaNavigation } from './features/spa';
import { resetActionMenus } from './ui/action-menus';

registerSpaDependencies({ hydratePage, resetActionMenus });

hydratePage();
setupSpaNavigation();
