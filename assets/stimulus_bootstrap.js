import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Import custom controllers
import CsrfProtectionController from './controllers/csrf_protection_controller.js';
import PlayerCardAssignerController from './controllers/player_card_assigner_controller.js';
import SortableController from './controllers/sortable_controller.js';
import DashboardController from './controllers/dashboard_controller.js';

app.register('csrf-protection', CsrfProtectionController);
app.register('player-card-assigner', PlayerCardAssignerController);
app.register('sortable', SortableController);
app.register('dashboard', DashboardController);
